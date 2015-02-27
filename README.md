# o2cms Database Migration

Pour cakePHP 1.3

## Installation

* Placer le dossier "migration" dans "app/plugins/".</li>
* Exécuter les commandes SQL de "database.sql" dans la base de donnée MySql.</li>

## todo

* ~~Overridable fields~~
* ~~On demand entry sync~~
* ~~Relations~~
* ~~Files~~
* ~~Set a global sync option~~
* ~~diff viewer~~
* ~~check invalidated entries at the end of a full synch~~
* handle deleted entries
* create on map conflict opt
* handle many files with the same name
* console

## Developpement

js and css must be compiled
```sh
cd app/plugins/migration
coffee -wmo webroot/js webroot/js/src
sass --sourcemap --watch webroot/sass:webroot/css
```