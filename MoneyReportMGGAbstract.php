<?php
/**
 * Created by PhpStorm.
 * User: pavel
 * Date: 27.08.18
 * Time: 15:49
 */

namespace AppBundle\Service;

use \Doctrine\DBAL\DBALException;
use \Doctrine\DBAL\Connection;
use AppBundle\Entity\BlockchainWallets;
use AppBundle\Entity\Currencies;

abstract class MoneyReportMGGAbstract extends MoneyReportAbstract
{
    public const USD = 1;
    public const BTC = 2;
    public const LTC = 3;
    public const RUB = 4;
    public const EUR = 5;
    public const DASH = 30;
    public const ZEC = 31;

    protected $projectToServerCurrencies = [
        self::USD => Currencies::USD,
        self::BTC => Currencies::BTC,
        self::LTC => Currencies::LTC,
        self::RUB => Currencies::RUB,
        self::EUR => Currencies::EUR,
        self::DASH => Currencies::DASH,
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

    private $clientWalletsSumCache;
    private $clientWithdrawOrdersSumCache;
    private $activeGiftCardsSumCache;
    private $trustedWalletsSumCache;
    private $trustedWithdrawOrdersSumCache;
    private $trustedDepositWaitingOrdersSumCache;
    private $systemCryptoWalletsSumCache;
    private $clientRoleId;
    private $trustedRoleId;
    private $blockchainWalletsSumCache;
    private $coldStoragesCache;
    private $projectId;

    protected $cryptoTransactionActiveSumCache;

    protected $projectConnection;

    /**
     * @return array
     * @throws DBALException
     */
    protected function getRolesDB(): array
    {
        $sql = 'SELECT * FROM roles';
        $st = $this->getProjectConnection()->prepare($sql);
        $st->execute();
        $roles = [];
        while ($row = $st->fetch(\PDO::FETCH_ASSOC)) {
            $roles[+$row['id']] = $row;
            if ($row['name'] === 'ROLE_CLIENT') {
                $this->clientRoleId = +$row['id'];
            }
            if ($row['name'] === 'ROLE_TRUSTED') {
                $this->trustedRoleId = +$row['id'];
            }
        }
        return $roles;
    }

    /**
     * @return int
     * @throws DBALException
     */
    protected function getRoleClientId(): int
    {
        if (null === $this->clientRoleId) {
            $this->getRolesDB();
        }
        return $this->clientRoleId;
    }

    /**
     * @return int
     * @throws DBALException
     */
    protected function getRoleTrustedId(): int
    {
        if (null === $this->trustedRoleId) {
            $this->getRolesDB();
        }
        return $this->trustedRoleId;
    }

    /**
     * @param bool $withCache
     * @return array
     * @throws DBALException
     */
    protected function getClientWalletsSum(bool $withCache = true): array
    {
        if (!$withCache || null === $this->clientWalletsSumCache) {
            $sql = '
                SELECT
                    w.currency_id,
                    SUM(w.balance) as sum
                FROM
                    wallets as w
                    LEFT JOIN user_role ur ON w.user_id = ur.user_id
                WHERE
                    ur.role_id = '.$this->getRoleClientId().'
                    AND w.user_id NOT IN ('.implode(',', $this->getSystemsExcludedUserIds()).')
                GROUP BY
                    w.currency_id
            ';
            $st = $this->getProjectConnection()->prepare($sql);
            $st->execute();
            $result = [];
            while ($row = $st->fetch(\PDO::FETCH_ASSOC)) {
                $result[+$row['currency_id']] = (float) $row['sum'];
            }
            $result = $this->setCurrenciesArrayWithZero($result);
            $this->clientWalletsSumCache = $result;
        }
        return $this->clientWalletsSumCache;
    }

    /**
     * @param bool $withCache
     * @return array
     * @throws DBALException
     */
    protected function getClientWithdrawOrdersSum(bool $withCache = true): array
    {
        if (!$withCache || null === $this->clientWithdrawOrdersSumCache) {
            $inStatuses = [
                static::WAITING,
                static::FREEZE,
                static::ARBITRATION,
                static::PAYED_WITHDRAW,
                static::NOT_PAYED
            ];
            $sql = '
                SELECT
                    o.currencyId,
                    SUM(o.amount + o.comms + o.comms_trusted) as sum
                FROM
                    orders as o
                    LEFT JOIN user_role as ur ON o.user_id = ur.user_id
                WHERE
                    ur.role_id = ' . $this->getRoleClientId() . '
                    AND o.user_id NOT IN (' . implode(',', $this->getSystemsExcludedUserIds()) . ')
                    AND o.type = ' . static::TYPE_WITHDRAW . '
                    AND o.status IN (' . implode(',', $inStatuses) . ')
                    AND o.time_update <= '.$this->getStatsTime().'
                GROUP BY
                    o.currencyId
            ';
            $st = $this->getProjectConnection()->prepare($sql);
            $st->execute();
            $result = [];
            while ($row = $st->fetch(\PDO::FETCH_ASSOC)) {
                $result[+$row['currencyId']] = (float) $row['sum'];
            }
            $this->clientWithdrawOrdersSumCache = $this->setCurrenciesArrayWithZero($result);
        }
        return $this->clientWithdrawOrdersSumCache;
    }

    /**
     * @param bool $withCache
     * @return array
     * @throws DBALException
     */
    protected function getTrustedWalletsSum(bool $withCache = true): array
    {
        if (!$withCache || null === $this->trustedWalletsSumCache) {
            $sql = '
                SELECT
                    w.currency_id,
                    SUM(w.balance) as sum
                FROM
                    wallets as w
                    LEFT JOIN user_role ur ON w.user_id = ur.user_id
                WHERE
                    ur.role_id = '.$this->getRoleTrustedId().'
                    AND w.user_id NOT IN ('.implode(',', $this->getSystemsExcludedUserIds()).')
                GROUP BY
                    w.currency_id
            ';
            $st = $this->getProjectConnection()->prepare($sql);
            $st->execute();
            $result = [];
            while ($row = $st->fetch(\PDO::FETCH_ASSOC)) {
                $result[$row['currency_id']] = (float) $row['sum'];
            }
            $this->trustedWalletsSumCache = $this->setCurrenciesArrayWithZero($result);
        }
        return $this->trustedWalletsSumCache;
    }

    /**
     * @param bool $withCache
     * @return array
     * @throws DBALException
     */
    protected function getTrustedWithdrawOrdersSum(bool $withCache = true): array
    {
        if (!$withCache || null === $this->trustedWithdrawOrdersSumCache) {
            $inStatuses = [
                static::WAITING,
                static::FREEZE,
                static::ARBITRATION,
                static::PAYED_WITHDRAW,
                static::NOT_PAYED,
            ];
            $sql = '
                SELECT
                    o.currencyId,
                    SUM(o.amount) as sum
                FROM
                    orders as o
                    LEFT JOIN user_role as ur ON o.user_id = ur.user_id
                WHERE
                    ur.role_id = ' . $this->getRoleTrustedId() . '
                    AND o.user_id NOT IN (' . implode(',', $this->getSystemsExcludedUserIds()) . ')
                    AND o.type = ' . static::TYPE_WITHDRAW . '
                    AND o.status IN (' . implode(',', $inStatuses) . ')
                    AND o.time_update <= '.$this->getStatsTime().'
                GROUP BY
                    o.currencyId
            ';
            $st = $this->getProjectConnection()->prepare($sql);
            $st->execute();
            $result = [];
            while ($row = $st->fetch(\PDO::FETCH_ASSOC)) {
                $result[+$row['currencyId']] = (float) $row['sum'];
            }
            $this->trustedWithdrawOrdersSumCache = $this->setCurrenciesArrayWithZero($result);
        }
        return $this->trustedWithdrawOrdersSumCache;
    }

    /**
     * @param bool $withCache
     * @return array
     * @throws DBALException
     */
    protected function getTrustedDepositWaitingOrdersSum(bool $withCache = true): array
    {
        if (!$withCache || null === $this->trustedDepositWaitingOrdersSumCache) {
            $inStatuses = [
                static::WAITING,
            ];
            $sql = '
                SELECT
                    o.currencyId,
                    SUM(o.amount) as sum
                FROM
                    orders as o
                    LEFT JOIN user_role as ur ON o.user_id = ur.user_id
                WHERE
                    ur.role_id = ' . $this->getRoleTrustedId() . '
                    AND o.user_id NOT IN (' . implode(',', $this->getSystemsExcludedUserIds()) . ')
                    AND o.type = ' . static::TYPE_DEPOSIT . '
                    AND o.status IN (' . implode(',', $inStatuses) . ')
                    AND o.time_update <= ' . $this->getStatsTime() . '
                GROUP BY
                    o.currencyId
            ';
            $st = $this->getProjectConnection()->prepare($sql);
            $st->execute();
            $result = [];
            while ($row = $st->fetch(\PDO::FETCH_ASSOC)) {
                $result[+$row['currencyId']] = (float) $row['sum'];
            }
            $this->trustedDepositWaitingOrdersSumCache = $this->setCurrenciesArrayWithZero($result);
        }
        return $this->trustedDepositWaitingOrdersSumCache;
    }

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
                    ct.currency_id,
                    SUM(ct.amount + ct.fee) as sum
                FROM
                    crypto_transactions as ct
                WHERE
                    ct.user_id NOT IN (' . implode(',', $this->getSystemsExcludedUserIds()) . ')
                    AND ct.status IN (' . implode(',', $inStatuses) . ')
                    AND ct.type = ' . static::INPUT . '
                GROUP BY
                    ct.currency_id
            ';
            $st = $this->getProjectConnection()->prepare($sql);
            $st->execute();
            $result = [];
            while ($row = $st->fetch(\PDO::FETCH_ASSOC)) {
                $result[+$row['currency_id']] = (float)$row['sum'];
            }
            $this->cryptoTransactionActiveSumCache = $this->setCurrenciesArrayWithZero($result);
        }
        return $this->cryptoTransactionActiveSumCache;
    }

    /**
     * @param bool $withCache
     * @return array
     * @throws DBALException
     */
    protected function getActiveGiftCardsSum(bool $withCache = true): array
    {
        if (!$withCache || null === $this->activeGiftCardsSumCache) {
            $sql = '
                SELECT
                    g.currency_id,
                    SUM(g.amount) as sum
                FROM
                    gift_cards as g
                WHERE
                    g.activated = 0
                    AND g.time_update <= ' . $this->getStatsTime() . '
                    AND g.user_id IN ('.implode(', ', $this->getSystemsExcludedUserIds()).')
                GROUP BY
                    g.currency_id
            ';
            $st = $this->getProjectConnection()->prepare($sql);
            $st->execute();
            $result = [];
            while ($row = $st->fetch(\PDO::FETCH_ASSOC)) {
                $result[+$row['currency_id']] = (float) $row['sum'];
            }
            $this->activeGiftCardsSumCache = $this->setCurrenciesArrayWithZero($result);
        }
        return $this->activeGiftCardsSumCache;
    }

    /**
     * @param bool $withCache
     * @return array
     * @throws DBALException
     */
    protected function getSystemCryptoWalletsSum(bool $withCache = true): array
    {
        if (!$withCache || null === $this->systemCryptoWalletsSumCache) {
            $sql = '
                SELECT
                    cs.currency_id,
                    SUM(cs.wallet_balance) as sum
                FROM
                    cold_storage as cs
                GROUP BY
                    cs.currency_id
            ';
            $st = $this->getProjectConnection()->prepare($sql);
            $st->execute();
            $result = [];
            while ($row = $st->fetch(\PDO::FETCH_ASSOC)) {
                $result[+$row['currency_id']] = (float)$row['sum'];
            }
            $this->systemCryptoWalletsSumCache = $this->setCurrenciesArrayWithZero($result);
        }
        return $this->systemCryptoWalletsSumCache;
    }

    protected function setProjectConnection(Connection $projectConnection): self
    {
        $this->projectConnection = $projectConnection;
        return $this;
    }

    protected function getProjectConnection(): Connection
    {
        return $this->projectConnection;
    }

    protected function setProjectId(int $projectId): self
    {
        $this->projectId = $projectId;
        return $this;
    }

    /**
     * @return array
     * @throws DBALException
     */
    public function getCurrenciesDB(): array
    {
        $sql = 'SELECT * FROM currency';
        $st = $this->getProjectConnection()->prepare($sql);
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
            $projectCurrencyId = $this->projectToServerCurrencies[$currecy_id] ?? null;
            if ($projectCurrencyId === null) {
                $this->log[] = 'Валюта с id = ' . $currecy_id . ' не найдена на сервере отчетов.';
                continue;
            }
            if ($projectCurrencyId !== null) {
                $serverCurrenciesFormat[$projectCurrencyId] = $value;
            }
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
     */
    protected function getBlockchainWalletsSum(bool $withCache = true): array
    {
        if (!$withCache || null === $this->blockchainWalletsSumCache) {
            $blockchainWalletsRep = $this->connections->getDoctrine()->getManager()
                ->getRepository(BlockchainWallets::class)->getBlockchainWallets($this->projectId);

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
     * @throws DBALException
     */
    protected function getColdStorages(bool $withCache = true): array
    {
        if (!$withCache || null === $this->coldStoragesCache) {
            $sql = '
                SELECT SUM(cs.wallet_balance) as sum,
                       cs.currency_id
                  FROM cold_storage cs
                    GROUP BY cs.currency_id
            ';
            $st = $this->getProjectConnection()->prepare($sql);
            $st->execute();
            $coldStoragesRep = $st->fetchAll(\PDO::FETCH_ASSOC);

            $coldStorages = [];
            foreach ($coldStoragesRep as $coldStorage) {
                $coldStorages[$coldStorage['currency_id']] = $coldStorage['sum'];
            }
            $this->coldStoragesCache = $coldStorages;
        }
        return $this->coldStoragesCache;
    }

    /**
     * @return array
     */
    public function getMoneyData(): array
    {
        return [];
    }
}
