<?php

// Debug
$microtime = microtime(true);

// Load dependencies
require_once __DIR__ . '/../../../vendor/autoload.php';

// Define helpers
function getLastSnapTime(array $files): int
{
    $time = [];

    foreach ($files as $file)
    {
        if (in_array($file, ['.', '..']))
        {
            continue;
        }

        $time[] = preg_replace(
            '/\D/',
            '',
            basename(
                $file
            )
        );
    }

    if ($time)
    {
        return max(
            $time
        );
    }

    return 0;
}

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

// Init client
try {

    $client = new \Manticoresearch\Client(
        [
            'host' => $config->manticore->server->host,
            'port' => $config->manticore->server->port,
        ]
    );

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

// Begin crawl queue
// thanks to @manticoresearch for help with random feature implementation:
// https://github.com/manticoresoftware/manticoresearch-php/discussions/176

foreach($index->search('')
              ->expression('random', 'rand()')
              ->sort('index', 'desc')
              ->sort('time', 'asc')
              ->sort('random', 'asc')
              ->limit($config->cli->document->crawl->queue->limit)
              ->get() as $document)
{
    // Define data
    $time = time();

    $data =
    [
        'url'         => $document->get('url'),
        'title'       => $document->get('title'),
        'description' => $document->get('description'),
        'keywords'    => $document->get('keywords'),
        'code'        => $document->get('code'),
        'size'        => $document->get('size'),
        'mime'        => $document->get('mime'),
        'rank'        => $document->get('rank'),
        'time'        => $time,
        'index'       => 0
    ];

    // Debug target
    if ($config->cli->document->crawl->debug->level->notice)
    {
        echo sprintf(
            _('[%s] [notice] index "%s" in "%s"') . PHP_EOL,
            date('c'),
            $document->get('url'),
            $config->manticore->index->document->name
        );
    }

    // Update index time anyway and set reset code to 404
    $index->updateDocument(
        [
            'time'  => time(),
            'code'  => 200,
            'index' => 0
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
            global $config;

            global $index;
            global $document;

            $index->updateDocument(
                [
                    'time'  => time(),
                    'code'  => 200,
                    'index' => 0
                ],
                $document->getId()
            );

            return $downloaded > $config->cli->document->crawl->curl->download->size->max ? 1 : 0;
        }
    );

    // Begin request
    if ($response = curl_exec($request))
    {
        // Update HTTP code or skip on empty
        if ($code = curl_getinfo($request, CURLINFO_HTTP_CODE))
        {
            // Delete deprecated document from index as HTTP code still not 200
            /*
            if ($code != 200 && !empty($data['code']) && $data['code'] != 200)
            {
                $index->deleteDocument(
                    $document->getId()
                );

                continue;
            }
            */

            $data['code'] = $code;

        } else continue;

        // Update size or skip on empty
        if ($size = curl_getinfo($request, CURLINFO_SIZE_DOWNLOAD))
        {
            $data['size'] = $size;

        } else continue;

        // Update MIME type or skip on empty
        if ($type = curl_getinfo($request, CURLINFO_CONTENT_TYPE))
        {
            $data['mime'] = $type;

            // On document charset specified
            if (preg_match('/charset=([^\s;]+)/i', $type, $charset))
            {
                if (!empty($charset[1]))
                {
                    // Get system encodings
                    foreach (mb_list_encodings() as $encoding)
                    {
                        if (strtolower($charset[1]) == strtolower($encoding))
                        {
                            // Convert response to UTF-8
                            $response = mb_convert_encoding(
                                $response,
                                'UTF-8',
                                $charset[1]
                            );

                            break;
                        }
                    }
                }
            }

        } else continue;

        // DOM crawler
        if (
            false !== stripos($type, 'text/html')
            ||
            false !== stripos($type, 'text/xhtml')
            ||
            false !== stripos($type, 'application/xhtml')
        ) {
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
                    $data['title'] = trim(
                        strip_tags(
                            html_entity_decode(
                                $value
                            )
                        )
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
                    $data['description'] = trim(
                        strip_tags(
                            html_entity_decode(
                                $value
                            )
                        )
                    );
                }
            }

            // Get keywords
            $keywords = [];

            // Extract from meta tag
            foreach ($crawler->filter('head > meta[name="keywords"]')->each(function($node) {

                return $node->attr('content');

            }) as $value)
            {
                if (!empty($value))
                {
                    foreach ((array) explode(
                        ',',
                        mb_strtolower(
                            strip_tags(
                                html_entity_decode(
                                    $value
                                )
                            )
                        )
                    ) as $keyword)
                    {
                        // Remove extra spaces
                        $keyword = trim(
                            $keyword
                        );

                        // Skip short words
                        if (mb_strlen($keyword) > 2)
                        {
                            $keywords[] = $keyword;
                        }
                    }
                }
            }

            // Get keywords from headers
            /* Disable keywords collection from headers as body index enabled

            foreach ($crawler->filter('h1,h2,h3,h4,h5,h6')->each(function($node) {

                return $node->text();

            }) as $value)
            {
                if (!empty($value))
                {
                    foreach ((array) explode(
                        ',',
                        mb_strtolower(
                            strip_tags(
                                html_entity_decode(
                                    $value
                                )
                            )
                        )
                    ) as $keyword)
                    {
                        // Remove extra spaces
                        $keyword = trim(
                            $keyword
                        );

                        // Skip short words
                        if (mb_strlen($keyword) > 2)
                        {
                            $keywords[] = $keyword;
                        }
                    }
                }
            }
            */

            // Keep keywords unique
            $keywords = array_unique(
                $keywords
            );

            // Update previous keywords when new value exists
            if ($keywords)
            {
                $data['keywords'] = implode(',', $keywords);
            }

            // Save document body text to index
            foreach ($crawler->filter('html > body')->each(function($node) {

                return $node->html();

            }) as $value)
            {
                if (!empty($value))
                {
                    $data['body'] = trim(
                        preg_replace(
                            '/[\s]{2,}/', // strip extra separators
                            ' ',
                            strip_tags(
                                str_replace( // make text separators before strip any closing tag, new line, etc
                                    [
                                        '<',
                                        '>',
                                        PHP_EOL,
                                    ],
                                    [
                                        ' <',
                                        '> ',
                                        PHP_EOL . ' ',
                                    ],
                                    preg_replace(
                                        [
                                            '/<script([^>]*)>([\s\S]*?)<\/script>/i', // strip js content
                                            '/<style([^>]*)>([\s\S]*?)<\/style>/i', // strip css content
                                            '/<pre([^>]*)>([\s\S]*?)<\/pre>/i', // strip code content
                                            '/<code([^>]*)>([\s\S]*?)<\/code>/i',
                                        ],
                                        '',
                                        html_entity_decode(
                                            $value
                                        )
                                    )
                                )
                            )
                        )
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
                               ->filter('id', $crc32url)
                               ->limit(1)
                               ->get()
                               ->getTotal())
                    {

                        $index->addDocument(
                            [
                                'url'  => $url
                            ],
                            $crc32url
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
                if (str_starts_with($config->snap->storage->tmp->directory, '/'))
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
                    $type
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
                    // Check for mime allowed
                    $allowed = false;

                    foreach ($config->snap->storage->local->mime->stripos as $whitelist)
                    {
                        if (false !== stripos($type, $whitelist))
                        {
                            $allowed = true;
                            break;
                        }
                    }

                    // Check for url allowed
                    if ($allowed)
                    {
                        $allowed = false;

                        foreach ($config->snap->storage->local->url->stripos as $whitelist)
                        {
                            if (false !== stripos($document->get('url'), $whitelist))
                            {
                                $allowed = true;
                                break;
                            }
                        }

                        // Check size limits
                        if ($allowed)
                        {
                            $allowed = false;

                            if ($size <= $config->snap->storage->local->size->max)
                            {
                                $allowed = true;
                            }
                        }
                    }

                    // Copy snap to the permanent storage
                    if ($allowed)
                    {
                        /// absolute
                        if (str_starts_with($config->snap->storage->local->directory, '/'))
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

                        // Check latest snap older than defined in settings
                        if (time() - getLastSnapTime((array) scandir($filepath)) > $config->cli->document->crawl->snap->timeout)
                        {
                            $filename = sprintf(
                                '%s/%s',
                                $filepath,
                                sprintf(
                                    '%s.tar.gz',
                                    $time
                                )
                            );

                            if (copy($tmp, $filename))
                            {
                                if ($config->cli->document->crawl->debug->level->notice)
                                {
                                    echo sprintf(
                                        _('[%s] [notice] save snap to "%s" on local storage') . PHP_EOL,
                                        date('c'),
                                        $filename
                                    );
                                }
                            }

                            else
                            {
                                if ($config->cli->document->crawl->debug->level->error)
                                {
                                    echo sprintf(
                                        _('[%s] [error] could not copy "%s" to "%s" on local storage') . PHP_EOL,
                                        date('c'),
                                        $tmp,
                                        $filename
                                    );
                                }
                            }
                        }

                        else
                        {
                            if ($config->cli->document->crawl->debug->level->notice)
                            {
                                echo sprintf(
                                    _('[%s] [notice] local snap is up to date by timeout settings') . PHP_EOL,
                                    date('c')
                                );
                            }
                        }
                    }

                    else
                    {
                        if ($config->cli->document->crawl->debug->level->notice)
                        {
                            echo sprintf(
                                _('[%s] [notice] local snap skipped by settings condition') . PHP_EOL,
                                date('c')
                            );
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

                    // Check for mime allowed
                    $allowed = false;

                    foreach ($ftp->mime->stripos as $whitelist)
                    {
                        if (false !== stripos($type, $whitelist))
                        {
                            $allowed = true;
                            break;
                        }
                    }

                    if (!$allowed)
                    {
                        continue;
                    }

                    // Check for url allowed
                    $allowed = false;

                    foreach ($ftp->url->stripos as $whitelist)
                    {
                        if (false !== stripos($document->get('url'), $whitelist))
                        {
                            $allowed = true;
                            break;
                        }
                    }

                    if (!$allowed)
                    {
                        continue;
                    }

                    // Check size limits
                    $allowed = false;

                    if ($size <= $ftp->size->max)
                    {
                        $allowed = true;
                    }

                    if (!$allowed)
                    {
                        if ($config->cli->document->crawl->debug->level->notice)
                        {
                            echo sprintf(
                                _('[%s] [notice] remote snap skipped on "%s" by settings condition') . PHP_EOL,
                                date('c'),
                                $ftp->connection->host
                            );
                        }

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
                        sprintf(
                            '%s.tar.gz',
                            $time
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

                            // Check latest snap older than defined in settings
                            if (time() - getLastSnapTime((array) $remote->nlist($filepath)) > $config->cli->document->crawl->snap->timeout)
                            {
                                if ($remote->copy($tmp, $filename))
                                {
                                    if ($config->cli->document->crawl->debug->level->notice)
                                    {
                                        echo sprintf(
                                            _('[%s] [notice] save snap to "%s" on remote host "%s"') . PHP_EOL,
                                            date('c'),
                                            $filename,
                                            $ftp->connection->host
                                        );
                                    }
                                }

                                else
                                {
                                    if ($config->cli->document->crawl->debug->level->error)
                                    {
                                        echo sprintf(
                                            _('[%s] [error] could not copy snap "%s" to "%s" on destination "%s"') . PHP_EOL,
                                            date('c'),
                                            $tmp,
                                            $filename,
                                            $ftp->connection->host
                                        );
                                    }
                                }
                            }

                            else
                            {
                                if ($config->cli->document->crawl->debug->level->notice)
                                {
                                    echo sprintf(
                                        _('[%s] [notice] remote snap on destination "%s" is up to date by timeout settings') . PHP_EOL,
                                        date('c'),
                                        $ftp->connection->host
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
                            _('[%s] [notice] remove tmp snap file "%s"') . PHP_EOL,
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
                            _('[%s] [error] could not remove tmp snap file "%s"') . PHP_EOL,
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