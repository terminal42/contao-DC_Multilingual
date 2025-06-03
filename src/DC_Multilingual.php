<?php

declare(strict_types=1);

use Terminal42\DcMultilingualBundle\Driver;

/*
 * dc_multilingual Extension for Contao Open Source CMS
 *
 * @copyright  Copyright (c) 2011-2017, terminal42 gmbh
 * @author     terminal42 gmbh <info@terminal42.ch>
 * @license    http://opensource.org/licenses/lgpl-3.0.html LGPL
 * @link       http://github.com/terminal42/contao-dc_multilingual
 */

trigger_deprecation('terminal42/dc_multilingual', '4.6', 'The global DC_Multilingual class is deprecated, use Terminal42\DcMultilingualBundle\Driver instead.');

/**
 * @deprecated Use the Terminal42\DcMultilingualBundle\Driver instead
 */
class DC_Multilingual extends Driver
{
}
