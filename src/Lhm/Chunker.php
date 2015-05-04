<?php

namespace Lhm;

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
     * @var Intersection
     */
    protected $intersection;

    /**
     * @param AdapterInterface $adapter
     * @param \Phinx\Db\Table $origin
     * @param \Lhm\Table $destination
     * @param SqlHelper $sqlHelper
     * @param array $options
     *                      - `stride`
     *                          Size of chunk ( defaults to 2000 )
     */
    public function __construct(AdapterInterface $adapter, \Phinx\Db\Table $origin, \Lhm\Table $destination, SqlHelper $sqlHelper = null, array $options = [])
    {
        $this->adapter = $adapter;
        $this->origin = $origin;
        $this->destination = $destination;
        $this->sqlHelper = $sqlHelper ?: new SqlHelper($this->adapter);

        $this->options = $options + ['stride' => 2000];

        $this->primaryKey = $this->adapter->quoteColumnName($this->sqlHelper->extractPrimaryKey($this->origin));

        $this->nextToInsert = $this->start = $this->selectStart();
        $this->limit = $this->selectLimit();

        $this->intersection = new Intersection($this->origin, $this->destination);
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
        $name = $this->adapter->quoteTableName($this->origin->getName());
        $start = $this->adapter->fetchRow("SELECT MIN(id) FROM {$name}")[0];

        return (int)$start;
    }

    protected function selectLimit()
    {
        $name = $this->adapter->quoteTableName($this->origin->getName());
        $limit = $this->adapter->fetchRow("SELECT MAX(id) FROM {$name}")[0];

        return (int)$limit;
    }

    /**
     * @param integer $lowest
     * @param integer $highest
     * @return string
     */
    protected function copy($lowest, $highest)
    {
        $originName = $this->adapter->quoteTableName($this->origin->getName());
        $destinationName = $this->adapter->quoteTableName($this->destination->getName());

        $destinationColumns = implode(
            ',',
            $this->sqlHelper->quoteColumns($this->intersection->destination())
        );

        $originColumns = implode(
            ',',
            $this->sqlHelper->typedColumns(
                $originName,
                $this->sqlHelper->quoteColumns($this->intersection->origin())
            )
        );


        return implode(" ", [
            "INSERT IGNORE INTO {$destinationName} ({$destinationColumns})",
            "SELECT {$originColumns} FROM {$originName}",
            "WHERE {$originName}.{$this->primaryKey} BETWEEN {$lowest} AND {$highest}"
        ]);
    }
}
