<?php
/**
 * Created by PhpStorm.
 * User: pavel
 * Date: 28.08.18
 * Time: 16:18
 */

namespace AppBundle\Service;

use AppBundle\Entity\GeneralActive;
use \Doctrine\DBAL\DBALException;
use \AppBundle\Entity\Currencies;
use \AppBundle\Entity\Projects;
use \AppBundle\Entity\BlockchainWallets;
use Services\LocalBitcoins\LocalBitcoins;
use \Doctrine\ORM\NonUniqueResultException;

class MoneyReportFrancs extends MoneyReportAbstract
{
    public const RUB = 1;
    public const EUR = 2;
    public const USD = 3;
    public const BTC = 4;
    public const XMR = 5;
    public const DASH = 6;
    public const LTC = 7;
    public const ZEC = 8;

    protected $projectToServerCurrencies = [
        self::RUB => Currencies::RUB,
        self::EUR => Currencies::EUR,
        self::USD => Currencies::USD,
        self::BTC => Currencies::BTC,
        self::XMR => Currencies::XMR,
        self::DASH => Currencies::DASH,
        self::LTC => Currencies::LTC,
        self::ZEC => Currencies::ZEC,
    ];

    // статусы
    protected const NOSTATUS = 0;
    protected const WAITING = 1;
    protected const FREEZE = 2;
    protected const COMPLETED = 3;
    protected const ARBITRATION = 4;
    protected const CANCELED = 5;
    protected const SUMMARIZED = 6;
    protected const PAYED_WITHDRAW = 7;
    protected const NOT_PAYED = 10;
    protected const PAYED_DEPOSIT = 11;
    protected const CONFIRMED = 12;

    // типы крипты
    protected const OUTPUT = 1;
    protected const INPUT = 2;

    // типы заявок
    protected const TYPE_WITHDRAW = 1;
    protected const TYPE_DEPOSIT = 2;

    // группы
    private const OFFICE = 1;
    private const YACHEIKA = 2;
    private const RESERVE = 10;
    private const SAFEPLACE = 11;


    private $officeCardsSumCache;
    private $reserveMTBSumCache;
    private $xmrActiveCache;
    private $blockchainWalletsSumCache;
    private $localBitcoinsCahce;

    /**
     * @return array
     * @throws DBALException
     */
    public function getCurrenciesDB(): array
    {
        $sql = 'SELECT * FROM currencies';
        $st = $this->connections->getReports()->prepare($sql);
        $st->execute();
        $currencies = [];
        while ($row = $st->fetch(\PDO::FETCH_ASSOC)) {
            $currencies[+$row['id']] = $row['id'];
        }
        return $currencies;
    }

    /**
     * @param array $arrayOfCurrenciesData
     * @return array
     */
    public function getServerToProjectFormatCurrencies(array $arrayOfCurrenciesData = []): array
    {
        $serverCurrenciesFormat = [];
        foreach ($arrayOfCurrenciesData as $currecy_id => $value) {
            if ($value === 0) {
                continue;
            }
            if (!isset($this->projectToServerCurrencies[$currecy_id])) {
                $this->log[] = 'Валюта с id = ' . $currecy_id . ' не найдена на сервере отчетов.';
                continue;
            }
            $serverCurrenciesFormat[$this->projectToServerCurrencies[$currecy_id]] = $value;
        }
        return $serverCurrenciesFormat;
    }

    /**
     * @param array $arrayOfCurrenciesData
     * @return array
     */
    public function getProjectToServerFormatCurrencies(array $arrayOfCurrenciesData = []): array
    {
        $projectCurrenciesFormat = [];
        $serverToProjectCurrencies = array_flip($this->projectToServerCurrencies);
        foreach ($arrayOfCurrenciesData as $currecy_id => $value) {
            if ($value === 0) {
                continue;
            }
            $serverCurrencyId = $serverToProjectCurrencies[$currecy_id] ?? null;
            if ($serverCurrencyId === null) {
                $this->log[] = 'Валюта с id = ' . $currecy_id . ' не найдена на сервере проекта.';
                continue;
            }
            if ($serverCurrencyId !== null) {
                $projectCurrenciesFormat[$serverCurrencyId] = $value;
            }
        }
        return $projectCurrenciesFormat;
    }

