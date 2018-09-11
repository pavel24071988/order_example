<?php
/**
 * Created by PhpStorm.
 * User: pavel
 * Date: 30.08.18
 * Time: 12:37
 */

namespace AppBundle\Service;

class CalculatorCommon
{
    private const VALUES = 'values';
    private const PARTS = 'parts';

    private const ACTIVE = 'active';
    private const PASSIVE = 'passive';
    private const DIFF = 'diff';
    private const INFO = 'info';

    private $moneyData;

    /**
     * @return array
     */
    public function getMoneyData(): array
    {
        return $this->moneyData;
    }

    /**
     * @param array $moneyData
     * @return array
     */
    public function getMoneyDataGlued(array $moneyData): array
    {
        $this->setActiveSum($this->getGlued($moneyData, self::ACTIVE));
        $this->setPassiveSum($this->getGlued($moneyData, self::PASSIVE));
        $this->setInfoSum($this->getGlued($moneyData, self::INFO));
        $this->setDiffSum($this->getGlued($moneyData, self::DIFF));

        return $this->getMoneyData();
    }

    /**
     * @param array $actives
     */
    private function setActiveSum(array $actives): void
    {
        $result = [
            self::VALUES => [],
            self::PARTS => [],
        ];
        foreach ($actives as $active) {
            $result[self::VALUES] = $this->getSumOfValues($active[self::VALUES], $result[self::VALUES]);
            $result[self::PARTS] = $this->getSumOfParts($active[self::PARTS], $result[self::PARTS]);
        }

        $this->moneyData[self::ACTIVE] = $result;
    }

    /**
     * @param array $passives
     */
    private function setPassiveSum(array $passives): void
    {
        $result = [
            self::VALUES => [],
            self::PARTS => [],
        ];
        foreach ($passives as $passive) {
            $result[self::VALUES] = $this->getSumOfValues($passive[self::VALUES], $result[self::VALUES]);
            $result[self::PARTS] = $this->getSumOfParts($passive[self::PARTS], $result[self::PARTS]);
        }

        $this->moneyData[self::PASSIVE] = $result;
    }

    /**
     * @param array $info
     */
    private function setInfoSum(array $info): void
    {
        $result = [
            self::VALUES => [],
            self::PARTS => [],
        ];
        foreach ($info as $value) {
            $result[self::VALUES] = $this->getSumOfValues($value[self::VALUES], $result[self::VALUES]);
            $result[self::PARTS] = $this->getSumOfParts($value[self::PARTS], $result[self::PARTS]);
        }

        $this->moneyData[self::INFO] = $result;
    }

    /**
     * @param array $diff
     */
    private function setDiffSum(array $diff): void
    {
        $result = [];
        foreach ($diff as $value) {
            $result = $this->getSumOfValues($value, $result);
        }

        $this->moneyData[self::DIFF] = $result;
    }

    /**
     * @param $array
     * @param $key
     * @return array
     */
    private function getGlued(array $array, string $key): array
    {
        $result = [];
        foreach ($array as $assocArray) {
            $result[] = $assocArray[$key];
        }
        return $result;
    }

    /**
     * @param $values
     * @param $result
     * @return array
     */
    private function getSumOfValues($values, $result): array
    {
        foreach ($values as $key => $value) {
            if (isset($result[$key])) {
                $result[$key] += $value;
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    /**
     * @param $values
     * @param $result
     * @return array
     */
    private function getSumOfParts($values, $result): array
    {
        foreach ($values as $partName => $parts) {
            foreach ($parts as $key => $value) {
                if (isset($result[$partName][$key])) {
                    $result[$partName][$key] += $value;
                } else {
                    $result[$partName][$key] = $value;
                }
            }
        }
        return $result;
    }
}
