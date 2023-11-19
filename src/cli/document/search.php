<?php

// Load dependencies
require_once __DIR__ . '/../../../vendor/autoload.php';

// Init config
$config = json_decode(
    file_get_contents(
        __DIR__ . '/../../config.json'
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
    $config->manticore->index->document
);

// Search
foreach($index->search($argv[1])
              ->limit($argv[2] ? $argv[2] : 10)
              ->get() as $result)
{
    var_dump(
        $result
    );
}
