<?php

declare(strict_types=1);

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
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Builds a MultilingualQueryBuilder.
     *
     * @param string $table
     * @param string $pidColumnName
     * @param string $langColumnName
     *
     * @return MultilingualQueryBuilderInterface
     */
    public function build($table, $pidColumnName, $langColumnName, array $regularFields, array $translatableFields)
    {
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
