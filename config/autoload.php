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
 * Register the classes
 */
ClassLoader::addClasses(array
(
	'DC_Multilingual'          => 'system/modules/dc_multilingual/drivers/DC_Multilingual.php',
	'MultilingualModel'        => 'system/modules/dc_multilingual/models/MultilingualModel.php',
	'MultilingualQueryBuilder' => 'system/modules/dc_multilingual/classes/MultilingualQueryBuilder.php',
	'DC_Multilingual_Query'    => 'system/modules/dc_multilingual/classes/DC_Multilingual_Query.php'
));
