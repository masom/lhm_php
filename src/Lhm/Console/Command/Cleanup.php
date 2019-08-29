<?php


namespace Lhm\Console\Command;


use Lhm\Lhm;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Phinx\Console\Command\AbstractCommand;
use Symfony\Bridge\Monolog\Handler\ConsoleHandler;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Cleanup extends AbstractCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this->setName('cleanup')
            ->setDescription('Cleanup LHM tables, old archives and triggers')
            ->setHelp(sprintf(
                '%sCleanup LHM tables, old archives and triggers. Defaults to a dry-run unless --run is specified.%s',
                PHP_EOL,
                PHP_EOL
            ));

        $this->addOption('--environment', '-e', InputOption::VALUE_REQUIRED, 'The target environment');

        // Apply operations
        $this->addOption('--run', 'r', InputOption::VALUE_NONE, 'Apply the cleanup operations.');

        // Archives older than the specified UTC date + time will be dropped.
        $this->addOption('--until', 'u', InputOption::VALUE_REQUIRED, 'Drop archive tables older than the specified date at UTC (YYYY-MM-DD_hh:mm:ss).');
    }

    /**
     * Cleanup the database
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->getConfig()) {
            $this->loadConfig($input, $output);
        }

        $this->loadManager($input, $output);

        $environment = $input->getOption('environment');

        if (null === $environment) {
            $environment = $this->getConfig()->getDefaultEnvironment();
            $output->writeln('<comment>warning</comment> no environment specified, defaulting to: ' . $environment);
        } else {
            $output->writeln('<info>using environment</info> ' . $environment);
        }

        $envOptions = $this->getConfig()->getEnvironment($environment);
        if (isset($envOptions['adapter'])) {
            $output->writeln('<info>using adapter</info> ' . $envOptions['adapter']);
        }

        if (isset($envOptions['name'])) {
            $output->writeln('<info>using database</info> ' . $envOptions['name']);
        }

        $run = (bool)$input->getOption('run');
        $until = $input->getOption('until');

        if ($until) {
            $until = \DateTime::createFromFormat('Y-m-d_H:i:s', $until, new \DateTimeZone('UTC'));

            if ($until === false) {
                throw new \InvalidArgumentException("The specified date in `until` is invalid.");
            }
        }


        if ($run) {
            $output->writeln('<info>LHM will drop temporary tables and triggers</info>');
        } else {
            $output->writeln('<info>Executing dry-run</info>');
        }

        $options = [];
        if ($until) {
            $options['until'] = $until;
            $output->writeln('<warning>LHM will drop archives created before ' . $until->format('Y-M-D H:i:s T') . '</warning>');
        }


        $output->setVerbosity(OutputInterface::VERBOSITY_VERY_VERBOSE);
        $handler = new ConsoleHandler($output);

        $logger = new Logger('lhm');
        $logger->pushHandler($handler);

        Lhm::setLogger($logger);

        $environment = $this->manager->getEnvironment($environment);

        Lhm::setAdapter($environment->getAdapter());
        Lhm::cleanup($run, $options);
    }
}
