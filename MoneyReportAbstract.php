<?php
/**
 * Created by PhpStorm.
 * User: pavel
 * Date: 27.08.18
 * Time: 16:50
 */

namespace AppBundle\Service;

abstract class MoneyReportAbstract
{
    protected $connections;
    protected $statsTime = 0;

    private $currencies;
    private $currenciesIds;
    private $systemsExcludedUserIds = [];
    private $courses = [];
    private $coursesById = [];
    private $coursesByName = [];

    protected $log = [];

    public function __construct(
        ConnectionService $connections,
        int $statsTime = null,
        array $courses = [],
        array $coursesById = [],
        array $coursesByName = []
    ) {
        $this->setStatsTime($statsTime);
        $this->setCourses($courses);
        $this->setCoursesById($coursesById);
        $this->setCoursesByName($coursesByName);
        $this->connections = $connections;
    }

    /**
     * @param array $courses
     * @return MoneyReportAbstract
     */
    private function setCourses(array $courses = []): self
    {
        $this->courses = $courses;
        return $this;
    }

    /**
     * @return array
     */
    public function getCourses(): array
    {
        return $this->courses;
    }

    /**
     * @param array $coursesById
     * @return MoneyReportAbstract
     */
    private function setCoursesById(array $coursesById = []): self
    {
        $this->coursesById = $coursesById;
        return $this;
    }

    /**
     * @return array
     */
    public function getCoursesById(): array
    {
        return $this->coursesById;
    }

    /**
     * @param array $coursesByName
     * @return MoneyReportAbstract
     */
    private function setCoursesByName(array $coursesByName = []): self
    {
        $this->coursesByName = $coursesByName;
        return $this;
    }

    /**
     * @return array
     */
    public function getCoursesByName(): array
    {
        return $this->coursesByName;
    }

    /**
     * @param int $statsTime
     * @return MoneyReportAbstract
     */
    public function setStatsTime(int $statsTime): self
    {
        $this->statsTime = $statsTime;
        return $this;
    }

    /**
     * @return int
     */
    public function getStatsTime(): int
    {
        return $this->statsTime;
    }

    /**
     * @param array $systemsExcludedUserIds
     * @return MoneyReportAbstract
     */
    public function setSystemsExcludedUserIds(array $systemsExcludedUserIds = []): self
    {
        $this->systemsExcludedUserIds = $systemsExcludedUserIds;
        return $this;
    }

    /**
     * @return array
     */
    public function getSystemsExcludedUserIds(): array
    {
        return $this->systemsExcludedUserIds;
    }

    /**
     * @param array $currenciesIds
     * @return MoneyReportAbstract
     */
    public function setCurrenciesIds(array $currenciesIds): self
    {
        $this->currenciesIds = $currenciesIds;
        return $this;
    }

    /**
     * @return array
     */
    public function getCurrenciesIds(): array
    {
        if (null === $this->currencies) {
            $this->currencies = $this->getCurrenciesDB();
        }
        return $this->currencies;
    }

    /**
     * @param array $dataArray
     * @return array
     */
    protected function setCurrenciesArrayWithZero(array $dataArray): array
    {
        $result = [];
        foreach ($this->getCurrenciesIds() as $currenciesId) {
            if (!isset($dataArray[$currenciesId])) {
                $dataArray[$currenciesId] = 0;
            }
            $result[$currenciesId] = $dataArray[$currenciesId];
        }
        return $result;
    }

    /**
     * @param array $arrayOfArray
     * @return array
     */
    protected function sumArrayByCurrencyKey(array $arrayOfArray): array
    {
        $result = $this->setCurrenciesArrayWithZero([]);
        foreach ($arrayOfArray as $dataArray) {
            foreach ($dataArray as $currencyId => $sum) {
                $result[$currencyId] += $sum;
            }
        }
        return $result;
    }

    /**
     * @return array
     */
    abstract protected function getMoneyData(): array;

    /**
     * @return array
     */
    abstract protected function getCurrenciesDB(): array;
}
