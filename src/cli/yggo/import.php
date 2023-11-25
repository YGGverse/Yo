<?php

// Load dependencies
require_once __DIR__ . '/../../../vendor/autoload.php';

// Init config
$config = json_decode(
    file_get_contents(
        __DIR__ . '/../../../config.json'
    )
);

// Init manticore
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

// Connect Yggo DB
try
{
    $yggo = new PDO(
        'mysql:dbname=' . $argv[5] . ';host=' . $argv[1] . ';port=' . $argv[2] . ';charset=utf8',
        $argv[3],
        $argv[4],
        [
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'
        ]
    );

    $yggo->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

    $yggo->setAttribute(
        PDO::ATTR_DEFAULT_FETCH_MODE,
        PDO::FETCH_OBJ
    );

    $yggo->setAttribute(
        PDO::ATTR_TIMEOUT,
        600
    );
}

catch (Exception $error)
{
    var_dump(
        $error
    );

    exit;
}

$start = 0;
$limit = 100;

$total = $yggo->query('SELECT COUNT(*) AS `total` FROM `hostPage`

                                                  WHERE `hostPage`.`httpCode` = 200
                                                    AND `hostPage`.`timeUpdated` IS NOT NULL
                                                    AND `hostPage`.`mime` IS NOT NULL
                                                    AND `hostPage`.`size` IS NOT NULL')->fetch()->total;

$processed = 0;

for ($i = 0; $i <= $total; $i++)
{
    $query = $yggo->query('SELECT `hostPage`.`hostPageId`,
                                  `hostPage`.`httpCode`,
                                  `hostPage`.`mime`,
                                  `hostPage`.`size`,
                                  `hostPage`.`timeUpdated`,
                                  `hostPage`.`uri`,

                                  `host`.`scheme`,
                                  `host`.`name`,
                                  `host`.`port`,

                                  (
                                        SELECT `hostPageDescription`.`title` FROM `hostPageDescription`
                                                                             WHERE `hostPageDescription`.`hostPageId` = `hostPage`.`hostPageId`
                                                                             ORDER BY `hostPageDescription`.`timeAdded` DESC
                                                                             LIMIT 1
                                   ) AS `title`,

                                  (
                                        SELECT `hostPageDescription`.`description` FROM `hostPageDescription`
                                                                                   WHERE `hostPageDescription`.`hostPageId` = `hostPage`.`hostPageId`
                                                                                   ORDER BY `hostPageDescription`.`timeAdded` DESC
                                                                                   LIMIT 1
                                   ) AS `description`,

                                  (
                                        SELECT `hostPageDescription`.`keywords` FROM `hostPageDescription`
                                                                                WHERE `hostPageDescription`.`hostPageId` = `hostPage`.`hostPageId`
                                                                                ORDER BY `hostPageDescription`.`timeAdded` DESC
                                                                                LIMIT 1
                                   ) AS `keywords`

                                  FROM `hostPage`
                                  JOIN `host` ON (`host`.`hostId` = `hostPage`.`hostId`)

                                  WHERE `hostPage`.`httpCode` = 200
                                    AND `hostPage`.`timeUpdated` IS NOT NULL
                                    AND `hostPage`.`mime` IS NOT NULL
                                    AND `hostPage`.`size` IS NOT NULL

                                  GROUP BY `hostPage`.`hostPageId`

                                  LIMIT ' . $start . ',' . $limit);


    foreach ($query->fetchAll() as $remote)
    {
        $url = $remote->scheme . '://' . $remote->name . ($remote->port ? ':' . $remote->port : false) . $remote->uri;

        // Check for unique URL requested
        if (isset($argv[6]))
        {
            $local = $index->search('@url "' . trim($argv[1]) . '"')
                           ->limit(1)
                           ->get();

            if ($local->getTotal())
            {
                // Result
                echo sprintf(
                    _('[%s/%s] [skip duplicate] %s') . PHP_EOL,
                    $processed++,
                    $total,
                    $url
                );

                continue;
            }
        }

        $index->addDocument(
            [
                'url'         => $url,
                'time'        => $remote->timeUpdated,
                'code'        => $remote->httpCode,
                'mime'        => $remote->mime,
                'size'        => $remote->size,
                'title'       => $remote->title,
                'description' => $remote->description,
                'keywords'    => $remote->keywords
            ]
        );

        // Result
        echo sprintf(
            _('[%s/%s] [add] %s') . PHP_EOL,
            $processed++,
            $total,
            $url
        );
    }

    // Update queue offset
    $start = $start + $limit;
}

// Done
echo _('import completed!') . PHP_EOL;