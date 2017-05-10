<?php
namespace Lhm\Tests\Unit;

use Lhm\SqlHelper;
use Phinx\Db\Adapter\AdapterInterface;
use PHPUnit\Framework\TestCase;

class SqlHelperTest extends TestCase
{
    /**
     * @var SqlHelper
     */
    protected $helper;

    protected function setUp()
    {
        $adapter = $this->getMockBuilder(AdapterInterface::class)->getMock();
        $this->helper = new SqlHelper($adapter);
    }

    protected function tearDown()
    {
        unset($this->helper);
        parent::tearDown();
    }

    public function testAnnotation()
    {
        $this->assertEquals(
            "/* large hadron migration (php) */",
            $this->helper->annotation()
        );
    }

    public function testTypedColumns()
    {
        $expected = ['NEW.id', 'NEW.name', 'NEW.test'];
        $this->assertEquals(
            $expected,
            $this->helper->typedColumns('NEW', ['id', 'name', 'test'])
        );
    }
}
