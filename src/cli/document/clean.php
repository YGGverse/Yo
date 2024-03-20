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

// Apply new configuration rules
echo _('apply new configuration rules...') . PHP_EOL;

foreach ($config->cli->document->crawl->skip->stripos->url as $condition)
{
    echo sprintf(
        _('cleanup documents with url that contain substring "%s"...') . PHP_EOL,
        $condition
    );

    $query = new \Manticoresearch\Query();

    $query->add(
        'url',
        @\Manticoresearch\Utils::escape(
            $condition
        )
    );

    $result = $index->deleteDocuments(
        $query
    );

    echo sprintf(
        _('documents deleted: %d') . PHP_EOL,
        $result['deleted']
    );
}

echo _('new configuration rules apply completed.') . PHP_EOL;

// Optimize indexes
echo _('indexes optimization begin...') . PHP_EOL;

$index->optimize();

echo _('indexes optimization completed.') . PHP_EOL;