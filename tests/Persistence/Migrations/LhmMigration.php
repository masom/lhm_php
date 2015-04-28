<?php


namespace Lhm\Tests\Persistence\Migrations;

use Lhm\Lhm;
use Phinx\Db\Table;
use Phinx\Migration\AbstractMigration;

class LhmMigration extends AbstractMigration
{
    protected $names = [
        'Applejack',
        'Pinkie Pie',
        'Aloe',
        'Cheerilee',
        'Cherry Jubilee',
        'Coco Pommel',
        'Granny Smith',
        'Fluttershy',
        'Rainbow Dash',
        'Blossomforth',
        'Bulk Biceps'
    ];

    public function up()
    {
        $max = count($this->names) - 1;

        for ($i = 0; $i < 100; $i++) {
            $name = $this->names[rand(0, $max)];
            $this->query("INSERT INTO ponies(name) VALUES ('{$name}')");
        }

        Lhm::changeTable($this, 'ponies', function (Table $ponies) {
            $ponies->addColumn('age', 'integer', ['default' => 1]);
            $ponies->addColumn('nickname', 'string', ['after' => 'name', 'length' => 20, 'null' => true]);
            $ponies->save();
        });
    }

    public function down()
    {
    }


}
