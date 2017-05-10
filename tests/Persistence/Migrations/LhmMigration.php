<?php
namespace Lhm\Tests\Persistence\Migrations;

use Lhm\Lhm;
use Phinx\Db\Table;
use Phinx\Migration\AbstractMigration;

class LhmMigration extends AbstractMigration
{
    public function up()
    {
        Lhm::setAdapter($this->getAdapter());
        Lhm::changeTable('ponies', function (Table $ponies) {
            $ponies->addColumn('age', 'integer', ['default' => 1]);
            $ponies->addColumn('nickname', 'string', ['after' => 'name', 'length' => 20, 'null' => true, 'default' => 'derp']);
            $ponies->save();
        });
    }

    public function down()
    {
    }


}
