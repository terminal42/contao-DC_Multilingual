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

use Symfony\Component\HttpKernel\Bundle\Bundle;

class Terminal42DcMultilingualBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
