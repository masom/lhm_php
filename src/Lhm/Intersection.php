<?php


namespace Lhm;


class Intersection
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
     * @var array
     */
    protected $renames;

    /**
     * @param \Phinx\Db\Table $origin
     * @param \Lhm\Table $destination
     */
    public function __construct(\Phinx\Db\Table $origin, \Lhm\Table $destination)
    {
        $this->origin = $origin;
        $this->destination = $destination;
    }

    /**
     * @return array
     */
    public function origin()
    {
        return array_merge($this->common(), array_keys($this->destination->getRenamedColumns()));
    }

    /**
     * @return array
     */
    public function destination()
    {
        return array_merge($this->common(), array_values($this->destination->getRenamedColumns()));
    }

    /**
     * @return array
     */
    public function common()
    {
        $origin = [];
        foreach ($this->origin->getColumns() as $column) {
            $origin[] = $column->getName();

        }

        $destination = [];
        foreach ($this->destination->getColumns() as $column) {
            $destination[] = $column->getName();
        }

        $intersection = array_intersect($origin, $destination);

        sort($intersection);

        return $intersection;
    }
}
