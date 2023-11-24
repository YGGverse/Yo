<?php

// Prevent multi-thread execution
$semaphore = sem_get(crc32('yo.cli.document.crawl'), 1);

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

// Init search
$search = new \Manticoresearch\Search(
    $client
);

$search->setIndex(
    $config->manticore->index->document
);

$search->match(
    '*',
    'url'
);

$search->sort(
    'time',
    'asc'
);

$search->limit(
    $config->cli->document->crawl->queue->limit
);

// Init index
$index = $client->index(
    $config->manticore->index->document
);

// Begin queue
foreach($search->get() as $document)
{
    // Update index time
    $index->updateDocument(
        [
            'time' => time()
        ],
        $document->getId()
    );

    // Request remote URL
    $request = curl_init(
        $document->get('url')
    );

    curl_setopt(
        $request,
        CURLOPT_RETURNTRANSFER,
        true
    );

    if ($response = curl_exec($request))
    {
        // Update HTTP code
        if ($code = curl_getinfo($request, CURLINFO_HTTP_CODE))
        {
            $index->updateDocument(
                [
                    'code' => $code
                ],
                $document->getId()
            );

        } else continue;

        // Update size
        if ($size = curl_getinfo($request, CURLINFO_SIZE_DOWNLOAD))
        {
            $index->updateDocument(
                [
                    'size' => $size
                ],
                $document->getId()
            );

        } else continue;

        // Update MIME type
        if ($mime = curl_getinfo($request, CURLINFO_CONTENT_TYPE))
        {
            $index->updateDocument(
                [
                    'mime' => $mime
                ],
                $document->getId()
            );

        } else continue;

        // DOM crawler
        if (false !== stripos($mime, 'text/html'))
        {
            $crawler = new Symfony\Component\DomCrawler\Crawler();
            $crawler->addHtmlContent(
                $response
            );

            // Get title
            $title = '';
            foreach ($crawler->filter('head > title')->each(function($node) {

                return $node->text();

            }) as $value) {

                $title = html_entity_decode(
                    $value
                );
            }

            // Get description
            $description = '';
            foreach ($crawler->filter('head > meta[name="description"]')->each(function($node) {

                return $node->attr('content');

            }) as $value) {

                $description = html_entity_decode(
                    $value
                );
            }

            // Get keywords
            $keywords = '';
            foreach ($crawler->filter('head > meta[name="keywords"]')->each(function($node) {

                return $node->attr('content');

            }) as $value) {

                $keywords = html_entity_decode(
                    $value
                );
            }

            // Replace document
            // https://github.com/manticoresoftware/manticoresearch-php/issues/10#issuecomment-612685916
            $data =
            [
                'url'         => $document->get('url'),
                'title'       => $title,
                'description' => $description,
                'keywords'    => $keywords,
                'code'        => $code,
                'size'        => $size,
                'mime'        => $mime,
                'time'        => time()
            ];

            $result = $index->replaceDocument(
                $data,
                $document->getId()
            );

            echo sprintf(
                'index "%s" updated: %s %s' . PHP_EOL,
                $config->manticore->index->document,
                print_r(
                    $result,
                    true
                ),
                print_r(
                    $data,
                    true
                ),
            );

            // Crawl documents
            $documents = [];

            $scheme = parse_url($document->get('url'), PHP_URL_SCHEME);
            $host   = parse_url($document->get('url'), PHP_URL_HOST);
            $port   = parse_url($document->get('url'), PHP_URL_PORT);

            foreach ($config->cli->document->crawl->selector as $selector => $settings)
            {
                foreach ($crawler->filter($selector)->each(function($node) {

                    return $node;

                }) as $value) {

                    if ($url = $value->attr($settings->attribute))
                    {
                        //Make relative links absolute
                        if (!parse_url($url, PHP_URL_HOST))
                        {
                            $url =  $scheme . '://' . $host . ($port ? ':' . $port : null) .
                                    '/' .
                                    trim(
                                        ltrim(
                                            str_replace(
                                                [
                                                    './',
                                                    '../'
                                                ],
                                                '',
                                                $url
                                            ),
                                            '/'
                                        ),
                                        '.'
                                    );
                        }

                        // Regex rules
                        if (!preg_match($settings->regex, $url))
                        {
                            continue;
                        }

                        // External host rules
                        if (!$settings->external && parse_url($url, PHP_URL_HOST) != $host)
                        {
                            continue;
                        }

                        $documents[] = $url;
                    }
                }
            }

            if ($documents)
            {
                foreach (array_unique($documents) as $url)
                {
                    $url = trim($url);

                    if (!$index->search('@url "' . $url . '"')
                               ->limit(1)
                               ->get()
                               ->getTotal())
                    {
                        $index->addDocument(
                            [
                                'url' => $url
                            ]
                        );

                        echo sprintf(
                            'add "%s" to "%s"' . PHP_EOL,
                            $url,
                            $config->manticore->index->document
                        );
                    }
                }
            }
        }

        // Create snap
        if ($config->cli->document->crawl->snap->enabled && $code === 200)
        {
            try
            {
                // Generate path
                $time = time();

                $md5url = md5(
                    $document->get('url')
                );

                /// absolute
                if ('/' === substr($config->snap->storage->local->directory, 0, 1))
                {
                    $filepath = $config->snap->storage->local->directory;
                }

                /// relative
                else
                {
                    $filepath = __DIR__ . '/../../../' . $config->snap->storage->local->directory;
                }

                $filepath = sprintf(
                    '%s/%s',
                    $filepath,
                    implode(
                        '/',
                        str_split(
                            $md5url
                        )
                    )
                );

                $filename = sprintf(
                    '%s/%s.tar',
                    $filepath,
                    $time
                );

                @mkdir($filepath, 0755, true);

                // Compress response to archive
                $snap = new PharData($filename);

                $snap->addFromString(
                    'DATA',
                    $response
                );

                $snap->addFromString(
                    'MIME',
                    $mime
                );

                $snap->addFromString(
                    'URL',
                    $document->get('url')
                );

                $snap->compress(
                    Phar::GZ
                );

                unlink(
                    $filename
                );

                $filename = sprintf(
                    '%s.gz',
                    $filename
                );

                // Copy to mirror storage on enabled
                if ($config->snap->storage->mirror->enabled)
                {
                    // @TODO copy
                    // Snap match remote storage size/mime conditions
                }

                // Remove snap on local storage disabled
                if (!$config->snap->storage->local->enabled)
                {
                    @unlink(
                        $filename
                    );
                }

                // Remove snap on out of local storage size limits
                if ($size > $config->snap->storage->local->size->max)
                {
                    @unlink(
                        $filename
                    );
                }

                // Remove snap on mime not allowed
                $remove = true;
                foreach ($config->snap->storage->local->mime as $whitelist)
                {
                    if (false !== stripos($mime, $whitelist))
                    {
                        $remove = false;
                        break;
                    }
                }

                if ($remove)
                {
                    @unlink(
                        $filename
                    );
                }
            }

            catch (Exception $exception)
            {
                var_dump(
                    $exception
                );
            }
        }
    }

    // Crawl queue delay
    sleep(
        $config->cli->document->crawl->queue->limit
    );
}