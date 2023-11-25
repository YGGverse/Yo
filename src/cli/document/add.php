<?php

// Load dependencies
require_once __DIR__ . '/../../../vendor/autoload.php';

// Init config
$config = json_decode(
    file_get_contents(
        __DIR__ . '/../../../config.json'
    )
);

// Init
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

// Check URL for exist
$result = $index->search('@url "' . trim($argv[1]) . '"')
                ->limit(1)
                ->get();

if ($result->getTotal())
{
    echo sprintf(
        'URL "%s" already exists in "%s" index!' . PHP_EOL,
        $argv[1],
        $config->manticore->index->document->name
    );

    exit;
}

// Add
$result = $index->addDocument(
    [
        'url' => trim($argv[1])
    ]
);

echo sprintf(
    'URL "%s" added to "%s" index: %s' . PHP_EOL,
    $argv[1],
    $config->manticore->index->document->name,
    print_r(
        $result,
        true
    )
);
