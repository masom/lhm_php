<?php

namespace Lhm;

use Phinx\Table;
use Phinx\Migration\MigrationInterface;

class Chunker extends Command {

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
    public function __construct(AdapterInterface $adapter, Table $origin, Table $destination, SqlHelper $sqlHelper = null) {
        $this->adapter = $adapter;
        $this->origin = $origin;
        $this->destination = $destination;
        $this->sqlHelper = $sqlHelper ?: new SqlHelper($this->adapter);
    }

    protected function execute() {
        $this->adapter->query($this->copy());
    }

    protected function copy() {
        $destinationColumns = implode(",", $this->sqlHelper->quotedIntersectionColumns($this->origin, $this->destination));
        $originColumns = implode(",", $this->sqlHelper->typedColumns($this->origin));

        return implode(" ", [
            "INSERT IGNORE INTO {$this->destination->getName()} ({$destinationColumns})",
            "SELECT {$originColumns} from {$this->origin->getName()}"
        ]);
    }
}
