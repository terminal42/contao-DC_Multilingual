<?php

declare(strict_types=1);

namespace Terminal42\DcMultilingualBundle\ContaoManager;

use Contao\CoreBundle\ContaoCoreBundle;
use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Terminal42\DcMultilingualBundle\Terminal42DcMultilingualBundle;

class Plugin implements BundlePluginInterface
{
    /**
     * {@inheritdoc}
     */
    public function getBundles(ParserInterface $parser)
    {
        return [
            BundleConfig::create(Terminal42DcMultilingualBundle::class)
                ->setLoadAfter([ContaoCoreBundle::class])
                ->setReplace(['dc_multilingual']),
        ];
    }
}
