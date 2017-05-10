<?php
namespace Lhm\Tests\Persistence;

use Lhm\AtomicSwitcher;
use Lhm\Invoker;
use Lhm\Tests\Persistence\AbstractPersistenceTest;
use Lhm\Tests\Persistence\Migrations\InitialMigration;
use Phinx\Db\Table;

class AtomicSwitcherTest extends AbstractPersistenceTest
{

    /**
     * @var AtomicSwitcher
     */
    protected $switcher;

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

        $this->adapter->execute("SET GLOBAL innodb_lock_wait_timeout=3");
        $this->adapter->execute("SET GLOBAL lock_wait_timeout=3");

        $this->origin = new Table('ponies');


        $migration = new InitialMigration(time());
        $migration->setAdapter($this->adapter);
        $migration->up();

        $invoker = new Invoker($this->adapter, $this->origin);

        $this->destination = $invoker->temporaryTable();
        $this->switcher = new AtomicSwitcher($this->adapter, $this->origin, $this->destination);
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
