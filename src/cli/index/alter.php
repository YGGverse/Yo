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

// Validate request
if (empty($argv[1]))
{
    exit(
        _('Operation name required!') . PHP_EOL
    );
}

if (empty($argv[2]))
{
    exit(
        _('Operated column required!') . PHP_EOL
    );
}

if ($argv[1] == 'add')
{
    if (empty($argv[3]))
    {
        exit(
            _('Operated column type required!') . PHP_EOL
        );
    }

    if (!in_array($argv[3], ['integer', 'text']))
    {
        exit(
            _('Undefined column type!') . PHP_EOL
        );
    }
}

// Route query
switch ($argv[1])
{
    case 'add':

        if ($result = $index->alter($argv[1], $argv[2], $argv[3]))
        {
            echo sprintf(
                'row "%s" with type "%s" successfully added to index "%s" with result: %s' . PHP_EOL,
                $argv[2],
                $argv[3],
                $config->manticore->index->document->name,
                print_r(
                    $result,
                    true
                )
            );
        }

    break;

    case 'drop':

        if ($result = $index->alter($argv[1], $argv[2]))
        {
            echo sprintf(
                'row "%s" successfully deleted from index "%s" with result: %s' . PHP_EOL,
                $argv[2],
                $config->manticore->index->document->name,
                print_r(
                    $result,
                    true
                )
            );
        }

    break;

    default:

        echo _('Unknown operation!') . PHP_EOL;
}