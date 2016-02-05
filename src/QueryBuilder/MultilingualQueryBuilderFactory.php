<?php

namespace Terminal42\DcMultilingualBundle\QueryBuilder;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Terminal42\DcMultilingualBundle\MultilingualQueryBuilder;
use Terminal42\DcMultilingualBundle\MultilingualQueryBuilderInterface;

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
