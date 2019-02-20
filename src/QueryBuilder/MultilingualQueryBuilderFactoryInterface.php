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

interface MultilingualQueryBuilderFactoryInterface
{
    /**
     * Builds a MultilingualQueryBuilder.
     *
     * @param string $table
     * @param string $pidColumnName
     * @param string $langColumnName
     * @param array  $regularFields
     * @param array  $translatableFields
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
