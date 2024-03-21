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

    // Process conditions match indexer settings
    if (
        isset($config->manticore->index->document->settings->min_word_len)
        &&
        mb_strlen($condition) < $config->manticore->index->document->settings->min_word_len
    ) {

        echo sprintf(
            _('condition skipped as "min_word_len" value is "%d"') . PHP_EOL,
            $config->manticore->index->document->settings->min_word_len
        );

        continue;
    }

    if (
        isset($config->manticore->index->document->settings->min_prefix_len)
        &&
        mb_strlen($condition) < $config->manticore->index->document->settings->min_prefix_len
    ) {

        echo sprintf(
            _('condition skipped as "min_prefix_len" value is "%d"') . PHP_EOL,
            $config->manticore->index->document->settings->min_prefix_len
        );

        continue;
    }

    // Begin search query
    $documents = 0;
    $snaps = 0;

    foreach(
        $index->search(
            sprintf(
                '@url "%s"',
                $condition
            )
        )->limit(
            isset($argv[1]) ? (int) $argv[1] : 10
        )->get() as $document)
    {
        // Delete found document by it ID
        $result = $index->deleteDocument(
            $document->getId()
        );

        // Delete local snaps
        $location = sprintf(
            '%s/%s',
            '/' === substr($config->snap->storage->local->directory, 0, 1) ? $config->snap->storage->local->directory
                                                                           : __DIR__ . '/../../../' . $config->snap->storage->local->directory,
            implode(
                '/',
                str_split(
                    md5(
                        $document->get('url')
                    )
                )
            )
        );

        if (file_exists($location))
        {
            if (unlink($location))
            {
                $snaps++;
            }
        }

        $documents++;

        // @TODO delete remote snaps
    }

    echo sprintf(
        _('deleted documents: %d') . PHP_EOL,
        $documents
    );

    echo sprintf(
        _('deleted local snaps: %d') . PHP_EOL,
        $snaps
    );
}

echo _('new configuration rules apply completed.') . PHP_EOL;

// Optimize indexes
echo _('indexes optimization begin...') . PHP_EOL;

$index->optimize();

echo _('indexes optimization completed.') . PHP_EOL;