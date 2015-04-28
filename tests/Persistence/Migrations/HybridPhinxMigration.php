<?php


namespace Lhm\Tests\Persistence\Migrations;


use Lhm\Invoker;
use Lhm\Lhm;
use Phinx\Db\Table;
use Phinx\Migration\AbstractMigration;

class HybridPhinxMigration extends AbstractMigration
{
    public function up()
    {

        $ponies = $this->table('ponies');
        $ponies
            ->addColumn('name', 'string', ['null' => true, 'length' => 255])
            ->addColumn('location', 'string', ['null' => true, 'length' => 255])
            ->save();

        Lhm::changeTable($this, 'ponies', function (Table $ponies) {
            $ponies
                ->removeColumn('location')
                ->addColumn('age', 'integer', ['null' => true])
                ->save();
        });
    }

    public function down()
    {
    }
}
