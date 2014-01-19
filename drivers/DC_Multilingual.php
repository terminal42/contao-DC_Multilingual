<?php

/**
 * Contao Open Source CMS
 * Copyright (C) 2005-2011 Leo Feyer
 *
 * Formerly known as TYPOlight Open Source CMS.
 *
 * This program is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this program. If not, please visit the Free
 * Software Foundation website at <http://www.gnu.org/licenses/>.
 *
 * PHP version 5
 * @copyright  terminal42 gmbh 2011-2012
 * @author     Yanick Witschi <yanick.witschi@terminal42.ch>
 * @author     Andreas Schempp <andreas.schempp@terminal42.ch>
 * @author     Kamil Kuzminski <kamil.kuzminski@codefog.pl>
 * @license    http://opensource.org/licenses/lgpl-3.0.html
 */


/**
 * Class DC_Multilingual
 *
 * Provide methods to handle multilingual DC_Table entries
 * @copyright  terminal42 gmbh 2011-2012
 * @author     Yanick Witschi <yanick.witschi@terminal42.ch>
 * @author     Andreas Schempp <andreas.schempp@terminal42.ch>
 * @author     Kamil Kuzminski <kamil.kuzminski@codefog.pl>
 * @package    dc_multilingual
 */
class DC_Multilingual extends \DC_Table
{

	/**
	 * True if we are editing a language that is not the fallback
	 * @param boolean
	 */
	protected $blnEditLanguage = false;

	/**
	 * Array containing all languages that are translatable
	 * @var array
	 */
	protected $arrLanguages = array();

	/**
	 * Fallback language
	 * @var string
	 */
	protected $strFallbackLang = '';

	/**
	 * Language we are currently editing
	 * @var string
	 */
	protected $strCurrentLang = '';

	/**
	 * Language column
	 * @var string
	 */
	protected $strLangColumn;

	/**
	 * pid column
	 * @var string
	 */
	protected $strPidColumn;


	/**
	 * Set language specific config
	 * @param string | the database table
	 */
	public function __construct($strTable)
	{
		parent::__construct($strTable);

		// languages array
		if (isset($GLOBALS['TL_DCA'][$this->strTable]['config']['languages']))
		{
			$this->arrLanguages = $GLOBALS['TL_DCA'][$this->strTable]['config']['languages'];
		}
		else
		{
			$this->arrLanguages = $this->getRootPageLanguages();
		}

		// fallback language
		if (isset($GLOBALS['TL_DCA'][$this->strTable]['config']['fallbackLang']))
		{
			$this->strFallbackLang = $GLOBALS['TL_DCA'][$this->strTable]['config']['fallbackLang'];

			if (!in_array($this->strFallbackLang, $this->arrLanguages))
			{
				$this->arrLanguages[] = $this->strFallbackLang;
			}
		}

		$this->strPidColumn = static::getPidColumnForTable($this->strTable);
		$this->strLangColumn = static::getLanguageColumnForTable($this->strTable);
	}


	/**
	 * Get the language pid column name of particular table
	 * @param string
	 * @return string
	 */
	public static function getPidColumnForTable($strTable)
	{
		if ($GLOBALS['TL_DCA'][$strTable]['config']['langPid']) {
			return $GLOBALS['TL_DCA'][$strTable]['config']['langPid'];
		}

		return 'langPid';
	}


	/**
	 * Get the language column name of particular table
	 * @param string
	 * @return string
	 */
	public static function getLanguageColumnForTable($strTable)
	{
		if ($GLOBALS['TL_DCA'][$strTable]['config']['langColumn']) {
			return $GLOBALS['TL_DCA'][$strTable]['config']['langColumn'];
		}

		return 'language';
	}


