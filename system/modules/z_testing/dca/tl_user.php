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
 * @copyright  Leo Feyer 2005-2011
 * @author     Leo Feyer <http://www.contao.org>
 * @package    News
 * @license    LGPL
 * @filesource
 */


/**
 * Update tl_user configuration
 */
$GLOBALS['TL_DCA']['tl_user']['config']['dataContainer'] = 'Multilingual';
$GLOBALS['TL_DCA']['tl_user']['config']['languages'] = array('de', 'en');
$GLOBALS['TL_DCA']['tl_user']['config']['langPid'] = 'langPid';
$GLOBALS['TL_DCA']['tl_user']['config']['langColumn'] = 'language_dc';
$GLOBALS['TL_DCA']['tl_user']['config']['fallbackLang'] = 'de';


/**
 * Add the language fieldslanguage_dc
 */
$GLOBALS['TL_DCA']['tl_user']['config']['sql']['keys']['langPid'] = 'index';
$GLOBALS['TL_DCA']['tl_user']['config']['sql']['keys']['language_dc'] = 'index';
$GLOBALS['TL_DCA']['tl_user']['fields']['langPid']['sql'] = "int(10) unsigned NOT NULL default '0'";
$GLOBALS['TL_DCA']['tl_user']['fields']['language_dc']['sql'] = "varchar(2) NOT NULL default ''";


/**
 * Make some fields translatable
 */
$GLOBALS['TL_DCA']['tl_user']['fields']['username']['eval']['translatableFor'] = '*';
$GLOBALS['TL_DCA']['tl_user']['fields']['name']['eval']['translatableFor'] = array('de');


/**
 * Test the multilingual models
 */
if ($_GET['test'])
{
	$objUsers = \UserTestModel::findAll();
	print_r($objUsers->fetchAll());
}
