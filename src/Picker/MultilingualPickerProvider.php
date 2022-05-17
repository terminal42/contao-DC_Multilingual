<?php

declare(strict_types=1);

/*
 * dc_multilingual Extension for Contao Open Source CMS
 *
 * @copyright  Copyright (c) 2011-2022, terminal42 gmbh
 * @author     terminal42 gmbh <info@terminal42.ch>
 * @license    http://opensource.org/licenses/lgpl-3.0.html LGPL
 * @link       http://github.com/terminal42/contao-dc_multilingual
 */

namespace Terminal42\DcMultilingualBundle\Picker;

use Contao\CoreBundle\Picker\AbstractTablePickerProvider;
use Terminal42\DcMultilingualBundle\Driver;

final class MultilingualPickerProvider extends AbstractTablePickerProvider
{
    public function getName(): string
    {
        return 'multilingualPicker';
    }

    protected function getDataContainer(): string
    {
        return Driver::class;
    }
}
