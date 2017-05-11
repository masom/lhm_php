<?php
namespace Lhm\Tests\Integration;

use Lhm\SqlHelper;
use Phinx\Db\Adapter\AdapterInterface;
use Phinx\Db\Table;
use Phinx\Db\Table\Column;
use PHPUnit\Framework\TestCase;

class SqlHelperTest extends TestCase
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

    public function testQuoteColumns()
    {
        $columns = [
            'id',
            'name'
        ];

        $this->adapter
            ->expects($this->any())
            ->method('quoteColumnName')
            ->will($this->returnCallback(function ($name) {
                return "`{$name}`";
            }));

        $expected = ['`id`', '`name`'];
        $this->assertEquals($expected, $this->helper->quoteColumns($columns));
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

                $mock = $this->getMockBuilder(\stdClass::class)
                    ->setMethods(['fetchColumn'])
                    ->disableOriginalConstructor()
                    ->getMock();

                $mock->expects($this->once())
                    ->method('fetchColumn')
                    ->will($this->returnValue($value));
                return $mock;
            }));

        foreach ($expectedIterator as $version => $expected) {
            $this->assertEquals($expected, $this->helper->supportsAtomicSwitch());
        }
    }

    public function testVersionString()
    {
        $mock = $this->getMockBuilder(\stdClass::class)
            ->setMethods(['fetchColumn'])
            ->disableOriginalConstructor()
            ->getMock();
        $mock->expects($this->once())
            ->method('fetchColumn')
            ->will($this->returnValue("3.0.2"));

        $this->adapter
            ->expects($this->once())
            ->method('query')
            ->with("show variables like 'version'")
            ->will($this->returnValue($mock));

        $this->assertEquals("3.0.2", $this->helper->versionString());
    }
}
