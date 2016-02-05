<?php

/**
 * dc_multilingual Extension for Contao Open Source CMS
 *
 * @copyright  Copyright (c) 2011-2016, terminal42 gmbh
 * @author     terminal42 gmbh <info@terminal42.ch>
 * @license    http://opensource.org/licenses/lgpl-3.0.html LGPL
 * @link       http://github.com/terminal42/contao-dc_multilingual
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
