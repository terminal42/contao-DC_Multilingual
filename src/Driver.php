<?php

/*
 * dc_multilingual Extension for Contao Open Source CMS
 *
 * @copyright  Copyright (c) 2011-2017, terminal42 gmbh
 * @author     terminal42 gmbh <info@terminal42.ch>
 * @license    http://opensource.org/licenses/lgpl-3.0.html LGPL
 * @link       http://github.com/terminal42/contao-dc_multilingual
 */

namespace Terminal42\DcMultilingualBundle;

use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\CoreBundle\Exception\InternalServerErrorException;
use Contao\System;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Class Driver
 *
 * This class is based on the DC_Table driver of Contao 4.1
 */
class Driver extends \DC_Table
{
    /**
     * True if we are editing a language that is not the fallback
     *
     * @param bool
     */
    protected $editLang = false;

    /**
     * Array containing all languages that are translatable
     *
     * @var array
     */
    protected $translatableLangs = [];

    /**
     * Fallback language
     *
     * @var string
     */
    protected $fallbackLang = '';

    /**
     * Language we are currently editing
     *
     * @var string
     */
    protected $currentLang = '';

    /**
     * Language column name
     *
     * @var string
     */
    protected $langColumnName;

    /**
     * Language PID column name
     *
     * @var string
     */
    protected $pidColumnName;

    /**
     * Session key
     *
     * @var string
     */
    protected $sessionKey;

    /**
     * Initialize the object
     *
     * @param string $strTable
     * @param array  $arrModule
     */
    public function __construct($strTable, $arrModule=[])
    {
        parent::__construct($strTable, $arrModule);

        $dca = &$GLOBALS['TL_DCA'][$this->strTable];

        // Languages array
        if (isset($dca['config']['languages'])) {
            $this->translatableLangs = $dca['config']['languages'];
        } else {
            $this->translatableLangs = $this->getRootPageLanguages();
        }

        // Fallback language
        if (isset($dca['config']['fallbackLang'])) {
            $this->fallbackLang = $dca['config']['fallbackLang'];

            if (!in_array($this->fallbackLang, $this->translatableLangs)) {
                $this->translatableLangs[] = $this->fallbackLang;
            }
        }

        // Session key
        $this->sessionKey = 'dc_multilingual:' . $this->strTable . ':' . $this->intId;

        // Column names
        $this->pidColumnName = $dca['config']['langPid'] ?: 'langPid';
        $this->langColumnName = $dca['config']['langColumnName'] ?: 'language';

        // Filter out translations
        if ($dca['list']['sorting']['mode'] !== 5 && $dca['list']['sorting']['mode'] !== 6) {
            $dca['list']['sorting']['filter'][] = array($this->langColumnName.'=?', '');
        }

        // Add CSS file to place the language dropdown
        $GLOBALS['TL_CSS'][] = 'bundles/terminal42dcmultilingual/backend.min.css';
    }

