<?php

declare(strict_types=1);

namespace Terminal42\DcMultilingualBundle\QueryBuilder;

interface MultilingualQueryBuilderFactoryInterface
{
    /**
     * Builds a MultilingualQueryBuilder.
     *
     * @param string $table
     * @param string $pidColumnName
     * @param string $langColumnName
     *
     * @return MultilingualQueryBuilderInterface
     */
    public function build($table, $pidColumnName, $langColumnName, array $regularFields, array $translatableFields);
}
