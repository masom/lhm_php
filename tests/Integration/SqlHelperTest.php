<?php


namespace Tests\Integration;


use Lhm\SqlHelper;
use Phinx\Db\Adapter\AdapterInterface;
use Phinx\Db\Table;
use Phinx\Db\Table\Column;


class SqlHelperTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @var SqlHelper
     */
    protected $helper;

    /**
     * @var AdapterInterface
     */
    protected $adapter;

    protected function setUp()
    {
        $this->adapter = $this->getMockBuilder(AdapterInterface::class)->getMock();
        $this->helper = new SqlHelper($this->adapter);
    }

    protected function tearDown()
    {
        unset($this->helper, $this->adapter);
        parent::tearDown();
    }

    public function testTagged()
    {
        $this->assertEquals(
            'THIS SHOULD BE TAGGED /* large hadron migration (php) */',
            $this->helper->tagged('THIS SHOULD BE TAGGED')
        );
    }

    public function testQuotedColumns()
    {
        /**
         * @var Column[] $columns
         */
        $columns = [
            new Column(),
            new Column()
        ];

        $columns[0]->setName('id');
        $columns[1]->setName('name');

        $table = $this->getMockBuilder(Table::class)->disableOriginalConstructor()->getMock();
        $table
            ->expects($this->once())
            ->method('getColumns')
            ->will($this->returnValue($columns));

        $this->adapter
            ->expects($this->any())
            ->method('quoteColumnName')
            ->will($this->returnCallback(function ($name) {
                return "`{$name}`";
            }));

        $expected = ['`id`', '`name`'];
        $this->assertEquals($expected, $this->helper->quotedColumns($table));
    }

    public function testQuotedIntersectionColumns()
    {
        $this->adapter
            ->expects($this->any())
            ->method('quoteColumnName')
            ->will($this->returnCallback(function ($name) {
                return "`{$name}`";
            }));

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
        $destinationColumns[2]->setName('content');

        $origin = $this->getMockBuilder(Table::class)->disableOriginalConstructor()->getMock();
        $origin
            ->expects($this->once())
            ->method('getColumns')
            ->will($this->returnValue($originColumns));
        $destination = $this->getMockBuilder(Table::class)->disableOriginalConstructor()->getMock();
        $destination
            ->expects($this->once())
            ->method('getColumns')
            ->will($this->returnValue($destinationColumns));

        /**
         * `something` and `content` should not be in the field list.
         */

        $this->assertEquals(
            ['`id`', '`name`'],
            $this->helper->quotedIntersectionColumns($origin, $destination)
        );
    }

    public function testVersionString()
    {
        $this->adapter
            ->expects($this->once())
            ->method('query')
            ->with("show variables like 'version'")
            ->will($this->returnValue(["3.0.2"]));

        $this->assertEquals("3.0.2", $this->helper->versionString());
    }
}
