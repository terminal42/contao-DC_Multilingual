Changelog
=========

See also the [GitHub releases page](https://github.com/terminal42/contao-DC_Multilingual/releases).

4.0.0
-----

* Added the changelog ;-) (#56)
* Raised minimum PHP version to 5.6.
* Raised minimum Contao version to 4.4.
* **BC break:** Removed the deprecated `pidColumn` configuration. Use `langPid` instead.
* Multilingual aliases have been improved, so language records can have the same alias as main record. (#47)
* The models are not prevented from saving by default anymore. They are only prevented if fetched from database. (#51)
* Allow to count records using subqueries, e.g. with `HAVING` clause.
