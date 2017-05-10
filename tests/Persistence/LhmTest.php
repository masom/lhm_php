<?php
namespace Lhm\Tests\Persistence;

use Lhm\Lhm;
use Lhm\Tests\Persistence\Migrations\HybridPhinxMigration;
use Lhm\Tests\Persistence\Migrations\InitialMigration;
use Lhm\Tests\Persistence\Migrations\LhmMigration;
use Phinx\Db\Table;
use Phinx\Migration\Manager\Environment;
use Phinx\Migration\MigrationInterface;
use Lhm\Tests\Persistence\Migrations\IndexMigration;
use Lhm\Tests\Persistence\Migrations\RenameMigration;

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
        $this->adapter->query('CREATE TABLE test(id INT PRIMARY KEY);');
        $this->adapter->query('CREATE TABLE lhma_2015_03_04_13_22_33_test(id INT PRIMARY KEY);');
        $this->adapter->query(implode("\n ", [
            'CREATE TRIGGER lhmt_update_test',
            'AFTER UPDATE ON test FOR EACH ROW',
            'REPLACE INTO derp (`id`) /* large hadron migration (php) */',
            'VALUES (NEW.`id`)'
        ]));

        Lhm::setAdapter($this->adapter);
        Lhm::cleanup(true, ['until' => new \DateTime()]);

        /** @var \PDOStatement $statement */
        $statement = $this->adapter->query('show triggers');
        $this->assertEquals(false, $statement->fetchColumn(0));

        $statement = $this->adapter->query('show tables');
        $this->assertEquals(2, $statement->rowCount());
        $this->assertEquals('phinxlog', $statement->fetchColumn(0));
        $this->assertEquals('test', $statement->fetchColumn(0));
    }

    public function testCleanupDryRun()
    {
        $this->adapter->query('CREATE TABLE test(id INT PRIMARY KEY);');
        $this->adapter->query('CREATE TABLE lhma_2015_03_04_13_22_33_test(id INT PRIMARY KEY);');
        $this->adapter->query(implode("\n ", [
            'CREATE TRIGGER lhmt_update_test',
            'AFTER UPDATE ON test FOR EACH ROW',
            'REPLACE INTO derp (`id`) /* large hadron migration (php) */',
            'VALUES (NEW.`id`)'
        ]));

        Lhm::setAdapter($this->adapter);
        Lhm::cleanup(false, ['until' => new \DateTime()]);

        /** @var \PDOStatement $statement */
        $statement = $this->adapter->query('show triggers');
        $this->assertEquals('lhmt_update_test', $statement->fetchColumn(0));

        $statement = $this->adapter->query('show tables');
        $this->assertEquals(3, $statement->rowCount());
        $this->assertEquals('lhma_2015_03_04_13_22_33_test', $statement->fetchColumn(0));
        $this->assertEquals('phinxlog', $statement->fetchColumn(0));
        $this->assertEquals('test', $statement->fetchColumn(0));
    }

    public function testAddIndex()
    {
        $time = time();
        $this->environment->executeMigration(new InitialMigration($time - 1), MigrationInterface::UP);
        $this->environment->executeMigration(new IndexMigration($time), MigrationInterface::UP);

        $exists = false;
        $rows = $this->adapter->fetchAll(sprintf('SHOW INDEXES FROM %s', $this->adapter->quoteTableName('ponies')));
        foreach ($rows as $row) {
            if ($row['Key_name'] === 'ponies_age_idx') {
                if ($row['Column_name'] === 'age' && $row['Non_unique'] === '1') {
                    $exists = true;
                    break;
                }
            }
        }

        $this->assertTrue($exists, 'The ponies_name_idx should exist.');
    }

    public function testRemoveIndex()
    {
        $time = time();
        $this->environment->executeMigration(new InitialMigration($time - 1), MigrationInterface::UP);
        $this->environment->executeMigration(new IndexMigration($time), MigrationInterface::UP);
        $this->environment->executeMigration(new IndexMigration($time), MigrationInterface::DOWN);

        $rows = $this->adapter->fetchAll(sprintf('SHOW INDEXES FROM %s', $this->adapter->quoteTableName('ponies')));
        foreach ($rows as $row) {
            $this->assertFalse($row['Key_name'] === 'ponies_age_idx', 'The ponies_age_idx should have been removed.');
        }
    }

    public function testRenameColumn()
    {
        $time = time();
        $this->environment->executeMigration(new InitialMigration($time - 1), MigrationInterface::UP);

        $this->assertNotEmpty($this->adapter->fetchAll("SELECT * FROM ponies WHERE name IS NOT NULL"));

        $this->environment->executeMigration(new RenameMigration($time), MigrationInterface::UP);

        $this->assertEmpty($this->adapter->fetchAll("SELECT * FROM ponies WHERE first_name IS NULL"));
    }
}
