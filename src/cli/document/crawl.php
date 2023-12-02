<?php

// Debug
$microtime = microtime(true);

// Load dependencies
require_once __DIR__ . '/../../../vendor/autoload.php';

// Init config
$config = json_decode(
    file_get_contents(
        __DIR__ . '/../../../config.json'
    )
);

// Prevent multi-thread execution
$semaphore = sem_get(
    crc32(
        __DIR__ . '.yo.cli.document.crawl'
    ),
    1
);

if (false === sem_acquire($semaphore, true))
{
    if ($config->cli->document->crawl->debug->level->warning)
    {
        echo sprintf(
            _('[%s] [warning] process execution locked by another thread!') . PHP_EOL,
            date('c')
        );
    }

    exit;
}

// Set global options
define(
    'CONFIG_CLI_DOCUMENT_CRAWL_CURL_DOWNLOAD_SIZE_MAX',
    $config->cli->document->crawl->curl->download->size->max
);

// Init client
try {

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
        $config->manticore->index->document->name
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
        $config->manticore->index->document->name
    );
}

catch (Exception $exception)
{
    if ($config->cli->document->crawl->debug->level->error)
    {
        echo sprintf(
            _('[%s] [error] %s') . PHP_EOL,
            date('c'),
            print_r(
                $exception,
                true
            )
        );
    }

    exit;
}

// Debug totals
if ($config->cli->document->crawl->debug->level->notice)
{
    echo sprintf(
        _('[%s] [notice] crawl queue begin...') . PHP_EOL,
        date('c')
    );
}

