#!/usr/bin/env php
<?php

declare(strict_types=1);

use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Process\Process;

require dirname(__DIR__).'/vendor/autoload.php';
$root = dirname(__DIR__);
(new Dotenv())->bootEnv($root.'/.env');
$binary = $root.'/var/tools/meilisearch';
if (!is_executable($binary)) {
    fwrite(STDERR, "Install the official Meilisearch binary at var/tools/meilisearch first. See README.md.\n");
    exit(1);
}
$process = new Process([$binary], $root, [
    'MEILI_MASTER_KEY' => $_SERVER['MEILI_ADMIN_KEY'] ?? '',
    'MEILI_HTTP_ADDR' => '127.0.0.1:7702',
    'MEILI_DB_PATH' => $root.'/var/meili',
    'MEILI_MAX_INDEXING_MEMORY' => '256MiB',
    'MEILI_MAX_INDEXING_THREADS' => '2',
    'MEILI_EXPERIMENTAL_ALLOWED_IP_NETWORKS' => '127.0.0.1/32',
    'MEILI_NO_ANALYTICS' => 'true',
    'MEILI_ENV' => 'production',
], timeout: null);
exit($process->run(static fn (string $type, string $buffer) => print($buffer)));
