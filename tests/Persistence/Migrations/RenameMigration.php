<?php
namespace Lhm\Tests\Persistence\Migrations;

use Lhm\Lhm;
use Phinx\Db\Table;
use Phinx\Migration\AbstractMigration;

class RenameMigration extends AbstractMigration
{
    public function up()
    {
        Lhm::setAdapter($this->getAdapter());
        Lhm::changeTable('ponies', function (Table $ponies) {
            $ponies
                ->renameColumn('name', 'first_name')
                ->save();
        });
    }
}