	/**
	 * Auto-generate a form to edit the current database record
	 * @param integer
	 * @param integer
	 * @return string
	 */
	public function edit($intID=false, $ajaxId=false)
	{
		if ($GLOBALS['TL_DCA'][$this->strTable]['config']['notEditable'])
		{
			$this->log('Table "'.$this->strTable.'" is not editable', 'DC_Table edit()', TL_ERROR);
			$this->redirect('contao/main.php?act=error');
		}

		if ($intID)
		{
			$this->intId = $intID;
		}

		$return = '';
		$this->values[] = $this->intId;
		$this->procedure[] = 'id=?';
		$this->blnCreateNewVersion = false;
		$objVersions = new \Versions($this->strTable, $this->intId);

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

		// Get the current record
		$objRow = $this->Database->prepare("SELECT * FROM " . $this->strTable . " WHERE id=?")
								->limit(1)
								->executeUncached($this->intId);

		// Redirect if there is no record with the given ID
		if ($objRow->numRows < 1)
		{
			$this->log('Could not load record ID '.$this->intId.' of table "'.$this->strTable.'"', 'DC_Multilingual edit()', TL_ERROR);
			$this->redirect('contao/main.php?act=error');
		}

		// ID of a language record is not allowed
		elseif ($objRow->{$this->strLangColumn} != '')
		{
			$this->log('Cannot edit language record ID "'.$this->intId.'" of table "'.$this->strTable.'"!', 'DC_Multilingual edit()', TL_ERROR);
			$this->redirect('contao/main.php?act=error');
		}

		$this->objActiveRecord = $objRow;
		$this->checkForTinyMce();

		// Incomplete records can't be translated (see #17)
		if (!$objRow->tstamp) {
			$this->arrLanguages = array();
		}

		// Load and/or change language
		if ($this->Input->post('FORM_SUBMIT') == 'tl_language')
		{
			$session = $this->Session->getData();

			if (in_array($this->Input->post('language'), array_keys($this->arrLanguages)))
			{
				$session['language'][$this->strTable][$this->intId] = $this->Input->post('language');

				if (strlen($this->Input->post('deleteLanguage')))
				{
					$this->Database->prepare("DELETE FROM " . $this->strTable . " WHERE {$this->strPidColumn}=? AND {$this->strLangColumn}=?")->execute($this->intId, $this->Input->post('language'));
					unset($session['language'][$this->strTable][$this->intId]);
				}
			}
			else
			{
				unset($session['language'][$this->strTable][$this->intId]);
			}

			$this->Session->setData($session);
			$_SESSION['TL_INFO'] = '';
			$this->reload();
		}

		if (strlen($_SESSION['BE_DATA']['language'][$this->strTable][$this->intId]) && in_array($_SESSION['BE_DATA']['language'][$this->strTable][$this->intId], array_keys($this->arrLanguages)))
		{
			$objRow = $this->Database->prepare("SELECT * FROM " . $this->strTable . " WHERE {$this->strPidColumn}=? AND {$this->strLangColumn}=?")->execute($this->intId, $_SESSION['BE_DATA']['language'][$this->strTable][$this->intId]);

			if (!$objRow->numRows)
			{
				// Preserve the "pid" field
				if ($this->Database->fieldExists('pid', $this->strTable))
				{
					$objCurrent = $this->Database->prepare("SELECT pid FROM " . $this->strTable . " WHERE id=?")
												 ->limit(1)
												 ->executeUncached($this->intId);

					$intPid = ($objCurrent->numRows) ? $objCurrent->pid : 0;
					$intId = $this->Database->prepare("INSERT INTO " . $this->strTable . " ({$this->strPidColumn},tstamp,{$this->strLangColumn},pid) VALUES (?,?,?,?)")->execute($this->intId, time(), $_SESSION['BE_DATA']['language'][$this->strTable][$this->intId], $intPid)->insertId;
				}
				else
				{
					$intId = $this->Database->prepare("INSERT INTO " . $this->strTable . " ({$this->strPidColumn},tstamp,{$this->strLangColumn}) VALUES (?,?,?)")->execute($this->intId, time(), $_SESSION['BE_DATA']['language'][$this->strTable][$this->intId])->insertId;
				}

				$objRow = $this->Database->prepare("SELECT * FROM " . $this->strTable . " WHERE id=?")->execute($intId);
			}

			$this->objActiveRecord = $objRow;
			$this->values = array($this->intId, $_SESSION['BE_DATA']['language'][$this->strTable][$this->intId]);
			$this->procedure = array($this->strPidColumn . '=?',$this->strLangColumn . '=?');
			$this->blnEditLanguage = true;
			$this->strCurrentLang = $_SESSION['BE_DATA']['language'][$this->strTable][$this->intId];
		}

		$objVersions = new Versions($this->strTable, $this->intId);
		$objVersions->initialize();

		// Build an array from boxes and rows
		$this->strPalette = $this->getPalette();
		$boxes = trimsplit(';', $this->strPalette);
		$legends = array();

		if (count($boxes))
		{
			foreach ($boxes as $k=>$v)
			{
				$eCount = 1;
				$boxes[$k] = trimsplit(',', $v);

				foreach ($boxes[$k] as $kk=>$vv)
				{
					if (preg_match('/^\[.*\]$/i', $vv))
					{
						++$eCount;
						continue;
					}

					if (preg_match('/^\{.*\}$/i', $vv))
					{
						$legends[$k] = substr($vv, 1, -1);
						unset($boxes[$k][$kk]);
					}
					elseif ($GLOBALS['TL_DCA'][$this->strTable]['fields'][$vv]['exclude'] || !is_array($GLOBALS['TL_DCA'][$this->strTable]['fields'][$vv]))
					{
						unset($boxes[$k][$kk]);
					}

					// unset fields that are not translatable for the current language
					$translatableFor = $GLOBALS['TL_DCA'][$this->strTable]['fields'][$vv]['eval']['translatableFor'];

					// if editing the fallback or the field should be shown for all languages, we don't unset anything at all
					if ($this->strCurrentLang == '' || $translatableFor[0] == '*')
					{
						continue;
					}

					// if translatableFor is not set we unset it for every language
					if(!isset($translatableFor))
					{
						unset($boxes[$k][$kk]);
						continue;
					}

					// we check if the field is not editable for the current language
					if(!in_array($this->strCurrentLang, $translatableFor))
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

			$class = 'tl_tbox';
			$fs = $this->Session->get('fieldset_states');
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
					$this->varValue = $objRow->$vv;

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
								$this->varValue = $this->$callback[0]->$callback[1]($this->varValue, $this);
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
		if ($GLOBALS['TL_DCA'][$this->strTable]['config']['enableVersioning'])
		{
			$version = $objVersions->renderDropdown();
		}
		else
		{
			$version = '';
		}

		$hasLanguages = false;

		// Check languages
		if (is_array($this->arrLanguages) && count($this->arrLanguages) > 1)
		{
			$hasLanguages = true;

			$arrAvailableLanguages = $this->Database->prepare("SELECT {$this->strLangColumn} FROM " . $this->strTable . " WHERE {$this->strPidColumn}=?")->execute($this->intId)->fetchEach($this->strLangColumn);
			$arrLanguageLabels = $this->getLanguages();
			$available = ($this->strFallbackLang) ? '' : '<option value="">' . $GLOBALS['TL_LANG']['MSC']['defaultLanguage'] . '</option>';
			$undefined = '';

			foreach ($this->arrLanguages as $language)
			{
				$value = ($this->strFallbackLang == $language) ? '' : $language;
				$label = ($this->strFallbackLang == $language) ? ($arrLanguageLabels[$language] . ' (' . $GLOBALS['TL_LANG']['MSC']['defaultLanguage'] . ')') : $arrLanguageLabels[$language];
				$selected = $this->strCurrentLang == $language || ($this->strFallbackLang && $this->strCurrentLang == '' && $this->strFallbackLang == $language);

				// show the languages that are already translated (fallback is always "translated")
				if (in_array($language, $arrAvailableLanguages) || ($language == $this->strFallbackLang))
				{
					$available .= sprintf('<option value="%s"%s>%s</option>',
						$value,
						($selected) ? ' selected="selected"' : '',
						$label);

					// add translation hint
					if ($selected && (($this->strFallbackLang && $this->strFallbackLang != $language) || (!$this->strFallbackLang && $this->currentLang != '')))
					{
						$_SESSION['TL_INFO'] = array($GLOBALS['TL_LANG']['MSC']['editingLanguage']);
					}
				}
				else
				{
					$undefined .= '<option value="' . $value . '">' . $label . ' ('.$GLOBALS['TL_LANG']['MSC']['undefinedLanguage'].')' . '</option>';
				}
			}

			$languagePanel .= '<form action="'.ampersand($this->Environment->request, true).'" id="tl_language" class="tl_form" method="post"' . (strlen($version) ? ' style="float:left;width:360px"' : '') . '>
<div class="tl_formbody" style="margin-left:5px;float:left;">
<input type="hidden" name="FORM_SUBMIT" value="tl_language">
<input type="hidden" name="REQUEST_TOKEN" value="'.REQUEST_TOKEN.'">
<input type="submit" name="editLanguage" class="tl_submit" value="'.specialchars($GLOBALS['TL_LANG']['MSC']['editLanguage']).'">
<input type="submit" name="deleteLanguage" class="tl_submit" value="'.specialchars($GLOBALS['TL_LANG']['MSC']['deleteLanguage']).'" onclick="return confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteLanguageConfirm'] . '\')">
<select name="language" class="tl_select' . (strlen($_SESSION['BE_DATA']['language'][$this->strTable][$this->intId]) ? ' active' : '') . '" style="width:160px">
'.$available.$undefined.'
</select>
</div>
</form>';

			// God please forgive me this devilish code
			if (strlen($version)) {
				$version = str_replace('method="post"', 'method="post" style="float:right;"', $version);
				$version = str_replace('<div class="tl_version_panel">', '<div class="tl_version_panel language_panel" style="overflow:hidden;">' . $languagePanel, $version);
			} else {
				$version .= '<div class="tl_version_panel language_panel" style="overflow:hidden;">' . $languagePanel . '</div>';
			}
		}

		// Submit buttons
		$arrButtons = array();
		$arrButtons['save'] = '<input type="submit" name="save" id="save" class="tl_submit" accesskey="s" value="'.specialchars($GLOBALS['TL_LANG']['MSC']['save']).'">';

		if (!\Input::get('nb'))
		{
			$arrButtons['saveNclose'] = '<input type="submit" name="saveNclose" id="saveNclose" class="tl_submit" accesskey="c" value="'.specialchars($GLOBALS['TL_LANG']['MSC']['saveNclose']).'">';
		}

		if (!\Input::get('popup') && !$GLOBALS['TL_DCA'][$this->strTable]['config']['closed'] && !$GLOBALS['TL_DCA'][$this->strTable]['config']['notCreatable'])
		{
			$arrButtons['saveNcreate'] = '<input type="submit" name="saveNcreate" id="saveNcreate" class="tl_submit" accesskey="n" value="'.specialchars($GLOBALS['TL_LANG']['MSC']['saveNcreate']).'">';
		}

		if (\Input::get('s2e'))
		{
			$arrButtons['saveNedit'] = '<input type="submit" name="saveNedit" id="saveNedit" class="tl_submit" accesskey="e" value="'.specialchars($GLOBALS['TL_LANG']['MSC']['saveNedit']).'">';
		}
		elseif (!\Input::get('popup') && ($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] == 4 || strlen($this->ptable) || $GLOBALS['TL_DCA'][$this->strTable]['config']['switchToEdit']))
		{
			$arrButtons['saveNback'] = '<input type="submit" name="saveNback" id="saveNback" class="tl_submit" accesskey="g" value="'.specialchars($GLOBALS['TL_LANG']['MSC']['saveNback']).'">';
		}

		// Call the buttons_callback (see #4691)
		if (is_array($GLOBALS['TL_DCA'][$this->strTable]['edit']['buttons_callback']))
		{
			foreach ($GLOBALS['TL_DCA'][$this->strTable]['edit']['buttons_callback'] as $callback)
			{
				if (is_array($callback))
				{
					$this->import($callback[0]);
					$arrButtons = $this->$callback[0]->$callback[1]($arrButtons, $this);
				}
				elseif (is_callable($callback))
				{
					$arrButtons = $callback($arrButtons, $this);
				}
			}
		}

		// Add some buttons and end the form
		$return .= '
</div>

<div class="tl_formbody_submit">

<div class="tl_submit_container">
  ' . implode(' ', $arrButtons) . '
</div>

</div>
</form>

<script>
  window.addEvent(\'domready\', function() {
    Theme.focusInput("'.$this->strTable.'");
  });
</script>';

		// Begin the form (-> DO NOT CHANGE THIS ORDER -> this way the onsubmit attribute of the form can be changed by a field)
		$return = $version . '
<div id="tl_buttons">' . (\Input::get('nb') ? '&nbsp;' : '
<a href="'.$this->getReferer(true).'" class="header_back" title="'.specialchars($GLOBALS['TL_LANG']['MSC']['backBTTitle']).'" accesskey="b" onclick="Backend.getScrollOffset()">'.$GLOBALS['TL_LANG']['MSC']['backBT'].'</a>') . '
</div>

<h2 class="sub_headline">'.sprintf($GLOBALS['TL_LANG']['MSC']['editRecord'], ($this->intId ? 'ID '.$this->intId : '')).'</h2>
'.\Message::generate().'
<form action="'.ampersand(\Environment::get('request'), true).'" id="'.$this->strTable.'" class="tl_form" method="post" enctype="' . ($this->blnUploadable ? 'multipart/form-data' : 'application/x-www-form-urlencoded') . '"'.(!empty($this->onsubmit) ? ' onsubmit="'.implode(' ', $this->onsubmit).'"' : '').'>
<div class="tl_formbody_edit">
<input type="hidden" name="FORM_SUBMIT" value="'.specialchars($this->strTable).'">
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
						$this->$callback[0]->$callback[1]($this);
					}
					elseif (is_callable($callback))
					{
						$callback($this);
					}
				}
			}

			// Save the current version
			if ($this->blnCreateNewVersion && \Input::post('SUBMIT_TYPE') != 'auto')
			{
				$objVersions->create();

				// Call the onversion_callback
				if (is_array($GLOBALS['TL_DCA'][$this->strTable]['config']['onversion_callback'])) {
					foreach ($GLOBALS['TL_DCA'][$this->strTable]['config']['onversion_callback'] as $callback) {
						if (is_array($callback)) {
							$this->import($callback[0]);
							$this->$callback[0]->$callback[1]($this->strTable, $this->intId, $this);
						} elseif (is_callable($callback)) {
							$callback($this->strTable, $this->intId, $this);
						}
					}
				}

				$this->log('A new version of record "'.$this->strTable.'.id='.$this->intId.'" has been created'.$this->getParentEntries($this->strTable, $this->intId), 'DC_Table edit()', TL_GENERAL);
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
				\System::setCookie('BE_PAGE_OFFSET', 0, 0);
				$this->redirect($this->getReferer());
			}
			elseif (isset($_POST['saveNedit']))
			{
				\Message::reset();
				\System::setCookie('BE_PAGE_OFFSET', 0, 0);
				$strUrl = $this->addToUrl($GLOBALS['TL_DCA'][$this->strTable]['list']['operations']['edit']['href']);

				$strUrl = preg_replace('/(&amp;)?s2e=[^&]*/i', '', $strUrl);
				$strUrl = preg_replace('/(&amp;)?act=[^&]*/i', '', $strUrl);

				$this->redirect($strUrl);
			}
			elseif (isset($_POST['saveNback']))
			{
				\Message::reset();
				\System::setCookie('BE_PAGE_OFFSET', 0, 0);

				if ($this->ptable == '')
				{
					$this->redirect(\Environment::get('script') . '?do=' . \Input::get('do'));
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
				\System::setCookie('BE_PAGE_OFFSET', 0, 0);
				$strUrl = \Environment::get('script') . '?do=' . \Input::get('do');

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
	 * Duplicate a particular record of the current table with all the translations
	 * @param boolean
	 * @return integer|boolean
	 */
	public function copy($blnDoNotRedirect=false)
	{
		$insertId = parent::copy(true);
		$time = time();
		$objTranslations = $this->Database->prepare("SELECT * FROM " . $this->strTable . " WHERE " . $this->strPidColumn . "=?")->execute($this->intId);

		while ($objTranslations->next())
		{
			$arrInsert = array_merge($this->set, $objTranslations->row());
			$arrInsert['tstamp'] = $time; // array_merge() overwrites tstamp which is wrong
			$arrInsert[$this->strPidColumn] = $insertId; // add language reference id
			unset($arrInsert['id']); // unset id
			$this->Database->prepare("INSERT INTO " . $this->strTable . " %s")->set($arrInsert)->execute();
		}

		// Switch to edit mode
		if (!$blnDoNotRedirect)
		{
			$this->redirect($this->switchToEdit($insertId));
		}

		return $insertId;
	}


	/**
	 * Duplicate all child records of a duplicated record
	 * @param string
	 * @param integer
	 * @param integer
	 * @param integer
	 */
	protected function copyChilds($table, $insertID, $id, $parentId)
	{
		parent::copyChilds($table, $insertID, $id, $parentId);

		// Return if the table is not multilingual
		if ($GLOBALS['TL_DCA'][$table]['config']['dataContainer'] != 'Multilingual')
		{
			return;
		}

		$strPidColumn = $GLOBALS['TL_DCA'][$table]['config']['pidColumn'] ? $GLOBALS['TL_DCA'][$table]['config']['pidColumn'] : $this->strPidColumn;
		$objLanguage = $this->Database->prepare("SELECT id FROM " . $table . " WHERE " . $strPidColumn . "=? AND id>?")
									  ->limit(1)
									  ->execute($id, $parentId);

		// Update the language pid column
		if ($objLanguage->numRows)
		{
			$this->Database->prepare("UPDATE " . $table . " SET " . $strPidColumn . "=? WHERE id=?")
						   ->execute($insertID, $objLanguage->id);
		}
	}


	/**
	 * List all records of a particular table
	 * @return string
	 */
	public function showAll()
	{
		$this->procedure[] = "{$this->strLangColumn}=''";
		return parent::showAll();
	}


	/**
	 * List all records of the current table as tree and return them as HTML string
	 * @return string
	 */
	public function treeView()
	{
		$strWhere = '';

		if (is_array($this->root) && count($this->root))
		{
			$strWhere = ' AND id IN(' . implode(',', $this->root) . ')';
		}

		$strOrderBy = "";

		if ($this->Database->fieldExists('sorting', $this->strTable))
		{
			$strOrderBy = " ORDER BY sorting";
		}

		$this->root = $this->Database->query("SELECT id FROM $this->strTable WHERE {$this->strLangColumn}=''" . $strWhere . $strOrderBy)->fetchEach('id');

		return parent::treeView();
	}


	/**
	 * Generate a particular subpart of the tree and return it as HTML string
	 * @param integer
	 * @param integer
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
		$objRows = $this->Database->prepare("SELECT id FROM " . $table . " WHERE " . $this->strPidColumn . "=0 AND pid=?" . ($hasSorting ? " ORDER BY sorting" : ""))
							 	  ->execute($id);

		while ($objRows->next())
		{
			$arrIds[] = $objRows->id;
		}

		$blnClipboard = false;
		$arrClipboard = $this->Session->get('CLIPBOARD');

		// Check clipboard
		if (!empty($arrClipboard[$this->strTable]))
		{
			$blnClipboard = true;
			$arrClipboard = $arrClipboard[$this->strTable];
		}

		for ($i=0; $i<count($arrIds); $i++)
		{
			$return .= ' ' . trim($this->generateTree($table, $arrIds[$i], array('p'=>$arrIds[($i-1)], 'n'=>$arrIds[($i+1)]), $hasSorting, $margin, ($blnClipboard ? $arrClipboard : false), ($id == $arrClipboard ['id'] || (is_array($arrClipboard ['id']) && in_array($id, $arrClipboard ['id'])) || (!$blnPtable && !is_array($arrClipboard['id']) && in_array($id, $this->getChildRecords($arrClipboard['id'], $table)))), $blnProtected));
		}

		return $return;
	}


	/**
	 * Recursively generate the tree and return it as HTML string (taken from Contao 2.11.2)
	 * @param string
	 * @param integer
	 * @param array
	 * @param boolean
	 * @param integer
	 * @param array
	 * @param boolean
	 * @param boolean
	 * @param boolean
	 * @return string
	 */
	protected function generateTree($table, $id, $arrPrevNext, $blnHasSorting, $intMargin=0, $arrClipboard=null, $blnCircularReference=false, $protectedPage=false, $blnNoRecursion=false)
	{
		static $session;

		$session = $this->Session->getData();
		$node = ($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] == 6) ? $this->strTable.'_'.$table.'_tree' : $this->strTable.'_tree';

		// Toggle nodes
		if (\Input::get('ptg'))
		{
			$session[$node][\Input::get('ptg')] = (isset($session[$node][\Input::get('ptg')]) && $session[$node][\Input::get('ptg')] == 1) ? 0 : 1;
			$this->Session->setData($session);

			$this->redirect(preg_replace('/(&(amp;)?|\?)ptg=[^& ]*/i', '', \Environment::get('request')));
		}

		$objRow = $this->Database->prepare("SELECT * FROM " . $table . " WHERE id=?")
								 ->limit(1)
								 ->execute($id);

		// Return if there is no result
		if ($objRow->numRows < 1)
		{
			$this->Session->setData($session);
			return '';
		}

		$return = '';
		$intSpacing = 20;
		$childs = array();

		// Add the ID to the list of current IDs
		if ($this->strTable == $table)
		{
			$this->current[] = $objRow->id;
		}


		// Check whether there are child records
		if (!$blnNoRecursion)
		{
			if ($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] == 5 || $this->strTable != $table)
			{
				$objChilds = $this->Database->prepare("SELECT id FROM " . $table . " WHERE pid=? AND {$this->strLangColumn}=''" . ($blnHasSorting ? " ORDER BY sorting" : ''))
											->execute($id);

				if ($objChilds->numRows)
				{
					$childs = $objChilds->fetchEach('id');
				}
			}
		}

		$blnProtected = false;

		// Check whether the page is protected
		if ($table == 'tl_page')
		{
			$blnProtected = ($objRow->protected || $protectedPage) ? true : false;
		}

		$session[$node][$id] = (is_int($session[$node][$id])) ? $session[$node][$id] : 0;
		$mouseover = ($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] == 5 || $table == $this->strTable) ? ' onmouseover="Theme.hoverDiv(this,1)" onmouseout="Theme.hoverDiv(this,0)" onclick="Theme.toggleSelect(this)"' : '';

		$return .= "\n  " . '<li class="'.((($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] == 5 && $objRow->type == 'root') || $table != $this->strTable) ? 'tl_folder' : 'tl_file').' click2edit"'.$mouseover.'><div class="tl_left" style="padding-left:'.($intMargin + $intSpacing + (empty($childs) ? 20 : 0)).'px">';

		// Calculate label and add a toggle button
		$args = array();
		$showFields = $GLOBALS['TL_DCA'][$table]['list']['label']['fields'];
		$level = ($intMargin / $intSpacing + 1);

		if (!empty($childs))
		{
			$img = ($session[$node][$id] == 1) ? 'folMinus.gif' : 'folPlus.gif';
			$alt = ($session[$node][$id] == 1) ? $GLOBALS['TL_LANG']['MSC']['collapseNode'] : $GLOBALS['TL_LANG']['MSC']['expandNode'];
			$return .= '<a href="'.$this->addToUrl('ptg='.$id).'" title="'.specialchars($alt).'" onclick="Backend.getScrollOffset();return AjaxRequest.toggleStructure(this,\''.$node.'_'.$id.'\','.$level.','.$GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'].')">'.\Image::getHtml($img, '', 'style="margin-right:2px"').'</a>';
		}

		foreach ($showFields as $k=>$v)
		{
			// Decrypt the value
			if ($GLOBALS['TL_DCA'][$table]['fields'][$v]['eval']['encrypt'])
			{
				$objRow->$v = \Encryption::decrypt(deserialize($objRow->$v));
			}

			if (strpos($v, ':') !== false)
			{
				list($strKey, $strTable) = explode(':', $v);
				list($strTable, $strField) = explode('.', $strTable);

				$objRef = $this->Database->prepare("SELECT " . $strField . " FROM " . $strTable . " WHERE id=?")
										 ->limit(1)
										 ->execute($objRow->$strKey);

				$args[$k] = $objRef->numRows ? $objRef->$strField : '';
			}
			elseif (in_array($GLOBALS['TL_DCA'][$table]['fields'][$v]['flag'], array(5, 6, 7, 8, 9, 10)))
			{
				$args[$k] = \Date::parse($GLOBALS['TL_CONFIG']['datimFormat'], $objRow->$v);
			}
			elseif ($GLOBALS['TL_DCA'][$table]['fields'][$v]['inputType'] == 'checkbox' && !$GLOBALS['TL_DCA'][$table]['fields'][$v]['eval']['multiple'])
			{
				$args[$k] = ($objRow->$v != '') ? (isset($GLOBALS['TL_DCA'][$table]['fields'][$v]['label'][0]) ? $GLOBALS['TL_DCA'][$table]['fields'][$v]['label'][0] : $v) : '';
			}
			else
			{
				$args[$k] = $GLOBALS['TL_DCA'][$table]['fields'][$v]['reference'][$objRow->$v] ?: $objRow->$v;
			}
		}

		$label = vsprintf(((strlen($GLOBALS['TL_DCA'][$table]['list']['label']['format'])) ? $GLOBALS['TL_DCA'][$table]['list']['label']['format'] : '%s'), $args);

		// Shorten the label if it is too long
		if ($GLOBALS['TL_DCA'][$table]['list']['label']['maxCharacters'] > 0 && $GLOBALS['TL_DCA'][$table]['list']['label']['maxCharacters'] < utf8_strlen(strip_tags($label)))
		{
			$label = trim(\String::substrHtml($label, $GLOBALS['TL_DCA'][$table]['list']['label']['maxCharacters'])) . ' …';
		}

		$label = preg_replace('/\(\) ?|\[\] ?|\{\} ?|<> ?/', '', $label);

		// Call the label_callback ($row, $label, $this)
		if (is_array($GLOBALS['TL_DCA'][$table]['list']['label']['label_callback'])) {
			$strClass = $GLOBALS['TL_DCA'][$table]['list']['label']['label_callback'][0];
			$strMethod = $GLOBALS['TL_DCA'][$table]['list']['label']['label_callback'][1];

			$this->import($strClass);
			$return .= $this->$strClass->$strMethod($objRow->row(), $label, $this, '', false, $blnProtected);
		} elseif (is_callable($GLOBALS['TL_DCA'][$table]['list']['label']['label_callback'])) {
			$return .= $GLOBALS['TL_DCA'][$table]['list']['label']['label_callback']($objRow->row(), $label, $this, '', false, $blnProtected);
		} else {
			$return .= \Image::getHtml('iconPLAIN.gif', '') . ' ' . $label;
		}

		$return .= '</div> <div class="tl_right">';
		$previous = ($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] == 6) ? $arrPrevNext['pp'] : $arrPrevNext['p'];
		$next = ($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] == 6) ? $arrPrevNext['nn'] : $arrPrevNext['n'];
		$_buttons = '';

		// Regular buttons ($row, $table, $root, $blnCircularReference, $childs, $previous, $next)
		if ($this->strTable == $table)
		{
			$_buttons .= (\Input::get('act') == 'select') ? '<input type="checkbox" name="IDS[]" id="ids_'.$id.'" class="tl_tree_checkbox" value="'.$id.'">' : $this->generateButtons($objRow->row(), $table, $this->root, $blnCircularReference, $childs, $previous, $next);
		}

		// Paste buttons
		if ($arrClipboard !== false && \Input::get('act') != 'select')
		{
			$_buttons .= ' ';

			// Call paste_button_callback(&$dc, $row, $table, $blnCircularReference, $arrClipboard, $childs, $previous, $next)
			if (is_array($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['paste_button_callback'])) {
				$strClass = $GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['paste_button_callback'][0];
				$strMethod = $GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['paste_button_callback'][1];

				$this->import($strClass);
				$_buttons .= $this->$strClass->$strMethod($this, $objRow->row(), $table, $blnCircularReference, $arrClipboard, $childs, $previous, $next);
			} elseif (is_callable($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['paste_button_callback'])) {
				$_buttons .= $GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['paste_button_callback']($this, $objRow->row(), $table, $blnCircularReference, $arrClipboard, $childs, $previous, $next);
			} else {
				$imagePasteAfter = \Image::getHtml('pasteafter.gif', sprintf($GLOBALS['TL_LANG'][$this->strTable]['pasteafter'][1], $id));
				$imagePasteInto = \Image::getHtml('pasteinto.gif', sprintf($GLOBALS['TL_LANG'][$this->strTable]['pasteinto'][1], $id));

				// Regular tree (on cut: disable buttons of the page all its childs to avoid circular references)
				if ($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] == 5)
				{
					$_buttons .= ($arrClipboard['mode'] == 'cut' && ($blnCircularReference || $arrClipboard['id'] == $id) || $arrClipboard['mode'] == 'cutAll' && ($blnCircularReference || in_array($id, $arrClipboard['id'])) || (!empty($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['root']) && in_array($id, $this->root))) ? \Image::getHtml('pasteafter_.gif').' ' : '<a href="'.$this->addToUrl('act='.$arrClipboard['mode'].'&amp;mode=1&amp;pid='.$id.(!is_array($arrClipboard['id']) ? '&amp;id='.$arrClipboard['id'] : '')).'" title="'.specialchars(sprintf($GLOBALS['TL_LANG'][$this->strTable]['pasteafter'][1], $id)).'" onclick="Backend.getScrollOffset()">'.$imagePasteAfter.'</a> ';
					$_buttons .= ($arrClipboard['mode'] == 'paste' && ($blnCircularReference || $arrClipboard['id'] == $id) || $arrClipboard['mode'] == 'cutAll' && ($blnCircularReference || in_array($id, $arrClipboard['id']))) ? \Image::getHtml('pasteinto_.gif').' ' : '<a href="'.$this->addToUrl('act='.$arrClipboard['mode'].'&amp;mode=2&amp;pid='.$id.(!is_array($arrClipboard['id']) ? '&amp;id='.$arrClipboard['id'] : '')).'" title="'.specialchars(sprintf($GLOBALS['TL_LANG'][$this->strTable]['pasteinto'][1], $id)).'" onclick="Backend.getScrollOffset()">'.$imagePasteInto.'</a> ';
				}

				// Extended tree
				else
				{
					$_buttons .= ($this->strTable == $table) ? (($arrClipboard['mode'] == 'cut' && ($blnCircularReference || $arrClipboard['id'] == $id) || $arrClipboard['mode'] == 'cutAll' && ($blnCircularReference || in_array($id, $arrClipboard['id']))) ? \Image::getHtml('pasteafter_.gif') : '<a href="'.$this->addToUrl('act='.$arrClipboard['mode'].'&amp;mode=1&amp;pid='.$id.(!is_array($arrClipboard['id']) ? '&amp;id='.$arrClipboard['id'] : '')).'" title="'.specialchars(sprintf($GLOBALS['TL_LANG'][$this->strTable]['pasteafter'][1], $id)).'" onclick="Backend.getScrollOffset()">'.$imagePasteAfter.'</a> ') : '';
					$_buttons .= ($this->strTable != $table) ? '<a href="'.$this->addToUrl('act='.$arrClipboard['mode'].'&amp;mode=2&amp;pid='.$id.(!is_array($arrClipboard['id']) ? '&amp;id='.$arrClipboard['id'] : '')).'" title="'.specialchars(sprintf($GLOBALS['TL_LANG'][$this->strTable]['pasteinto'][1], $id)).'" onclick="Backend.getScrollOffset()">'.$imagePasteInto.'</a> ' : '';
				}
			}
		}

		$return .= ($_buttons ?: '&nbsp;') . '</div><div style="clear:both"></div></li>';

		// Add the records of the table itself
		if ($table != $this->strTable)
		{
			$objChilds = $this->Database->prepare("SELECT id FROM " . $this->strTable . " WHERE pid=?" . ($blnHasSorting ? " ORDER BY sorting" : ''))
							 			->execute($id);

			if ($objChilds->numRows)
			{
				$ids = $objChilds->fetchEach('id');

				for ($j=0, $c=count($ids); $j<$c; $j++)
				{
					$return .= $this->generateTree($this->strTable, $ids[$j], array('pp'=>$ids[($j-1)], 'nn'=>$ids[($j+1)]), $blnHasSorting, ($intMargin + $intSpacing), $arrClipboard, false, ($j<(count($ids)-1) || !empty($childs)));
				}
			}
		}

		// Begin a new submenu
		if (!$blnNoRecursion)
		{
			if (!empty($childs) && $session[$node][$id] == 1)
			{
				$return .= '<li class="parent" id="'.$node.'_'.$id.'"><ul class="level_'.$level.'">';
			}

			// Add the records of the parent table
			if ($session[$node][$id] == 1)
			{
				if (is_array($childs))
				{
					for ($k=0, $c=count($childs); $k<$c; $k++)
					{
						$return .= $this->generateTree($table, $childs[$k], array('p'=>$childs[($k-1)], 'n'=>$childs[($k+1)]), $blnHasSorting, ($intMargin + $intSpacing), $arrClipboard, ((($GLOBALS['TL_DCA'][$this->strTable]['list']['sorting']['mode'] == 5 && $childs[$k] == $arrClipboard['id']) || $blnCircularReference) ? true : false), ($blnProtected || $protectedPage));
					}
				}
			}

			// Close the submenu
			if (!empty($childs) && $session[$node][$id] == 1)
			{
				$return .= '</ul></li>';
			}
		}

		$this->Session->setData($session);
		return $return;
	}


	/**
	 * Calculate the new position of a moved or inserted record
	 * @param string
	 * @param integer
	 * @param boolean
	 */
	protected function getNewPosition($mode, $pid=null, $insertInto=false)
	{
		// If there is pid and sorting
		if ($this->Database->fieldExists('pid', $this->strTable) && $this->Database->fieldExists('sorting', $this->strTable))
		{
			// PID is not set - only valid for duplicated records, as they get the same parent ID as the original record!
			if ($pid === null && $this->intId && $mode == 'copy')
			{
				$pid = $this->intId;
			}

			// PID is set (insert after or into the parent record)
			if (is_numeric($pid))
			{
				// Insert the current record at the beginning when inserting into the parent record
				if ($insertInto)
				{
					$newPID = $pid;
					$objSorting = $this->Database->prepare("SELECT MIN(sorting) AS sorting FROM " . $this->strTable . " WHERE pid=? AND {$this->strPidColumn}=0")
												 ->executeUncached($pid);

					// Select sorting value of the first record
					if ($objSorting->numRows)
					{
						$curSorting = $objSorting->sorting;

						// Resort if the new sorting value is not an integer or smaller than 1
						if (($curSorting % 2) != 0 || $curSorting < 1)
						{
							$objNewSorting = $this->Database->prepare("SELECT id, sorting FROM " . $this->strTable . " WHERE pid=? AND {$this->strPidColumn}=0 ORDER BY sorting" )
															->executeUncached($pid);

							$count = 2;
							$newSorting = 128;

							while ($objNewSorting->next())
							{
								$this->Database->prepare("UPDATE " . $this->strTable . " SET sorting=? WHERE id=?")
											   ->limit(1)
											   ->execute(($count++*128), $objNewSorting->id);
							}
						}

						// Else new sorting = (current sorting / 2)
						else $newSorting = ($curSorting / 2);
					}

					// Else new sorting = 128
					else $newSorting = 128;
				}

				// Else insert the current record after the parent record
				elseif ($pid > 0)
				{
					$objSorting = $this->Database->prepare("SELECT * FROM " . $this->strTable . " WHERE id=? AND {$this->strPidColumn}=0")
												 ->limit(1)
												 ->executeUncached($pid);

					// Set parent ID of the current record as new parent ID
					if ($objSorting->numRows)
					{
						$newPID = $objSorting->pid;
						$curSorting = $objSorting->sorting;

						// Do not proceed without a parent ID
						if (is_numeric($newPID))
						{
							$objNextSorting = $this->Database->prepare("SELECT MIN(sorting) AS sorting FROM " . $this->strTable . " WHERE pid=? AND {$this->strPidColumn}=0 AND sorting>?")
											  				 ->executeUncached($newPID, $curSorting);

							// Select sorting value of the next record
							if ($objNextSorting->sorting !== null)
							{
								$nxtSorting = $objNextSorting->sorting;

								// Resort if the new sorting value is no integer or bigger than a MySQL integer
								if ((($curSorting + $nxtSorting) % 2) != 0 || $nxtSorting >= 4294967295)
								{
									$count = 1;

									$objNewSorting = $this->Database->prepare("SELECT id, sorting FROM " . $this->strTable . " WHERE pid=? AND {$this->strPidColumn}=0 ORDER BY sorting")
																	->executeUncached($newPID);

									while ($objNewSorting->next())
									{
										$this->Database->prepare("UPDATE " . $this->strTable . " SET sorting=? WHERE id=?")
													   ->execute(($count++*128), $objNewSorting->id);

										if ($objNewSorting->sorting == $curSorting)
										{
											$newSorting = ($count++*128);
										}
									}
								}

								// Else new sorting = (current sorting + next sorting) / 2
								else $newSorting = (($curSorting + $nxtSorting) / 2);
							}

							// Else new sorting = (current sorting + 128)
							else $newSorting = ($curSorting + 128);
						}
					}

					// Use the given parent ID as parent ID
					else
					{
						$newPID = $pid;
						$newSorting = 128;
					}
				}

				// Set new sorting and new parent ID
				$this->set['pid'] = intval($newPID);
				$this->set['sorting'] = intval($newSorting);
			}
		}
		else
		{
			parent::getNewPosition($mode, $pid, $insertInto);
		}
	}


	/**
	 * Delete record and associated translations
	 * @param boolean
	 */
	public function delete($blnDoNotRedirect=false)
	{
		$this->Database->prepare("DELETE FROM " . $this->strTable . " WHERE " . $this->strPidColumn . "=?")
					   ->execute($this->intId);

		parent::delete($blnDoNotRedirect);
	}


	/**
	 * Recursively get all related table names and language records
	 * @param string
	 * @param integer
	 * @param array
	 */
	public function deleteChilds($table, $id, &$delete)
	{
		parent::deleteChilds($table, $id, $delete);

		// Return if the table is not multilingual
		if ($GLOBALS['TL_DCA'][$table]['config']['dataContainer'] != 'Multilingual')
		{
			return;
		}

		$objLanguages = $this->Database->prepare("SELECT id FROM " . $table . " WHERE " . ($GLOBALS['TL_DCA'][$table]['config']['pidColumn'] ? $GLOBALS['TL_DCA'][$table]['config']['pidColumn'] : $this->strPidColumn) . " IN (SELECT id FROM " . $table . " WHERE pid=?)")
									   ->execute($id);

		while ($objLanguages->next())
		{
			$delete[$table][] = $objLanguages->id;
		}
	}


	/**
	 * Get all languages
	 * @return array
	 */
	public function getAllowedLanguages()
	{
		return $this->arrLanguages;
	}


	/**
	 * Get the fallback language
	 * @return string
	 */
	public function getFallbackLanguage()
	{
		return $this->strFallbackLang;
	}


	/**
	 * Get the current language
	 * @return string
	 */
	public function getCurrentLanguage()
	{
		return $this->strCurrentLang;
	}


	/**
	 * Get the language column
	 * @return string
	 */
	public function getLanguageColumn()
	{
		return $this->strLangColumn;
	}


	/**
	 * Get the parent reference column
	 * @return string
	 */
	public function getPidColumn()
	{
		return $this->strPidColumn;
	}


	/**
	 * Get the list of languages based on root pages
	 * @return array
	 */
	protected function getRootPageLanguages()
	{
		$objPages = $this->Database->execute("SELECT DISTINCT language FROM tl_page WHERE type='root' AND language!=''");
		return $objPages->fetchEach('language');
	}
}
