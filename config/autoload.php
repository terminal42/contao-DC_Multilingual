<?php

/**
 * @author  Kamil Kuzminski <kamil.kuzminski@codefog.pl>
 * @package DC_Multilingual
 * @license LGPL
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
