<?php

/**
 * dc_multilingual Extension for Contao Open Source CMS
 *
 * @copyright  Copyright (c) 2011-2014, terminal42 gmbh
 * @author     terminal42 gmbh <info@terminal42.ch>
 * @license    http://opensource.org/licenses/lgpl-3.0.html LGPL
 * @link       http://github.com/terminal42/contao-dc_multilingual
 */


/**
 * Class MultilingualModel
 *
 * Provide methods to handle multilingual models
 */
abstract class MultilingualModel extends \Model
{
    /**
     * Prevent the model from saving
     *
     * @param \Database\Result $objResult An optional database result
     */
    public function __construct(\Database\Result $objResult = null)
    {
        parent::__construct($objResult);

        $this->preventSaving(false);
    }

    /**
     * Use the multilingual query
     * @param boolean
     */
    protected static $blnMultilingual = true;


    /**
     * Build a query based on the given options
     *
     * @param array $arrOptions The options array
     *
     * @return string The query string
     */
    protected static function buildFindQuery(array $arrOptions)
    {
        if (static::$blnMultilingual === false) {
            return parent::buildFindQuery($arrOptions);
        }

        return \MultilingualQueryBuilder::find($arrOptions);
    }


    /**
     * Build a query based on the given options to count the number of records
     *
     * @param array $arrOptions The options array
     *
     * @return string The query string
     */
    protected static function buildCountQuery(array $arrOptions)
    {
        if (static::$blnMultilingual === false) {
            return parent::buildCountQuery($arrOptions);
        }

        return \MultilingualQueryBuilder::count($arrOptions);
    }
}
