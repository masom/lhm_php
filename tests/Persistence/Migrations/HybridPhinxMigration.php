<?php
namespace Lhm\Tests\Persistence\Migrations;

use Lhm\Lhm;
use Phinx\Db\Table;
use Phinx\Migration\AbstractMigration;

class HybridPhinxMigration extends AbstractMigration
{
    public function up()
    {
        $ponies = $this->table('ponies');
        $ponies
            ->addColumn('location', 'string', ['null' => true, 'length' => 255, 'default' => 'Canada'])
            ->save();

        Lhm::setAdapter($this->getAdapter());
        Lhm::changeTable('ponies', function (Table $ponies) {
            $ponies
                ->addColumn('age', 'integer', ['null' => true])
                ->save();
        });
    }

    public function down()
    {
    }
}
