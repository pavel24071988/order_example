<?php
/**
 * Created by PhpStorm.
 * User: pavel
 * Date: 27.08.18
 * Time: 15:49
 */

namespace AppBundle\Service;

use AppBundle\Entity\Currencies;
use \Doctrine\DBAL\DBALException;
use AppBundle\Entity\Projects;
use AppBundle\Entity\GefaraActive;
use AppBundle\Entity\GeneralActive;
use AppBundle\General\KrakenController;
use AppBundle\General\BitstampController;
use \Doctrine\ORM\NonUniqueResultException;

class MoneyReportGefaraCz extends MoneyReportMGGAbstract
{
    private $banksCache;
    private $banksDeltaCache;
    private $krakenCache;
    private $bitstampCache;

    /**
     * @param bool $withCache
     * @return array
     */
    private function getBanks(bool $withCache = true): array
    {
        if (!$withCache || null === $this->banksCache) {
            $gefaraActive = $this->connections->getDoctrine()->getManager()
                ->getRepository(GefaraActive::class)->findOneBy([], ['id' => 'DESC']);

            $this->banksCache = [
                Currencies::EUR => null === $gefaraActive ? 0 :
                    $gefaraActive->getFioBank() + $gefaraActive->getMonetaBank() + $gefaraActive->getEpaBank()
            ];
        }
        return $this->banksCache;
    }

    /**
     * @param bool $withCache
     * @return array
     * @throws DBALException
     */
    private function getBanksDelta(bool $withCache = true): array
    {
        if (!$withCache || null === $this->banksDeltaCache) {
            $sql = 'SELECT
                (SELECT ga.date FROM gefara_active ga GROUP BY ga.fio_bank ORDER BY ga.date DESC LIMIT 1),
                (SELECT ga.date FROM gefara_active ga GROUP BY ga.moneta_bank ORDER BY ga.date DESC LIMIT 1),
                (SELECT ga.date FROM gefara_active ga GROUP BY ga.epa_bank ORDER BY ga.date DESC LIMIT 1)';
            $st = $this->connections->getReports()->prepare($sql);
            $st->execute();
            $lastDatesBankUpdate = $st->fetch(\PDO::FETCH_NUM);
            $lastDateBankUpdate = max([
                strtotime($lastDatesBankUpdate[0]),
                strtotime($lastDatesBankUpdate[1]),
                strtotime($lastDatesBankUpdate[2])
            ]);

            $sql = 'SELECT SUM(o.amount) as sum,
                           o.currencyId
                      FROM orders o
                        WHERE o.`status` = 12 AND
                              o.user_id NOT IN (' . implode(', ', $this->getSystemsExcludedUserIds()) . ') AND
                              o.type = 2 AND
                              o.time_update >= :lastDate
                          GROUP BY o.currencyId';
            $st = $this->connections->getGefaraCz()->prepare($sql);
            $st->execute(['lastDate' => $lastDateBankUpdate]);
            $orders = $st->fetchAll(\PDO::FETCH_ASSOC);

            $coursesById = $this->getCoursesById();
            $rubSum = 0;
            foreach ($orders as $order) {
                $rubSum += $coursesById[$order['currencyId'] . Currencies::RUB] * $order['sum'];
            }
            $this->banksDeltaCache = [static::RUB => $rubSum];
        }
        return $this->banksDeltaCache;
    }

    /**
     * @param bool $withCache
     * @return array
     * @throws NonUniqueResultException
     */
    private function getKraken(bool $withCache = true): array
    {
        if (!$withCache || null === $this->krakenCache) {
            $key = '....';
            $secret = '....';
            $url = 'https://api.kraken.com';
            $sslverify = true;
            $ver = 0;
            $kraken = new KrakenController($key, $secret, $url, $ver, $sslverify);

            try {
                $res = $kraken->QueryPrivate('Balance');
                $eur = $res['result']['ZEUR'] ?? 0;
                $btc = $res['result']['XXBT'] ?? 0;
            } catch (\Exception $e) {
                $lastBtc = $this->connections->getDoctrine()->getManager()
                    ->getRepository(GeneralActive::class)
                    ->getLastActive(Currencies::BTC, GeneralActive::KRAKEN);
                $lastEur = $this->connections->getDoctrine()->getManager()
                    ->getRepository(GeneralActive::class)
                    ->getLastActive(Currencies::EUR, GeneralActive::KRAKEN);
                $btc = $lastBtc->getValue();
                $eur = $lastEur->getValue();

                $this->log[] = 'Kraken кошельки не спарсились по причине: ' . $e->getMessage() . "\n" .
                               'Взяли предыдущие значение';
            }
            // логика от Олега - если кракен в битках вернул нуль, то берем предыдущее значение
            if ($btc === 0) {
                $lastBtc = $this->connections->getDoctrine()->getManager()
                    ->getRepository(GeneralActive::class)
                    ->getLastActive(Currencies::BTC, GeneralActive::KRAKEN);
                $btc = $lastBtc->getValue();
                $this->log[] = 'Kraken по btc вернул 0. Взяли предыдущие значение';
            }
            $this->krakenCache = [
                self::BTC => $btc,
                self::EUR => $eur,
            ];
        }
        return $this->krakenCache;
    }

