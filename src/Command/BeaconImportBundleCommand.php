<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use App\BasicRum\Beacon\Importer\Process;

use App\BasicRum\Beacon\Catcher\Storage\File;

class BeaconImportBundleCommand extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'basicrum:beacon:import-bundle';

    /** @var  \Doctrine\Persistence\ManagerRegistry */
    private $registry;

    public function __construct(\Doctrine\Persistence\ManagerRegistry $registry)
    {
        $this->registry = $registry;

        parent::__construct();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $storage = new File();

        $bundleFiles = $storage->getBundleFilePaths();

        foreach ($bundleFiles as $file) {
            $reader = new Process\Reader\CatcherService($file);
            $process = new Process($this->registry);

            $output->writeln('Importing bundle: ' . $file);

            $count = $process->runImport($reader);

            $output->writeln('Beacons imported: ' . $count);
        }

        return 0;
    }

}