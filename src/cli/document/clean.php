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

// Get totals
$total = $index->search('')
               ->option('cutoff', 0)
               ->limit(0)
               ->get()
               ->getTotal();

// Delete duplicates #5
$delete = [];

foreach($index->search('')->limit($total)->get() as $queue)
{
    $duplicates = $index->search('')->filter('crc32url', $queue->crc32url)->limit($total)->get();

    if ($duplicates->getTotal() > 1)
    {
        foreach ($duplicates as $duplicate)
        {
            $delete[$duplicate->crc32url][] = $duplicate->getId();
        }
    }
}

$i = 0;
foreach ($delete as $crc32url => $ids)
{
    $j = 0;
    foreach ($ids as $id)
    {
        $i++;
        $j++;

        // Skip first link
        if ($j == 1) continue;

        // Delete duplicate
        $index->deleteDocument($id);
    }
}

// Free mem
$delete = [];

// Dump operation result
echo sprintf(
    _('duplicated URLs deleted: %s') . PHP_EOL,
    number_format($i)
);

// Optimize indexes
echo _('indexes optimization begin') . PHP_EOL;

$index->optimize();

echo _('indexes optimization completed') . PHP_EOL;