    /**
     * Auto-generate a form to edit the current database record
     *
     * @param integer $intId
     * @param integer $ajaxId
     *
     * @return string
     *
     * @throws AccessDeniedException
     * @throws InternalServerErrorException
     */
    public function edit($intId=null, $ajaxId=null)
    {
        if ($GLOBALS['TL_DCA'][$this->strTable]['config']['notEditable'])
        {
            throw new InternalServerErrorException('Table "' . $this->strTable . '" is not editable.');
        }

        if ($intId != '')
        {
            $this->intId = $intId;
            $this->sessionKey = 'dc_multilingual:' . $this->strTable . ':' . $this->intId;
        }

        // Get the current record
        $objRow = $this->Database->prepare("SELECT * FROM " . $this->strTable . " WHERE id=?")
            ->limit(1)
            ->execute($this->intId);

        // Access to a translation detected, redirect with main language id and request translated version
        if ($objRow->{$this->pidColumnName} > 0)
        {
            $this->sessionKey = 'dc_multilingual:' . $this->strTable . ':' . $objRow->{$this->pidColumnName};
            $objSessionBag = \System::getContainer()->get('session')->getBag('contao_backend');
            $objSessionBag->set($this->sessionKey, $objRow->{$this->langColumnName});
            $this->redirect($this->addToUrl('id=' . $objRow->{$this->pidColumnName}));
        }

        // Deny access if there is no record with the given ID or a translated version is accessed
        if ($objRow->numRows < 1 || $objRow->{$this->pidColumnName} > 0)
        {
            throw new AccessDeniedException('Cannot load record "' . $this->strTable . '.id=' . $this->intId . '".');
        }

        $this->objActiveRecord = $objRow;

        $return = '';
        $this->values[] = $this->intId;
        $this->procedure[] = 'id=?';

        $this->blnCreateNewVersion = false;
        $objVersions = new \Versions($this->strTable, $this->intId);

        if (!$GLOBALS['TL_DCA'][$this->strTable]['config']['hideVersionMenu'])
        {
            // Compare versions
            if (\Input::get('versions'))
            {
                $objVersions->compare();
            }

            // Restore a version
            if (\Input::post('FORM_SUBMIT') == 'tl_version' && \Input::post('version') != '')
            {
                $objVersions->restore(\Input::post('version'));
                $this->reload();
            }
        }

        // Handle language change or deletion
        $this->handleLanguageOperation();

        // Load the language record
        $this->loadCurrentLanguageRecord();

        $objVersions->initialize();

        // Build an array from boxes and rows
        $this->strPalette = $this->getPalette();
        $boxes = trimsplit(';', $this->strPalette);
        $legends = array();

        if (!empty($boxes))
        {
            foreach ($boxes as $k=>$v)
            {
                $eCount = 1;
                $boxes[$k] = trimsplit(',', $v);

                foreach ($boxes[$k] as $kk=>$vv)
                {
                    if (preg_match('/^\[.*\]$/', $vv))
                    {
                        ++$eCount;
                        continue;
                    }

                    if (preg_match('/^\{.*\}$/', $vv))
                    {
                        $legends[$k] = substr($vv, 1, -1);
                        unset($boxes[$k][$kk]);
                    }
                    elseif ($GLOBALS['TL_DCA'][$this->strTable]['fields'][$vv]['exclude'] || !is_array($GLOBALS['TL_DCA'][$this->strTable]['fields'][$vv]))
                    {
                        unset($boxes[$k][$kk]);
                    }
                }

                // Unset a box if it does not contain any fields
                if (count($boxes[$k]) < $eCount)
                {
                    unset($boxes[$k]);
                }
            }

            /** @var SessionInterface $objSessionBag */
            $objSessionBag = \System::getContainer()->get('session')->getBag('contao_backend');

            $class = 'tl_tbox';
            $fs = $objSessionBag->get('fieldset_states');
            $blnIsFirst = true;

            // Render boxes
            foreach ($boxes as $k=>$v)
            {
                $strAjax = '';
                $blnAjax = false;
                $key = '';
                $cls = '';
                $legend = '';

                if (isset($legends[$k]))
                {
                    list($key, $cls) = explode(':', $legends[$k]);
                    $legend = "\n" . '<legend onclick="AjaxRequest.toggleFieldset(this,\'' . $key . '\',\'' . $this->strTable . '\')">' . (isset($GLOBALS['TL_LANG'][$this->strTable][$key]) ? $GLOBALS['TL_LANG'][$this->strTable][$key] : $key) . '</legend>';
                }

                if (isset($fs[$this->strTable][$key]))
                {
                    $class .= ($fs[$this->strTable][$key] ? '' : ' collapsed');
                }
                else
                {
                    $class .= (($cls && $legend) ? ' ' . $cls : '');
                }

                $return .= "\n\n" . '<fieldset' . ($key ? ' id="pal_'.$key.'"' : '') . ' class="' . $class . ($legend ? '' : ' nolegend') . '">' . $legend;

                // Build rows of the current box
                foreach ($v as $vv)
                {
                    if ($vv == '[EOF]')
                    {
                        if ($blnAjax && \Environment::get('isAjaxRequest'))
                        {
                            return $strAjax . '<input type="hidden" name="FORM_FIELDS[]" value="'.specialchars($this->strPalette).'">';
                        }

                        $blnAjax = false;
                        $return .= "\n" . '</div>';

                        continue;
                    }

                    if (preg_match('/^\[.*\]$/', $vv))
                    {
                        $thisId = 'sub_' . substr($vv, 1, -1);
                        $blnAjax = ($ajaxId == $thisId && \Environment::get('isAjaxRequest')) ? true : false;
                        $return .= "\n" . '<div id="'.$thisId.'">';

                        continue;
                    }

                    $this->strField = $vv;
                    $this->strInputName = $vv;
                    $this->varValue = $this->objActiveRecord->$vv; // Original DC_Table uses $objRow here!

                    // Autofocus the first field
                    if ($blnIsFirst && $GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['inputType'] == 'text')
                    {
                        $GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['eval']['autofocus'] = 'autofocus';
                        $blnIsFirst = false;
                    }

                    // Convert CSV fields (see #2890)
                    if ($GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['eval']['multiple'] && isset($GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['eval']['csv']))
                    {
                        $this->varValue = trimsplit($GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['eval']['csv'], $this->varValue);
                    }

                    // Call load_callback
                    if (is_array($GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['load_callback']))
                    {
                        foreach ($GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['load_callback'] as $callback)
                        {
                            if (is_array($callback))
                            {
                                $this->import($callback[0]);
                                $this->varValue = $this->{$callback[0]}->{$callback[1]}($this->varValue, $this);
                            }
                            elseif (is_callable($callback))
                            {
                                $this->varValue = $callback($this->varValue, $this);
                            }
                        }
                    }

                    // Re-set the current value
                    $this->objActiveRecord->{$this->strField} = $this->varValue;

                    // Build the row and pass the current palette string (thanks to Tristan Lins)
                    $blnAjax ? $strAjax .= $this->row($this->strPalette) : $return .= $this->row($this->strPalette);
                }

                $class = 'tl_box';
                $return .= "\n" . '</fieldset>';
            }
        }

        // Versions overview
        if ($GLOBALS['TL_DCA'][$this->strTable]['config']['enableVersioning'] && !$GLOBALS['TL_DCA'][$this->strTable]['config']['hideVersionMenu'])
        {
            $version = $objVersions->renderDropdown();
        }
        else
        {
            $version = '';
        }

        // Add language switch panel
        $version = $this->addLanguageSwitchPanel($version);

        // Submit buttons
        $arrButtons = array();
        $arrButtons['save'] = '<button type="submit" name="save" id="save" class="tl_submit" accesskey="s">'.$GLOBALS['TL_LANG']['MSC']['save'].'</button>';

        if (!\Input::get('nb'))
        {
            $arrButtons['saveNclose'] = '<button type="submit" name="saveNclose" id="saveNclose" class="tl_submit" accesskey="c">'.$GLOBALS['TL_LANG']['MSC']['saveNclose'].'</button>';
        }

        if (!\Input::get('popup') && !$GLOBALS['TL_DCA'][$this->strTable]['config']['closed'] && !$GLOBALS['TL_DCA'][$this->strTable]['config']['notCreatable'])
        {
            $arrButtons['saveNcreate'] = '<button type="submit" name="saveNcreate" id="saveNcreate" class="tl_submit" accesskey="n">'.$GLOBALS['TL_LANG']['MSC']['saveNcreate'].'</button>';
        }

        if ($GLOBALS['TL_DCA'][$this->strTable]['config']['switchToEdit'])
        {
            $arrButtons['saveNedit'] = '<button type="submit" name="saveNedit" id="saveNedit" class="tl_submit" accesskey="e">'.$GLOBALS['TL_LANG']['MSC']['saveNedit'].'</button>';
        }

        if (!\Input::get('popup') && ($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] == 4 || strlen($this->ptable) || $GLOBALS['TL_DCA'][$this->strTable]['config']['switchToEdit']))
        {
            $arrButtons['saveNback'] = '<button type="submit" name="saveNback" id="saveNback" class="tl_submit" accesskey="g">'.$GLOBALS['TL_LANG']['MSC']['saveNback'].'</button>';
        }

