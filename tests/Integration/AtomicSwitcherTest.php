<?php
namespace Lhm\Tests\Integration;

use Lhm\AtomicSwitcher;
use Phinx\Db\Adapter\AdapterInterface;
use PHPUnit\Framework\TestCase;

class AtomicSwitcherTest extends TestCase
{

    /**
     * @var AtomicSwitcher
     */
    protected $switcher;
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

        $this->origin = $this->getMockBuilder(\Phinx\Db\Table::class)->disableOriginalConstructor()->getMock();
        $this->destination = $this->getMockBuilder(\Lhm\Table::class)->disableOriginalConstructor()->getMock();

        $this->switcher = new AtomicSwitcher(
            $this->adapter,
            $this->origin,
            $this->destination,
            [
                'archive_name' => 'users_archive'
            ]
        );
    }

    protected function tearDown()
    {
        unset($this->switcher, $this->adapter, $this->origin, $this->destination);
        parent::tearDown();
    }


    public function test()
    {

        $this->adapter
            ->expects($this->atLeastOnce())
            ->method('hasTable')
            ->will($this->returnValueMap([['users', true], ['users_new', true]]));

        $this->adapter
            ->expects($this->atLeastOnce())
            ->method('quoteTableName')
            ->will($this->returnCallback(function ($name) {
                return "`{$name}`";
            }));

        $this->adapter
            ->expects($this->once())
            ->method('query')
            ->with('RENAME TABLE `users` TO `users_archive`, `users_new` TO `users`');

        $this->origin
            ->expects($this->atLeastOnce())
            ->method('getName')
            ->will($this->returnValue('users'));

        $this->destination
            ->expects($this->atLeastOnce())
            ->method('getName')
            ->will($this->returnValue('users_new'));

        $this->switcher->run();
    }
}
