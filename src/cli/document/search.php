<?php

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

// Init search query
$query = $index->search(
    $argv[1]
);

// Apply search options (e.g. field_weights)
foreach ($config->webui->search->options as $key => $value)
{
  if (is_int($value) || is_string($value))
  {
    $query->option(
      $key,
      $value
    );
  }

  else
  {
    $query->option(
      $key,
      (array) $value
    );
  }
}

// Search
foreach($query->limit($argv[2] ? $argv[2] : 10)->get() as $result)
{
    var_dump(
        $result
    );
}
