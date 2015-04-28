<?php

namespace Lhm;

use Phinx\Db\Adapter\AdapterInterface;
use Phinx\Db\Table;
use Phinx\Db\Table\Column;


class SqlHelper
{

    /**
     * @var AdapterInterface
     */
    protected $adapter;

    public function __construct(AdapterInterface $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * @return string
     */
    public function annotation()
    {
        return '/* large hadron migration (php) */';
    }

    /**
     * @param string $statement
     * @return string
     */
    public function tagged($statement)
    {
        return "{$statement} {$this->annotation()}";
    }

    /**
     * @return string
     */
    public function versionString()
    {
        $data = $this->adapter->query("show variables like 'version'");

        return $data[0];
    }

    /**
     * Extract the primary key of a table.
     *
     * @param Table $table
     * @return string
     */
    public function extractPrimaryKey(Table $table)
    {
        $tableName = $table->getName();
        $databaseName = $this->adapter->getOption('name');

        $query = implode(" ", [
            'SELECT `COLUMN_NAME`',
            'FROM `information_schema`.`COLUMNS`',
            "WHERE (`TABLE_SCHEMA` = '{$databaseName}')",
            "AND (`TABLE_NAME` = '{$tableName}')",
            "AND (`COLUMN_KEY` = 'PRI');"
        ]);

        $result = $this->adapter->query($query);

        if ($result instanceof \PDOStatement) {
            return $result->fetchColumn(0);
        }

        if (is_array($result)) {
            return $result[0];
        }

        return $result;
    }

    /**
     * @return Column[]
     */
    public function tableColumns(Table $table)
    {
        $databaseName = $this->adapter->getOption('name');

        $schema = $this->adapter->fetchAll(implode(" ", [
            "SELECT * FROM information_schema.columns",
            "WHERE table_name = '{$table->GetName()}'",
            "AND table_schema ='{$databaseName}'"

        ]));

        $columns = [];

        foreach ($schema as $definition) {
            $column = new Column();
            $column->setName($definition['COLUMN_NAME']);

            $columns[] = $column;
        }

        return $columns;
    }

    /**
     * @param string $type
     * @param array $columns
     * @return array
     */
    public function typedColumns($type, array $columns)
    {
        $typed = [];

        foreach ($columns as $column) {
            $typed[] = "{$type}.{$column}";
        }

        return $typed;
    }

    /**
     * @param Table $origin
     * @param Table $destination
     * @return array
     */
    public function quotedIntersectionColumns(Table $origin, Table $destination)
    {
        $originColumns = $this->quotedColumns($origin);
        $destinationColumns = $this->quotedColumns($destination);

        return array_intersect($destinationColumns, $originColumns);
    }

    /**
     * @param Table $table
     * @return string[]
     */
    public function quotedColumns(Table $table)
    {
        $columns = [];

        foreach ($this->tableColumns($table) as $column) {

            $columns[] = $this->adapter->quoteColumnName($column->getName());
        }

        return $columns;
    }

    /**
     * @link https://github.com/soundcloud/lhm/blob/master/lib/lhm/sql_helper.rb
     *
     * Older versions of MySQL contain an atomic rename bug affecting bin
     * log order. Affected versions extracted from bug report:
     *
     *   http://bugs.mysql.com/bug.php?id=39675
     *
     * More Info: http://dev.mysql.com/doc/refman/5.5/en/metadata-locking.html
     *
     * @return boolean
     */
    public function supportsAtomicSwitch()
    {
        $version = $this->versionString();

        list($major, $minor, $tiny) = array_map('intval', explode('.', $version));

        switch ($major) {
            case 4:
                if ($minor < 2) {
                    return false;
                }
                break;
            case 5:
                switch ($minor) {
                    case 0:
                        if ($tiny < 52) {
                            return false;
                        }
                        break;
                    case 1:
                        return false;
                    case 4:
                        if ($tiny < 4) {
                            return false;
                        }
                        break;
                    case 5:
                        if ($tiny < 3) {
                            return false;
                        }
                        break;
                }
                break;
            case 6:
                switch ($minor) {
                    case 0:
                        if ($tiny < 11) {
                            return false;
                        }
                        break;
                }
                break;
        }

        return true;
    }
}
