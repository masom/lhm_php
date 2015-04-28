<?php

namespace Lhm;

use Phinx\Db\Table;
use Phinx\Db\Adapter\AdapterInterface;

/**
 * Switched the origin table with the destination using an atomic rename.
 */
class AtomicSwitcher extends Command
{

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

    /**
     * @var array
     */
    protected $options;

    /**
     * @param AdapterInterface $adapter
     * @param Table $origin
     * @param Table $destination
     * @param array $options
     */
    public function __construct(AdapterInterface $adapter, Table $origin, Table $destination, array $options = [])
    {
        $this->options = $options + [
                'retry_sleep_time' => 10,
                'max_retries' => 600,
                'archive_name' => "{$origin->getName()}_" . gmdate('Y_m_d_H_i_s')
            ];

        $this->adapter = $adapter;
        $this->origin = $origin;
        $this->destination = $destination;
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * Set an option
     * @param string $name
     * @param mixed $value
     */
    public function setOption($name, $value)
    {
        $this->options[$name] = $value;
    }

    /**
     * @return array
     */
    protected function statements()
    {
        $archiveName = $this->options['archive_name'];
        return [
            "RENAME TABLE {$this->origin->getName()} TO {$archiveName}, {$this->destination->getName()} TO {$this->origin->getName()}",
        ];
    }

    /**
     * @throws \RuntimeException
     */
    protected function validate()
    {
        if ($this->adapter->hasTable($this->origin->getName()) && $this->adapter->hasTable($this->destination->getName())) {
            return;
        }

        throw new \RuntimeException("Table `{$this->origin->getName()}` and `{$this->destination->getName()}` must exist.");
    }


    /**
     * Execute the atomic rename.
     */
    protected function execute()
    {
        $retries = 0;

        while ($retries < $this->options['max_retries']) {
            $retries++;

            try {
                foreach ($this->statements() as $statement) {
                    $this->adapter->query($statement);
                }

                return;
            } catch (\Exception $e) {
                if ($this->shouldRetryException($e)) {
                    sleep($this->options['retry_sleep_time']);

                    //TODO log the retry
                    continue;
                }

                throw $e;
            }
        }
    }

    /**
     * Determine if the operation should be retried.
     */
    protected function shouldRetryException(\Exception $e)
    {
        return preg_match('/Lock wait timeout exceeded/', $e->getMessage());
    }
}
