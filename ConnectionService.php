<?php
/**
 * Created by PhpStorm.
 * User: pavel
 * Date: 29.08.18
 * Time: 10:52
 */

namespace AppBundle\Service;

use Symfony\Component\DependencyInjection\ContainerInterface;
use \Doctrine\DBAL\Connection;
use \Doctrine\Bundle\DoctrineBundle\Registry;

class ConnectionService
{
    protected const DOCTRINE = 'doctrine';
    protected const REPORTS = 'reports';
    protected const MATBEA = 'matbea';
    protected const GEFARACZ= 'gefaracz';
    protected const GEFARAAT= 'gefaraat';
    protected const ASKOIN= 'askoin';
    protected const FRANCS= 'francs';
    protected const YANDEX= 'yandex';

    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @return Registry|object
     */
    public function getDoctrine(): Registry
    {
        return $this->container->get(self::DOCTRINE);
    }

    /**
     * @return Connection
     */
    public function getReports(): Connection
    {
        return $this->getDoctrine()->getConnection(self::REPORTS);
    }

    /**
     * @return Connection
     */
    public function getMatbea(): Connection
    {
        return $this->getDoctrine()->getConnection(self::MATBEA);
    }

    /**
     * @return Connection
     */
    public function getGefaraCz(): Connection
    {
        return $this->getDoctrine()->getConnection(self::GEFARACZ);
    }

    /**
     * @return Connection
     */
    public function getGefaraAt(): Connection
    {
        return $this->getDoctrine()->getConnection(self::GEFARAAT);
    }

    /**
     * @return Connection
     */
    public function getAskoin(): Connection
    {
        return $this->getDoctrine()->getConnection(self::ASKOIN);
    }

    /**
     * @return Connection
     */
    public function getFrancs(): Connection
    {
        return $this->getDoctrine()->getConnection(self::FRANCS);
    }

    /**
     * @return Connection
     */
    public function getYandex(): Connection
    {
        return $this->getDoctrine()->getConnection(self::YANDEX);
    }
}
