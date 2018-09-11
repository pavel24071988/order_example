<?php
/**
 * Created by PhpStorm.
 * User: pavel
 * Date: 28.08.18
 * Time: 16:18
 */

namespace AppBundle\Service;

use \Doctrine\DBAL\DBALException;

class MoneyReportCommon extends MoneyReportAbstract
{
    public const RUB = 1;
    public const BTC = 2;
    public const USD = 3;
    public const EUR = 4;
    public const CZK = 5;
    public const XMR = 6;
    public const DASH = 7;
    public const ZEC = 8;
    public const LTC = 9;

    private $trustedDebtsSumCache;

    /**
     * Долги не привязаны к какому либо проекту
     * @param bool $withCache
     * @return array
     * @throws DBALException
     */
    public function getDebt(bool $withCache = true): array
    {
        if (!$withCache || null === $this->trustedDebtsSumCache) {
            $sql = '
                SELECT
                    dla.currency_id,
                    SUM(dla.amount) as sum
                FROM
                    debt_list_amounts as dla
                GROUP BY
                    dla.currency_id';

            $st = $this->connections->getReports()->prepare($sql);
            $st->execute();
            $debtsSums = [];
            while ($row = $st->fetch(\PDO::FETCH_ASSOC)) {
                $debtsSums[+$row['currency_id']] = (float)$row['sum'];
            }

            $this->trustedDebtsSumCache = $this->setCurrenciesArrayWithZero($debtsSums);
        }

        return $this->trustedDebtsSumCache;
    }

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
     * @return array
     * @throws DBALException
     */
    public function getMoneyData(): array
    {
        $debt = $this->getDebt();
        $active = $this->sumArrayByCurrencyKey([$debt]);
        $passive = [];
        $diff = [];
        foreach ($active as $currencyId => $activeSum) {
            $passive[$currencyId] = $passive[$currencyId] ?? 0;
            $diff[$currencyId] = $active[$currencyId] - $passive[$currencyId];
        }

        return [
            'active' => [
                'values' => $active,
                'parts' => [
                    'debt' => $debt,
                ]
            ],
            'passive' => [
                'values' => $passive,
                'parts' => []
            ],
            'info' => [
                'values' => [],
                'parts' => [],
            ],
            'diff' => $diff,
        ];
    }
}
