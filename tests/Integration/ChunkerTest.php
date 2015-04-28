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
        $definitions = [
            'origin' => [
                ['COLUMN_NAME' => 'id'],
                ['COLUMN_NAME' => 'name'],
                ['COLUMN_NAME' => 'something']
            ],
            'destination' => [
                ['COLUMN_NAME' => 'id'],
                ['COLUMN_NAME' => 'name'],
                ['COLUMN_NAME' => 'content']
            ]
        ];

        $this->origin
            ->expects($this->atLeastOnce())
            ->method('getName')
            ->will($this->returnValue('users'));


        $this->destination
            ->expects($this->atLeastOnce())
            ->method('getName')
            ->will($this->returnValue('users_new'));

        $matcher = $this->atLeastOnce();
        $this->adapter
            ->expects($matcher)
            ->method('query')
            ->will($this->returnCallback(function ($query) use ($matcher, $definitions) {
                switch ($matcher->getInvocationCount()) {
                    case 1:
                        $this->assertEquals(
                            "SELECT * FROM information_schema.columns WHERE table_name = 'users' AND table_schema =''",
                            $query
                        );
                        return $definitions['origin'];
                        break;

                    case 2:
                        $this->assertEquals(
                            "SELECT * FROM information_schema.columns WHERE table_name = 'users_new' AND table_schema =''",
                            $query
                        );
                        return $definitions['destination'];
                        break;

                    case 3:
                        $this->assertEquals(
                            'INSERT IGNORE INTO users_new (`id`,`name`) SELECT users.`id`,users.`name` FROM users',
                            $query
                        );
                        break;
                    default:
                        $this->fail($query);
                        break;
                }
            }));
        $this->chunker->run();
    }

}
