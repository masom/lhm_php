<?php


namespace Lhm\Tests\Integration;

use Lhm\Entangler;
use Lhm\SqlHelper;
use Phinx\Db\Adapter\AdapterInterface;
use Phinx\Db\Table\Column;
use PHPUnit\Framework\TestCase;

class EntanglerTest extends TestCase
{

    /**
     * @var MyEntangler
     */
    protected $entangler;

    /**
     * @var AdapterInterface
     */
    protected $adapter;
    /**
     * @var \Phinx\Db\Table
     */
    protected $origin;
    /**
     * @var \Lhm\Table
     */
    protected $destination;

    /**
     * @var SqlHelper
     */
    protected $sqlHelper;

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

        $this->adapter
            ->expects($this->any())
            ->method('quoteTableName')
            ->will($this->returnCallback(function ($name) {
                return "'{$name}'";
            }));

        $this->origin = $this->getMockBuilder(\Phinx\Db\Table::class)->disableOriginalConstructor()->getMock();
        $this->destination = $this->getMockBuilder(\Lhm\Table::class)->disableOriginalConstructor()->getMock();

        $this->sqlHelper = new SqlHelper($this->adapter);

        $this->entangler = new MyEntangler($this->adapter, $this->origin, $this->destination, $this->sqlHelper);
    }

    protected function tearDown()
    {
        unset($this->entangler, $this->adapter, $this->origin, $this->destination, $this->sqlHelper);
        parent::tearDown();
    }

    public function testValidate_OriginNotFound()
    {

        $this->adapter
            ->expects($this->once())
            ->method('hasTable')
            ->with('users')
            ->will($this->returnValue(false));

        $this->origin
            ->expects($this->atLeastOnce())
            ->method('getName')
            ->will($this->returnValue('users'));

        try {

            $this->entangler->validate();
            $this->fail();
        } catch (\Exception $e) {
            $this->assertEquals('Table `users` does not exists.', $e->getMessage());
        }
    }

    public function testValidate_DestinationNotFound()
    {

        $this->adapter
            ->expects($this->atLeastOnce())
            ->method('hasTable')
            ->will($this->returnValueMap([['users', true], ['users_new', false]]));

        $this->origin
            ->expects($this->atLeastOnce())
            ->method('getName')
            ->will($this->returnValue('users'));

        $this->destination
            ->expects($this->atLeastOnce())
            ->method('getName')
            ->will($this->returnValue('users_new'));

        try {
            $this->entangler->validate();
            $this->fail();
        } catch (\Exception $e) {
            $this->assertEquals('Table `users_new` does not exists.', $e->getMessage());
        }
    }

    public function testBefore()
    {
        /** @var Column[] $originColumns */
        $originColumns = [
            new Column(),
            new Column(),
            new Column()
        ];
        $originColumns[0]->setName('id');
        $originColumns[1]->setName('name');
        $originColumns[2]->setName('something');

        /** @var Column[] $destinationColumns */
        $destinationColumns = [
            new Column(),
            new Column(),
            new Column()
        ];
        $destinationColumns[0]->setName('id');
        $destinationColumns[1]->setName('name');
        $destinationColumns[2]->setName('something_else');

        $this->origin
            ->expects($this->atLeastOnce())
            ->method('getName')
            ->will($this->returnValue('users'));

        $this->origin
            ->expects($this->atLeastOnce())
            ->method('getColumns')
            ->will($this->returnValue($originColumns));


        $this->destination
            ->expects($this->atLeastOnce())
            ->method('getName')
            ->will($this->returnValue('users_new'));

        $this->destination
            ->expects($this->atLeastOnce())
            ->method('getColumns')
            ->will($this->returnValue($destinationColumns));

        $this->destination
            ->expects($this->atLeastOnce())
            ->method('getRenamedColumns')
            ->will($this->returnValue([]));

        $expectations = [
            "SELECT `COLUMN_NAME` FROM `information_schema`.`COLUMNS` WHERE (`TABLE_SCHEMA` = '') AND (`TABLE_NAME` = 'users') AND (`COLUMN_KEY` = 'PRI');",

            implode("\n ", [
                'CREATE TRIGGER lhmt_delete_users',
                "AFTER DELETE ON 'users' FOR EACH ROW",
                "DELETE IGNORE FROM 'users_new' /* large hadron migration (php) */",
                "WHERE 'users_new'.`id` = OLD.`id` /* large hadron migration (php) */"
            ]),
            implode("\n ", [
                'CREATE TRIGGER lhmt_insert_users',
                "AFTER INSERT ON 'users' FOR EACH ROW",
                "REPLACE INTO 'users_new' (`id`,`name`) /* large hadron migration (php) */",
                'VALUES (NEW.`id`,NEW.`name`) /* large hadron migration (php) */'
            ]),
            implode("\n ", [
                'CREATE TRIGGER lhmt_update_users',
                "AFTER UPDATE ON 'users' FOR EACH ROW",
                "REPLACE INTO 'users_new' (`id`,`name`) /* large hadron migration (php) */",
                'VALUES (NEW.`id`,NEW.`name`) /* large hadron migration (php) */'
            ])
        ];

        $matcher = $this->atLeastOnce();
        $this->adapter
            ->expects($matcher)
            ->method('query')
            ->will($this->returnCallback(function ($query) use ($matcher, &$expectations) {
                $this->assertEquals($expectations[$matcher->getInvocationCount() - 1], $query);
                if ($matcher->getInvocationCount() === 1) {
                    return 'id';
                }
            }));

        $this->entangler->before();
    }

    public function testAfter()
    {
        $this->origin
            ->expects($this->atLeastOnce())
            ->method('getName')
            ->will($this->returnValue('users'));

        $expectations = [
            'DROP TRIGGER IF EXISTS lhmt_delete_users /* large hadron migration (php) */',
            'DROP TRIGGER IF EXISTS lhmt_insert_users /* large hadron migration (php) */',
            'DROP TRIGGER IF EXISTS lhmt_update_users /* large hadron migration (php) */'
        ];

        $matcher = $this->atLeastOnce();
        $this->adapter
            ->expects($matcher)
            ->method('query')
            ->will($this->returnCallback(function ($query) use ($matcher, &$expectations) {
                $this->assertEquals($expectations[$matcher->getInvocationCount() - 1], $query);
            }));
        $this->entangler->after();
    }

    public function testCreateDeleteTrigger()
    {
        $this->origin
            ->expects($this->atLeastOnce())
            ->method('getName')
            ->will($this->returnValue('users'));

        $this->destination
            ->expects($this->atLeastOnce())
            ->method('getName')
            ->will($this->returnValue('users_new'));

        $this->adapter
            ->expects( $this->once() )
            ->method('query')
            ->will($this->returnValue("id"));

        $this->assertEquals(
            implode("\n ", [
                'CREATE TRIGGER lhmt_delete_users',
                "AFTER DELETE ON 'users' FOR EACH ROW",
                "DELETE IGNORE FROM 'users_new' /* large hadron migration (php) */",
                "WHERE 'users_new'.`id` = OLD.`id`"
            ]),
            $this->entangler->createDeleteTrigger()
        );
    }

    public function testCreateInsertTrigger()
    {
        /** @var Column[] $originColumns */
        $originColumns = [
            new Column(),
            new Column(),
            new Column()
        ];
        $originColumns[0]->setName('id');
        $originColumns[1]->setName('name');
        $originColumns[2]->setName('something');

        /** @var Column[] $destinationColumns */
        $destinationColumns = [
            new Column(),
            new Column(),
            new Column()
        ];
        $destinationColumns[0]->setName('id');
        $destinationColumns[1]->setName('name');
        $destinationColumns[2]->setName('something_else');

        $this->origin
            ->expects($this->atLeastOnce())
            ->method('getName')
            ->will($this->returnValue('users'));

        $this->origin
            ->expects($this->atLeastOnce())
            ->method('getColumns')
            ->will($this->returnValue($originColumns));

        $this->destination
            ->expects($this->atLeastOnce())
            ->method('getName')
            ->will($this->returnValue('users_new'));

        $this->destination
            ->expects($this->atLeastOnce())
            ->method('getColumns')
            ->will($this->returnValue($destinationColumns));

        $this->destination
            ->expects($this->atLeastOnce())
            ->method('getRenamedColumns')
            ->will($this->returnValue([]));

        $this->assertEquals(
            implode("\n ", [
                'CREATE TRIGGER lhmt_insert_users',
                "AFTER INSERT ON 'users' FOR EACH ROW",
                "REPLACE INTO 'users_new' (`id`,`name`) /* large hadron migration (php) */",
                'VALUES (NEW.`id`,NEW.`name`)'
            ]),
            $this->entangler->createInsertTrigger()
        );
    }

    public function testCreateUpdateTrigger()
    {
        /** @var Column[] $originColumns */
        $originColumns = [
            new Column(),
            new Column(),
            new Column()
        ];
        $originColumns[0]->setName('id');
        $originColumns[1]->setName('name');
        $originColumns[2]->setName('something');

        /** @var Column[] $destinationColumns */
        $destinationColumns = [
            new Column(),
            new Column(),
            new Column()
        ];
        $destinationColumns[0]->setName('id');
        $destinationColumns[1]->setName('name');
        $destinationColumns[2]->setName('something_else');

        $this->origin
            ->expects($this->atLeastOnce())
            ->method('getName')
            ->will($this->returnValue('users'));

        $this->origin
            ->expects($this->atLeastOnce())
            ->method('getColumns')
            ->will($this->returnValue($originColumns));

        $this->destination
            ->expects($this->atLeastOnce())
            ->method('getName')
            ->will($this->returnValue('users_new'));

        $this->destination
            ->expects($this->atLeastOnce())
            ->method('getColumns')
            ->will($this->returnValue($destinationColumns));

        $this->destination
            ->expects($this->atLeastOnce())
            ->method('getRenamedColumns')
            ->will($this->returnValue([]));

        $this->assertEquals(
            implode("\n ", [
                'CREATE TRIGGER lhmt_update_users',
                "AFTER UPDATE ON 'users' FOR EACH ROW",
                "REPLACE INTO 'users_new' (`id`,`name`) /* large hadron migration (php) */",
                'VALUES (NEW.`id`,NEW.`name`)'
            ]),
            $this->entangler->createUpdateTrigger()
        );
    }

    public function testTrigger()
    {
        $this->origin
            ->expects($this->once())
            ->method('getName')
            ->will($this->returnValue('avatars'));
        $this->assertEquals('lhmt_test_avatars', $this->entangler->trigger('test'));
    }
}

class MyEntangler extends Entangler
{
    public function before()
    {
        parent::before();
    }

    public function after()
    {
        parent::after();
    }

    public function execute()
    {
        parent::execute();
    }

    public function revert()
    {
        parent::revert();
    }

    public function entangle()
    {
        return parent::entangle();
    }

    public function createInsertTrigger()
    {
        return parent::createInsertTrigger();
    }

    public function createUpdateTrigger()
    {
        return parent::createUpdateTrigger();
    }

    public function createDeleteTrigger()
    {
        return parent::createDeleteTrigger();
    }

    public function untangle()
    {
        return parent::untangle();
    }

    public function validate()
    {
        parent::validate();
    }

    public function trigger($type)
    {
        return parent::trigger($type);
    }
}