// Begin queue
foreach($search->get() as $document)
{
    // Define data
    $time = time();

    $data =
    [
        'url'         => $document->get('url'),
        'crc32url'    => $document->get('crc32url'),
        'title'       => $document->get('title'),
        'description' => $document->get('description'),
        'keywords'    => $document->get('keywords'),
        'code'        => $document->get('code'),
        'size'        => $document->get('size'),
        'mime'        => $document->get('mime'),
        'time'        => $time
    ];

    // Debug target
    echo sprintf(
        _('[%s] index "%s" in "%s"') . PHP_EOL,
        date('c'),
        $document->get('url'),
        $config->manticore->index->document->name
    );

    // Update index time anyway and set reset code to 404
    $index->updateDocument(
        [
            'time' => time(),
            'code' => 404
        ],
        $document->getId()
    );

    // Request remote URL
    $request = curl_init(
        $document->get('url')
    );

    // Drop URL with long response
    curl_setopt(
        $request,
        CURLOPT_CONNECTTIMEOUT,
        $config->cli->document->crawl->curl->connection->timeout
    );

    curl_setopt(
        $request,
        CURLOPT_TIMEOUT,
        $config->cli->document->crawl->curl->connection->timeout
    );

    // Prevent huge content download e.g. media streams URL
    curl_setopt(
        $request,
        CURLOPT_RETURNTRANSFER,
        true
    );

    curl_setopt(
        $request,
        CURLOPT_NOPROGRESS,
        false
    );

    curl_setopt(
        $request,
        CURLOPT_PROGRESSFUNCTION,
        function(
            $download,
            $downloaded,
            $upload,
            $uploaded
        ) {
            return $downloaded > CONFIG_CLI_DOCUMENT_CRAWL_CURL_DOWNLOAD_SIZE_MAX ? 1 : 0;
        }
    );

    // Begin request
    if ($response = curl_exec($request))
    {
        // Update HTTP code or skip on empty
        if ($code = curl_getinfo($request, CURLINFO_HTTP_CODE))
        {
            $data['code'] = $code;

        } else continue;

        // Update size or skip on empty
        if ($size = curl_getinfo($request, CURLINFO_SIZE_DOWNLOAD))
        {
            $data['size'] = $size;

        } else continue;

        // Update MIME type or skip on empty
        if ($mime = curl_getinfo($request, CURLINFO_CONTENT_TYPE))
        {
            $data['mime'] = $mime;

        } else continue;

        // DOM crawler
        if (false !== stripos($mime, 'text/html'))
        {
            $crawler = new Symfony\Component\DomCrawler\Crawler();
            $crawler->addHtmlContent(
                $response
            );

            // Get title
            foreach ($crawler->filter('head > title')->each(function($node) {

                return $node->text();

            }) as $value)
            {
                if (!empty($value))
                {
                    $data['title'] = html_entity_decode(
                        $value
                    );
                }
            }

            // Get description
            foreach ($crawler->filter('head > meta[name="description"]')->each(function($node) {

                return $node->attr('content');

            }) as $value)
            {
                if (!empty($value))
                {
                    $data['description'] = html_entity_decode(
                        $value
                    );
                }
            }

            // Get keywords
            $keywords = '';
            foreach ($crawler->filter('head > meta[name="keywords"]')->each(function($node) {

                return $node->attr('content');

            }) as $value)
            {
                if (!empty($value))
                {
                    $data['keywords'] = html_entity_decode(
                        $value
                    );
                }
            }

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
                    // Apply stripos condition
                    $skip = false;

                    foreach ($config->cli->document->crawl->skip->stripos->url as $condition)
                    {
                        if (false !== stripos($url, $condition)) {

                            $skip = true;

                            break;
                        }
                    }

                    if ($skip)
                    {
                        if ($config->cli->document->crawl->debug->level->notice)
                        {
                            echo sprintf(
                                _('[%s] [notice] skip "%s" by stripos condition "%s"') . PHP_EOL,
                                date('c'),
                                $url,
                                print_r(
                                    $config->cli->document->crawl->skip->stripos->url,
                                    true
                                )
                            );
                        }

                        continue;
                    }

                    // Save index
                    $url      = trim($url);
                    $crc32url = crc32($url);

                    if (!$index->search('')
                               ->filter('crc32url', $crc32url)
                               ->limit(1)
                               ->get()
                               ->getTotal())
                    {
                        $index->addDocument(
                            [
                                'url'      => $url,
                                'crc32url' => $crc32url
                            ]
                        );

                        if ($config->cli->document->crawl->debug->level->notice)
                        {
                            echo sprintf(
                                _('[%s] [notice] add "%s" to "%s"') . PHP_EOL,
                                date('c'),
                                $url,
                                $config->manticore->index->document->name
                            );
                        }
                    }
                }
            }
        }

        // Replace document data
        // https://github.com/manticoresoftware/manticoresearch-php/issues/10#issuecomment-612685916
        $result = $index->replaceDocument(
            $data,
            $document->getId()
        );

        // Debug result
        if ($config->cli->document->crawl->debug->level->notice)
        {
            echo sprintf(
                '[%s] [notice] index "%s" updated: %s %s' . PHP_EOL,
                date('c'),
                $config->manticore->index->document->name,
                print_r(
                    $result,
                    true
                ),
                print_r(
                    $data,
                    true
                ),
            );
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
                if ('/' === substr($config->snap->storage->tmp->directory, 0, 1))
                {
                    $filepath = $config->snap->storage->tmp->directory;
                }

                /// relative
                else
                {
                    $filepath = __DIR__ . '/../../../' . $config->snap->storage->tmp->directory;
                }

                @mkdir($filepath, 0755, true);

                $tmp = sprintf(
                    '%s/%s.%s.tar',
                    $filepath,
                    $md5url,
                    $time
                );

                // Compress response to archive
                $snap = new PharData($tmp);

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

                unlink( // remove tarball
                    $tmp
                );

                $tmp = sprintf(
                    '%s.gz',
                    $tmp
                );

                // Copy to local storage on enabled
                if ($config->snap->storage->local->enabled)
                {
                    $allowed = false;

                    // Check for mime allowed
                    foreach ($config->snap->storage->local->mime as $whitelist)
                    {
                        if (false !== stripos($mime, $whitelist))
                        {
                            $allowed = true;
                            break;
                        }
                    }

                    // Check size limits
                    if ($size > $config->snap->storage->local->size->max)
                    {
                        $allowed = false;
                    }

                    // Copy snap to the permanent storage
                    if ($allowed)
                    {
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

                        @mkdir($filepath, 0755, true);

                        $filename = sprintf(
                            '%s/%s',
                            $filepath,
                            basename(
                                $tmp
                            )
                        );

                        if (!copy($tmp, $filename))
                        {
                            if ($config->cli->document->crawl->debug->level->error)
                            {
                                echo sprintf(
                                    _('[%s] [error] could not copy "%" to "%" on local storage') . PHP_EOL,
                                    date('c'),
                                    $tmp,
                                    $filename
                                );
                            }
                        }
                    }
                }

                // Copy to FTP storage on enabled
                foreach ($config->snap->storage->remote->ftp as $ftp)
                {
                    // Resource enabled
                    if (!$ftp->enabled)
                    {
                        continue;
                    }

                    $allowed = false;

                    // Check for mime allowed
                    foreach ($ftp->mime as $whitelist)
                    {
                        if (false !== stripos($mime, $whitelist))
                        {
                            $allowed = true;
                            break;
                        }
                    }

                    // Check size limits
                    if ($size > $ftp->size->max)
                    {
                        $allowed = false;
                    }

                    if (!$allowed)
                    {
                        continue;
                    }

                    // Prepare location
                    $filepath = implode(
                        '/',
                        str_split(
                            $md5url
                        )
                    );

                    $filename = sprintf(
                        '%s/%s',
                        $filepath,
                        basename(
                            $tmp
                        )
                    );

                    // Init connection
                    $attempt = 1;

                    do {

                        $remote = new \Yggverse\Ftp\Client();

                        $connection = $remote->connect(
                                $ftp->connection->host,
                                $ftp->connection->port,
                                $ftp->connection->username,
                                $ftp->connection->password,
                                $ftp->connection->directory,
                                $ftp->connection->timeout,
                                $ftp->connection->passive
                        );

                        // Remote host connected
                        if ($connection) {

                            $remote->mkdir(
                                $filepath,
                                true
                            );

                            if (!$remote->copy($tmp, $filename))
                            {
                                if ($config->cli->document->crawl->debug->level->error)
                                {
                                    echo sprintf(
                                        _('[%s] [error] could not copy "%" to "%" on destination "%s"') . PHP_EOL,
                                        date('c'),
                                        $tmp,
                                        $filename,
                                        $ftp->connection->host,
                                    );
                                }
                            }

                            $remote->close();

                        // On remote connection lost, repeat attempt
                        } else {

                            // Stop connection attempts on limit provided
                            if ($ftp->connection->attempts->limit > 0 && $attempt > $ftp->connection->attempts->limit)
                            {
                                break;
                            }

                            // Log event
                            if ($config->cli->document->crawl->debug->level->warning)
                            {
                                echo sprintf(
                                    _('[%s] [warning] attempt: %s, wait for remote storage "%s" reconnection...') . PHP_EOL,
                                    date('c'),
                                    $attempt++,
                                    $ftp->connection->host,
                                );
                            }

                            // Delay next attempt
                            if ($ftp->connection->attempts->delay)
                            {
                                if ($config->cli->document->crawl->debug->level->warning)
                                {
                                    echo sprintf(
                                        _('[%s] [warning] pending %s seconds to reconnect...') . PHP_EOL,
                                        date('c'),
                                        $ftp->connection->attempts->delay
                                    );
                                }

                                sleep(
                                    $ftp->connection->attempts->delay
                                );
                            }
                        }

                    } while ($connection === false);
                }

                // Remove tmp data
                if (unlink($tmp))
                {
                    if ($config->cli->document->crawl->debug->level->notice)
                    {
                        echo sprintf(
                            _('[%s] [notice] remove tmp snap file %s') . PHP_EOL,
                            date('c'),
                            $tmp
                        );
                    }
                }

                else
                {
                    if ($config->cli->document->crawl->debug->level->error)
                    {
                        echo sprintf(
                            _('[%s] [error] could not remove tmp snap file %s') . PHP_EOL,
                            date('c'),
                            $tmp
                        );
                    }
                }
            }

            catch (Exception $exception)
            {
                if ($config->cli->document->crawl->debug->level->error)
                {
                    echo sprintf(
                        _('[%s] [error] %s') . PHP_EOL,
                        date('c'),
                        print_r(
                            $exception,
                            true
                        )
                    );
                }
            }
        }
    }

    // Crawl queue delay
    if ($config->cli->document->crawl->queue->delay)
    {
        if ($config->cli->document->crawl->debug->level->notice)
        {
            echo sprintf(
                _('[%s] [notice] pending %s seconds...') . PHP_EOL,
                date('c'),
                $config->cli->document->crawl->queue->delay
            );
        }

        sleep(
            $config->cli->document->crawl->queue->delay
        );
    }

    // Debug totals
    if ($config->cli->document->crawl->debug->level->notice)
    {
        echo sprintf(
            _('[%s] [notice] crawl queue completed in %s') . PHP_EOL,
            date('c'),
            microtime(true) - $microtime
        );
    }
}