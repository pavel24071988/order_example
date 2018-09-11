<?php
/**
 * Created by NetBeans.
 * User: shcherbakov
 * Date: 23.08.2018
 * Time: 22:00
 */

namespace AppBundle\Service;

class MainService
{

    public function __construct($container, array $courses)
    {
    }

    public static function getClientsMoneySql(string $systemUsers, string $inCurrencyes): string
    {
        return 'SELECT res.balance,
                       c.short_name,
                       c.`type`
                  FROM (
                    SELECT w.currency_id, SUM(w.balance) as balance
                      FROM wallets w
                        WHERE w.user_id IN (SELECT user_id FROM user_role WHERE role_id = 5) AND
                              w.user_id NOT IN (' . $systemUsers . ') AND
                              w.currency_id IN (' . $inCurrencyes . ')
                          GROUP BY w.currency_id) res
                  JOIN currency c ON res.currency_id = c.id';
    }
}
