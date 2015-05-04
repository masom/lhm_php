<?php

namespace Lhm;

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
     * @var Intersection
     */
    protected $intersection;

    /**
     * @param AdapterInterface $adapter
     * @param \Phinx\Db\Table $origin
     * @param Table $destination
     * @param SqlHelper $sqlHelper
     * @param Intersection $intersection
     */
    public function __construct(AdapterInterface $adapter, \Phinx\Db\Table $origin, \Lhm\Table $destination, SqlHelper $sqlHelper = null, Intersection $intersection = null)
    {
        $this->adapter = $adapter;
        $this->origin = $origin;
        $this->destination = $destination;
        $this->sqlHelper = $sqlHelper ?: new SqlHelper($this->adapter);
        $this->intersection = $intersection ?: new Intersection($this->origin, $this->destination);
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
        $destinationColumns = implode(
            ',',
            $this->sqlHelper->quoteColumns(
                $this->intersection->destination()
            )
        );

        $originColumns = implode(
            ',',
            $this->sqlHelper->typedColumns(
                'NEW',
                $this->sqlHelper->quoteColumns(
                    $this->intersection->origin()
                )
            )
        );

        $name = $this->trigger('insert');

        $originName = $this->adapter->quoteTableName($this->origin->getName());
        $destinationName = $this->adapter->quoteTableName($this->destination->getName());

        return implode("\n ", [
            "CREATE TRIGGER {$name}",
            "AFTER INSERT ON {$originName} FOR EACH ROW",
            "REPLACE INTO {$destinationName} ({$destinationColumns}) {$this->sqlHelper->annotation()}",
            "VALUES ({$originColumns})"
        ]);
    }

    /**
     * @return string
     */
    protected function createUpdateTrigger()
    {
        $destinationColumns = implode(
            ',',
            $this->sqlHelper->quoteColumns(
                $this->intersection->destination()
            )
        );

        $originColumns = implode(
            ',',
            $this->sqlHelper->typedColumns(
                'NEW',
                $this->sqlHelper->quoteColumns(
                    $this->intersection->origin()
                )
            )
        );

        $name = $this->trigger('update');

        $originName = $this->adapter->quoteTableName($this->origin->getName());
        $destinationName = $this->adapter->quoteTableName($this->destination->getName());

        return implode("\n ", [
            "CREATE TRIGGER {$name}",
            "AFTER UPDATE ON {$originName} FOR EACH ROW",
            "REPLACE INTO {$destinationName} ({$destinationColumns}) {$this->sqlHelper->annotation()}",
            "VALUES ({$originColumns})"
        ]);
    }

    /**
     * @return string
     */
    protected function createDeleteTrigger()
    {
        $name = $this->trigger('delete');

        $primaryKey = $this->sqlHelper->extractPrimaryKey($this->origin);
        if (empty($primaryKey)) {
            throw new \RuntimeException("Table `{$this->origin->getName()}` does not have a primary key.");
        }

        $primaryKey = $this->sqlHelper->quoteColumn($primaryKey);

        $originName = $this->adapter->quoteTableName($this->origin->getName());
        $destinationName = $this->adapter->quoteTableName($this->destination->getName());

        return implode("\n ", [
            "CREATE TRIGGER {$name}",
            "AFTER DELETE ON {$originName} FOR EACH ROW",
            "DELETE IGNORE FROM {$destinationName} {$this->sqlHelper->annotation()}",
            "WHERE {$destinationName}.{$primaryKey} = OLD.{$primaryKey}"
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
