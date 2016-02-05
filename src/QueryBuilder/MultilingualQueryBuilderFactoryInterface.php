<?php

namespace Terminal42\DcMultilingualBundle\QueryBuilder;

use Terminal42\DcMultilingualBundle\MultilingualQueryBuilderInterface;

interface MultilingualQueryBuilderFactoryInterface
{
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
    );
}
