<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2014 Leo Feyer
 *
 * @package Dc_multilingual
 * @link    https://contao.org
 * @license http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */


/**
 * Register the classes
 */
ClassLoader::addClasses(array
(
	// Classes
	'Contao\DC_Multilingual_Query'    => 'system/modules/dc_multilingual/classes/DC_Multilingual_Query.php',
	'Contao\MultilingualQueryBuilder' => 'system/modules/dc_multilingual/classes/MultilingualQueryBuilder.php',

	// Drivers
	'DC_Multilingual'                 => 'system/modules/dc_multilingual/drivers/DC_Multilingual.php',

	// Models
	'Contao\MultilingualModel'        => 'system/modules/dc_multilingual/models/MultilingualModel.php',
));
