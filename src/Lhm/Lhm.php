<?php


namespace Lhm;


use Phinx\Migration\MigrationInterface;
use Psr\Log\LoggerInterface;

class Lhm
{
    /** @var LoggerInterface */
    protected static $logger;

    public static function setLogger(LoggerInterface $logger)
    {
        static::$logger = $logger;
    }

    /**
     * @return LoggerInterface
     */
    public static function getLogger()
    {
        return static::$logger ?: new NullLogger();
    }

    public static function changeTable(MigrationInterface $migration, $name, callable $operations, array $options = [])
    {
        $invoker = new Invoker($migration, $migration->table($name, []), $options);
        $invoker->setLogger(static::getLogger());
        $invoker->execute($operations);
    }
}
