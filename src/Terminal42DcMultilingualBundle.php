<?php

declare(strict_types=1);

namespace Terminal42\DcMultilingualBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class Terminal42DcMultilingualBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
