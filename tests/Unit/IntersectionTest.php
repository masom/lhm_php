<?php
namespace tests\Unit;

use Lhm\Intersection;
use Phinx\Db\Table\Column;
use PHPUnit\Framework\TestCase;

class IntersectionTest extends TestCase
{
    /**
     * @var \Phinx\Db\Table
     */
    protected $origin;

    /**
     * @var \Lhm\Table
     */
    protected $destination;

    /**
     * @var Intersection
     */
    protected $intersection;


    protected function setUp()
    {
        parent::setUp();
        $this->origin = $this->getMockBuilder(\Phinx\Db\Table::class)->disableOriginalConstructor()->getMock();
        $this->destination = $this->getMockBuilder(\Lhm\Table::class)->disableOriginalConstructor()->getMock();

        /** @var Column[] $originColumns */
        $originColumns = [
            new Column(),
            new Column(),
            new Column()
        ];
        $originColumns[0]->setName('id');
        $originColumns[1]->setName('name');
        $originColumns[2]->setName('something');

        /** @var Column[] $destinationColumns */
        $destinationColumns = [
            new Column(),
            new Column(),
            new Column()
        ];
        $destinationColumns[0]->setName('id');
        $destinationColumns[1]->setName('name');
        $destinationColumns[2]->setName('something_else');

        $this->origin
            ->expects($this->atLeastOnce())
            ->method('getColumns')
            ->will($this->returnValue($originColumns));

        $this->destination
            ->expects($this->atLeastOnce())
            ->method('getColumns')
            ->will($this->returnValue($destinationColumns));

        $this->intersection = new Intersection($this->origin, $this->destination);
    }

    protected function tearDown()
    {
        unset($this->origin, $this->destination);
        parent::tearDown();
    }


    public function testCommon()
    {
        $this->assertEquals(['id', 'name'], $this->intersection->common());
    }

    public function testDestinationRenamed()
    {
        $this->destination
            ->expects($this->atLeastOnce())
            ->method('getRenamedColumns')
            ->will($this->returnValue(['something' => 'something_else']));

        $this->assertEquals(['id', 'name', 'something_else'], $this->intersection->destination());
    }

    public function testDestination()
    {
        $this->destination
            ->expects($this->atLeastOnce())
            ->method('getRenamedColumns')
            ->will($this->returnValue([]));

        $this->assertEquals(['id', 'name'], $this->intersection->destination());
    }

    public function testOrigin() {
        $this->destination
            ->expects($this->atLeastOnce())
            ->method('getRenamedColumns')
            ->will($this->returnValue([]));

        $this->assertEquals(['id', 'name'], $this->intersection->origin());
    }

    public function testOriginRenamed() {
        $this->destination
            ->expects($this->atLeastOnce())
            ->method('getRenamedColumns')
            ->will($this->returnValue(['something' => 'something_else']));

        $this->assertEquals(['id', 'name', 'something'], $this->intersection->origin());
    }
}
