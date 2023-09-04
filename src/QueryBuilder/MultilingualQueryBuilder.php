<?php

declare(strict_types=1);

namespace Terminal42\DcMultilingualBundle\QueryBuilder;

use Doctrine\DBAL\Query\QueryBuilder;

class MultilingualQueryBuilder implements MultilingualQueryBuilderInterface
{
    /**
     * MultilingualQueryBuilder constructor.
     *
     * @param string $table
     * @param string $pidColumnName
     * @param string $langColumnName
     */
    public function __construct(
        private readonly QueryBuilder $qb,
        private $table,
        private $pidColumnName,
        private $langColumnName,
        private readonly array $regularFields,
        private readonly array $translatableFields,
    ) {
    }

    /**
     * Build the query for a simple count query.
     */
    public function buildQueryBuilderForCount(): void
    {
        $this->qb->resetQueryParts();

        $this->qb->addSelect("COUNT({$this->table}.id) AS count")
            ->from($this->table, $this->table)
            ->where("{$this->table}.{$this->pidColumnName}=0")
        ;
    }

    /**
     * Build the query for a simple count query with a subquery.
     */
    public function buildQueryBuilderForCountWithSubQuery(QueryBuilder $queryBuilder): void
    {
        $this->qb->resetQueryParts();

        $this->qb->addSelect('COUNT(t1.id) AS count')
            ->from($this->table, 't1')
            ->join('t1', sprintf('(%s)', $queryBuilder->getSQL()), 't3', 't1.id = t3.id')
        ;
    }

    /**
     * Build the query builder for a find query.
     *
     * @param string $language
     */
    public function buildQueryBuilderForFind($language): void
    {
        $this->qb->resetQueryParts();

        // Always translate system columns
        $systemColumns = ['id', $this->langColumnName, $this->pidColumnName];

        foreach ($systemColumns as $field) {
            $this->qb->addSelect("IFNULL(translation.$field, {$this->table}.$field) AS $field");
        }

        // Regular fields
        foreach (array_diff($this->regularFields, $this->translatableFields, $systemColumns) as $field) {
            $this->qb->addSelect("{$this->table}.$field");
        }

        // Translatable fields
        foreach (array_intersect($this->translatableFields, $this->regularFields) as $field) {
            $this->qb->addSelect("IFNULL(translation.$field, {$this->table}.$field) AS $field");
        }

        $this->qb->from($this->table, $this->table);
        $this->qb->add('join', [
            $this->table => [
                'joinType' => 'left outer',
                'joinTable' => $this->table,
                'joinAlias' => 'translation',
                'joinCondition' => "{$this->table}.id=translation.{$this->pidColumnName} AND translation.{$this->langColumnName}='$language'",
            ],
        ], true);

        $this->qb->where("{$this->table}.{$this->pidColumnName}=0");
    }

    /**
     * @return QueryBuilder
     */
    public function getQueryBuilder()
    {
        return $this->qb;
    }
}
