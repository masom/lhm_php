<?php


namespace Lhm\Tests\Integration;


use Lhm\Chunker;
use Lhm\SqlHelper;
use Phinx\Db\Adapter\AdapterInterface;
use Phinx\Db\Table;
use Phinx\Db\Table\Column;


class ChunkerTest extends \PHPUnit_Framework_TestCase
{

    /** @var Chunker */
    protected $chunker;
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

    protected function setUp()
    {
        parent::setUp();
        $this->adapter = $this->getMockBuilder(AdapterInterface::class)->getMock();
        $this->adapter
            ->expects($this->any())
            ->method('quoteColumnName')
            ->will($this->returnCallback(function ($name) {
                return "`{$name}`";
            }));

        $this->origin = $this->getMockBuilder(Table::class)->disableOriginalConstructor()->getMock();
        $this->destination = $this->getMockBuilder(Table::class)->disableOriginalConstructor()->getMock();

        $this->sqlHelper = new SqlHelper($this->adapter);

        $this->chunker = new Chunker($this->adapter, $this->origin, $this->destination, $this->sqlHelper);
    }

    protected function tearDown()
    {
        unset($this->chunker, $this->adapter, $this->origin, $this->destination, $this->sqlHelper);
        parent::tearDown();
    }

    public function test()
    {
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
            ->method('getName')
            ->will($this->returnValue('users'));

        $this->origin
            ->expects($this->atLeastOnce())
            ->method('getColumns')
            ->will($this->returnValue($originColumns));

        $this->destination
            ->expects($this->atLeastOnce())
            ->method('getName')
            ->will($this->returnValue('users_new'));

        $this->destination
            ->expects($this->atLeastOnce())
            ->method('getColumns')
            ->will($this->returnValue($destinationColumns));

        $this->adapter
            ->expects($this->once())
            ->method('query')
            ->with('INSERT IGNORE INTO users_new (`id`,`name`) SELECT `id`,`name`,`something` FROM users');
        $this->chunker->run();
    }

}
