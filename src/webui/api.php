<?php

// Debug
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Load dependencies
require_once __DIR__ . '/../../vendor/autoload.php';

// Init config
$config = json_decode(
    file_get_contents(
        __DIR__ . '/../../config.json'
    )
);

// Set headers
header('Content-Type: application/json; charset=utf-8');

// Action
switch (!empty($_GET['action']) ? $_GET['action'] : false) {

    // Snap methods
    case 'snap':

        switch (!empty($_GET['method']) ? $_GET['method'] : false) {

            case 'download':

                // Validate required attributes
                switch (false)
                {
                    case isset($_GET['source']):

                        echo json_encode(
                            [
                                'status'  => false,
                                'message' => _('valid source required')
                            ]
                        );

                    exit;

                    case isset($_GET['md5url']) && preg_match('/^[a-f0-9]{32}$/', $_GET['md5url']):

                        echo json_encode(
                            [
                                'status'  => false,
                                'message' => _('valid md5url required')
                            ]
                        );

                    exit;

                    case isset($_GET['time']) && preg_match('/^[\d]+$/', $_GET['time']):

                        echo json_encode(
                            [
                                'status'  => false,
                                'message' => _('valid time required')
                            ]
                        );

                    exit;
                }

                // Detect remote snap source
                if (preg_match('/^[\d]+$/', $_GET['source']))
                {
                    if (!isset($config->snap->storage->remote->ftp[$_GET['source']]) || !$config->snap->storage->remote->ftp[$_GET['source']]->enabled)
                    {
                        echo json_encode(
                            [
                                'status'  => false,
                                'message' => _('requested source not found')
                            ]
                        );

                        exit;
                    }

                    // Connect remote
                    $remote = new \Yggverse\Ftp\Client();

                    $connection = $remote->connect(
                        $config->snap->storage->remote->ftp[$_GET['source']]->connection->host,
                        $config->snap->storage->remote->ftp[$_GET['source']]->connection->port,
                        $config->snap->storage->remote->ftp[$_GET['source']]->connection->username,
                        $config->snap->storage->remote->ftp[$_GET['source']]->connection->password,
                        $config->snap->storage->remote->ftp[$_GET['source']]->connection->directory,
                        $config->snap->storage->remote->ftp[$_GET['source']]->connection->timeout,
                        $config->snap->storage->remote->ftp[$_GET['source']]->connection->passive
                    );

                    // Remote host connected
                    if ($connection) {

                        // Prepare snap path
                        $filename = sprintf(
                            '%s/%s.tar.gz',
                            implode(
                                '/',
                                str_split(
                                    $_GET['md5url']
                                )
                            ),
                            $_GET['time']
                        );

                        // Check snap exist
                        if (!$size = $remote->size($filename))
                        {
                            echo json_encode(
                                [
                                    'status'  => false,
                                    'message' => _('requested snap not found')
                                ]
                            );

                            exit;
                        }

                        // Set headers
                        header(
                            'Content-Type: application/tar+gzip'
                        );

                        header(
                            sprintf(
                                'Content-Length: %s',
                                $size
                            )
                        );

                        header(
                            sprintf(
                                'Content-Disposition: filename="snap.%s.%s"',
                                $_GET['md5url'],
                                basename(
                                    $filename
                                )
                            )
                        );

                        // Return file
                        $remote->get(
                            $filename,
                            'php://output'
                        );

                        $remote->close();
                    }
                }

                // Local
                else if ($config->snap->storage->local->enabled)
                {
                    // Prefix absolute
                    if ('/' === substr($config->snap->storage->local->directory, 0, 1))
                    {
                        $prefix = $config->snap->storage->local->directory;
                    }

                    // Prefix relative
                    else
                    {
                        $prefix = __DIR__ . '/../../' . $config->snap->storage->local->directory;
                    }

                    // Prepare snap path
                    $filename = sprintf(
                        '%s/%s/%s.tar.gz',
                        $prefix,
                        implode(
                            '/',
                            str_split(
                                $_GET['md5url']
                            )
                        ),
                        $_GET['time']
                    );

                    // Check snap exist
                    if (!file_exists($filename) || !is_readable($filename))
                    {
                        echo json_encode(
                            [
                                'status'  => false,
                                'message' => _('requested snap not found')
                            ]
                        );

                        exit;
                    }

                    // Check snap has valid size
                    if (!$size = filesize($filename))
                    {
                        echo json_encode(
                            [
                                'status'  => false,
                                'message' => _('requested snap has invalid size')
                            ]
                        );

                        exit;
                    }

                    // Set headers
                    header(
                        'Content-Type: application/tar+gzip'
                    );

                    header(
                        sprintf(
                            'Content-Length: %s',
                            $size
                        )
                    );

                    header(
                        sprintf(
                            'Content-Disposition: filename="snap.%s.%s"',
                            $_GET['md5url'],
                            basename(
                                $filename
                            )
                        )
                    );

                    readfile(
                        $filename
                    );

                    exit;
                }

                else
                {
                    echo json_encode(
                        [
                            'status'  => false,
                            'message' => _('requested source not found')
                        ]
                    );
                }

            break;

            default:

                echo json_encode(
                    [
                        'status'  => false,
                        'message' => _('Undefined API method')
                    ]
                );
            }

    break;

    default:

        echo json_encode(
            [
                'status'  => false,
                'message' => _('Undefined API action')
            ]
        );
}
