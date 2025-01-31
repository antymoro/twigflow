<?php

namespace App\Console;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class Commands
{
    public static function publishResources()
    {
        $app = new Application();

        $app->add(new class extends Command {
            protected static $defaultName = 'publish:resources';

            protected function configure()
            {
                $this->setDescription('Publish the default resources to the project directory.');
            }

            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                $filesystem = new Filesystem();
                $sourceDir = __DIR__ . '/../../resources';
                $targetDir = getcwd() . '/resources';

                if (!$filesystem->exists($targetDir)) {
                    $filesystem->mkdir($targetDir);
                }

                $filesystem->mirror($sourceDir . '/templates', $targetDir . '/templates', null, ['override' => false]);
                $filesystem->mirror($sourceDir . '/modules', $targetDir . '/modules', null, ['override' => false]);

                // Copy index.php to the project root
                $indexFileSource = __DIR__ . '/../../index.php';
                $indexFileTarget = getcwd() . '/index.php';

                if (!$filesystem->exists($indexFileTarget)) {
                    $filesystem->copy($indexFileSource, $indexFileTarget);
                }

                $output->writeln('Resources published successfully.');

                return Command::SUCCESS;
            }
        });

        $app->run();
    }
}