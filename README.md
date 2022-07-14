DC_Multilingual
===============

This is a standalone DC driver for Contao Open Source CMS that allows you to easily make your data translatable.

## DCA configuration

```php
// Set the driver
$GLOBALS['TL_DCA']['table']['config']['dataContainer'] = 'Multilingual';

// Languages you want to provide for translation (default: Languages of all root pages)
$GLOBALS['TL_DCA']['table']['config']['languages'] = ['en', 'de', 'pl'];

// Database column that contains the language keys (default: "language")
$GLOBALS['TL_DCA']['table']['config']['langColumnName'] = 'language';

// Database column that contains the reference id (default: "langPid")
$GLOBALS['TL_DCA']['table']['config']['langPid'] = 'langPid';

// Fallback language - if none is given then there will be another language "fallback" selectable from the dropdown
$GLOBALS['TL_DCA']['table']['config']['fallbackLang'] = 'en';

// Use '*' to make a field translatable for all languages
$GLOBALS['TL_DCA']['table']['fields']['username']['eval']['translatableFor'] = '*';

// Use an array of language keys to specify for which languages the field is translatable
$GLOBALS['TL_DCA']['table']['fields']['name']['eval']['translatableFor'] = ['de'];

// Note:
// If you don't use ['eval']['translatableFor'] and the user is not editing the fallback language, then the field will be hidden for all the languages
```

## Example usage

```php
// Update tl_news configuration
$GLOBALS['TL_DCA']['tl_news']['config']['dataContainer'] = 'Multilingual';
$GLOBALS['TL_DCA']['tl_news']['config']['languages'] = ['en', 'de', 'pl'];
$GLOBALS['TL_DCA']['tl_news']['config']['langPid'] = 'langPid';
$GLOBALS['TL_DCA']['tl_news']['config']['langColumnName'] = 'language';
$GLOBALS['TL_DCA']['tl_news']['config']['fallbackLang'] = 'en';

// Add the language fields
$GLOBALS['TL_DCA']['tl_news']['config']['sql']['keys']['langPid'] = 'index';
$GLOBALS['TL_DCA']['tl_news']['config']['sql']['keys']['language'] = 'index';
$GLOBALS['TL_DCA']['tl_news']['fields']['langPid']['sql'] = "int(10) unsigned NOT NULL default '0'";
$GLOBALS['TL_DCA']['tl_news']['fields']['language']['sql'] = "varchar(2) NOT NULL default ''";

// Make some fields translatable
$GLOBALS['TL_DCA']['tl_news']['fields']['headline']['eval']['translatableFor'] = '*';
$GLOBALS['TL_DCA']['tl_news']['fields']['subheadline']['eval']['translatableFor'] = ['de'];
```

## Querying using the model

```php
class NewsModel extends Terminal42\DcMultilingualBundle\Model\Multilingual
{
    protected static $strTable = 'tl_news';

    public static function findPublished()
    {
        return static::findBy(['t1.published=?'], [1]);
    }
}
```

## How does it work under the hood

Basically, the driver just stores translations into the same table, building up
a relationship to its parent entry using the "langPid" (or whatever you
configured it to be) column. In the back end list and tree view it makes sure
translations are filtered so you only see the fallback language there.
When querying using the `Multilingual` model or using the
`MultilingualQueryBuilder`, the same table is simply joined so we have the
fallback language aliased as `t1` and the target language (which you specify
 explicitly or it uses the current page's language) aliased as `translation`. Now, using
 MySQL's `IFNULL()` function, it checks whether there's a translated value and
 if not, automatically falls back to the fallback language. This allows you to
 translate only a subset of fields.


## Alias handling

You can share the alias for all translations, so you'd have something like this:

    * EN: domain.com/my-post/my-beautiful-alias.html
    * DE: domain.de/mein-artikel/my-beautiful-alias.html
    * FR: domain.fr/mon-post/my-beautiful-alias.html

This can be achieved by using the regular alias handling you may know from
other modules such as news etc. in the back end and for the front end you simply
use the `findByAlias()` method which the `Multilingual` model provides:

```php
MyModel::findByAlias($alias);
```

However, there are many situations where you would like to have your aliases
translated so you end up with something like this:

    * EN: domain.com/my-post/my-beautiful-alias.html
    * DE: domain.de/mein-artikel/mein-wunderschoenes-alias.html
    * FR: domain.fr/mon-post/mon-alias-magnifique.html

In the back end it's slightly more difficult now because it does not make sense
to check for duplicate aliases within the whole table but only within the whole
table **and** the same language. To make this as easy as possible for you, simply
use the following `eval` definitions on your `alias` field:

```php
'eval'      => [
    'maxlength'                 => 255,
    'rgxp'                      => 'alias',
    'translatableFor'           => '*',
    'isMultilingualAlias'       => true,
    'generateAliasFromField'    => 'title' // optional ("title" is default)
],
```

It will automatically generate an alias if not present yet and check for
duplicates within the same language.

In the front end you can then search by a multilingual alias like this:

```php
MyModel::findByMultilingualAlias($alias);
```


## Usage with Doctrine entities

Since the driver only writes data to the database from the backend, it is fully
compatible with using Doctrine entities in the frontend. For now, you must take care
of translations yourself though. Here's how an entity could look like:

```php
#[Entity()]
#[Table('tl_my_entity')]
class MyEntity
{
    #[Id]
    #[GeneratedValue('IDENTITY')]
    #[Column(options: ['unsigned' => true])]
    private int $id;
    #[Column(options: ['unsigned' => true, 'default' => 0])]
    private int $tstamp;

    #[OneToMany('parent', self::class)]
    protected $translations;
    #[ManyToOne(self::class, inversedBy: 'translations')]
    #[JoinColumn('langPid')]
    protected $parent;
    #[Column(length: 5, options: ['default' => ''])]
    protected string $language;

    // ... any other properties of your entity
}
```


## Useful notes

1. Sometimes a table you want to make multilingual already contains the `language` field (e.g. `tl_user`),
which may lead to unexpected results. In such cases you have to make sure that data container's property
`$GLOBALS['TL_DCA']['tl_table']['config']['langColumnName']` is set to something else than `language`.
See [#53](https://github.com/terminal42/contao-DC_Multilingual/issues/53) for more details.
