<?php


namespace Lhm;


use Phinx\Db\Adapter\AdapterInterface;
use Psr\Log\LoggerInterface;

class Lhm
{
    /** @var LoggerInterface */
    protected static $logger;

    /**
     * @var AdapterInterface
     */
    protected static $adapter;

    /**
     * @param LoggerInterface $logger
     */
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

    /**
     * @return AdapterInterface
     */
    public static function getAdapter()
    {
        return self::$adapter;
    }

    /**
     * @param AdapterInterface $adapter
     */
    public static function setAdapter(AdapterInterface $adapter)
    {
        self::$adapter = $adapter;
    }

    /**
     * @param $name
     * @param callable $operations
     * @param array $options
     *                      - `stride` integer
     *                          Size of a chunk (defaults to 2000)
     *                      - `atomic_switch` boolean
     *                          Enable atomic switching (defaults to true)
     *                      - `retry_sleep_time` integer
     *                          How long should the switch wait until retrying ( defaults to 10 )
     *                      - `max_retries` integer
     *                          How many times the switch should be attempted ( defaults to 600 )
     *                      - `archive_name` string
     *                          Name of the archive table ( defaults to 'lhma_' . gmdate('Y_m_d_H_i_s') . "_{$origin->getName()}" )
     *
     */
    public static function changeTable($name, callable $operations, array $options = [])
    {
        if (!static::getAdapter()) {
            throw new \RuntimeException(__CLASS__ . ' must have an adapter set. Call ' . __CLASS__ . '::setAdapter()');
        }

        $invoker = new Invoker(static::$adapter, new \Phinx\Db\Table($name, [], static::getAdapter()), $options);
        $invoker->setLogger(static::getLogger());
        $invoker->execute($operations);
    }

    /**
     * Cleanup LHM temporary tables, old archives and triggers.
     *
     * @param bool $run When set to `false` the cleanup operations will not be executed. ( dry-run )
     * @param array $options
     *                      - `until` \DateTime Archives older than this date will be deleted.
     *
     * @return bool
     */
    public static function cleanup($run = false, array $options = [])
    {
        if (!static::getAdapter()) {
            throw new \RuntimeException(__CLASS__ . ' must have an adapter. Call ' . __CLASS__ . '::setAdapter()');
        }

        $logger = static::getLogger();

        $options += ['until' => false];

        if ($options['until'] && !($options['until'] instanceof \DateTime)) {
            throw new \UnexpectedValueException('The `until` option must be an instance of \DateTime');
        }

        /** @var \PDOStatement $statement */
        $statement = static::getAdapter()->query('show tables');

        $lhmTables = [];
        while (($table = $statement->fetchColumn(0)) !== false) {
            if (!preg_match('/^lhm(a|n)_/', $table)) {
                continue;
            }
            $lhmTables[] = $table;
        }

        if ($options['until']) {
            $tablesToClean = [];

            foreach ($lhmTables as $table) {
                if (!preg_match("/^lhma_([0-9]{4}_[0-9]{2}_[0-9]{2}_[0-9]{2}_[0-9]{2}_[0-9]{2})_/", $table, $matches)) {
                    continue;
                }

                $dateTime = \DateTime::createFromFormat('Y_m_d_H_i_s', $matches[1], new \DateTimeZone("UTC"));

                if ($dateTime <= $options['until']) {
                    $tablesToClean[] = $table;
                }
            }

            unset($table);
            $lhmTables = $tablesToClean;
        }


        $lhmTriggers = [];
        /** @var \PDOStatement $statement */
        $statement = static::getAdapter()->query('show triggers');
        while (($trigger = $statement->fetchColumn(0)) !== false) {
            if (!preg_match('/^lhmt/', $trigger)) {
                continue;
            }
            $lhmTriggers[] = $trigger;
        }
        unset($trigger);

        if (empty($lhmTables) && empty($lhmTriggers)) {
            $logger->info("Everything is clean. Nothing to do.");
            return true;
        }

        if ($run) {
            $adapter = static::$adapter;

            foreach ($lhmTriggers as $trigger) {
                $logger->info("Dropping trigger `{$trigger}`");
                $adapter->query("DROP TRIGGER IF EXISTS {$trigger}");
            }

            foreach ($lhmTables as $table) {
                $logger->info("Dropping table `{$table}`");

                $table = $adapter->quoteTableName($table);
                $adapter->query("DROP TABLE IF EXISTS {$table}");
            }

            return true;
        } else {
            $tables = implode(", ", $lhmTables);
            $triggers = implode(", ", $lhmTriggers);
            $logger->info("Existing LHM backup tables: {$tables}");
            $logger->info("Existing LHM triggers: {$triggers}");
            $logger->info('Run Lhm::cleanup(true) to drop them all.');
            return false;
        }
    }
}
