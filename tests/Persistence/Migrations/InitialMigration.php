<?php


namespace Lhm\Tests\Persistence\Migrations;


use Phinx\Migration\AbstractMigration;

class InitialMigration extends AbstractMigration
{
    public function up()
    {
        $ponies = $this->table('ponies');
        $ponies
            ->addColumn('name', 'string', ['null' => true, 'length' => 255])
            ->save();
    }

    public function down()
    {

    }
}