        if ($this->editLang) {
            $arrButtons['deleteLanguage'] = '<input type="submit" name="deleteLanguage" class="tl_submit" style="float:right" value="' . specialchars($GLOBALS['TL_LANG']['MSC']['deleteLanguage']) . '" onclick="return confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteLanguageConfirm'] . '\')">';
        }

        // Call the buttons_callback (see #4691)
        if (is_array($GLOBALS['TL_DCA'][$this->strTable]['edit']['buttons_callback']))
        {
            foreach ($GLOBALS['TL_DCA'][$this->strTable]['edit']['buttons_callback'] as $callback)
            {
                if (is_array($callback))
                {
                    $this->import($callback[0]);
                    $arrButtons = $this->{$callback[0]}->{$callback[1]}($arrButtons, $this);
                }
                elseif (is_callable($callback))
                {
                    $arrButtons = $callback($arrButtons, $this);
                }
            }
        }

        // Add the buttons and end the form
        $return .= '
</div>

<div class="tl_formbody_submit">

<div class="tl_submit_container">
  ' . implode(' ', $arrButtons) . '
</div>

</div>
</form>';

        // Set the focus if there is an error
        if ($this->noReload)
        {
            $return .= '
<script>
  window.addEvent(\'domready\', function() {
    Backend.vScrollTo(($(\'' . $this->strTable . '\').getElement(\'label.error\').getPosition().y - 20));
  });
</script>';
        }

        // Begin the form (-> DO NOT CHANGE THIS ORDER -> this way the onsubmit attribute of the form can be changed by a field)
        $return = $version . '
<div id="tl_buttons">' . (\Input::get('nb') ? '&nbsp;' : '
<a href="'.$this->getReferer(true).'" class="header_back" title="'.specialchars($GLOBALS['TL_LANG']['MSC']['backBTTitle']).'" accesskey="b" onclick="Backend.getScrollOffset()">'.$GLOBALS['TL_LANG']['MSC']['backBT'].'</a>') . '
</div>
'.\Message::generate().'
<form action="'.ampersand(\Environment::get('request'), true).'" id="'.$this->strTable.'" class="tl_form" method="post" enctype="' . ($this->blnUploadable ? 'multipart/form-data' : 'application/x-www-form-urlencoded') . '"'.(!empty($this->onsubmit) ? ' onsubmit="'.implode(' ', $this->onsubmit).'"' : '').' novalidate>
<div class="tl_formbody_edit">
<input type="hidden" name="FORM_SUBMIT" value="'.$this->strTable.'">
<input type="hidden" name="REQUEST_TOKEN" value="'.REQUEST_TOKEN.'">
<input type="hidden" name="FORM_FIELDS[]" value="'.specialchars($this->strPalette).'">'.($this->noReload ? '

<p class="tl_error">'.$GLOBALS['TL_LANG']['ERR']['general'].'</p>' : '').$return;

        // Reload the page to prevent _POST variables from being sent twice
        if (\Input::post('FORM_SUBMIT') == $this->strTable && !$this->noReload)
        {
            $arrValues = $this->values;
            array_unshift($arrValues, time());

            // Trigger the onsubmit_callback
            if (is_array($GLOBALS['TL_DCA'][$this->strTable]['config']['onsubmit_callback']))
            {
                foreach ($GLOBALS['TL_DCA'][$this->strTable]['config']['onsubmit_callback'] as $callback)
                {
                    if (is_array($callback))
                    {
                        $this->import($callback[0]);
                        $this->{$callback[0]}->{$callback[1]}($this);
                    }
                    elseif (is_callable($callback))
                    {
                        $callback($this);
                    }
                }
            }

            // Save the current version
            if ($this->blnCreateNewVersion)
            {
                $objVersions->create();

                // Call the onversion_callback
                if (is_array($GLOBALS['TL_DCA'][$this->strTable]['config']['onversion_callback']))
                {
                    foreach ($GLOBALS['TL_DCA'][$this->strTable]['config']['onversion_callback'] as $callback)
                    {
                        if (is_array($callback))
                        {
                            $this->import($callback[0]);
                            $this->{$callback[0]}->{$callback[1]}($this->strTable, $this->intId, $this);
                        }
                        elseif (is_callable($callback))
                        {
                            $callback($this->strTable, $this->intId, $this);
                        }
                    }
                }

                $this->log('A new version of record "'.$this->strTable.'.id='.$this->intId.'" has been created'.$this->getParentEntries($this->strTable, $this->intId), __METHOD__, TL_GENERAL);
            }

            // Set the current timestamp (-> DO NOT CHANGE THE ORDER version - timestamp)
            if ($GLOBALS['TL_DCA'][$this->strTable]['config']['dynamicPtable'])
            {
                $this->Database->prepare("UPDATE " . $this->strTable . " SET ptable=?, tstamp=? WHERE id=?")
                    ->execute($this->ptable, time(), $this->intId);
            }
            else
            {
                $this->Database->prepare("UPDATE " . $this->strTable . " SET tstamp=? WHERE id=?")
                    ->execute(time(), $this->intId);
            }

            // Redirect
            if (isset($_POST['saveNclose']))
            {
                \Message::reset();

                if (version_compare(VERSION, '4.8', '<')) {
                    \System::setCookie('BE_PAGE_OFFSET', 0, 0);
                }

                $this->redirect($this->getReferer());
            }
            elseif (isset($_POST['saveNedit']))
            {
                \Message::reset();

                if (version_compare(VERSION, '4.8', '<')) {
                    \System::setCookie('BE_PAGE_OFFSET', 0, 0);
                }

                $this->redirect($this->addToUrl($GLOBALS['TL_DCA'][$this->strTable]['list']['operations']['edit']['href'], false, array('s2e', 'act')));
            }
            elseif (isset($_POST['saveNback']))
            {
                \Message::reset();

                if (version_compare(VERSION, '4.8', '<')) {
                    \System::setCookie('BE_PAGE_OFFSET', 0, 0);
                }

                if ($this->ptable == '')
                {
                    $this->redirect(TL_SCRIPT . '?do=' . \Input::get('do'));
                }
                // TODO: try to abstract this
                elseif (($this->ptable == 'tl_theme' && $this->strTable == 'tl_style_sheet') || ($this->ptable == 'tl_page' && $this->strTable == 'tl_article'))
                {
                    $this->redirect($this->getReferer(false, $this->strTable));
                }
                else
                {
                    $this->redirect($this->getReferer(false, $this->ptable));
                }
            }
            elseif (isset($_POST['saveNcreate']))
            {
                \Message::reset();

                if (version_compare(VERSION, '4.8', '<')) {
                    \System::setCookie('BE_PAGE_OFFSET', 0, 0);
                }

                $strUrl = TL_SCRIPT . '?do=' . \Input::get('do');

                if (isset($_GET['table']))
                {
                    $strUrl .= '&amp;table=' . \Input::get('table');
                }

                // Tree view
                if ($this->treeView)
                {
                    $strUrl .= '&amp;act=create&amp;mode=1&amp;pid=' . $this->intId;
                }

                // Parent view
                elseif ($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] == 4)
                {
                    $strUrl .= $this->Database->fieldExists('sorting', $this->strTable) ? '&amp;act=create&amp;mode=1&amp;pid=' . $this->intId . '&amp;id=' . $this->activeRecord->pid : '&amp;act=create&amp;mode=2&amp;pid=' . $this->activeRecord->pid;
                }

                // List view
                else
                {
                    $strUrl .= ($this->ptable != '') ? '&amp;act=create&amp;mode=2&amp;pid=' . CURRENT_ID : '&amp;act=create';
                }

                $this->redirect($strUrl . '&amp;rt=' . REQUEST_TOKEN);
            }

            $this->reload();
        }

        // Set the focus if there is an error
        if ($this->noReload)
        {
            $return .= '

<script>
  window.addEvent(\'domready\', function() {
    Backend.vScrollTo(($(\'' . $this->strTable . '\').getElement(\'label.error\').getPosition().y - 20));
  });
</script>';
        }

        return $return;
    }


    /**
     * Duplicate a particular record of the current table with all the
     * translations
     *
     * @param  bool
     *
     * @return int|bool
     */
    public function copy($blnDoNotRedirect = false)
    {
        $insertId = parent::copy(true);
        $time = time();
        $objTranslations = \Database::getInstance()
            ->prepare(
                "SELECT *
                 FROM " . $this->strTable . "
                 WHERE " . $this->pidColumnName . "=? AND " . $this->langColumnName . "!=''"
            )
            ->execute($this->intId);

        while ($objTranslations->next()) {
            $set = $this->set;

            foreach ($objTranslations->row() as $k => $v) {
                if (array_key_exists($k, $GLOBALS['TL_DCA'][$this->strTable]['fields'])) {
                    if (!$GLOBALS['TL_DCA'][$this->strTable]['fields'][$k]['eval']['translatableFor']) {
                        if (isset($GLOBALS['TL_DCA'][$this->strTable]['fields'][$k]['default'])) {
                            $set[$k] = $GLOBALS['TL_DCA'][$this->strTable]['fields'][$k]['default'];
                        } else {
                            unset($set[$k]);
                        }
                        continue;
                    }
                    // Empty unique fields or add a unique identifier in copyAll mode
                    if ($GLOBALS['TL_DCA'][$this->strTable]['fields'][$k]['eval']['unique']) {
                        if (\Input::get('act') == 'copyAll') {
                            $v = $v . '-' . substr(md5(uniqid(mt_rand(), true)), 0, 8);
                        } else {
                            $v = '';
                        }
                    } // Reset doNotCopy and fallback fields to their default value
                    elseif ($GLOBALS['TL_DCA'][$this->strTable]['fields'][$k]['eval']['doNotCopy']
                        || $GLOBALS['TL_DCA'][$this->strTable]['fields'][$k]['eval']['fallback']
                    ) {
                        $v = '';

                        // Use array_key_exists to allow NULL (see #5252)
                        if (array_key_exists('default', $GLOBALS['TL_DCA'][$this->strTable]['fields'][$k])) {
                            $v = is_array($GLOBALS['TL_DCA'][$this->strTable]['fields'][$k]['default']) ? serialize(
                                $GLOBALS['TL_DCA'][$this->strTable]['fields'][$k]['default']
                            ) : $GLOBALS['TL_DCA'][$this->strTable]['fields'][$k]['default'];
                        }

                        // Encrypt the default value (see #3740)
                        if ($GLOBALS['TL_DCA'][$this->strTable]['fields'][$k]['eval']['encrypt']) {
                            $v = \Encryption::encrypt($v);
                        }
                    }

                    // Set fields (except password fields)
                    $set[$k] = ($GLOBALS['TL_DCA'][$this->strTable]['fields'][$k]['inputType'] == 'password' ? '' : $v);
                }
            }

            $set['tstamp'] = $time;
            $set[$this->pidColumnName] = $insertId;
            $set[$this->langColumnName] = $objTranslations->{$this->langColumnName};
            unset($set['id']);

            \Database::getInstance()->prepare("INSERT INTO {$this->strTable} %s")->set($set)->execute();
        }

        // Switch to edit mode
        if (!$blnDoNotRedirect) {
            $this->redirect($this->switchToEdit($insertId));
        }

        return $insertId;
    }


    /**
     * Duplicate all child records of a duplicated record
     *
     * @param string
     * @param integer
     * @param integer
     * @param integer
     */
    protected function copyChilds($table, $insertID, $id, $parentId)
    {
        parent::copyChilds($table, $insertID, $id, $parentId);

        if ($GLOBALS['TL_DCA'][$table]['config']['langPid']) {
            $pidColumnName = $GLOBALS['TL_DCA'][$table]['config']['langPid'];
        } else {
            $pidColumnName = $this->pidColumnName;
        }

        $objLanguage = \Database::getInstance()->prepare("SELECT id FROM " . $table . " WHERE " . $pidColumnName . "=? AND id>?")
            ->limit(1)
            ->execute($id, $parentId);

        // Update the language pid column
        if ($objLanguage->numRows) {
            \Database::getInstance()->prepare("UPDATE " . $table . " SET " . $pidColumnName . "=? WHERE id=?")
                ->execute($insertID, $objLanguage->id);
        }
    }

    /**
     * List all records of the current table as tree and return them as HTML
     * string
     *
     * @return string
     */
    public function treeView()
    {
        $where = array();

        // Child mode
        if ($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] == 6) {
            $table = $GLOBALS['TL_DCA'][$this->strTable]['config']['ptable'];

            if ($GLOBALS['TL_DCA'][$table]['config']['dataContainer'] == 'Multilingual') {
                $where[] = "$this->langColumnName=''";
            }

        } else {

            $table = $this->strTable;
            $where[] = "$this->langColumnName=''";
        }

        if (is_array($this->root) && count($this->root)) {
            $where[] = 'id IN(' . implode(',', $this->root) . ')';
        }

        $orderBy = '';

        if (\Database::getInstance()->fieldExists('sorting', $table)) {
            $orderBy = " ORDER BY sorting";
        }

        $where = implode(' AND ', $where);

        $this->root = \Database::getInstance()
            ->query("SELECT id FROM $table WHERE " . $where . $orderBy)
            ->fetchEach('id');

        return parent::treeView();
    }


    /**
     * Generate a particular subpart of the tree and return it as HTML string
     *
     * @param integer $id
     * @param integer $level
     *
     * @return string
     */
    public function ajaxTreeView($id, $level)
    {
        if (!\Environment::get('isAjaxRequest'))
        {
            return '';
        }

        $return = '';
        $table = $this->strTable;
        $blnPtable = false;

        // Load parent table
        if ($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] == 6)
        {
            $table = $this->ptable;

            \System::loadLanguageFile($table);
            $this->loadDataContainer($table);

            $blnPtable = true;
        }

        $blnProtected = false;

        // Check protected pages
        if ($table == 'tl_page')
        {
            $objParent = \PageModel::findWithDetails($id);
            $blnProtected = $objParent->protected ? true : false;
        }

        $margin = ($level * 20);
        $hasSorting = $this->Database->fieldExists('sorting', $table);
        $arrIds = array();

        // Get records
        $objRows = $this->Database->prepare("SELECT id FROM " . $table . " WHERE " . $this->pidColumnName . "=0 AND pid=?" . ($hasSorting ? " ORDER BY sorting" : ""))
            ->execute($id);

        while ($objRows->next())
        {
            $arrIds[] = $objRows->id;
        }

        /** @var SessionInterface $objSession */
        $objSession = \System::getContainer()->get('session');

        $blnClipboard = false;
        $arrClipboard = $objSession->get('CLIPBOARD');

        // Check clipboard
        if (!empty($arrClipboard[$this->strTable]))
        {
            $blnClipboard = true;
            $arrClipboard = $arrClipboard[$this->strTable];
        }

        for ($i=0, $c=count($arrIds); $i<$c; $i++)
        {
            $return .= ' ' . trim($this->generateTree($table, $arrIds[$i], array('p'=>$arrIds[($i-1)], 'n'=>$arrIds[($i+1)]), $hasSorting, $margin, ($blnClipboard ? $arrClipboard : false), ($id == $arrClipboard ['id'] || (is_array($arrClipboard ['id']) && in_array($id, $arrClipboard ['id'])) || (!$blnPtable && !is_array($arrClipboard['id']) && in_array($id, $this->Database->getChildRecords($arrClipboard['id'], $table)))), $blnProtected));
        }

        return $return;
    }


    /**
     * Recursively generate the tree and return it as HTML string
     *
     * @param string  $table
     * @param integer $id
     * @param array   $arrPrevNext
     * @param boolean $blnHasSorting
     * @param integer $intMargin
     * @param array   $arrClipboard
     * @param boolean $blnCircularReference
     * @param boolean $protectedPage
     * @param boolean $blnNoRecursion
     * @param array   $arrFound
     *
     * @return string
     */
    protected function generateTree($table, $id, $arrPrevNext, $blnHasSorting, $intMargin=0, $arrClipboard=null, $blnCircularReference=false, $protectedPage=false, $blnNoRecursion=false, $arrFound=array())
    {
        // Check whether there are child records
        if (!$blnNoRecursion) {
            \Controller::loadDataContainer($table);

            if ($GLOBALS['TL_DCA'][$table]['config']['dataContainer'] === 'Multilingual'
                && ($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] === 5 || $GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] === 6 || $this->strTable != $table)
            ) {
                $langColumn = $GLOBALS['TL_DCA'][$table]['config']['langColumnName'] ?: 'language';

                // Now that there's $arrFound we can just add all that main languages to the found array
                // so it will never show translated items and we don't have to override the whole method
                // anymore - hurray!
                $objSessionBag = System::getContainer()->get('session')->getBag('contao_backend');
                $session = $objSessionBag->all();
                $node = ($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] == 6) ? $this->strTable . '_' . $table . '_tree' : $this->strTable . '_tree';
                $blnIsOpen = (!empty($arrFound) || $session[$node][$id] == 1);
                if ($blnIsOpen) {
                    $translationIds = \Database::getInstance()->prepare(
                        "SELECT id FROM " . $table . " WHERE pid=? AND $langColumn=''")
                        ->execute($id)
                        ->fetchEach('id');

                    $arrFound = array_merge($arrFound, $translationIds);
                }

                // Do not display the language records in the child list
                if ($table === $this->strTable && $GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] === 6) {
                    static $languageRecords;

                    if (!is_array($languageRecords)) {
                        $languageRecords = \Database::getInstance()->execute("SELECT id FROM $table WHERE $langColumn=''")
                            ->fetchEach('id');

                        $languageRecords = array_map('intval', $languageRecords);
                    }

                    // Return an empty string if the ID is a langauge record
                    if (!in_array((int)$id, $languageRecords, true)) {
                        return '';
                    }
                }
            }
        }

        return parent::generateTree($table, $id, $arrPrevNext, $blnHasSorting, $intMargin, $arrClipboard, $blnCircularReference, $protectedPage, $blnNoRecursion, $arrFound);
    }


    /**
     * Calculate the new position of a moved or inserted record
     *
     * @param string
     * @param integer
     * @param boolean
     */
    protected function getNewPosition($mode, $pid = null, $insertInto = false)
    {
        // If there is pid and sorting
        if (\Database::getInstance()->fieldExists('pid', $this->strTable) && \Database::getInstance()->fieldExists('sorting', $this->strTable)) {
            // PID is not set - only valid for duplicated records, as they get the same parent ID as the original record!
            if ($pid === null && $this->intId && $mode == 'copy') {
                $pid = $this->intId;
            }

            // PID is set (insert after or into the parent record)
            if (is_numeric($pid)) {
                $newPID = null;
                $newSorting = null;

                // Insert the current record at the beginning when inserting into the parent record
                if ($insertInto) {
                    $newPID = $pid;

                    $objSorting = \Database::getInstance()->prepare("SELECT MIN(sorting) AS sorting FROM " . $this->strTable . " WHERE pid=? AND {$this->pidColumnName}=0")
                        ->execute($pid);

                    // Select sorting value of the first record
                    if ($objSorting->numRows) {
                        $curSorting = $objSorting->sorting;

                        // Resort if the new sorting value is not an integer or smaller than 1
                        if (($curSorting % 2) != 0 || $curSorting < 1) {
                            $objNewSorting = \Database::getInstance()->prepare("SELECT id FROM " . $this->strTable . " WHERE pid=? AND {$this->pidColumnName}=0 ORDER BY sorting")
                                ->execute($pid);

                            $count = 2;
                            $newSorting = 128;

                            while ($objNewSorting->next()) {
                                \Database::getInstance()->prepare("UPDATE " . $this->strTable . " SET sorting=? WHERE id=?")
                                    ->limit(1)
                                    ->execute(($count++ * 128), $objNewSorting->id);
                            }
                        } // Else new sorting = (current sorting / 2)
                        else $newSorting = ($curSorting / 2);
                    } // Else new sorting = 128
                    else $newSorting = 128;
                } // Else insert the current record after the parent record
                elseif ($pid > 0) {
                    $objSorting = \Database::getInstance()->prepare("SELECT pid, sorting FROM " . $this->strTable . " WHERE id=? AND {$this->pidColumnName}=0")
                        ->limit(1)
                        ->execute($pid);

                    // Set parent ID of the current record as new parent ID
                    if ($objSorting->numRows) {
                        $newPID = $objSorting->pid;
                        $curSorting = $objSorting->sorting;

                        // Do not proceed without a parent ID
                        if (is_numeric($newPID)) {
                            $objNextSorting = \Database::getInstance()->prepare("SELECT MIN(sorting) AS sorting FROM " . $this->strTable . " WHERE pid=? AND {$this->pidColumnName}=0 AND sorting>?")
                                ->execute($newPID, $curSorting);

                            // Select sorting value of the next record
                            if ($objNextSorting->sorting !== null) {
                                $nxtSorting = $objNextSorting->sorting;

                                // Resort if the new sorting value is no integer or bigger than a MySQL integer
                                if ((($curSorting + $nxtSorting) % 2) != 0 || $nxtSorting >= 4294967295) {
                                    $count = 1;

                                    $objNewSorting = \Database::getInstance()->prepare("SELECT id, sorting FROM " . $this->strTable . " WHERE pid=? AND {$this->pidColumnName}=0 ORDER BY sorting")
                                        ->execute($newPID);

                                    while ($objNewSorting->next()) {
                                        \Database::getInstance()->prepare("UPDATE " . $this->strTable . " SET sorting=? WHERE id=?")
                                            ->execute(($count++ * 128), $objNewSorting->id);

                                        if ($objNewSorting->sorting == $curSorting) {
                                            $newSorting = ($count++ * 128);
                                        }
                                    }
                                } // Else new sorting = (current sorting + next sorting) / 2
                                else $newSorting = (($curSorting + $nxtSorting) / 2);
                            } // Else new sorting = (current sorting + 128)
                            else $newSorting = ($curSorting + 128);
                        }
                    } // Use the given parent ID as parent ID
                    else {
                        $newPID = $pid;
                        $newSorting = 128;
                    }
                }

                // Set new sorting and new parent ID
                $this->set['pid'] = intval($newPID);
                $this->set['sorting'] = intval($newSorting);
            }
        } else {
            parent::getNewPosition($mode, $pid, $insertInto);
        }
    }

    /**
     * Save the current value. Support multilingual aliases
     *
     * @param mixed $varValue
     *
     * @throws \Exception
     */
    protected function save($varValue)
    {
        if (\Input::post('FORM_SUBMIT') != $this->strTable) {
            return;
        }

        $data = $GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField];

        if (!isset($data['eval']['isMultilingualAlias'])) {
            parent::save($varValue);
            return;
        }

        $fromField = $data['eval']['generateAliasFromField'] ?: 'title';
        $autoAlias = false;

        // Generate $varValue alias if there is none
        if ('' === $varValue) {
            $autoAlias = true;
            $varValue = \StringUtil::generateAlias($this->objActiveRecord->{$fromField});
        }

        $records = \Database::getInstance()
            ->prepare("SELECT id FROM {$this->strTable} WHERE {$this->pidColumnName}=?")
            ->execute(($this->objActiveRecord->{$this->pidColumnName} > 0) ? $this->objActiveRecord->{$this->pidColumnName} : $this->objActiveRecord->id)
        ;

        $excludedIds = $records->fetchEach('id');

        if ($this->objActiveRecord->{$this->pidColumnName} > 0) {
            $excludedIds[] = $this->objActiveRecord->{$this->pidColumnName};
        } else {
            $excludedIds[] = $this->objActiveRecord->id;
        }

        // Check for duplicates in current language
        $objAlias = \Database::getInstance()->prepare(
            "SELECT id FROM {$this->strTable} WHERE id NOT IN (" . implode(',', $excludedIds) . ") AND {$this->strField}=? AND {$this->langColumnName}=?"
        )->execute($varValue, $this->currentLang);

        $skipAliasValidation = false;

        // Check whether the alias exists
        if ($objAlias->numRows > 0) {
            if (!$autoAlias) {
                throw new \InvalidArgumentException(sprintf($GLOBALS['TL_LANG']['ERR']['aliasExists'], $varValue));
            }

            // For child record take the parent record alias
            if ($this->objActiveRecord->{$this->pidColumnName} > 0) {
                $parent = \Database::getInstance()
                    ->prepare("SELECT {$this->strField} FROM {$this->strTable} WHERE id=?")
                    ->execute($this->objActiveRecord->{$this->pidColumnName})
                ;

                if ($parent->numRows) {
                    $varValue = $parent->{$this->strField};
                    $skipAliasValidation = true;
                }
            } else {
                $varValue .= '-' . $this->intId;
            }
        } else {
            $skipAliasValidation = true;
        }

        // Skip the further alias validation
        if ($skipAliasValidation) {
            $GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['eval']['unique'] = false;

            // Avoid alias validation in callbacks as well
            unset($GLOBALS['TL_DCA'][$this->strTable]['fields'][$this->strField]['save_callback']);
        }

        parent::save($varValue);
    }


    /**
     * Delete record and associated translations
     *
     * @param boolean
     */
    public function delete($blnDoNotRedirect = false)
    {
        \Database::getInstance()->prepare("DELETE FROM " . $this->strTable . " WHERE " . $this->pidColumnName . "=?")
            ->execute($this->intId);

        parent::delete($blnDoNotRedirect);
    }


    /**
     * Recursively get all related table names and language records
     *
     * @param string
     * @param integer
     * @param array
     */
    public function deleteChilds($table, $id, &$delete)
    {
        parent::deleteChilds($table, $id, $delete);

        // Do not delete record if there is no parent table
        if ($GLOBALS['TL_DCA'][$table]['config']['ptable'] == '') {
            return;
        }

        // Do not take the config of the current table because $table might
        // be a child table
        if ($GLOBALS['TL_DCA'][$table]['config']['langPid']) {
            $pidColumnName = $GLOBALS['TL_DCA'][$table]['config']['langPid'];
        } else {
            $pidColumnName = $this->pidColumnName;
        }

        $objLanguages = \Database::getInstance()->prepare(
            "SELECT id FROM " . $table . " WHERE " . $pidColumnName . " IN (SELECT id FROM " . $table . " WHERE pid=?)")
            ->execute($id);

        while ($objLanguages->next()) {
            $delete[$table][] = $objLanguages->id;
        }
    }


    /**
     * Get translatable languages
     *
     * @return array
     */
    public function getTranslatableLanguages()
    {
        return $this->translatableLangs;
    }


    /**
     * Get the fallback language
     *
     * @return string
     */
    public function getFallbackLanguage()
    {
        return $this->fallbackLang;
    }


    /**
     * Get the current language
     *
     * @return string
     */
    public function getCurrentLanguage()
    {
        return $this->currentLang;
    }


    /**
     * Get the language column name
     *
     * @return string
     */
    public function getLanguageColumn()
    {
        return $this->langColumnName;
    }


    /**
     * Get the PID column name
     *
     * @return string
     */
    public function getPidColumn()
    {
        return $this->pidColumnName;
    }


    /**
     * Get the list of languages based on root pages
     *
     * @return array
     */
    protected function getRootPageLanguages()
    {
        $objPages = \Database::getInstance()->execute("SELECT DISTINCT language FROM tl_page WHERE type='root' AND language!=''");
        $languages = $objPages->fetchEach('language');

        array_walk(
            $languages,
            function (&$value) {
                $value = str_replace('-', '_', $value);
            }
        );

        return $languages;
    }

    /**
     * Handles the language dropdown change and the delete action
     */
    protected function handleLanguageOperation()
    {
        // Incomplete records can't be translated (see #17)
        if (!$this->objActiveRecord->tstamp) {
            $this->translatableLangs = [];
        }

        /** @var SessionInterface $objSessionBag */
        $objSessionBag = \System::getContainer()->get('session')->getBag('contao_backend');

        /** @var Request $request */
        $request = \System::getContainer()->get('request_stack')->getCurrentRequest();

        if (0 !== count($this->translatableLangs)) {
            $needsReload = false;

            if ('tl_language' === $request->request->get('FORM_SUBMIT')) {
                $language = $request->request->get('language');

                if (in_array($language, $this->translatableLangs)) {
                    $objSessionBag->set($this->sessionKey, $language);
                } else {
                    $objSessionBag->remove($this->sessionKey);
                }

                $needsReload = true;
            } elseif ($this->strTable === $request->request->get('FORM_SUBMIT')
                && $request->request->has('deleteLanguage')
            ) {
                \Database::getInstance()
                    ->prepare(
                        "DELETE FROM " . $this->strTable . "
                         WHERE {$this->pidColumnName}=? AND {$this->langColumnName}=?"
                    )
                    ->execute(
                        $this->intId,
                        $objSessionBag->get($this->sessionKey)
                    );

                $objSessionBag->remove($this->sessionKey);
                $needsReload = true;
            }

            if ($needsReload) {
                $_SESSION['TL_INFO'] = '';
                \Controller::reload();
            }
        }
    }

    /**
     * Load the current language record if available.
     */
    protected function loadCurrentLanguageRecord()
    {
        /** @var SessionInterface $objSessionBag */
        $objSessionBag = \System::getContainer()->get('session')->getBag('contao_backend');

        $language = $objSessionBag->get($this->sessionKey);

        if (null === $language || !in_array($language, $this->translatableLangs)) {

            return;
        }

        $objRow = \Database::getInstance()->prepare("SELECT * FROM " . $this->strTable . " WHERE {$this->pidColumnName}=? AND {$this->langColumnName}=?")->execute($this->intId, $language);

        if (!$objRow->numRows) {
            // Preserve the "pid" field
            if (\Database::getInstance()->fieldExists('pid', $this->strTable)) {
                $objCurrent = \Database::getInstance()->prepare("SELECT pid FROM " . $this->strTable . " WHERE id=?")
                    ->limit(1)
                    ->execute($this->intId);

                $intPid = ($objCurrent->numRows) ? $objCurrent->pid : 0;

                if ($GLOBALS['TL_DCA'][$this->strTable]['config']['dynamicPtable']) {
                    $intId = \Database::getInstance()->prepare("INSERT INTO " . $this->strTable . " ({$this->pidColumnName},tstamp,{$this->langColumnName},pid,ptable) VALUES (?,?,?,?,?)")
                        ->execute($this->intId, time(), $language, $intPid, $this->ptable)
                        ->insertId;
                } else {
                    $intId = \Database::getInstance()->prepare("INSERT INTO " . $this->strTable . " ({$this->pidColumnName},tstamp,{$this->langColumnName},pid) VALUES (?,?,?,?)")
                        ->execute($this->intId, time(), $language, $intPid)
                        ->insertId;
                }
            } else {
                $intId = \Database::getInstance()->prepare("INSERT INTO " . $this->strTable . " ({$this->pidColumnName},tstamp,{$this->langColumnName}) VALUES (?,?,?)")
                    ->execute($this->intId, time(), $language)
                    ->insertId;
            }

            $objRow = \Database::getInstance()->prepare("SELECT * FROM " . $this->strTable . " WHERE id=?")->execute($intId);
        }

        $this->objActiveRecord  = $objRow;
        $this->procedure        = [$this->pidColumnName . '=?', $this->langColumnName . '=?'];
        $this->values           =  [$this->intId, $language];
        $this->editLang         = true;
        $this->currentLang      = $language;
    }

    /**
     * Replace the version panel and add the language panel to it.
     *
     * @param string $version
     *
     * @return mixed
     */
    protected function addLanguageSwitchPanel($version)
    {
        // Check languages
        if (!is_array($this->translatableLangs) || count($this->translatableLangs) <= 1) {

            return $version;
        }

        // Make sure there's always a panel to replace
        if ('' === $version) {
            $version = '<div class="tl_version_panel"></div>';
        }

        $availableLangs = \Database::getInstance()->prepare("SELECT {$this->langColumnName} FROM " . $this->strTable . " WHERE {$this->pidColumnName}=?")
            ->execute($this->intId)
            ->fetchEach($this->langColumnName);
        $langLabels = \System::getLanguages();
        $available = ($this->fallbackLang) ? '' : '<option value="">' . $GLOBALS['TL_LANG']['MSC']['defaultLanguage'] . '</option>';
        $undefined = '';

        foreach ($this->translatableLangs as $language) {
            $value = ($this->fallbackLang == $language) ? '' : $language;
            $label = ($this->fallbackLang == $language) ? ($langLabels[$language] . ' (' . $GLOBALS['TL_LANG']['MSC']['defaultLanguage'] . ')') : $langLabels[$language];
            $selected = $this->currentLang == $language || ($this->fallbackLang && $this->currentLang == '' && $this->fallbackLang == $language);

            // Show the languages that are already translated (fallback is always "translated")
            if (in_array($language, $availableLangs) || ($language == $this->fallbackLang)) {
                $available .= sprintf('<option value="%s"%s>%s</option>',
                    $value,
                    ($selected) ? ' selected="selected"' : '',
                    $label);

                // Add translation hint
                if ($selected
                    && (
                        ($this->fallbackLang && $this->fallbackLang != $language)
                        || (!$this->fallbackLang && $this->currentLang != '')
                    )
                ) {
                    $_SESSION['TL_INFO'] = array($GLOBALS['TL_LANG']['MSC']['editingLanguage']);
                }
            }
            else {
                $undefined .= '<option value="' . $value . '">' . $label . ' ('.$GLOBALS['TL_LANG']['MSC']['undefinedLanguage'].')' . '</option>';
            }
        }


        return str_replace(
            '<div class="tl_version_panel">',
            '<div class="tl_version_panel language_panel">
<form action="' . ampersand(\Environment::get('request'), true) . '" id="tl_language" class="tl_form" method="post">
<div class="tl_formbody">
<input type="hidden" name="FORM_SUBMIT" value="tl_language">
<input type="hidden" name="REQUEST_TOKEN" value="' . REQUEST_TOKEN . '">
<select name="language" class="tl_select' . (strlen($_SESSION['BE_DATA']['language'][$this->strTable][$this->intId]) ? ' active' : '') . '" onchange="document.id(this).getParent(\'form\').submit()">
' . $available . $undefined . '
</select>
<noscript>
<input type="submit" name="editLanguage" class="tl_submit" value="' . specialchars($GLOBALS['TL_LANG']['MSC']['editLanguage']) . '">
</noscript>
</div>
</form>',
            $version
        );
    }

    /**
     * Override palette to get rid of fields that should not be displayed
     * because of translation settings.
     *
     * @return string
     */
    public function getPalette()
    {
        $palette = parent::getPalette();
        $modifiedPalette = '';

        $legendChunks = trimsplit(';', $palette);

        foreach ($legendChunks as $legendChunk) {

            $fieldChunks    = trimsplit(',', $legendChunk);
            $newFieldChunks = [];

            foreach ($fieldChunks as $fieldChunk) {

                // Do not handle any special stuff like legends
                if (preg_match('/^\[.*\]$/', $fieldChunk)
                    || preg_match('/^\{.*\}$/', $fieldChunk)
                ) {
                    $newFieldChunks[] = $fieldChunk;
                    continue;
                }

                // Now we only have regular fields here and we check if we
                // need should display them in the palette or not
                $translatableFor = $GLOBALS['TL_DCA'][$this->strTable]['fields'][$fieldChunk]['eval']['translatableFor'];

                // If translatableFor is not set at all and we are
                // editing a language, we don't add it to the palette
                if (!isset($translatableFor) && $this->editLang) {
                    continue;
                }

                // If editing the fallback or the field should be shown for all
                // languages, we add it to the palette
                if ('' === $this->currentLang || '*' === $translatableFor[0]) {
                    $newFieldChunks[] = $fieldChunk;
                    continue;
                }

                // Also we don't add it if the current language is not in the
                // translatableFor setting
                if (!in_array($this->currentLang, $translatableFor)) {
                    continue;
                }

                $newFieldChunks[] = $fieldChunk;
            }

            $modifiedPalette .= implode(',', $newFieldChunks) . ';';

        }

        return $modifiedPalette;
    }
}
