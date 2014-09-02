DC_Multilingual
===============

This is a standalone DC driver for contao that allows you to easily make your data translatable.

Contributors:

* Yanick Witschi <yanick.witschi@terminal42.ch>
* Andreas Schempp <andreas.schempp@terminal42.ch>
* Kamil Kuzminski <kamil.kuzminski@codefog.pl>
* weyert, psi-4ward, andreasisaak

## DCA configuration

```php
// Set the driver
$GLOBALS['TL_DCA']['table']['config']['dataContainer'] = 'Multilingual';

// Languages you want to provide for translation
$GLOBALS['TL_DCA']['table']['config']['languages'] = array('en', 'de', 'pl');

// Database column that contains the language keys (default: "language")
$GLOBALS['TL_DCA']['table']['config']['langColumn'] = 'language';

// Database column that contains the reference id (default: "langPid")
$GLOBALS['TL_DCA']['table']['config']['langPid'] = 'langPid';

// Fallback language - if none is given then there will be another language "fallback" selectable from the dropdown
$GLOBALS['TL_DCA']['table']['config']['fallbackLang'] = 'en';

// Use '*' to make a field translatable for all languages
$GLOBALS['TL_DCA']['table']['fields']['username']['eval']['translatableFor'] = '*';

// Use an array of language keys to specify for which languages the field is translatable
$GLOBALS['TL_DCA']['table']['fields']['name']['eval']['translatableFor'] = array('de');

// Note:
// If you don't use ['eval']['translatableFor'] and the user is not editing the fallback language, then the field will be hidden for all the languages
```

## Example usage

```php
// Update tl_user configuration
$GLOBALS['TL_DCA']['tl_user']['config']['dataContainer'] = 'Multilingual';
$GLOBALS['TL_DCA']['tl_user']['config']['languages'] = array('en', 'de', 'pl');
$GLOBALS['TL_DCA']['tl_user']['config']['langPid'] = 'langPid';
$GLOBALS['TL_DCA']['tl_user']['config']['langColumn'] = 'language';
$GLOBALS['TL_DCA']['tl_user']['config']['fallbackLang'] = 'en';

// Add the language fields
$GLOBALS['TL_DCA']['tl_user']['config']['sql']['keys']['langPid'] = 'index';
$GLOBALS['TL_DCA']['tl_user']['config']['sql']['keys']['language'] = 'index';
$GLOBALS['TL_DCA']['tl_user']['fields']['langPid']['sql'] = "int(10) unsigned NOT NULL default '0'";
$GLOBALS['TL_DCA']['tl_user']['fields']['language']['sql'] = "varchar(2) NOT NULL default ''";

// Make some fields translatable
$GLOBALS['TL_DCA']['tl_user']['fields']['username']['eval']['translatableFor'] = '*';
$GLOBALS['TL_DCA']['tl_user']['fields']['name']['eval']['translatableFor'] = array('de');
```

## Querying using the model

```php
class UserModel extends \MultilingualModel
{
	protected static $strTable = 'tl_user';

	public function findActive()
	{
		$t = static::$strTable;
		$arrColumns = array("$t.disable=''");
		return static::findBy($arrColumns, null);
	}
}
```

## Querying using the DC_Multilingual_Query builder (old)

```php
$objQuery = new DC_Multilingual_Query('table');

// The base query as string.
// You see the table-alias t1 for the base-fields
// and t2 for the language-fields.
echo $objQuery->getQuery();

// Probably you would change the language, default is $GLOBALS['TL_LANGUAGE']
$objQuery->language = 'en';

// Add some conditions
$objQuery->addField('CONCAT(t2.firstname," ",t2.lastname") AS fullname')
         ->addField('joinedTable.anotherField')
		 ->addJoin('LEFT JOIN joinedTable ON (t1.joinedID = joinedTable.id)');

// Some WHERE and ORDER conditions
$objQuery->addWhere('t1.published="1"')->addOrder('t2.lastname');

echo $objQuery->getQuery();

// Or get the Database_Statement instance
$objStatement = $objQuery->getStatement();

$objResult = $objQuery->getStatement()->execute();
```
