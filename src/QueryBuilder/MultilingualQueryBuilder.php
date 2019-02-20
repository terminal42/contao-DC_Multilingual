<?php

/*
 * dc_multilingual Extension for Contao Open Source CMS
 *
 * @copyright  Copyright (c) 2011-2019, terminal42 gmbh
 * @author     terminal42 gmbh <info@terminal42.ch>
 * @license    http://opensource.org/licenses/lgpl-3.0.html LGPL
 * @link       http://github.com/terminal42/contao-dc_multilingual
 */

namespace Terminal42\DcMultilingualBundle\QueryBuilder;

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
    public function buildQueryBuilderForCount()
    {
        $this->qb->resetQueryParts();

        $this->qb->addSelect('COUNT(t1.id) AS count')
            ->from($this->table, 't1')
            ->where("t1.{$this->pidColumnName}=0");
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
            't1' => [
                'joinType' => 'left outer',
                'joinTable' => $this->table,
                'joinAlias' => 't2',
                'joinCondition' => "t1.id=t2.{$this->pidColumnName} AND t2.{$this->langColumnName}='$language'",
            ],
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
