# Database Migration

Pour cakePHP 1.3

Plugin allowing to synchronize data from models between many instance of CakePHP

## Installation

* Put the content of this plugin in "app/plugins/" in a folder named "migration".
* Ex�cuter les commandes SQL de "database.sql" dans la base de donn�e MySql.
* Run the following to generate the config file :
  ```sh
  php cake/console/cake.php install_migration
  ```

## Configuration

See libs/migration_config.php and  models/behaviors/migration.php
  
## todo

* ~~Overridable fields~~
* ~~On demand entry sync~~
* ~~Relations~~
* ~~Files~~
* ~~Set a global sync option~~
* ~~diff viewer~~
* ~~check invalidated entries at the end of a full synch~~
* ~~handle deleted entries~~
* "create on map conflict" option
* handle many files with the same name
* console

## Developpement

js and css must be compiled
```sh
cd app/plugins/migration
coffee -wmo webroot/js webroot/js/src
sass --sourcemap --watch webroot/sass:webroot/css
```