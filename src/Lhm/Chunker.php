<?php

namespace Lhm;

use Phinx\Db\Table;
use Phinx\Db\Adapter\AdapterInterface;


class Chunker extends Command
{

    /**
     * @var AdapterInterface
     */
    protected $adapter;

    /**
     * @var Table
     */
    protected $origin;

    /**
     * @var Table
     */
    protected $destination;

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

    protected function execute()
    {
        $query = $this->copy();

        $this->getLogger()->info("Copying data from `{$this->origin->getName()}` into `{$this->destination->getName()}`");
        $this->getLogger()->debug($query);

        $this->adapter->query($query);
    }

    protected function copy()
    {
        $intersectedColumns = $this->sqlHelper->quotedIntersectionColumns($this->origin, $this->destination);
        $destinationColumns = implode(",", $intersectedColumns);
        $originColumns = implode(",", $this->sqlHelper->typedColumns($this->origin->getName(), $intersectedColumns));

        return implode(" ", [
            "INSERT IGNORE INTO {$this->destination->getName()} ({$destinationColumns})",
            "SELECT {$originColumns} FROM {$this->origin->getName()}"
        ]);
    }
}
