[![Build Status](https://travis-ci.org/masom/lhm_php.svg)](https://travis-ci.org/masom/lhm_php)

# Large Hadron Migrator
[Phinx](https://github.com/robmorgan/phinx) meets [LHM](https://github.com/soundcloud/lhm)


This is a PHP port of https://github.com/soundcloud/lhm


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
        \Lhm\Lhm::changeTable($this, 'characters', function (Phinx\Db\Table $table) {
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
        \Lhm\Lhm::changeTable($this, 'characters', function (Phinx\Db\Table $table) {
            $table
                ->addColumn('alternate_name', 'string', ['limit' => 255, 'null' => true, 'default' => null])
                ->addColumn('alternate_bio', 'string', ['limit' => 255, 'null' => true, 'default' => null])
                ->addColumn('alternate_storyline', 'string', ['limit' => 255, 'null' => true, 'default' => null])
                ->save();
        });
    }
}
```
