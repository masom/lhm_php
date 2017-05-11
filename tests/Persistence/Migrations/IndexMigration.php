<?php
namespace Lhm\Tests\Persistence\Migrations;

use Lhm\Lhm;
use Phinx\Db\Table;
use Phinx\Migration\AbstractMigration;

class IndexMigration extends AbstractMigration
{

    public function up()
    {
        Lhm::setAdapter($this->getAdapter());
        Lhm::changeTable('ponies', function (Table $ponies) {
            $ponies
                ->addColumn('age', 'integer', ['null' => true])
                ->addIndex('age', ['name' => 'ponies_age_idx'])
                ->save();
        });
    }

    public function down()
    {
        Lhm::setAdapter($this->getAdapter());
        Lhm::changeTable('ponies', function (Table $ponies) {
            $ponies
                ->removeIndex('age', ['name' => 'ponies_age_idx'])
                ->removeColumn('age', 'integer', ['null' => true])
                ->save();
        }, ['archive_name' => 'drop_the_ponies_age']);
    }
}
