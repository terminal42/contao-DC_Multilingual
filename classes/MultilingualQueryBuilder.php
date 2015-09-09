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
 * Class MultilingualQueryBuilder
 *
 * The class reads the relation meta data from the DCA and creates the necessary
 * JOIN queries to retrieve an object from the database.
 */
class MultilingualQueryBuilder
{

    /**
     * Build a query based on the given options
     *
     * @param array $arrOptions The options array
     *
     * @return string The query string
     *
     * @throws \BadMethodCallException
     */
    public static function find($arrOptions)
    {
        $arrLanguageFields = static::getMultilingualFields($arrOptions['table']);
        $strPid = \DC_Multilingual::getPidColumnForTable($arrOptions['table']);
        $strLang = \DC_Multilingual::getLanguageColumnForTable($arrOptions['table']);

        // Use the current language if none provided
        if (!isset($arrOptions['language'])) {
            $arrOptions['language'] = str_replace('-', '_', $GLOBALS['TL_LANGUAGE']);
        }

        // Consider the fallback language
        $fallbackLang = \DC_Multilingual::getFallbackLanguageForTable($arrOptions['table']);
        if (null !== $fallbackLang
            && $fallbackLang === $arrOptions['language']
        ) {
            $arrOptions['language'] = '';
        }


        $strQuery = "SELECT dcm1.*" . (!empty($arrLanguageFields) ? (", " . implode(", ", static::generateFieldsSubquery($arrLanguageFields, 'dcm1', 'dcm2'))) : "") . " FROM " . $arrOptions['table'] . " AS dcm1";

        // Fetch language fields
        if (!empty($arrLanguageFields)) {
            $strQuery .= " LEFT OUTER JOIN " . $arrOptions['table'] . " AS dcm2 ON (dcm1.id=dcm2." . $strPid . " AND dcm2.$strLang='" . $arrOptions['language'] . "')";
        }

        $strQuery .= " WHERE dcm1.$strPid=0";

        // Where condition
        if ($arrOptions['column'] !== null) {
            $strQuery .= " AND " . str_replace($arrOptions['table'] . ".", "dcm1.", (is_array($arrOptions['column']) ? implode(" AND ", $arrOptions['column']) : $arrOptions['table'] . '.' . $arrOptions['column'] . "=?"));
        }

        // Group by
        if ($arrOptions['group'] !== null) {
            $strQuery .= " GROUP BY " . str_replace($arrOptions['table'] . ".", "dcm1.", $arrOptions['group']);
        }

        // Order by
        if ($arrOptions['order'] !== null) {
            $strQuery .= " ORDER BY " . str_replace($arrOptions['table'] . ".", "dcm1.", $arrOptions['order']);
        }

        return $strQuery;
    }


    /**
     * Build a query based on the given options to count the number of records
     *
     * @param array $arrOptions The options array
     *
     * @return string The query string
     */
    public static function count($arrOptions)
    {
        $strPid = \DC_Multilingual::getPidColumnForTable($arrOptions['table']);
        $strQuery = "SELECT COUNT(*) AS count FROM " . $arrOptions['table'] . " WHERE $strPid=0";

        if ($arrOptions['column'] !== null) {
            $strQuery .= " AND " . (is_array($arrOptions['column']) ? implode(" AND ", $arrOptions['column']) : $arrOptions['table'] . '.' . $arrOptions['column'] . "=?");
        }

        return $strQuery;
    }


    /**
     * Get the multilingual fields as array
     * @param string
     * @return array
     */
    public static function getMultilingualFields($strTable)
    {
        $arrFields = array();

        \Controller::loadDataContainer($strTable);

        foreach ($GLOBALS['TL_DCA'][$strTable]['fields'] as $k => $v) {
            if ($v['eval']['translatableFor']) {
                $arrFields[] = $k;
            }
        }

        return $arrFields;
    }


    /**
     * Generate the fields subquery and return it as array
     * @param mixed
     * @param string
     * @param string
     * @param string
     * @return mixed
     */
    public static function generateFieldsSubquery($varFields, $strFirstTable, $strSecondTable, $strPrefix='')
    {
        if (is_array($varFields))
        {
            $arrReturn = array();

            foreach ($varFields as $field)
            {
                $arrReturn[] = static::generateFieldsSubquery($field, $strFirstTable, $strSecondTable, $strPrefix);
            }

            return $arrReturn;
        }

        return "IFNULL(" . $strSecondTable . "." . $varFields . ", " . $strFirstTable . "." . $varFields . ") AS " . (strlen($strPrefix) ? $strPrefix : "") . $varFields;
    }
}
