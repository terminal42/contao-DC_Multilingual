<?php

namespace Contao;


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
     */
    public static function find($arrOptions)
    {
        $objBase = new \DcaExtractor($arrOptions['table']);
        $arrLanguageFields = static::getMultilingualFields($arrOptions['table']);
        $strPid = isset($GLOBALS['TL_DCA'][$arrOptions['table']]['config']['pidColumn']) ? $GLOBALS['TL_DCA'][$arrOptions['table']]['config']['pidColumn'] : 'langPid';
        $strLang = isset($GLOBALS['TL_DCA'][$arrOptions['table']]['config']['langColumn']) ? $GLOBALS['TL_DCA'][$arrOptions['table']]['config']['langColumn'] : 'language';

        // Use the current language if none provided
        if (!isset($arrOptions['language'])) {
            $arrOptions['language'] = $GLOBALS['TL_LANGUAGE'];
        }

        // Regular query
        if (!$objBase->hasRelations()) {
            $strQuery = "SELECT dcm1.*" . (!empty($arrLanguageFields) ? (", " . implode(", ", static::generateFieldsSubquery($arrLanguageFields, 'dcm1', 'dcm2'))) : "") . $arrOptions['table'] . " AS dcm1";

            if (!empty($arrLanguageFields)) {
                $strQuery .= " LEFT OUTER JOIN " . $arrOptions['table'] . " AS dcm2 ON (dcm1.id=dcm2." . $strPid . " AND dcm2.$strLang='" . $arrOptions['language'] . "')";
            }
        } else {
            // Query with relations
            $arrJoins = array();
            $arrFields = array("dcm1.*");
            $intCount = 0;

            // Add the language fields
            if (!empty($arrLanguageFields)) {
                $arrFields = array_merge($arrFields, static::generateFieldsSubquery($arrLanguageFields, 'dcm1', 'dcm2'));
            }

            foreach ($objBase->getRelations() as $strKey => $arrConfig) {
                // Automatically join the single-relation records
                if ($arrConfig['load'] == 'eager' || $arrOptions['eager']) {
                    if ($arrConfig['type'] == 'hasOne' || $arrConfig['type'] == 'belongsTo') {
                        ++$intCount;
                        $objRelated = new \DcaExtractor($arrConfig['table']);
                        $arrLanguageFieldsRelation = static::getMultilingualFields($arrConfig['table']);

                        foreach (array_keys($objRelated->getFields()) as $strField) {
                            if (in_array($strField, $arrLanguageFieldsRelation)) {
                                $arrFields[] = static::generateFieldsSubquery($strField, "j".$intCount."dcm1", "j".$intCount."dcm2", $strKey . "__");
                            } else {
                                $arrFields[] = "j" . $intCount . "." . $strField . " AS " . $strKey . "__" . $strField;
                            }
                        }
// @todo - make sure the relations also use " WHERE dcm1.$strPid=0"
                        // Use multilingual query or normal
                        if (!empty($arrLanguageFieldsRelation)) {
                            $strPidRelation = isset($GLOBALS['TL_DCA'][$arrConfig['table']]['config']['pidColumn']) ? $GLOBALS['TL_DCA'][$arrConfig['table']]['config']['pidColumn'] : 'langPid';
                            $strLangRelation = isset($GLOBALS['TL_DCA'][$arrConfig['table']]['config']['langColumn']) ? $GLOBALS['TL_DCA'][$arrConfig['table']]['config']['langColumn'] : 'language';

                            $arrJoins[] = " LEFT JOIN " . $arrConfig['table'] . " j$intCount" . "dcm1 ON " . $arrOptions['table'] . "." . $strKey . "=j$intCount" . "dcm1.id";
                            $arrJoins[] = " LEFT OUTER JOIN " . $arrConfig['table'] . " AS j$intcount" . "dcm2 ON (j$intCount" . "dcm1.id=j$intCount" . "dcm2." . $strPid . " AND j$intCount" . "dcm2.$strLangRelation='" . $arrOptions['language'] . "')";
                        } else {
                            $arrJoins[] = " LEFT JOIN " . $arrConfig['table'] . " j$intCount ON " . $arrOptions['table'] . "." . $strKey . "=j$intCount.id";
                        }
                    }
                }
            }

            // Generate the query
            $strQuery = "SELECT " . implode(', ', $arrFields) . " FROM " . $arrOptions['table'] . " AS dcm1" . implode("", $arrJoins);

            if (!empty($arrLanguageFields)) {
                $strQuery .= " LEFT OUTER JOIN " . $arrOptions['table'] . " AS dcm2 ON (dcm1.id=dcm2." . $strPid . " AND dcm2.language_dc='" . $arrOptions['language'] . "')";
            }
        }

        $strQuery .= " WHERE dcm1.$strPid=0";

        // Where condition
        if ($arrOptions['column'] !== null) {
            foreach ($arrOptions['column'] as $k => $v) {
                $arrOptions[$k] = str_replace($arrOptions['table'] . ".", "dcm1.", $v);
            }

            $strQuery .= " AND " . (is_array($arrOptions['column']) ? implode(" AND ", $arrOptions['column']) : $arrOptions['table'] . '.' . $arrOptions['column'] . "=?");
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
        $strPid = isset($GLOBALS['TL_DCA'][$arrOptions['table']]['config']['pidColumn']) ? $GLOBALS['TL_DCA'][$arrOptions['table']]['config']['pidColumn'] : 'langPid';
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
