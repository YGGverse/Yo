<?php

// Load dependencies
require_once __DIR__ . '/../../../vendor/autoload.php';

// Init config
$config = json_decode(
    file_get_contents(
        __DIR__ . '/../../../config.json'
    )
);

// Validate request
if (empty($argv[1]))
{
  exit(
    _('search query required as the first argument!') . PHP_EOL
  );
}

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

// Search
foreach($index->search($argv[1])
              ->limit(isset($argv[2]) ? (int) $argv[2] : 10)
              ->get() as $result)
{
    var_dump(
        $result
    );
}