    /**
     * @param bool $withCache
     * @return array
     * @throws DBALException
     */
    private function getOfficeCardsSum(bool $withCache = true): array
    {
        $inGroups = implode(',', [self::OFFICE, self::YACHEIKA, self::SAFEPLACE]);
        $excGroups = implode(',', [self::OFFICE, self::YACHEIKA, self::RESERVE, self::SAFEPLACE]);

        if (!$withCache || null === $this->officeCardsSumCache) {
            $sql = '
                SELECT
                    (SELECT SUM(amount_card_complete)
                       FROM details_cards
                         WHERE currency_id = 1 AND
                               hidden = 0 AND
                               enabled = 1 AND
                               details_group_id IN (' . $inGroups . ')),
                    (SELECT SUM(amount_card_complete)
                        FROM details_cards
                          WHERE currency_id = 3 AND
                                hidden = 0 AND
                                enabled = 1 AND
                                details_group_id IN (' . $inGroups . ')),
                    (SELECT SUM(amount_card_complete)
                        FROM details_cards
                          WHERE currency_id = 2 AND
                                hidden = 0 AND
                                enabled = 1 AND
                                details_group_id IN (' . $inGroups . ')),
                    (SELECT SUM(amount_card_complete)
                        FROM details_cards
                          WHERE currency_id = 1 AND
                                hidden = 0 AND
                                enabled = 1 AND
                                details_group_id NOT IN (' . $excGroups . ')),
                    (SELECT SUM(amount_card_complete)
                        FROM details_cards
                          WHERE currency_id = 3 AND
                                hidden = 0 AND
                                enabled = 1 AND
                                details_group_id NOT IN (' . $excGroups . ')),
                    (SELECT SUM(amount_card_complete)
                        FROM details_cards
                          WHERE currency_id = 2 AND
                                hidden = 0 AND
                                enabled = 1 AND
                                details_group_id NOT IN (' . $excGroups . ')),
                    (SELECT SUM(amount_card_complete)
                        FROM details_cards
                          WHERE currency_id = 1 AND
                                hidden = 0 AND
                                enabled = 0 AND
                                details_group_id IN (' . $inGroups . ') AND
                                title LIKE \'ВЫКЛ%\'),
                    (SELECT SUM(amount_card_complete)
                        FROM details_cards
                          WHERE currency_id = 3 AND
                                hidden = 0 AND
                                enabled = 0 AND
                                details_group_id IN (' . $inGroups . ') AND
                                title LIKE \'ВЫКЛ%\'),
                    (SELECT SUM(amount_card_complete)
                        FROM details_cards
                          WHERE currency_id = 2 AND
                                hidden = 0 AND
                                enabled = 0 AND
                                details_group_id IN (' . $inGroups . ') AND
                                title LIKE \'ВЫКЛ%\'),
                    (SELECT SUM(amount_card_complete)
                        FROM details_cards
                          WHERE currency_id = 1 AND
                                hidden = 0 AND
                                enabled = 0 AND
                                details_group_id NOT IN (' . $excGroups . ') AND
                                title LIKE \'ВЫКЛ%\'),
                    (SELECT SUM(amount_card_complete)
                        FROM details_cards
                          WHERE currency_id = 3 AND
                                hidden = 0 AND
                                enabled = 0 AND
                                details_group_id NOT IN (' . $excGroups . ') AND
                                title LIKE \'ВЫКЛ%\'),
                    (SELECT SUM(amount_card_complete)
                        FROM details_cards
                          WHERE currency_id = 2 AND
                                hidden = 0 AND
                                enabled = 0 AND
                                details_group_id NOT IN (' . $excGroups . ') AND
                                title LIKE \'ВЫКЛ%\')
            ';
            $st = $this->connections->getFrancs()->prepare($sql);
            $st->execute();
            $officeCardsSum = $st->fetch(\PDO::FETCH_NUM);

            $rows = [
                'office_rub_active' => 0,
                'office_usd_active' => 1,
                'office_eur_active' => 2,
                'cards_rub_active' => 3,
                'cards_usd_active' => 4,
                'cards_eur_active' => 5,
                'office_rub_nonactive' => 6,
                'office_usd_nonactive' => 7,
                'office_eur_nonactive' => 8,
                'cards_rub_nonactive' => 9,
                'cards_usd_nonactive' => 10,
                'cards_eur_nonactive' => 11,
            ];

            $this->officeCardsSumCache = [
                'officeActive' => $this->setCurrenciesArrayWithZero([
                    self::RUB => $officeCardsSum[$rows['office_rub_active']],
                    self::USD => $officeCardsSum[$rows['office_usd_active']],
                    self::EUR => $officeCardsSum[$rows['office_eur_active']],
                ]),
                'officeNonactive' => $this->setCurrenciesArrayWithZero([
                    self::RUB => $officeCardsSum[$rows['office_rub_nonactive']],
                    self::USD => $officeCardsSum[$rows['office_usd_nonactive']],
                    self::EUR => $officeCardsSum[$rows['office_eur_nonactive']],
                ]),
                'cardsActive' => $this->setCurrenciesArrayWithZero([
                    self::RUB => $officeCardsSum[$rows['cards_rub_active']],
                    self::USD => $officeCardsSum[$rows['cards_usd_active']],
                    self::EUR => $officeCardsSum[$rows['cards_eur_active']],
                ]),
                'cardsNonactive' => $this->setCurrenciesArrayWithZero([
                    self::RUB => $officeCardsSum[$rows['cards_rub_nonactive']],
                    self::USD => $officeCardsSum[$rows['cards_usd_nonactive']],
                    self::EUR => $officeCardsSum[$rows['cards_eur_nonactive']],
                ]),
            ];
        }
        return $this->officeCardsSumCache;
    }

    /**
     * получим баланс по карте Резерв МТБ
     * @param bool $withCache
     * @return array
     * @throws DBALException
     */
    private function getReserveMTBSum(bool $withCache = true): array
    {
        if (!$withCache || null === $this->reserveMTBSumCache) {
            $sql = '
                SELECT amount_card_complete
                  FROM details_cards
                    WHERE title = \'Резерв МТБ\'';
            $st = $this->connections->getFrancs()->prepare($sql);
            $st->execute();
            $this->reserveMTBSumCache = $this->setCurrenciesArrayWithZero([
                self::RUB => $st->fetchColumn(0),
            ]);
        }
        return $this->reserveMTBSumCache;
    }

    private function getXmrActive(bool $withCache = true): array
    {
        if (!$withCache || null === $this->xmrActiveCache) {
            $xmr = 0;
            $params = [
                'key=....',
                'method=findCountByCrypto',
                'from=2017-01-01&to=' . date('Y-m-d') . '23:59:59'
            ];
            $crypto = file_get_contents('https://..../api?' . implode('&', $params));
            $crypto = json_decode($crypto);
            if (isset($crypto->data->XMR)) {
                $xmr = $crypto->data->XMR;
            }
            $this->xmrActiveCache = $this->setCurrenciesArrayWithZero([self::XMR => $xmr]);
        }
        return $this->xmrActiveCache;
    }

    /**
     * @param bool $withCache
     * @return array
     */
    private function getBlockchainWalletsSum(bool $withCache = true): array
    {
        if (!$withCache || null === $this->blockchainWalletsSumCache) {
            $blockchainWalletsRep = $this->connections->getDoctrine()->getManager()
                ->getRepository(BlockchainWallets::class)->getBlockchainWallets(Projects::FRANCS);

            $blockchainWalletsSum = [];
            foreach ($blockchainWalletsRep as $blockchainWallet) {
                $currencyId = $blockchainWallet->getCurrencyId();
                $balance = $blockchainWallet->getBalance() / 100000000;
                $blockchainWalletsSum[$currencyId] = isset($blockchainWalletsSum[$currencyId]) ?
                    $blockchainWalletsSum[$currencyId] + $balance :
                    $balance;
            }
            $this->blockchainWalletsSumCache = $blockchainWalletsSum;
        }
        return $this->blockchainWalletsSumCache;
    }

    /**
     * @param bool $withCache
     * @return array
     * @throws NonUniqueResultException
     */
    private function getLocalBitcoins(bool $withCache = true): array
    {
        if (!$withCache || null === $this->localBitcoinsCahce) {
            try {
                $localBitcoins = new LocalBitcoins(
                    '6735f32f2d9fdffb1cfcc51e86a45cea',
                    'a4f3e07203f5c5dea18e3c76ec4a4fe2a1b67c1e2c84b61b89507885ffa10c2d'
                );
                $wallets = $localBitcoins->get_wallet();
                $btc = $wallets['data']['total'];
                $btc = $btc['balance'];

                if (empty($wallets['data'])) {
                    // если вышли в эту ошибку то берем последнии спарсенные значения
                    $lastLocalbitcoins = $this->connections->getDoctrine()->getManager()
                        ->getRepository(GeneralActive::class)
                        ->getLastActive(Currencies::BTC, GeneralActive::LOCALBITCOINSBTC);
                    $btc = $lastLocalbitcoins->getValue();
                }
            } catch (\Exception $e) {
                $this->log[] = '
                    Localbitcoins баланс не спарсился по причине: ' . $e->getMessage() .
                    "\n" .
                    'вернул нули - взяли предыдущие значения';
                $lastLocalbitcoins = $this->connections->getDoctrine()->getManager()
                    ->getRepository(GeneralActive::class)
                    ->getLastActive(Currencies::BTC, GeneralActive::LOCALBITCOINSBTC);
                $btc = $lastLocalbitcoins->getValue();
            }

            if (empty($btc)) {
                $this->log[] = 'Localbitcoins баланс вернул нуль через проверку на пустоту';
                $btc = 0;
            }

            $this->localBitcoinsCahce = [self::BTC => $btc];
        }
        return $this->localBitcoinsCahce;
    }

    /**
     * @return array
     * @throws DBALException
     * @throws NonUniqueResultException
     */
    public function getMoneyData(): array
    {
        $officeCardsSum = $this->getOfficeCardsSum();
        $reserveMTBSum = $this->getReserveMTBSum();
        $xmrCountByCrypto = $this->getXmrActive();
        $blockchainWalletsSum = $this->getProjectToServerFormatCurrencies($this->getBlockchainWalletsSum());
        $localBitcoins = $this->getLocalBitcoins();
        $active = $this->sumArrayByCurrencyKey([
            $officeCardsSum['officeActive'],
            $officeCardsSum['officeNonactive'],
            $officeCardsSum['cardsActive'],
            $officeCardsSum['cardsNonactive'],
            $reserveMTBSum,
            $xmrCountByCrypto,
            $blockchainWalletsSum,
            $localBitcoins,
        ]);
        $passive = [];
        $diff = [];
        foreach ($active as $currencyId => $activeSum) {
            $passive[$currencyId] = $passive[$currencyId] ?? 0;
            $diff[$currencyId] = $active[$currencyId] - $passive[$currencyId];
        }

        return [
            'active' => [
                'values' => $this->getServerToProjectFormatCurrencies($active),
                'parts' => [
                    'officeActive' => $this->getServerToProjectFormatCurrencies($officeCardsSum['officeActive']),
                    'officeNonactive' => $this->getServerToProjectFormatCurrencies($officeCardsSum['officeNonactive']),
                    'cardsActive' => $this->getServerToProjectFormatCurrencies($officeCardsSum['cardsActive']),
                    'cardsNonactive' => $this->getServerToProjectFormatCurrencies($officeCardsSum['cardsNonactive']),
                    'reserveMTBSum' => $this->getServerToProjectFormatCurrencies($reserveMTBSum),
                    'xmrCountByCrypto' => $this->getServerToProjectFormatCurrencies($xmrCountByCrypto),
                    'blockchainWalletsSum' => $this->getServerToProjectFormatCurrencies($blockchainWalletsSum),
                    'localBitcoins' => $localBitcoins,
                ]
            ],
            'passive' => [
                'values' => $this->getServerToProjectFormatCurrencies($passive),
                'parts' => []
            ],
            'info' => [
                'values' => [],
                'parts' => [],
            ],
            'diff' => $this->getServerToProjectFormatCurrencies($diff),
        ];
    }
}
