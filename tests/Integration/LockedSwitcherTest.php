<?php
namespace Lhm\Tests\Integration;

use Lhm\LockedSwitcher;
use Phinx\Db\Adapter\AdapterInterface;
use PHPUnit\Framework\TestCase;

class LockedSwitcherTest extends TestCase
{

    /**
     * @var LockedSwitcher
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

        $this->switcher = new LockedSwitcher(
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

        $expected = [
            "set @lhm_auto_commit = @@session.autocommit /* large hadron migration (php) */",
            "set session autocommit = 0 /* large hadron migration (php) */",
            "LOCK TABLE `users` write, `users_new` write /* large hadron migration (php) */",
            "ALTER TABLE  `users` rename `users_archive` /* large hadron migration (php) */",
            "ALTER TABLE `users_new` rename `users` /* large hadron migration (php) */",
            "COMMIT /* large hadron migration (php) */",
            "UNLOCK TABLES /* large hadron migration (php) */",
            "set session autocommit = @lhm_auto_commit /* large hadron migration (php) */"
        ];
        $matcher = $this->any();
        $this->adapter
            ->expects($matcher)
            ->method('query')
            ->will($this->returnCallback(function ($query) use ($matcher, $expected) {
                $this->assertEquals($expected[$matcher->getInvocationCount() - 1], $query);
            }));

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
