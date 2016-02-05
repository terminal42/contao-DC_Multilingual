<?php
/**
 * Created by PhpStorm.
 * User: yanickwitschi
 * Date: 05.02.16
 * Time: 13:10
 */

namespace Terminal42\DcMultilingualBundle;

use Doctrine\DBAL\Query\QueryBuilder;

interface MultilingualQueryBuilderInterface
{
    /**
     * Build the query for a simple count query.
     */
    public function getQueryBuilderForCount();

    /**
     * Build the query builder for a find query.
     *
     * @param string $language
     */
    public function buildQueryBuilderForFind($language);

    /**
     * Get the QueryBuilder instance.
     *
     * @return QueryBuilder
     */
    public function getQueryBuilder();
}
