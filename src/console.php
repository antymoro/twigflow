#!/usr/bin/env php
<?php

require __DIR__ . '/../../vendor/autoload.php';

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

// Create a new Symfony Console application
$application = new Application();

// Register the publishResources command
$application->add(new class extends Command {
    protected static $defaultName = 'publish:resources';

    protected function configure()
    {
        $this->setDescription('Publish the default resources to the project directory.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filesystem = new Filesystem();
        $sourceDir = __DIR__ . '/../resources';
        $targetDir = getcwd() . '/resources';

        $output->writeln("Source Directory: $sourceDir");
        $output->writeln("Target Directory: $targetDir");

        if (!$filesystem->exists($targetDir)) {
            $filesystem->mkdir($targetDir);
            $output->writeln("Created target directory: $targetDir");
        }

        $filesystem->mirror($sourceDir . '/templates', $targetDir . '/templates', null, ['override' => false]);
        $output->writeln("Copied templates from $sourceDir/templates to $targetDir/templates");

        $filesystem->mirror($sourceDir . '/modules', $targetDir . '/modules', null, ['override' => false]);
        $output->writeln("Copied modules from $sourceDir/modules to $targetDir/modules");

        // Copy index.php to the project root
        $indexFileSource = __DIR__ . '/../index.php';
        $indexFileTarget = getcwd() . '/index.php';

        $output->writeln("Index File Source: $indexFileSource");
        $output->writeln("Index File Target: $indexFileTarget");

        if (!$filesystem->exists($indexFileTarget)) {
            $filesystem->copy($indexFileSource, $indexFileTarget);
            $output->writeln("Copied index.php to $indexFileTarget");
        }

        $output->writeln('Resources published successfully.');

        return Command::SUCCESS;
    }
});

// Run the application
$application->run();