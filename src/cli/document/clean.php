<?php

// Prevent multi-thread execution
$semaphore = sem_get(
    crc32(
        __DIR__ . '.yo.cli.document.clean'
    ),
    1
);

if (false === sem_acquire($semaphore, true))
{
  exit ('process execution locked by another thread!' . PHP_EOL);
}

// Load dependencies
require_once __DIR__ . '/../../../vendor/autoload.php';

// Init config
$config = json_decode(
    file_get_contents(
        __DIR__ . '/../../../config.json'
    )
);

// Init client
$client = new \Manticoresearch\Client(
    [
        'host' => $config->manticore->server->host,
        'port' => $config->manticore->server->port,
    ]
);

// Init index
$index = $client->index(
    $config->manticore->index->document->name
);

// Optimize indexes
echo _('indexes optimization begin') . PHP_EOL;

$index->optimize();

echo _('indexes optimization completed') . PHP_EOL;