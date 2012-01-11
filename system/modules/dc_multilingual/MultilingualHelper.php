<?php if (!defined('TL_ROOT')) die('You cannot access this file directly!');

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
 * @copyright  Andreas Schempp 2011-2012
 * @copyright  certo web & design GmbH 2011-2012
 * @author     Andreas Schempp <andreas@schempp.ch>
 * @license    http://opensource.org/licenses/lgpl-3.0.html
 */


class MultilingualHelper extends System
{
	
	/**
	 * Generate a select statement that includes translated fields
	 * @param string
	 * @param string
	 * @param string
	 * @param string
	 * @return string
	 */
	public static function getSelectForTable($strTable, $strFields='', $strJoin='', $strLanguage=null)
	{
		if (!is_array($GLOBALS['TL_DCA'][$strTable]))
		{
			throw new Exception('Please load data container for table "' . $strTable . '"');
		}
		
		if ($strLanguage === null)
		{
			$strLanguage = $GLOBALS['TL_LANGUAGE'];
		}
		
		$arrFields = $GLOBALS['TL_DCA'][$strTable]['fields'];
		$strPid = $GLOBALS['TL_DCA'][$strTable]['config']['pidColumn'];
		$arrSelect = array("'".$GLOBALS['TL_LANGUAGE']."' AS language");

		foreach ($arrFields as $field => $arrData)
		{
			if ($arrData['eval']['translatableFor'] == '')
			{
				continue;
			}
			
			$arrSelect[] = "IFNULL(t2.$field, t1.$field) AS $field";
		}

		$strSelect = "
SELECT t1.*,
	" . implode(', ', $arrSelect) . "
	$strFields
FROM $strTable t1
LEFT OUTER JOIN $strTable t2 ON t1.id=t2.$strPid AND t2.language='" . $strLanguage . "'
$strJoin
WHERE t1.$strPid=0";

		return $strSelect;
	}
}

