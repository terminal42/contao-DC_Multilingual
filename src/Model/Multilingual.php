<?php

/*
 * dc_multilingual Extension for Contao Open Source CMS
 *
 * @copyright  Copyright (c) 2011-2017, terminal42 gmbh
 * @author     terminal42 gmbh <info@terminal42.ch>
 * @license    http://opensource.org/licenses/lgpl-3.0.html LGPL
 * @link       http://github.com/terminal42/contao-dc_multilingual
 */

namespace Terminal42\DcMultilingualBundle\Model;

use Contao\Database;
use Contao\Database\Result;
use Contao\Model;
use Contao\Model\Collection;
use Doctrine\DBAL\Query\QueryBuilder;
use Terminal42\DcMultilingualBundle\QueryBuilder\MultilingualQueryBuilderFactoryInterface;

class Multilingual extends Model
{
    /**
     * Returns the ID of the fallback language.
     */
    public function getLanguageId()
    {
        $pidColumn = static::getPidColumn();

        if ($this->{$pidColumn} > 0) {
            return $this->{$pidColumn};
        }

        return $this->id;
    }

    /**
     * Get the alias of a multilingual model.
     *
     * @param string $language
     * @param string $aliasColumnName
     *
     * @return mixed
     */
    public function getAlias($language, $aliasColumnName = 'alias')
    {
        // Do not load any translation if already target language
        $langColumn = static::getLangColumn();
        $fallbackLang = static::getFallbackLanguage();

        if ($language === $fallbackLang && !$this->{$langColumn}) {
            return $this->{$aliasColumnName};
        }

        if ($language === $this->{$langColumn}) {
            return $this->{$aliasColumnName};
        }

        // Try to load the translated model
        $translatedModel = static::findByPk($this->getLanguageId(), ['language' => $language]);

        if (null === $translatedModel) {
            // Get fallback
            if ($language === $fallbackLang) {
                return $this->{$aliasColumnName};
            }

            $fallbackModel = static::findByPk($this->getLanguageId(), ['language' => $fallbackLang]);

            return $fallbackModel->{$aliasColumnName};
        }

        return $translatedModel->{$aliasColumnName};
    }

    /**
     * Find a model by its alias.
     *
     * @param        $alias
     * @param string $aliasColumnName
     * @param array  $options
     *
     * @return mixed
     */
    public static function findByAlias($alias, $aliasColumnName = 'alias', $options = [])
    {
        $table   = static::getTable();
        $options = array_merge([
                'limit' => 1,
                'column' => ["$table.$aliasColumnName=?"],
                'value' => [$alias],
                'return' => 'Model',
            ],
            $options
        );

        return static::find($options);
    }

    /**
     * Find a model by its alias when using multilingal aliases.
     *
     * @param        $alias
     * @param string $aliasColumnName
     * @param array  $options
     *
     * @return mixed
     */
    public static function findByMultilingualAlias($alias, $aliasColumnName = 'alias', $options = [])
    {
        $table   = static::getTable();
        $options = array_merge([
                'limit' => 1,
                'column' => ["($table.$aliasColumnName=? OR translation.$aliasColumnName=?)"],
                'value' => [$alias, $alias],
                'return' => 'Model',
            ],
            $options
        );

        return static::find($options);
    }

    /**
     * Get the language column.
     *
     * @return string
     */
    public static function getLangColumn()
    {
        static::ensureDataContainerIsLoaded();

        if ($GLOBALS['TL_DCA'][static::getTable()]['config']['langColumnName']) {
            return $GLOBALS['TL_DCA'][static::getTable()]['config']['langColumnName'];
        }

        return 'language';
    }

    /**
     * Get the fallback language if available.
     *
     * @return string|null
     */
    public static function getFallbackLanguage()
    {
        static::ensureDataContainerIsLoaded();

        if ($GLOBALS['TL_DCA'][static::getTable()]['config']['fallbackLang']) {
            return $GLOBALS['TL_DCA'][static::getTable()]['config']['fallbackLang'];
        }

        return null;
    }

    /**
     * Build a query based on the given options.
     * The method returns a QueryBuilder instance so you can easily modify
     * the query in your child class. We can just return the instance as the
     * QueryBuilder implements the __toString() method so we don't have to call
     * ->getSql() manually.
     *
     * @param array $options The options array
     *
     * @return QueryBuilder
     */
    protected static function buildFindQuery(array $options)
    {
        $mlqb = static::getMultilingualQueryBuilder();

        // Use the current language if none provided
        if (!isset($options['language'])) {
            $options['language'] = str_replace('-', '_', $GLOBALS['TL_LANGUAGE']);
        }

        // Consider the fallback language
        $fallbackLang = static::getFallbackLanguage();
        if (null !== $fallbackLang && $fallbackLang === $options['language']) {
            $options['language'] = '';
        }

        $mlqb->buildQueryBuilderForFind($options['language']);

        static::applyOptionsToQueryBuilder($mlqb->getQueryBuilder(), $options);

        return $mlqb->getQueryBuilder();
    }

