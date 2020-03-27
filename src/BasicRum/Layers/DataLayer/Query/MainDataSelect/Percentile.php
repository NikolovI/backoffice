<?php

declare(strict_types=1);

namespace App\BasicRum\Layers\DataLayer\Query\MainDataSelect;

class Percentile implements MainDataInterface
{
    /** @var string */
    private $tableName;

    /** @var string */
    private $fieldName;

    /** @var int */
    private $percentile;

    /**
     * Percentile constructor.
     */
    public function __construct(
        string $tableName,
        string $fieldName,
        int $percentile
    ) {
        $this->tableName = $tableName;
        $this->fieldName = $fieldName;
        $this->percentile = $percentile;
    }

    public function getPercentileSql(string $where, array $limitWhere): string
    {
        $limitWhereStr = implode(' AND ', $limitWhere);

        if (!empty($where)) {
            $where = ' AND '.$where;
        }

        return
"SELECT DISTINCT first_value({$this->fieldName}) OVER (ORDER BY CASE WHEN p <= 0.{$this->percentile} THEN p END DESC) x
FROM (
    SELECT
    {$this->tableName}.{$this->fieldName},
    percent_rank() OVER (ORDER BY {$this->tableName}.{$this->fieldName}) p
  FROM {$this->tableName}
  WHERE {$limitWhereStr} {$where} AND {$this->tableName}.{$this->fieldName} > 0
) t";
    }

    /**
     * @param $connection
     */
    public function retrieve($connection, string $where, array $limitWhere): array
    {
        $sql = $this->getPercentileSql($where, $limitWhere);

        $res = $connection->fetchAll($sql);

        return $res;
    }

    public function getCacheKeyPart(): string
    {
        return 'percentile_'.md5(
                $this->tableName.
                $this->fieldName.
                $this->percentile
            );
    }
}
