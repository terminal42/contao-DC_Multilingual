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
 * Create a Query for a DC_Multilingual table
 */
class DC_Multilingual_Query extends Controller
{

    protected $strTable = '';
    protected $arrFields = array();
    protected $arrWhere = array();
    protected $arrOrder = array();
    protected $arrJoin = array();

    /**
     * @var string Language to fetch
     */
    public $language = '';


    /**
     * Construct the querybuilder
     * If you need to reference the table you have
     * * t1 for the base-fields
     * * t2 for the language-fields
     * @param string $strTable
     * @return DC_Multilingual_Query current instance
     */
    public function __construct($strTable)
    {
        parent::__construct();

        $this->strTable = $strTable;

        // Load DataContainer if its not already done
        if (!is_array($GLOBALS['TL_DCA'][$this->strTable])) {
            $this->loadDataContainer($this->strTable);
        }

        // add multilingual fields
        foreach ($GLOBALS['TL_DCA'][$this->strTable]['fields'] as $field => $arrData) {
            if ($arrData['eval']['translatableFor'] == '')continue;
            $this->arrFields[] = "IFNULL(t2.$field, t1.$field) AS $field";
        }

        // set default language
        $this->language = str_replace('-', '_', $GLOBALS['TL_LANGUAGE']);

        return $this;
    }


    /**
     * Add a field
     *
     * @param string $strField fielname
     *
     * @return DC_Multilingual_Query current instance
     */
    public function addField($strField)
    {
        $this->arrFields[] = $strField;

        return $this;
    }


    /**
     * Add a WHERE-constraint
     * all WHERE-pieces glued with AND
     *
     * @param string $strWhere
     *
     * @return DC_Multilingual_Query current instance
     */
    public function addWhere($strWhere)
    {
        $this->arrWhere[] = $strWhere;

        return $this;
    }


    /**
     * Add a JOIN-statement
     *
     * @param string $strJoin
     *
     * @return DC_Multilingual_Query current instance
     */
    public function addJoin($strJoin)
    {
        $this->arrJoin[] = $strJoin;

        return $this;
    }


    /**
     * Add a ORDER-constraint
     *
     * @param string $strOrderfield
     *
     * @return DC_Multilingual_Query current instance
     */
    public function addOrder($strOrderfield)
    {
        $this->arrOrder[] = $strOrderfield;

        return $this;
    }


    /**
     * Returns the query
     * @return string
     */
    public function getQuery()
    {
        $strPid = \DC_Multilingual::getPidColumnForTable($this->strTable);
        $strLang = \DC_Multilingual::getLanguageColumnForTable($this->strTable);

        return "
SELECT t1.*,
    " . implode(', ', $this->arrFields) . "
FROM {$this->strTable} AS t1
LEFT OUTER JOIN {$this->strTable} AS t2 ON (t1.id=t2.$strPid AND t2.$strLang='{$this->language}') ".
implode(' ',$this->arrJoin) ."
WHERE t1.$strPid=0".
(count($this->arrWhere) ? ' AND (' .implode(' AND ',$this->arrWhere).')' : '').
(count($this->arrOrder) ? ' ORDER BY '.implode(',',$this->arrOrder) : '')
;
    }


    /**
     * Returns the Database_Statement for the query
     * @return \Database\Statement
     */
    public function getStatement()
    {
        return \Database::getInstance()->prepare($this->getQuery());
    }
}
