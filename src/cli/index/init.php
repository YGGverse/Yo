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
    $config->manticore->index->document
);

// Request options
if (isset($argv[1]))
{
    switch ($argv[1])
    {
        case 'reset':

            $result = $index->drop(true);

            echo sprintf(
                'index "%s" deleted: %s' . PHP_EOL,
                $config->manticore->index->document,
                print_r(
                    $result,
                    true
                )
            );

        break;
    }
}

// Init index
$result = $index->create(
    [
        'url' =>
        [
            'type' => 'text'
        ],
        'title' =>
        [
            'type' => 'text'
        ],
        'description' =>
        [
            'type' => 'text'
        ],
        'keywords' =>
        [
            'type' => 'text'
        ],
        'mime' =>
        [
            'type' => 'text'
        ],
        'code' =>
        [
            'type' => 'integer'
        ],
        'size' =>
        [
            'type' => 'integer'
        ],
        'time' =>
        [
            'type' => 'integer'
        ]
    ]
);

echo sprintf(
    'index "%s" created: %s' . PHP_EOL,
    $config->manticore->index->document,
    print_r(
        $result,
        true
    )
);