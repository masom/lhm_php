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

    /** @var integer */
    protected $nextToInsert;

    /** @var integer */
    protected $limit;

    /** @var integer */
    protected $start;

    /** @var string */
    protected $primaryKey;

    /** @var array */
    protected $options;

    /**
     * @param AdapterInterface $adapter
     * @param Table $origin
     * @param Table $destination
     * @param SqlHelper $sqlHelper
     */
    public function __construct(AdapterInterface $adapter, Table $origin, Table $destination, SqlHelper $sqlHelper = null, array $options = [])
    {
        $this->adapter = $adapter;
        $this->origin = $origin;
        $this->destination = $destination;
        $this->sqlHelper = $sqlHelper ?: new SqlHelper($this->adapter);

        $this->options = $options + ['stride' => 2000];

        $this->primaryKey = $this->adapter->quoteColumnName($this->sqlHelper->extractPrimaryKey($this->origin));

        $this->nextToInsert = $this->start = $this->selectStart();
        $this->limit = $this->selectLimit();
    }

    protected function execute()
    {

        $this->getLogger()->info("Copying data from `{$this->origin->getName()}` into `{$this->destination->getName()}`");


        while ($this->nextToInsert < $this->limit || ($this->nextToInsert == 1 && $this->start == 1)) {

            $query = $this->copy($this->bottom(), $this->top($this->options['stride']));

            $this->getLogger()->debug($query);

            $this->adapter->query($query);
            $this->nextToInsert = $this->top($this->options['stride']) + 1;
        }


    }

    protected function top($stride)
    {
        return min(($this->nextToInsert + $stride - 1), $this->limit);
    }

    protected function bottom()
    {
        return $this->nextToInsert;
    }

    protected function selectStart()
    {
        $start = $this->adapter->fetchRow("SELECT MIN(id) FROM `{$this->origin->getName()}`")[0];

        return (int)$start;
    }

    protected function selectLimit()
    {
        $limit = $this->adapter->fetchRow("SELECT MAX(id) FROM `{$this->origin->getName()}`")[0];

        return (int)$limit;
    }

    /**
     * @param integer $lowest
     * @param integer $highest
     * @return string
     */
    protected function copy($lowest, $highest)
    {
        $intersectedColumns = $this->sqlHelper->quotedIntersectionColumns($this->origin, $this->destination);
        $destinationColumns = implode(",", $intersectedColumns);
        $originColumns = implode(",", $this->sqlHelper->typedColumns($this->origin->getName(), $intersectedColumns));

        return implode(" ", [
            "INSERT IGNORE INTO {$this->destination->getName()} ({$destinationColumns})",
            "SELECT {$originColumns} FROM {$this->origin->getName()}",
            "WHERE `{$this->origin->getName()}`.{$this->primaryKey} BETWEEN {$lowest} AND {$highest}"
        ]);
    }
}
