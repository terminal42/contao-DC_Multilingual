<?php
/**
 * Created by PhpStorm.
 * User: yanickwitschi
 * Date: 05.02.16
 * Time: 13:10
 */

namespace Terminal42\DcMultilingualBundle;

use Doctrine\DBAL\Query\QueryBuilder;

class MultilingualQueryBuilder implements MultilingualQueryBuilderInterface
{
    /**
     * @var QueryBuilder
     */
    private $qb;

    /**
     * @var string
     */
    private $table;

    /**
     * @var string
     */
    private $langColumnName;

    /**
     * @var string
     */
    private $pidColumnName;

    /**
     * @var array
     */
    private $regularFields;

    /**
     * @var array
     */
    private $translatableFields;

    /**
     * MultilingualQueryBuilder constructor.
     *
     * @param QueryBuilder $qb
     * @param string       $table
     * @param string       $pidColumnName
     * @param string       $langColumnName
     * @param array        $regularFields
     * @param array        $translatableFields
     */
    public function __construct(
        QueryBuilder $qb,
        $table,
        $pidColumnName,
        $langColumnName,
        array $regularFields,
        array $translatableFields
    ) {
        $this->qb = $qb;
        $this->table = $table;
        $this->langColumnName = $langColumnName;
        $this->pidColumnName = $pidColumnName;
        $this->regularFields = $regularFields;
        $this->translatableFields = $translatableFields;
    }

    /**
     * Build the query for a simple count query.
     */
    public function getQueryBuilderForCount()
    {
        $this->qb->resetQueryParts();

        $this->qb->addSelect('COUNT(id) AS count')
            ->from($this->table)
            ->where("{$this->pidColumnName}=0");
    }

    /**
     * Build the query builder for a find query.
     *
     * @param string $language
     */
    public function buildQueryBuilderForFind($language)
    {
        $this->qb->resetQueryParts();

        // Regular fields
        foreach ($this->regularFields as $field) {
            $this->qb->addSelect("t1.$field");
        }

        // Translatable fields
        foreach ($this->translatableFields as $field) {
            $this->qb->addSelect("IFNULL(t2.$field, t1.$field) AS $field");
        }

        $this->qb->from($this->table, 't1');
        $this->qb->add('join', [
            [
                'joinType'      => 'left outer',
                'joinTable'     => $this->table,
                'joinAlias'     => 't2',
                'condition'     => "t1.id=t2.{$this->pidColumnName} AND t2.{$this->langColumnName}='$language')"
            ]
        ], true);

        $this->qb->where("t1.{$this->pidColumnName}=0");
    }

    /**
     * @return QueryBuilder
     */
    public function getQueryBuilder()
    {
        return $this->qb;
    }
}
