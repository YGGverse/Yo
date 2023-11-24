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

// Init
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

// Request
$q = !empty($_GET['q']) ? $_GET['q'] : '';
$p = !empty($_GET['p']) ? (int) $_GET['p'] : 1;

// Check URL for exist
$results = $index->search($q)
                 ->offset($p * $config->webui->pagination->limit - $config->webui->pagination->limit)
                 ->limit($config->webui->pagination->limit)
                 ->get();

?>

<!DOCTYPE html>
<html lang="<?php echo _('en-US'); ?>">
  <head>
  <title><?php echo sprintf(_('%s - YGGo!'), htmlentities($q)) ?></title>
    <meta charset="utf-8" />
    <meta name="keywords" content="<?php echo htmlentities($q) ?>" />
    <style>

      * {
        border: 0;
        margin: 0;
        padding: 0;
        font-family: Sans-serif;
        color: #ccc;
      }

      body {
        background-color: #2e3436;
        word-break: break-word;
      }

      header {
        background-color: #34393b;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
      }

      main {
        margin-top: 110px;
        margin-bottom: 76px;
        padding: 0 20px;
      }

      h1 {
        position: fixed;
        top: 8px;
        left: 24px;
      }

      h1 > a,
      h1 > a:visited,
      h1 > a:active,
      h1 > a:hover {
        color: #fff;
        font-weight: normal;
        font-size: 24px;
        margin: 10px 0;
        text-decoration: none;
      }

      h2 {
        display: block;
        font-size: 16px;
        font-weight: normal;
        margin: 4px 0;
        color: #fff;
      }

      form {
        display: block;
        max-width: 678px;
        margin: 0 auto;
        text-align: center;
      }

      input {
        width: 100%;
        margin: 12px 0;
        padding: 10px 0;
        border-radius: 32px;
        background-color: #000;
        color: #fff;
        font-size: 16px;
        text-align: center;
      }

      input:hover {
        background-color: #111
      }

      input:focus {
        outline: none;
        background-color: #111
      }

      input:focus::placeholder {
        color: #090808
      }

      label {
        font-size: 14px;
        color: #fff;
        float: left;
        margin-left: 16px;
        margin-bottom: 14px;
      }

      label > input {
        width: auto;
        margin: 0 4px;
      }

      button {
        padding: 8px 16px;
        border-radius: 4px;
        cursor: pointer;
        background-color: #3394fb;
        color: #fff;
        font-size: 14px;
        position: fixed;
        top: 15px;
        right: 24px;
      }

      button:hover {
        background-color: #4b9df4;
      }

      a, a:visited, a:active {
        color: #9ba2ac;
        display: inline-block;
        font-size: 12px;
        margin-top: 8px;
      }

      a:hover {
        color: #54a3f7;
      }

      img.icon {
        float: left;
        border-radius: 50%;
        margin-right: 8px;
      }

      img.image {
        max-width: 100%;
        border-radius: 3px;
      }

      div {
        max-width: 640px;
        margin: 0 auto;
        padding: 16px 0;
        border-top: 1px #000 dashed;
        font-size: 14px
      }

      span {
        display: block;
        margin: 8px 0;
      }

      p {
        margin: 16px 0;
        text-align: right;
        font-size: 11px;
      }

      p > a, p > a:visited, p > a:active {
        font-size: 11px;
      }

      .text-warning {
        color: #db6161;
      }

    </style>
  </head>
  <body>
    <header>
      <form name="search" method="GET" action="<?php echo $config->webui->url->base; ?>/search.php">
        <h1><a href="<?php echo $config->webui->url->base; ?>"><?php echo _('Yo!') ?></a></h1>
        <input type="text" name="q" placeholder="<?php echo _('request') ?>" value="<?php echo htmlentities($q) ?>" />
        <button type="submit"><?php echo _('search'); ?></button>
      </form>
    </header>
    <main>
      <?php if ($results->getTotal()) { ?>
        <?php foreach ($results as $result) { ?>
          <div>
            <?php if (!empty($result->url)) { ?>
              <h2><?php echo $result->title ?></h2>
            <?php } ?>
            <?php if (!empty($result->description)) { ?>
              <span><?php echo $result->description ?></span>
            <?php } ?>
            <?php if (!empty($result->keywords)) { ?>
              <span><?php echo $result->keywords ?></span>
            <?php } ?>
            <a href="<?php echo $result->url ?>">
              <?php
                $identicon = new \Jdenticon\Identicon();

                $identicon->setValue($result->url);
                $identicon->setSize(16);
                $identicon->setStyle(
                  [
                    'backgroundColor' => 'rgba(255, 255, 255, 0)',
                    'padding' => 0
                  ]
                );
              ?>
              <img src="<?php echo $identicon->getImageDataUri('webp') ?>" alt="identicon" width="16" height="16" class="icon" />
              <?php echo htmlentities(urldecode($result->url)) ?>
            </a>
            <!-- @TODO
            |
            <a href="<?php echo $config->webui->url->base; ?>/snap">
              <?php echo _('cache'); ?>
            </a>
            -->
          </div>
        <?php } ?>
        <?php if ($p * $config->webui->pagination->limit <= $results->getTotal()) { ?>
          <div>
            <a href="<?php echo $config->webui->url->base; ?>/search.php?q=<?php echo urlencode(htmlentities($q)) ?>&p=<?php echo $p + 1 ?>">
              <?php echo _('Next page') ?>
            </a>
          </div>
        <?php } ?>
      <?php } else { ?>
        <div>
          <?php echo _('Nothing found!') ?>
        </div>
      <?php } ?>
    </main>
  </body>
</html>