<?php

declare(strict_types=1);

namespace Terminal42\DcMultilingualBundle\Picker;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\CoreBundle\Picker\AbstractTablePickerProvider;
use Contao\DcaLoader;
use Doctrine\DBAL\Connection;
use Knp\Menu\FactoryInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Terminal42\DcMultilingualBundle\Driver;

final class MultilingualPickerProvider extends AbstractTablePickerProvider
{
    private const PREFIX = 'dc.';

    private readonly ContaoFramework $framework;

    public function __construct(ContaoFramework $framework, FactoryInterface $menuFactory, RouterInterface $router, TranslatorInterface $translator, Connection $connection)
    {
        parent::__construct($framework, $menuFactory, $router, $translator, $connection);

        $this->framework = $framework;
    }

    public function getName(): string
    {
        return 'multilingualPicker';
    }

    /**
     * We have to reimplement whole method because we have to check two names of the data container driver.
     *
     * @param string $context
     */
    #[\Override]
    public function supportsContext($context): bool
    {
        if (!str_starts_with((string) $context, self::PREFIX)) {
            return false;
        }

        $table = $this->getTableFromContext($context);

        $this->framework->initialize();
        $this->framework->createInstance(DcaLoader::class, [$table])->load();

        $drivers = [Driver::class, \DC_Multilingual::class];

        return isset($GLOBALS['TL_DCA'][$table]['config']['dataContainer'])
            && \in_array($GLOBALS['TL_DCA'][$table]['config']['dataContainer'], $drivers, true)
            && 0 !== \count($this->getModulesForTable($table));
    }

    protected function getDataContainer(): string
    {
        return Driver::class;
    }
}
