<?php

namespace Lhm;

use Phinx\Db\AdapterInterface;


class SqlHelper {

    /**
     * @var AdapterInterface
     */
    protected $adapter;

    public function __construct(AdapterInterface $adapter) {
        $this->adapter = $adapter;
    }

    /**
     * @return string
     */
    public function annotation() {
        return '/* large hadron migration (php) */';
    }

    /**
     * @param string $statement
     * @return string
     */
    public function tagged($statement) {
        return "{$statement} {$this->annotation()}";
    }

    public function versionString() {
        $data = $this->adapter->query("show variables like 'version'");

        if (!count($data) > 0) {
        }

        var_dump($data);die;
        return $data[0]['Value'];
    }

    /**
     * @param string $type
     * @param array $columns
     * @return array
     */
    public function typedColumns($type, array $columns) {
        $typed = [];
        foreach($columns as $column) {
            $typed[] = "{$type}.{$column}";
        }
        return $typed;
    }

    /**
     * @param Table $origin
     * @param Table $destination
     * @return array
     */
    public function quotedIntersectionColumns(Table $origin, Table $destination) {
        $originColumns = $this->quotedColumns($origin);
        $destinationColumns = $this->quotedColumns($destination);

        return array_intersect($destinationColumns, $originColumns);
    }

    /**
     * @param Table $table
     * @return string[]
     */
    public function quotedColumns(Table $table) {
        $columns = [];
        foreach($table->getColumns() as $column) {
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
    public function supportsAtomicSwitch() {
        list($major, $minor, $tiny) = array_map('intval', implode('.', $this->versionString()));

        switch($major) {
        case 4:
            if ($minor && $minor < 2) {
                return false;
            }
            break;
        case 5:
            switch($minor) {
            case 0:
                if ($tiny && $tiny < 52) {
                    return false;
                }
                break;
            case 1:
                return false;
            case 4:
                if ($tiny && $tiny < 4) {
                    return false;
                }
                break;
            case 5:
                if ($tiny && $tiny < 3) {
                    return false;
                }
                break;
            }
        case 6:
            switch($minor) {
            case 0:
                if ($tiny && $tiny < 11) {
                    return false;
                }
                break;
            }
      }

      return true;
    }
}
