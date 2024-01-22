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

// Prepare URL
$url      = trim($argv[1]);
$crc32url = crc32($url);

// Check URL for exist
$result = $index->search('')
                ->filter('id', $crc32url)
                ->limit(1)
                ->get();

if ($result->getTotal())
{
    echo sprintf(
        'URL "%s" already exists in "%s" index!' . PHP_EOL,
        $url,
        $config->manticore->index->document->name
    );

    exit;
}

// Add
$result = $index->addDocument(
    [
        'url' => $url
    ],
    $crc32url
);

echo sprintf(
    'URL "%s" added to "%s" index: %s' . PHP_EOL,
    $url,
    $config->manticore->index->document->name,
    print_r(
        $result,
        true
    )
);
