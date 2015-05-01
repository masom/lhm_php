[![Build Status](https://travis-ci.org/masom/lhm_php.svg)](https://travis-ci.org/masom/lhm_php)

# Large Hadron Migrator
[Phinx](https://github.com/robmorgan/phinx) meets [LHM](https://github.com/soundcloud/lhm)


This is a PHP port of https://github.com/soundcloud/lhm

### TODO
- [ ] [SqlHelper] Support column renames
- [x] [LHM] Support cleanup
- [ ] [Chunker] data limits/filtering
- [ ] [Chunker] throttle
- [ ] [Switchers] Locked switcher
- [ ] [LHM] Update LHM api to match soundcloud/lhm ( `options`, breaking )
- [ ] add index / remove index

### Usage
```php
<?php

use Phinx\Migration\AbstractMigration;


class DropLargeColumns extends AbstractMigration
{
    /**
     * Migrate Up.
     */
    public function up()
    {
        /**
         * Uncomment this to see logs.
         * $logger = new Monolog\Logger('test', [new \Monolog\Handler\StreamHandler('php://stdout')]);
         * \Lhm\Lhm::setLogger($logger);
         */
        \Lhm\Lhm::setAdapter($this->getAdapter());
        \Lhm\Lhm::changeTable('characters', function (Phinx\Db\Table $table) {
            $table
                ->removeColumn('alternate_name')
                ->removeColumn('alternate_bio')
                ->removeColumn('alternate_storyline')
                ->save();
        });
    }

    /**
     * Migrate Down.
     */
    public function down()
    {
        \Lhm\Lhm::setAdapter($this->getAdapter());
        \Lhm\Lhm::changeTable('characters', function (Phinx\Db\Table $table) {
            $table
                ->addColumn('alternate_name', 'string', ['limit' => 255, 'null' => true, 'default' => null])
                ->addColumn('alternate_bio', 'string', ['limit' => 255, 'null' => true, 'default' => null])
                ->addColumn('alternate_storyline', 'string', ['limit' => 255, 'null' => true, 'default' => null])
                ->save();
        });
    }
}
```

### Cleanup
If there is an error while LHM is running a few triggers and tables might be left in the database.

`bin/lhm` command will let you clean your database.

It executes a dry-run unless the `--run` option is specified.


```
Large Hadron Migrator version 0.3.0

Usage:
 cleanup [-c|--configuration="..."] [-p|--parser="..."] [-e|--environment="..."] [-r|--run] [-u|--until="..."]

Options:
 --configuration (-c)  The configuration file to load
 --parser (-p)         Parser used to read the config file. Defaults to YAML
 --environment (-e)    The target environment
 --run (-r)            Apply the cleanup operations.
 --until (-u)          Drop archive tables older than the specified date at UTC (YYYY-MM-DD_hh:mm:ss).
 --help (-h)           Display this help message
 --quiet (-q)          Do not output any message

Help:

 Cleanup LHM tables, old archives and triggers. Defaults to a dry-run unless --run is specified.
```

### AWS Considerations

Amazon RDS disables `log_bin_trust_function_creators` by default.

See https://github.com/soundcloud/lhm/issues/76 and https://github.com/soundcloud/lhm/issues/65

###### If your database instance is running a custom parameter group:

1. Open the RDS web console.
2. Open the “Parameter Groups” tab.
3. Create a new Parameter Group. On the dialog, select the MySQL family compatible to your MySQL database version, give it a name and confirm.
4. Select the just created Parameter Group and issue “Edit Parameters”.
5. Look for the parameter ‘log_bin_trust_function_creators’ and set its value to ‘1’.
6. Save the changes.

The changes to the parameter group will be applied immediately.

###### If your database instance is running on the default parameter group:


1. Open the RDS web console.
2. Open the “Parameter Groups” tab.
3. Create a new Parameter Group. On the dialog, select the MySQL family compatible to your MySQL database version, give it a name and confirm.
4. Select the just created Parameter Group and issue “Edit Parameters”.
5. Look for the parameter ‘log_bin_trust_function_creators’ and set its value to ‘1’.
6. Save the changes.
7. Open the “Instances” tab. Expand your MySQL instance and issue the “Instance Action” named “Modify”.
8. Select the just created Parameter Group and enable “Apply Immediately”.
9. Click on “Continue” and confirm the changes.
10. Open the “Instances” tab. Expand your MySQL instance and issue the “Instance Action” named “Reboot”.

source: https://techtavern.wordpress.com/2013/06/17/mysql-triggers-and-amazon-rds/