    /**
     * Build a query based on the given options to count the number of records.
     * The method returns a QueryBuilder instance so you can easily modify
     * the query in your child class. We can just return the instance as the
     * QueryBuilder implements the __toString() method so we don't have to call
     * ->getSql() manually.
     *
     * @param array $options The options array
     *
     * @return QueryBuilder
     */
    protected static function buildCountQuery(array $options)
    {
        $mlqb = static::getMultilingualQueryBuilder();

        if (isset($options['having'])) {
            $mlqb->buildQueryBuilderForCountWithSubQuery(static::buildFindQuery($options));
        } else {
            $mlqb->buildQueryBuilderForCount();
            static::applyOptionsToQueryBuilder($mlqb->getQueryBuilder(), $options);
        }

        return $mlqb->getQueryBuilder();
    }

    /**
     * Prevent model from saving when creating a model from a database result. See #51
     *
     * @param Result $objResult The database result object
     *
     * @return static The model
     */
    protected static function createModelFromDbResult(Result $objResult)
    {
        $model = new static($objResult);
        $model->preventSaving(false);

        return $model;
    }

    /**
     * Prevent new models from saving when creating a new collection from a database result. See #51
     *
     * @param Result $objResult The database result object
     * @param string $strTable  The table name
     *
     * @return Collection The model collection
     */
    protected static function createCollectionFromDbResult(Result $objResult, $strTable)
    {
        $collection = Collection::createFromDbResult($objResult, $strTable);

        /** @var self $model */
        foreach ($collection as $model) {
            $model->preventSaving(false);
        }

        return $collection->reset();
    }

    /**
     * Apply the model options to the query builder.
     *
     * @param QueryBuilder $qb
     * @param array        $options
     */
    protected static function applyOptionsToQueryBuilder(QueryBuilder $qb, array $options)
    {
        // Columns
        if (null !== $options['column']) {
            if (is_array($options['column'])) {
                foreach ($options['column'] as $column) {
                    $qb->andWhere($column);
                }
            } else {
                // Default is likely fallback table
                $table = static::getTable();
                $qb->andWhere("$table.{$options['column']}=?");
            }
        }

        // Group by
        if (null !== $options['group']) {
            $qb->groupBy($options['group']);
        }

        // Having
        if (null !== $options['having']) {
            $qb->having($options['having']);
        }

        // Order by
        if (null !== $options['order']) {
            $qb->add('orderBy', $options['order']);
        }
    }

    /**
     * Get the MultilingualQueryBuilder.
     *
     * @return \Terminal42\DcMultilingualBundle\QueryBuilder\MultilingualQueryBuilderInterface
     */
    protected static function getMultilingualQueryBuilder()
    {
        /** @var MultilingualQueryBuilderFactoryInterface $factory */
        $factory = \System::getContainer()->get('terminal42.dc_multilingual.querybuilder_factory');

        return $factory->build(
            static::getTable(),
            static::getPidColumn(),
            static::getLangColumn(),
            static::getRegularFields(),
            static::getTranslatableFields()
        );
    }

    /**
     * Get the regular fields.
     *
     * @return array
     */
    protected static function getRegularFields()
    {
        $extractor    = \DcaExtractor::getInstance(static::getTable());
        $tableColumns = Database::getInstance()->getFieldNames(static::getTable());

        return array_intersect($tableColumns, array_keys($extractor->getFields()));
    }

    /**
     * Get the fields that are translatable.
     *
     * @return array
     */
    protected static function getTranslatableFields()
    {
        static::ensureDataContainerIsLoaded();

        $fields       = [];
        $tableColumns = Database::getInstance()->getFieldNames(static::getTable());

        foreach ($GLOBALS['TL_DCA'][static::getTable()]['fields'] as $field => $data) {
            if (!isset($data['eval']['translatableFor']) || !in_array($field, $tableColumns, true)) {
                continue;
            }

            $fields[] = $field;
        }

        return $fields;
    }

    /**
     * Get the PID column.
     *
     * @return string
     */
    protected static function getPidColumn()
    {
        static::ensureDataContainerIsLoaded();

        if ($GLOBALS['TL_DCA'][static::getTable()]['config']['langPid']) {
            return $GLOBALS['TL_DCA'][static::getTable()]['config']['langPid'];
        }

        return 'langPid';
    }

    /**
     * Ensure the data container is loaded.
     */
    protected static function ensureDataContainerIsLoaded()
    {
        if (!isset($GLOBALS['TL_DCA'][static::getTable()])) {
            $loader = new \DcaLoader(static::getTable());
            $loader->load();
        }
    }
}
