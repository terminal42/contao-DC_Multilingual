<?php

declare(strict_types=1);

namespace Terminal42\DcMultilingualBundle\EventListener;

use Contao\CoreBundle\Slug\Slug;
use Contao\CoreBundle\Util\LocaleUtil;
use Doctrine\DBAL\Connection;
use Symfony\Contracts\Translation\TranslatorInterface;
use Terminal42\DcMultilingualBundle\Driver;

class MultilingualAliasListener
{
    public function __construct(
        private readonly Slug $slug,
        private readonly TranslatorInterface $translator,
        private readonly Connection $connection,
    ) {
    }

    public function __invoke(string $table): void
    {
        if (!\is_a(($GLOBALS['TL_DCA'][$table]['config']['dataContainer'] ?? null), Driver::class, true)) {
            return;
        }

        foreach (($GLOBALS['TL_DCA'][$table]['fields'] ?? []) as $field => $config) {
            if (!($config['eval']['isMultilingualAlias'] ?? false)) {
                continue;
            }

            $fromField = $config['eval']['generateAliasFromField'] ?? 'title';

            $GLOBALS['TL_DCA'][$table]['config']['onbeforesubmit_callback'][] = fn (array $values, Driver $dc) => $this->generateAlias($table, $field, $fromField, $values, $dc);
            $GLOBALS['TL_DCA'][$table]['fields'][$field]['save_callback'][] = fn ($value, Driver $dc) => $this->validateInput($table, $field, $value, $dc);
            $GLOBALS['TL_DCA'][$table]['fields'][$field]['eval']['unique'] = false;
        }
    }

    private function validateInput(string $table, string $field, mixed $value, Driver $dc): mixed
    {
        if ('' === $value) {
            return $value;
        }

        if (preg_match('/^[1-9]\d*$/', $value)) {
            throw new \RuntimeException($this->translator->trans('ERR.aliasNumeric', [$value], 'contao_default'));
        }

        if ($this->aliasExists($table, $field, $value, $dc->getPidColumn(), $dc->getLanguageColumn(), (int) $dc->id, $dc->getCurrentLanguage())) {
            throw new \RuntimeException($this->translator->trans('ERR.aliasExists', [$value], 'contao_default'));
        }

        return $value;
    }

    private function generateAlias(string $table, string $field, string $fromField, array $values, Driver $dc): array
    {
        $currentRecord = array_merge($dc->getCurrentRecord(), $values);

        // Alias already exists and is not empty
        if ('' !== $currentRecord[$field]) {
            return $values;
        }

        if (!empty($currentRecord[$fromField])) {
            $slugOptions = $GLOBALS['TL_DCA'][$table]['fields'][$field]['eval']['slugOptions'] ?? [];

            if (\is_callable($slugOptions)) {
                $slugOptions = $slugOptions($dc);
            }

            if (\is_array($slugOptions) && !isset($slugOptions['locale']) && ($locale = $dc->getCurrentLanguage())) {
                $slugOptions['locale'] = LocaleUtil::getPrimaryLanguage($locale);
            }

            $values[$field] = $this->slug->generate(
                $currentRecord[$fromField],
                $slugOptions,
                fn (string $slug) => $this->aliasExists($table, $field, $slug, $dc->getPidColumn(), $dc->getLanguageColumn(), (int) $dc->id, $dc->getCurrentLanguage()),
            );
        }

        return $values;
    }

    private function aliasExists(string $table, string $field, string $alias, string $pidColumn, string $langColumn, int $currentId, string $currentLanguage): bool
    {
        $field = $this->connection->quoteSingleIdentifier($field);
        $pidColumn = $this->connection->quoteSingleIdentifier($pidColumn);
        $langColumn = $this->connection->quoteSingleIdentifier($langColumn);

        return $this->connection->fetchOne(
            "SELECT COUNT(*) FROM $table WHERE $field=? AND id NOT IN (SELECT id FROM $table WHERE $pidColumn=?) AND id!=? AND $langColumn=?",
            [
                $alias,
                $currentId,
                $currentId,
                $currentLanguage,
            ],
        ) > 0;
    }
}
