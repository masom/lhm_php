<?php


namespace Lhm\Tests\Integration;


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

    public function testTableColumns()
    {

        $table = $this->getMockBuilder(Table::class)->disableOriginalConstructor()->getMock();
        $table
            ->expects($this->once())
            ->method('getName')
            ->will($this->returnValue('ponies'));

        $this->adapter
            ->expects($this->any())
            ->method('quoteColumnName')
            ->will($this->returnCallback(function ($name) {
                return "`{$name}`";
            }));

        $definitions = [['COLUMN_NAME' => 'id'], ['COLUMN_NAME' => 'name']];

        $this->adapter
            ->expects($this->once())
            ->method('getOption')
            ->with('name')
            ->will($this->returnValue('lhm_tests'));

        $this->adapter
            ->expects($this->once())
            ->method('query')
            ->with("SELECT * FROM information_schema.columns WHERE table_name = 'ponies' AND table_schema ='lhm_tests'")
            ->will($this->returnValue($definitions));

        $columns = $this->helper->tableColumns($table);
        $this->assertEquals(2, count($columns));
        $this->assertEquals('id', $columns[0]->getName());
    }

    public function testQuotedColumns()
    {
        $table = $this->getMockBuilder(Table::class)->disableOriginalConstructor()->getMock();
        $table
            ->expects($this->once())
            ->method('getName')
            ->will($this->returnValue('ponies'));

        $this->adapter
            ->expects($this->any())
            ->method('quoteColumnName')
            ->will($this->returnCallback(function ($name) {
                return "`{$name}`";
            }));

        $definitions = [['COLUMN_NAME' => 'id'], ['COLUMN_NAME' => 'name']];

        $this->adapter
            ->expects($this->once())
            ->method('getOption')
            ->with('name')
            ->will($this->returnValue('lhm_tests'));

        $this->adapter
            ->expects($this->once())
            ->method('query')
            ->with("SELECT * FROM information_schema.columns WHERE table_name = 'ponies' AND table_schema ='lhm_tests'")
            ->will($this->returnValue($definitions));

        $expected = ['`id`', '`name`'];
        $this->assertEquals($expected, $this->helper->quotedColumns($table));
    }

    public function testQuotedIntersectionColumns()
    {
        $origin = $this->getMockBuilder(Table::class)->disableOriginalConstructor()->getMock();
        $origin
            ->expects($this->once())
            ->method('getName')
            ->will($this->returnValue('ponies'));

        $destination = $this->getMockBuilder(Table::class)->disableOriginalConstructor()->getMock();
        $destination
            ->expects($this->once())
            ->method('getName')
            ->will($this->returnValue('ponies_new'));

        $this->adapter
            ->expects($this->any())
            ->method('quoteColumnName')
            ->will($this->returnCallback(function ($name) {
                return "`{$name}`";
            }));

        $definitions = [
            'origin'=>[
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

        $this->adapter
            ->expects($this->exactly(2))
            ->method('getOption')
            ->with('name')
            ->will($this->returnValue('lhm_tests'));

        $this->adapter
            ->expects($this->exactly(2))
            ->method('query')
            ->will($this->returnCallback(function ($query) use ($definitions) {
                switch ($query) {
                    case "SELECT * FROM information_schema.columns WHERE table_name = 'ponies' AND table_schema ='lhm_tests'":
                        return $definitions['origin'];
                    case "SELECT * FROM information_schema.columns WHERE table_name = 'ponies_new' AND table_schema ='lhm_tests'":
                        return $definitions['destination'];
                }
                return null;
            }));

        /**
         * `something` and `content` should not be in the field list.
         */

        $this->assertEquals(
            ['`id`', '`name`'],
            $this->helper->quotedIntersectionColumns($origin, $destination)
        );
    }

    public function testSupportsAtomicSwitch()
    {
        $versions = [
            '3.0.1' => true,
            '4.0.1' => false,
            '4.2.2' => true,
            '4.20.1' => true,
            '5.0.20' => false,
            '5.0.57' => true,
            '5.1.3' => false,
            '5.4.2' => false,
            '5.4.4' => true,
            '5.5.2' => false,
            '5.5.3' => true,
            '5.5.4' => true,
            '6.0.3' => false,
            '6.0.10' => false,
            '6.0.11' => true,
            '6.0.12' => true
        ];

        $expectedIterator = new \ArrayIterator($versions);
        $matcherIterator = new \ArrayIterator(array_keys($versions));

        $matcher = $this->any();
        $this->adapter
            ->expects($matcher)
            ->method('query')
            ->with("show variables like 'version'")
            ->will($this->returnCallback(function () use ($matcherIterator) {
                $value = $matcherIterator->current();
                $matcherIterator->next();
                return [$value];
            }));

        foreach ($expectedIterator as $version => $expected) {
            $this->assertEquals($expected, $this->helper->supportsAtomicSwitch());
        }
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
