<?php


namespace Lhm\Console;

use Symfony\Component\Console\Application;


class LhmApplication extends Application
{
    /**
     * Class Constructor.
     *
     * Initialize the Lhm console application.
     *
     * @param string $version The Application Version
     */
    public function __construct($version = '0.4.0')
    {
        parent::__construct('Large Hadron Migrator', $version);

        $this->addCommands([
            new Command\Cleanup()
        ]);
    }
}
