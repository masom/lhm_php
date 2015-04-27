<?php

namespace Lhm;

use Phinx\Db\Table;
use Phinx\Migration\MigrationInterface;


class Invoker extends Command {

    const LOCK_WAIT_TIMEOUT_DELTA = -2;

    /**
     * @var MigrationInterface
     */
    protected $migration;

    /**
     * @var Table
     */
    protected $origin;

    /**
     * @var Table
     */
    protected $destination;

    /**
     * @var array
     */
    protected $options;

    /**
     * @param MigrationInterface $migration
     * @param Table $origin
     * @param Table $destination
     * @param array $options
     */
    public function __construct(MigrationInterface $migration, Table $origin, array $options = []) {
        $this->options = $options + ['entangler' => true,  'temporary_table_suffix' => '_new' ];

        $this->migration = $migration;
        $this->origin = $origin;
    }

    /**
     * @param callable $migration Closure that receives the table to operate on.
     *
     *  <example>
     *  $migration->execute(function($table) {
     *      $table
     *          ->removeColumn('name')
     *          ->save();
     *  });
     *  </example>
     */
    public function execute(callable $migration) {
        if (!$this->options['entangler']) {
            $migration($this->origin);
            return;
        }

        if (!$this->destination) {
            $this->destination = $this->temporaryTable();
        }

        $adapter = $this->migration->getAdapter();

        $this->setSessionLockWaitTimeouts();

        $entangler = new Entangler($adapter, $this->origin, $this->destination);
        $switcher = new AtomicRenamer($adapter, $this->origin, $this->destination);
        $chunker = new Chunker($adapter, $this->origin, $this->destination);

        $entangler->run(function() use ($chunker, $switcher) {
            $chunker->run();
            $switcher->run();
        });

    }

    public function setSessionLockWaitTimeouts() {
        $globalInnodbLockWaitTimeout = $this->adapter->query("SHOW GLOBAL VARIABLES LIKE 'innodb_lock_wait_timeout'")[0];
        $globalLockWaitTimeout = $this->adapter->query("SHOW GLOBAL VARIABLES LIKE 'lock_wait_timeout'");

        if ($globalInnodbLockWaitTimeout) {
            $value = ((int)$globalInnodbLockWaitTimeout) + static::LOCK_WAIT_TIMEOUT_DELTA;
            $this->adapter->query("SET SESSION innodb_lock_wait_timeout={$value}");
        }

        if ($globalLockWaitTimeout){
            $value = ((int) globalLockWaitTimeout) + static::LOCK_WAIT_TIMEOUT_DELTA;
            $this->adapter->query("SET SESSION lock_wait_timeout={$value}");
        }
    }

    /**
     * Create/Get the temporary table.
     *
     * @return Table
     */
    public function temporaryTable() {

        if ($this->destination) {
            return $this->destination;
        }

        $adapter = $this->migration->getAdapter();

        $temporaryTableName = $this->temporaryTableName();

        if ($adapter->hasTable($temporaryTableName)) {
            throw new \TemporaryTableExistsError("The table `{$temporaryTableName}` already exists.");
        }

        $adapter->query("CREATE TABLE {$temporaryTableName} LIKE {$this->origin->getName()}");

        return $this->migration->table($temporaryTableName);
    }

    /**
     * Returns the temporary table name.
     * @return string
     */
    public function temporaryTableName() {
        return "{$this->origin->getName()}{$this->options['temporary_table_suffix']}";
    }
}
