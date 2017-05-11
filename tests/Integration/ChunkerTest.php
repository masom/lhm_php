<?php
namespace Lhm\Tests\Integration;

use Lhm\Chunker;
use Lhm\SqlHelper;
use Phinx\Db\Adapter\AdapterInterface;
use Phinx\Db\Table\Column;
use PHPUnit\Framework\TestCase;

class ChunkerTest extends TestCase
{

    /** @var Chunker */
    protected $chunker;
    /**
     * @var AdapterInterface
     */
    protected $adapter;
    /**
     * @var \Phinx\Db\Table
     */
    protected $origin;
    /**
     * @var \Lhm\Table
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

        $this->adapter
            ->expects($this->any())
            ->method('quoteTableName')
            ->will($this->returnCallback(function ($name) {
                return "'{$name}'";
            }));

        $this->origin = $this->getMockBuilder(\Phinx\Db\Table::class)->disableOriginalConstructor()->getMock();
        $this->destination = $this->getMockBuilder(\Lhm\Table::class)->disableOriginalConstructor()->getMock();

        $this->sqlHelper = new SqlHelper($this->adapter);
    }

    protected function tearDown()
    {
        unset($this->chunker, $this->adapter, $this->origin, $this->destination, $this->sqlHelper);
        parent::tearDown();
    }

    public function testRun()
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

        $this->destination
            ->expects($this->atLeastOnce())
            ->method('getRenamedColumns')
            ->will($this->returnValue([]));

        $matcher = $this->atLeastOnce();
        $this->adapter
            ->expects($matcher)
            ->method('fetchRow')
            ->will($this->returnCallback(function ($query) use ($matcher) {
                switch ($matcher->getInvocationCount()) {
                    case 1:
                        $this->assertEquals(
                            "SELECT MIN(`id`) FROM 'users'",
                            $query
                        );
                        return [1];

                    case 2:
                        $this->assertEquals(
                            "SELECT MAX(`id`) FROM 'users'",
                            $query
                        );
                        return [500];
                    default:

                        return null;
                        break;
                }
            }));

        $matcher = $this->atLeastOnce();
        $this->adapter
            ->expects($matcher)
            ->method('query')
            ->will($this->returnCallback(function ($query) use ($matcher) {
                switch ($matcher->getInvocationCount()) {
                    case 1:
                        $this->assertEquals(
                            "SELECT `COLUMN_NAME` FROM `information_schema`.`COLUMNS` WHERE (`TABLE_SCHEMA` = '') AND (`TABLE_NAME` = 'users') AND (`COLUMN_KEY` = 'PRI');",
                            $query
                        );

                        return 'id';
                    case 2:
                        $this->assertEquals(
                            "INSERT IGNORE INTO 'users_new' (`id`,`name`) SELECT 'users'.`id`,'users'.`name` FROM 'users' WHERE 'users'.`id` BETWEEN 1 AND 200",
                            $query
                        );
                        break;
                    case 3:
                        $this->assertEquals(
                            "INSERT IGNORE INTO 'users_new' (`id`,`name`) SELECT 'users'.`id`,'users'.`name` FROM 'users' WHERE 'users'.`id` BETWEEN 201 AND 400",
                            $query
                        );
                        break;
                    case 4:
                        $this->assertEquals(
                            "INSERT IGNORE INTO 'users_new' (`id`,`name`) SELECT 'users'.`id`,'users'.`name` FROM 'users' WHERE 'users'.`id` BETWEEN 401 AND 500",
                            $query
                        );
                        break;
                    default:
                        $this->fail('Unexpected query: ' . $query);
                        break;
                }
            }));

        $chunker = new Chunker($this->adapter, $this->origin, $this->destination, $this->sqlHelper, ['stride' => 200]);
        $chunker->run();
    }

}
