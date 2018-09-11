<?php
/**
 * Created by PhpStorm.
 * User: pavel
 * Date: 05.09.18
 * Time: 12:12
 */

namespace AppBundle\Service;

use AppBundle\Entity\GeneralParse;
use AppBundle\Entity\GeneralActive;
use AppBundle\Entity\GeneralPassive;
use AppBundle\Entity\GeneralDiff;
use AppBundle\Entity\Currencies;

class SaveDBData
{
    private $container;
    private $connections;
    private $dateObj;

    public function __construct($container, $connections, $dateObj)
    {
        $this->container = $container;
        $this->connections = $connections;
        $this->dateObj = $dateObj;
    }

    /**
     * @param $data
     */
    public function setDBData($data): void
    {
        $em = $this->connections->getDoctrine()->getManager();

        $currenciesRep = $em->getRepository(Currencies::class)->getAllCurrencies();
        $currencies = [];
        foreach ($currenciesRep as $curreincy) {
            $currencies[$curreincy->getId()] = $curreincy;
        }

        $em->getConnection()->beginTransaction();
        try {
            $generalParse = new GeneralParse;
            $generalParse->setDate($this->dateObj);

            $em->persist($generalParse);

            // set active
            $active = $data['active']['parts'];
            foreach ($active as $patrName => $partData) {
                foreach ($partData as $currencyId => $value) {
                    $generalActive = new GeneralActive;
                    $generalActive->setNameOfData($patrName);
                    $generalActive->setCurrencyId($currencyId);
                    $generalActive->setCurrency($currencies[$currencyId]);
                    $generalActive->setParseId($generalParse->getId());
                    $generalActive->setParse($generalParse);
                    $generalActive->setValue($value);

                    $em->persist($generalActive);
                }
            }

            // set passive
            $passive = $data['passive']['parts'];
            foreach ($passive as $patrName => $partData) {
                foreach ($partData as $currencyId => $value) {
                    $generalPassive = new GeneralPassive;
                    $generalPassive->setNameOfData($patrName);
                    $generalPassive->setCurrencyId($currencyId);
                    $generalPassive->setCurrency($currencies[$currencyId]);
                    $generalPassive->setParseId($generalParse->getId());
                    $generalPassive->setParse($generalParse);
                    $generalPassive->setValue($value);

                    $em->persist($generalPassive);
                }
            }

            // set passive
            $diff = $data['diff'];
            foreach ($diff as $currencyId => $value) {
                $generalDiff = new GeneralDiff;
                $generalDiff->setCurrencyId($currencyId);
                $generalDiff->setCurrency($currencies[$currencyId]);
                $generalDiff->setParseId($generalParse->getId());
                $generalDiff->setParse($generalParse);
                $generalDiff->setValue($value);

                $em->persist($generalDiff);
            }

            $em->flush();
            $em->getConnection()->commit();
        } catch (\Exception $exception) {
            $em->getConnection()->rollback();
            $em->close();

            $this->container->get('rocketchat')->send(
                'ошибка начисление партнерского вознаграждения' .
                "\n" .
                $exception->getMessage() .
                "\n" .
                $exception->getFile() . ' on ' . $exception->getLine() . ' line'
            );
        }
    }
}
