<?php
namespace Lhm\Tests\Persistence\Migrations;

use Phinx\Migration\AbstractMigration;

class InitialMigration extends AbstractMigration
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
        $ponies = $this->table('ponies');
        $ponies
            ->addColumn('name', 'string', ['null' => true, 'length' => 255])
            ->save();

        $max = count($this->names) - 1;

        for ($i = 0; $i < 100; $i++) {
            $name = $this->names[rand(0, $max)];
            $this->query("INSERT INTO ponies(name) VALUES ('{$name}')");
        }
    }

    public function down()
    {

    }
}
