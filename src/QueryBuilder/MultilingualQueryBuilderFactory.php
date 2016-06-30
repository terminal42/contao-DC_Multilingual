<?php

/**
 * dc_multilingual Extension for Contao Open Source CMS
 *
 * @copyright  Copyright (c) 2011-2016, terminal42 gmbh
 * @author     terminal42 gmbh <info@terminal42.ch>
 * @license    http://opensource.org/licenses/lgpl-3.0.html LGPL
 * @link       http://github.com/terminal42/contao-dc_multilingual
 */

namespace Terminal42\DcMultilingualBundle\QueryBuilder;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;

class MultilingualQueryBuilderFactory implements MultilingualQueryBuilderFactoryInterface
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * QueryBuilderFactory constructor.
     *
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Builds a MultilingualQueryBuilder.
     *
     * @param string       $table
     * @param string       $pidColumnName
     * @param string       $langColumnName
     * @param array        $regularFields
     * @param array        $translatableFields
     *
     * @return MultilingualQueryBuilderInterface
     */
    public function build(
        $table,
        $pidColumnName,
        $langColumnName,
        array $regularFields,
        array $translatableFields
    ) {

        return new MultilingualQueryBuilder(
            $this->createQueryBuilder(),
            $table,
            $pidColumnName,
            $langColumnName,
            $regularFields,
            $translatableFields
        );
    }

    /**
     * @return QueryBuilder
     */
    protected function createQueryBuilder()
    {
        return new QueryBuilder($this->connection);
    }
}
