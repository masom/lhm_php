<?php

namespace Lhm;

use Phinx\Db\Table;
use Phinx\Db\Adapter\AdapterInterface;

/**
 * Entangler links the origin table to the destination.
 * INSERT, UPDATE and DELETE statements executed on the origin table will be duplicated to the destination.
 */
class Entangler extends Command
{

    /**
     * @var Table
     */
    protected $origin;

    /**
     * @var Table
     */
    protected $destination;

    /**
     * @var AdapterInterface
     */
    protected $adapter;

    /**
     * @var SqlHelper
     */
    protected $sqlHelper;


    /**
     * @param AdapterInterface $adapter
     * @param Table $origin
     * @param Table $destination
     * @param SqlHelper $sqlHelper
     */
    public function __construct(AdapterInterface $adapter, Table $origin, Table $destination, SqlHelper $sqlHelper = null)
    {
        $this->adapter = $adapter;
        $this->origin = $origin;
        $this->destination = $destination;
        $this->sqlHelper = $sqlHelper ?: new SqlHelper($this->adapter);
    }

    /**
     * Executes required before migraiton statements
     */
    protected function before()
    {
        foreach ($this->entangle() as $statement) {
            $this->adapter->query($this->sqlHelper->tagged($statement));
        }
    }

    /**
     * Executes required after migration statements
     */
    protected function after()
    {
        foreach ($this->untangle() as $statement) {
            $this->adapter->query($this->sqlHelper->tagged($statement));
        }
    }

    protected function revert()
    {
        $this->after();
    }

    /**
     * @return array
     */
    protected function entangle()
    {
        return [
            $this->createDeleteTrigger(),
            $this->createInsertTrigger(),
            $this->createUpdateTrigger(),
        ];
    }

    /**
     * @return array
     */
    protected function untangle()
    {
        return [
            "DROP TRIGGER IF EXISTS {$this->trigger('delete')}",
            "DROP TRIGGER IF EXISTS {$this->trigger('insert')}",
            "DROP TRIGGER IF EXISTS {$this->trigger('update')}",
        ];
    }

    /**
     * @return string
     */
    protected function createInsertTrigger()
    {
        $intersectionColumns = $this->sqlHelper->quotedIntersectionColumns($this->origin, $this->destination);
        $destinationColumns = implode(',', $intersectionColumns);

        $originColumns = implode(',', $this->sqlHelper->typedColumns('NEW', $intersectionColumns));

        $name = $this->trigger('insert');

        return implode("\n ", [
            "CREATE TRIGGER {$name}",
            "AFTER INSERT ON {$this->origin->getName()} FOR EACH ROW",
            "REPLACE INTO {$this->destination->getName()} ({$destinationColumns}) {$this->sqlHelper->annotation()}",
            "VALUES ({$originColumns})"
        ]);
    }

    /**
     * @return string
     */
    protected function createUpdateTrigger()
    {
        $intersectionColumns = $this->sqlHelper->quotedIntersectionColumns($this->origin, $this->destination);
        $destinationColumns = implode(',', $intersectionColumns);

        $originColumns = implode(',', $this->sqlHelper->typedColumns('NEW', $intersectionColumns));

        $name = $this->trigger('update');

        return implode("\n ", [
            "CREATE TRIGGER {$name}",
            "AFTER UPDATE ON {$this->origin->getName()} FOR EACH ROW",
            "REPLACE INTO {$this->destination->getName()} ({$destinationColumns}) {$this->sqlHelper->annotation()}",
            "VALUES ({$originColumns})"
        ]);
    }

    /**
     * @return string
     */
    protected function createDeleteTrigger()
    {
        $name = $this->trigger('delete');

        $primaryKey = $this->origin->getOptions()['primary_key'];

        return implode("\n ", [
            "CREATE TRIGGER {$name}",
            "AFTER DELETE ON {$this->origin->getName()} FOR EACH ROW",
            "DELETE IGNORE FROM {$this->destination->getName()} {$this->sqlHelper->annotation()}",
            "WHERE {$this->destination->getName()}.{$primaryKey} = OLD.{$primaryKey}"
        ]);
    }

    /**
     * @throws \RuntimeException
     */
    protected function validate()
    {
        if (!$this->adapter->hasTable($this->origin->getName())) {
            throw new \RuntimeException("Table `{$this->origin->getName()}` does not exists.");
        }

        if (!$this->adapter->hasTable($this->destination->getName())) {
            throw new \RuntimeException("Table `{$this->destination->getName()}` does not exists.");
        }
    }

    /**
     * @param string $type
     * @return string
     */
    protected function trigger($type)
    {
        return "lhmt_{$type}_{$this->origin->getName()}";
    }
}
