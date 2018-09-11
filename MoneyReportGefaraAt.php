<?php
/**
 * Created by PhpStorm.
 * User: pavel
 * Date: 27.08.18
 * Time: 15:49
 */

namespace AppBundle\Service;

use \Doctrine\DBAL\DBALException;
use AppBundle\Entity\Projects;

class MoneyReportGefaraAt extends MoneyReportMGGAbstract
{
    /**
     * @param bool $withCache
     * @return array
     * @throws DBALException
     */
    protected function getCryptoTransactionActiveSum(bool $withCache = true): array
    {
        if (!$withCache || null === $this->cryptoTransactionActiveSumCache) {
            $inStatuses = [
                static::NOSTATUS,
                static::WAITING,
            ];
            $sql = '
                SELECT
                    ct.profit_currency_id,
                    SUM(ct.amount + ct.fee) as sum
                FROM
                    crypto_transactions as ct
                WHERE
                    ct.user_id NOT IN (' . implode(',', $this->getSystemsExcludedUserIds()) . ')
                    AND ct.status IN (' . implode(',', $inStatuses) . ')
                    AND ct.type = ' . static::INPUT . '
                GROUP BY
                    ct.profit_currency_id
            ';
            $st = $this->connections->getGefaraAt()->prepare($sql);
            $st->execute();
            $result = [];
            while ($row = $st->fetch(\PDO::FETCH_ASSOC)) {
                $result[+$row['profit_currency_id']] = (float)$row['sum'];
            }
            $this->cryptoTransactionActiveSumCache = $this->setCurrenciesArrayWithZero($result);
        }
        return $this->cryptoTransactionActiveSumCache;
    }

    /**
     * @return array
     * @throws DBALException
     */
    public function getMoneyData(): array
    {
        $this->setSystemsExcludedUserIds([1,2,4,5,7,133,774,991]);
        $this->setProjectId(Projects::GEFARAAT);
        $this->setProjectConnection($this->connections->getGefaraAt());

        $clientWallets = $this->getClientWalletsSum();
        $trustedWallets = $this->getTrustedWalletsSum();
        $systemCryptoWalletsSum = $this->getSystemCryptoWalletsSum();
        $clientWithdrawOrdersSum = $this->getClientWithdrawOrdersSum();
        $trustedWithdrawOrdersSum = $this->getTrustedWithdrawOrdersSum();
        $usersCryptoSum = $this->getCryptoTransactionActiveSum();
        $trustedDepositWaitingOrdersSum = $this->getTrustedDepositWaitingOrdersSum();
        $giftCardsSum = $this->getActiveGiftCardsSum();
        $blockchainWalletsSum = $this->getProjectToServerFormatCurrencies($this->getBlockchainWalletsSum());
        $coldStorages = $this->getColdStorages();
        $active = $this->sumArrayByCurrencyKey([
            $systemCryptoWalletsSum,
            $blockchainWalletsSum,
            $coldStorages
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
                    'coldStorages' => $this->getServerToProjectFormatCurrencies($coldStorages),
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
                    'trustedDepositWaitingOrdersSum' => $this->getServerToProjectFormatCurrencies($trustedDepositWaitingOrdersSum),
                ]
            ],
            'diff' => $this->getServerToProjectFormatCurrencies($diff),
        ];
    }
}
