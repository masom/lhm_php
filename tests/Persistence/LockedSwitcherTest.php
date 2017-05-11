<?php
namespace Lhm\Tests\Persistence;

use Lhm\LockedSwitcher;
use Lhm\Invoker;
use Lhm\Tests\Persistence\Migrations\InitialMigration;

/**
 * @see https://github.com/soundcloud/lhm/blob/b8819c550b1b471b563036276bbfffe5c990777d/lib/lhm/locked_switcher.rb#L9
 *
 * Switches origin with destination table nonatomically using a locked write.
 * LockedSwitcher adopts the Facebook strategy, with the following caveat:
 *
 * "Since alter table causes an implicit commit in innodb, innodb locks get
 * released after the first alter table. So any transaction that sneaks in
 * after the first alter table and before the second alter table gets
 * a 'table not found' error. The second alter table is expected to be very
 * fast though because copytable is not visible to other transactions and so
 * there is no need to wait."

 */
class LockedSwitcherTest extends AbstractPersistenceTest
{

    /**
     * @var LockedSwitcher
     */
    protected $switcher;

    /**
     * @var Table
     */
    protected $origin;
    /**
     * @var \Lhm\Table
     */
    protected $destination;

    protected function setUp()
    {
        parent::setUp();

        $this->adapter->execute("SET GLOBAL innodb_lock_wait_timeout=3");
        $this->adapter->execute("SET GLOBAL lock_wait_timeout=3");

        $this->origin = new \Phinx\Db\Table('ponies');


        $migration = new InitialMigration(time());
        $migration->setAdapter($this->adapter);
        $migration->up();

        $invoker = new Invoker($this->adapter, $this->origin);

        $this->destination = $invoker->temporaryTable();
        $this->switcher = new LockedSwitcher($this->adapter, $this->origin, $this->destination);
    }

    public function testRetryOnLockTimeouts()
    {
        $this->markTestSkipped('Requires implementation of https://github.com/soundcloud/lhm/blob/b8819c550b1b471b563036276bbfffe5c990777d/spec/integration/integration_helper.rb#L91');
        //$this->switcher->setOption('retry_sleep_time', 0.3);
        //$this->switcher->run();
    }

    public function testItRenamesOriginToArchive()
    {
        $archive = $this->switcher->getOptions()['archive_name'];
        $this->assertFalse($this->adapter->hasTable($archive));

        $this->switcher->run();

        $this->assertTrue($this->adapter->hasTable($archive));
    }

    public function testItRenamesDestinationToOrigin()
    {
        $this->assertTrue($this->adapter->hasTable($this->destination->getName()));

        $this->switcher->run();

        $this->assertFalse($this->adapter->hasTable($this->destination->getName()));
    }
}
