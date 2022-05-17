<?php

declare(strict_types=1);

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
