<?php


namespace Lhm\Tests\Persistence;


use Lhm\Tests\Persistence\Migrations\HybridPhinxMigration;
use Lhm\Tests\Persistence\Migrations\InitialMigration;
use Lhm\Tests\Persistence\Migrations\LhmMigration;
use Phinx\Db\Adapter\MysqlAdapter;
use Phinx\Db\Table;
use Phinx\Migration\Manager\Environment;
use Phinx\Migration\MigrationInterface;
use Symfony\Component\Console\Output\NullOutput;


class LhmTest extends \PHPUnit_Framework_TestCase
{

    /** @var Environment */
    protected $environment;

    protected function setUp()
    {
        parent::setUp();

        $options = [
            'host' => getenv('LHM_DATABASE_HOST') ?: 'localhost',
            'name' => getenv('LHM_DATABASE_NAME') ?: 'lhm_php_test',
            'user' => getenv('LHM_DATABASE_USER') ?: 'root',
            'pass' => getenv('LHM_DATABASE_PASSWORD') ?: null,
            'port' => getenv('LHM_DATABASE_PORT') ?: 3306
        ];

        $adapter = new MysqlAdapter($options, new NullOutput());

        // ensure the database is empty for each test
        $adapter->dropDatabase($options['name']);
        $adapter->createDatabase($options['name']);

        // leave the adapter in a disconnected state for each test
        $adapter->disconnect();

        $this->environment = new Environment('test', []);
        $this->environment->setAdapter($adapter);
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
    }

    public function testMigrateHybrid()
    {
        $this->environment->executeMigration(new HybridPhinxMigration(time()), MigrationInterface::UP);
    }
}
