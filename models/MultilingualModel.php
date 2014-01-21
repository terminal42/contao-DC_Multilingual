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
     * Use the multilingual query
     * @param boolean
     */
    protected static $blnMultilingual = true;


    /**
     * {@inheritdoc}
     */
    protected static function find(array $arrOptions)
    {
        if (static::$blnMultilingual === false) {
            return parent::find($arrOptions);
        }

        if (static::$strTable == '') {
            return null;
        }

        $arrOptions['table'] = static::$strTable;
        $strQuery = \MultilingualQueryBuilder::find($arrOptions);

        $objStatement = \Database::getInstance()->prepare($strQuery);

        // Defaults for limit and offset
        if (!isset($arrOptions['limit']))
        {
            $arrOptions['limit'] = 0;
        }
        if (!isset($arrOptions['offset']))
        {
            $arrOptions['offset'] = 0;
        }

        // Limit
        if ($arrOptions['limit'] > 0 || $arrOptions['offset'] > 0)
        {
            $objStatement->limit($arrOptions['limit'], $arrOptions['offset']);
        }

        $objStatement = static::preFind($objStatement);
        $objResult = $objStatement->execute($arrOptions['value']);

        if ($objResult->numRows < 1)
        {
            return null;
        }

        $objResult = static::postFind($objResult);

        if ($arrOptions['return'] == 'Model')
        {
            $strPk = static::$strPk;
            $intPk = $objResult->$strPk;

            // Try to load from the registry
            $objModel = \Model\Registry::getInstance()->fetch(static::$strTable, $intPk);

            if ($objModel !== null)
            {
                return $objModel->mergeRow($objResult->row());
            }

            return new static($objResult);
        }
        else
        {
            return \Model\Collection::createFromDbResult($objResult, static::$strTable);
        }
    }


    /**
     * {@inheritdoc}
     */
    public static function countBy($strColumn=null, $varValue=null)
    {
        if (static::$blnMultilingual === false) {
            return parent::count($strColumn, $varValue);
        }

        if (static::$strTable == '')
        {
            return 0;
        }

        $strQuery = \MultilingualQueryBuilder::count(array
        (
            'table'  => static::$strTable,
            'column' => $strColumn,
            'value'  => $varValue
        ));

        return (int) \Database::getInstance()->prepare($strQuery)->execute($varValue)->count;
    }
}
