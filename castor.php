<?php

declare(strict_types=1);

use Castor\Attribute\AsTask;
use function Castor\run;

#[AsTask(description: 'Refresh the project catalog and publish search indexes')]
function refresh(): void
{
    run(['php', 'bin/console', 'app:import', '--dataset=organizations', '--refresh', '--no-debug']);
    run(['php', 'bin/console', 'app:import', '--dataset=projects', '--refresh', '--no-debug']);
    run(['php', 'bin/console', 'app:index', '--no-debug']);
}

#[AsTask(description: 'Run tests, static analysis and application checks')]
function check(): void
{
    run(['vendor/bin/phpunit']);
    run(['vendor/bin/phpstan', 'analyse', '--no-progress']);
    run(['php', 'bin/console', 'lint:container']);
    run(['php', 'bin/console', 'lint:twig', 'templates/']);
    run(['php', 'bin/console', 'lint:yaml', 'config/']);
    run(['php', 'bin/console', 'doctrine:schema:validate']);
}

#[AsTask(description: 'Build the FrankenPHP image from published dependencies')]
function image(): void
{
    run([...(new \Symfony\Component\Process\ExecutableFinder())->find('docker-buildx') ? ['docker-buildx'] : ['docker', 'buildx'], 'build', '--load', '-t', 'global-giving:local', '.']);
}
