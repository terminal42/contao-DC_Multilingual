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

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Picker\AbstractTablePickerProvider;
use Contao\DcaLoader;
use DC_Multilingual;
use Doctrine\DBAL\Connection;
use Knp\Menu\FactoryInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Terminal42\DcMultilingualBundle\Driver;
use function strpos;

final class MultilingualPickerProvider extends AbstractTablePickerProvider
{
    private const PREFIX = 'dc.';

    /**
     * @var ContaoFramework
     */
    private $framework;

    public function __construct(ContaoFramework $framework, FactoryInterface $menuFactory, RouterInterface $router, TranslatorInterface $translator, Connection $connection)
    {
        parent::__construct($framework, $menuFactory, $router, $translator, $connection);

        $this->framework = $framework;
    }

    public function getName(): string
    {
        return 'multilingualPicker';
    }

    protected function getDataContainer(): string
    {
        return Driver::class;
    }

    /**
     * We have to reimplement whole method because we have to check two names of the data container driver.
     */
    public function supportsContext($context): bool
    {
        if (0 !== strpos($context, self::PREFIX)) {
            return false;
        }

        $table = $this->getTableFromContext($context);

        $this->framework->initialize();
        $this->framework->createInstance(DcaLoader::class, [$table])->load();

        $drivers = ['Multilingual', Driver::class, DC_Multilingual::class];

        return isset($GLOBALS['TL_DCA'][$table]['config']['dataContainer'])
            && \in_array($GLOBALS['TL_DCA'][$table]['config']['dataContainer'], $drivers, true)
            && 0 !== \count($this->getModulesForTable($table));
    }
}
