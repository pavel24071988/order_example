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

class MoneyReportMatbea extends MoneyReportMGGAbstract
{
    private $yandexSumCache;

    /**
     * @param bool $withCache
     * @return array
     * @throws DBALException
     */
    private function getYandexSum(bool $withCache = true): array
    {
        if (!$withCache || null === $this->yandexSumCache) {
            $sql = '
                SELECT SUM(a.balance)
                  FROM account a
                    WHERE a.numer NOT LIKE "%block%"
            ';
            $st = $this->connections->getYandex()->prepare($sql);
            $st->execute();
            $yadnexMonetSum = (float)$st->fetchColumn(0);

            $this->yandexSumCache = $this->setCurrenciesArrayWithZero([static::RUB => $yadnexMonetSum]);
        }
        return $this->yandexSumCache;
    }

    /**
     * Изза яндекса и скорее всего еще методы добавятся - переопределил метод
     * @return array
     * @throws DBALException
     */
    public function getMoneyData(): array
    {
        $this->setSystemsExcludedUserIds([1,3,5,22851,66888]);
        $this->setProjectId(Projects::MATBEA);
        $this->setProjectConnection($this->connections->getMatbea());

        $clientWallets = $this->getClientWalletsSum();
        $trustedWallets = $this->getTrustedWalletsSum();
        $systemCryptoWalletsSum = $this->getSystemCryptoWalletsSum();
        $systemSideAccountsSum = $this->getYandexSum();
        $clientWithdrawOrdersSum = $this->getClientWithdrawOrdersSum();
        $trustedWithdrawOrdersSum = $this->getTrustedWithdrawOrdersSum();
        $usersCryptoSum = $this->getCryptoTransactionActiveSum();
        $trustedDepositWaitingOrdersSum = $this->getTrustedDepositWaitingOrdersSum();
        $giftCardsSum = $this->getActiveGiftCardsSum();
        $blockchainWalletsSum = $this->getProjectToServerFormatCurrencies($this->getBlockchainWalletsSum());
        $coldStorages = $this->getColdStorages();
        $active = $this->sumArrayByCurrencyKey([
            $systemCryptoWalletsSum,
            $systemSideAccountsSum,
            $blockchainWalletsSum,
            $coldStorages,
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
                    'systemSideAccountsSum' => $this->getServerToProjectFormatCurrencies($systemSideAccountsSum),
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
                    'trustedDepositWaitingOrdersSum' =>
                        $this->getServerToProjectFormatCurrencies($trustedDepositWaitingOrdersSum),
                ]
            ],
            'diff' => $this->getServerToProjectFormatCurrencies($diff),
        ];
    }
}