    /**
     * @param bool $withCache
     * @return array
     * @throws NonUniqueResultException
     */
    private function getBitstamp(bool $withCache = true): array
    {
        if (!$withCache || null === $this->bitstampCache) {
            $key = '....';
            $secret = '....';
            $clientID = '....';
            $bitstamp = new BitstampController($key, $secret, $clientID);

            try {
                $res = $bitstamp->bitstamp_query('balance');
                if (empty($res)) {
                    $lastBtc = $this->connections->getDoctrine()->getManager()
                        ->getRepository(GeneralActive::class)
                        ->getLastActive(Currencies::BTC, GeneralActive::BITSTAMP);
                    $lastEur = $this->connections->getDoctrine()->getManager()
                        ->getRepository(GeneralActive::class)
                        ->getLastActive(Currencies::EUR, GeneralActive::BITSTAMP);
                    $btc = $lastBtc->getValue();
                    $eur = $lastEur->getValue();

                    $this->log[] = 'Bitstamp по btc вернул 0. Взяли предыдущие значение';
                } else {
                    $btc = $res['btc_balance'] ?? 0;
                    $eur = $res['eur_balance'] ?? 0;
                }
            } catch (\Exception $e) {
                $lastBtc = $this->connections->getDoctrine()->getManager()
                    ->getRepository(GeneralActive::class)
                    ->getLastActive(Currencies::BTC, GeneralActive::BITSTAMP);
                $lastEur = $this->connections->getDoctrine()->getManager()
                    ->getRepository(GeneralActive::class)
                    ->getLastActive(Currencies::EUR, GeneralActive::BITSTAMP);
                $btc = $lastBtc->getValue();
                $eur = $lastEur->getValue();

                $this->log[] = 'Bitstamp кошельки не спарсились по причине: ' . $e->getMessage() . "\n" .
                    'Взяли предыдущие значение';
            }

            $this->bitstampCache = [
                self::BTC => $btc,
                self::EUR => $eur,
            ];
        }
        return $this->bitstampCache;
    }

    /**
     * @return array
     * @throws DBALException
     * @throws NonUniqueResultException
     */
    public function getMoneyData(): array
    {
        $this->setSystemsExcludedUserIds([1,2,3,4,5,6,11,650,793]);
        $this->setProjectId(Projects::GEFARACZ);
        $this->setProjectConnection($this->connections->getGefaraCz());

        $clientWallets = $this->getClientWalletsSum();
        $trustedWallets = $this->getTrustedWalletsSum();
        $systemCryptoWalletsSum = $this->getSystemCryptoWalletsSum();
        $clientWithdrawOrdersSum = $this->getClientWithdrawOrdersSum();
        $trustedWithdrawOrdersSum = $this->getTrustedWithdrawOrdersSum();
        $usersCryptoSum = $this->getCryptoTransactionActiveSum();
        $trustedDepositWaitingOrdersSum = $this->getTrustedDepositWaitingOrdersSum();
        $giftCardsSum = $this->getActiveGiftCardsSum();
        $blockchainWalletsSum = $this->getProjectToServerFormatCurrencies($this->getBlockchainWalletsSum());
        $banksSum = $this->getProjectToServerFormatCurrencies($this->getBanks());
        $banksDeltaSum = $this->getBanksDelta();
        $coldStorages = $this->getColdStorages();
        $krakenStorages = $this->getKraken();
        $bitstampStorages = $this->getBitstamp();
        $active = $this->sumArrayByCurrencyKey([
            $systemCryptoWalletsSum,
            $blockchainWalletsSum,
            $banksSum,
            $banksDeltaSum,
            $coldStorages,
            $krakenStorages,
            $bitstampStorages,
        ]);
        $passive = $this->sumArrayByCurrencyKey([
            $clientWallets,
            $trustedWallets,
            $clientWithdrawOrdersSum,
            $trustedWithdrawOrdersSum,
            $usersCryptoSum,
            $giftCardsSum,
        ]);
        $diff = [];
        foreach ($active as $currencyId => $activeSum) {
            $passive[$currencyId] = $passive[$currencyId] ?? 0;
            $diff[$currencyId] = $active[$currencyId] - $passive[$currencyId];
        }

        return [
            'active' => [
                'values' => $this->getServerToProjectFormatCurrencies($active),
                'parts' => [
                    'systemCryptoWalletsSum' => $this->getServerToProjectFormatCurrencies($systemCryptoWalletsSum),
                    'blockchainWalletsSum' => $this->getServerToProjectFormatCurrencies($blockchainWalletsSum),
                    'banksSum' => $this->getServerToProjectFormatCurrencies($banksSum),
                    'banksDeltaSum' => $this->getServerToProjectFormatCurrencies($banksDeltaSum),
                    'coldStorages' => $this->getServerToProjectFormatCurrencies($coldStorages),
                    'krakenStorages' => $this->getServerToProjectFormatCurrencies($krakenStorages),
                    'bitstampStorages' => $this->getServerToProjectFormatCurrencies($bitstampStorages),
                ]
            ],
            'passive' => [
                'values' => $this->getServerToProjectFormatCurrencies($passive),
                'parts' => [
                    'clientWallets' => $this->getServerToProjectFormatCurrencies($clientWallets),
                    'trustedWallets' => $this->getServerToProjectFormatCurrencies($trustedWallets),
                    'clientWithdrawOrdersSum' => $this->getServerToProjectFormatCurrencies($clientWithdrawOrdersSum),
                    'trustedWithdrawOrdersSum' => $this->getServerToProjectFormatCurrencies($trustedWithdrawOrdersSum),
                    'usersCryptoSum' => $this->getServerToProjectFormatCurrencies($usersCryptoSum),
                    'giftCardsSum' => $this->getServerToProjectFormatCurrencies($giftCardsSum),
                ]
            ],
            'info' => [
                'values' => $this->getServerToProjectFormatCurrencies($trustedDepositWaitingOrdersSum),
                'parts' => [
                    'trustedDepositWaitingOrdersSum' =>
                        $this->getServerToProjectFormatCurrencies($trustedDepositWaitingOrdersSum),
                ]
            ],
            'diff' => $this->getServerToProjectFormatCurrencies($diff),
        ];
    }
}
