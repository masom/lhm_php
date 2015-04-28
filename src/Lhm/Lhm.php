<?php


namespace Lhm;


use Phinx\Migration\MigrationInterface;

class Lhm
{
    public static function changeTable(MigrationInterface $migration, $name, callable $operations, array $options = [])
    {
        $invoker = new Invoker($migration, $migration->table($name, []), $options);
        $invoker->execute($operations);
    }
}
