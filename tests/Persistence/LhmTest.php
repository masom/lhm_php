<?php


namespace Lhm\Tests\Persistence;


use Lhm\Lhm;
use Lhm\Tests\Persistence\Migrations\HybridPhinxMigration;
use Lhm\Tests\Persistence\Migrations\InitialMigration;
use Lhm\Tests\Persistence\Migrations\LhmMigration;
use Phinx\Db\Table;
use Phinx\Migration\Manager\Environment;
use Phinx\Migration\MigrationInterface;
use tests\Persistence\AbstractPersistenceTest;


class LhmTest extends AbstractPersistenceTest
{

    /** @var Environment */
    protected $environment;

    protected function setUp()
    {
        parent::setUp();

        $this->environment = new Environment('test', []);
        $this->environment->setAdapter($this->adapter);
    }

    protected function tearDown()
    {
        unset($this->environment, $this->migration);
        parent::tearDown();
    }

    public function testMigrateLhm()
    {
        $time = time();
        $this->environment->executeMigration(new InitialMigration($time - 1), MigrationInterface::UP);
        $this->environment->executeMigration(new LhmMigration($time), MigrationInterface::UP);

        $count = $this->adapter->query("SELECT COUNT(*) FROM ponies")->fetchColumn(0);

        /** @var \PDOStatement $statement */
        $statement = $this->adapter->query("SELECT nickname FROM ponies");
        $nickname = $statement->fetchColumn(0);

        $this->assertEquals(100, $count);
        $this->assertEquals('derp', $nickname);
    }

    public function testMigrateHybrid()
    {
        $time = time();
        $this->environment->executeMigration(new InitialMigration($time - 1), MigrationInterface::UP);
        $this->environment->executeMigration(new HybridPhinxMigration($time), MigrationInterface::UP);

        /** @var \PDOStatement $statement */
        $statement = $this->adapter->query("SELECT age, location FROM ponies");
        $age = $statement->fetchColumn(0);
        $location = $statement->fetchColumn(1);

        $this->assertEquals(null, $age);
        $this->assertEquals('Canada', $location);
    }

    public function testCleanup()
    {
        $this->adapter->query('CREATE TABLE derp(id INT PRIMARY KEY);');
        $this->adapter->query('CREATE TABLE lhma_2014_03_04_13_22_33_test(id INT PRIMARY KEY);');
        $this->adapter->query(implode("\n ", [
            'CREATE TRIGGER lhmt_update_users',
            'AFTER UPDATE ON derp FOR EACH ROW',
            'REPLACE INTO derp (`id`) /* large hadron migration (php) */',
            'VALUES (NEW.`id`)'
        ]));

        Lhm::setLogger(new \Monolog\Logger('test', [new \Monolog\Handler\StreamHandler('php://stdout')]));
        Lhm::setAdapter($this->adapter);
        Lhm::cleanup(true);
    }
}
