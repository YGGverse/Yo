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

// Show totals in placeholder

// Init
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

$placeholder = sprintf(
  _('Search in %s documents %s'),
  number_format(
      $total
  ),
  $config->webui->search->index->request->url->enabled ? _('or enter new address to crawl...') : false
);

// Get document data
$document = $index->getDocumentById(
  isset($_GET['i']) ? $_GET['i'] : 0
);

// Get icon
$hostname = parse_url(
  $document->url,
  PHP_URL_HOST
);

$identicon = new \Jdenticon\Identicon();

$identicon->setValue(
  $hostname
);

$identicon->setSize(36);

$identicon->setStyle(
  [
    'backgroundColor' => 'rgba(255, 255, 255, 0)',
    'padding' => 0
  ]
);

$icon = $identicon->getImageDataUri('webp');

// Get snaps info
$snaps = [];

?>

<!DOCTYPE html>
<html lang="<?php echo _('en-US'); ?>">
  <head>
  <title><?php echo _('Yo! explore') ?></title>
    <meta charset="utf-8" />
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
        z-index: 2;
      }

      main {
        margin-top: 80px;
        margin-bottom: 76px;
        padding: 0 20px;
      }

      main > div {
        max-width: 640px;
        margin: 0 auto;
        padding: 8px 0;
        border-top: 1px #000 dashed;
        font-size: 14px;
      }

      main > div > div {
        margin: 8px 0;
        font-size: 12px;
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
        font-size: 15px;
        font-weight: normal;
        margin: 4px 0;
        color: #fff;
      }

      h3 {
        display: block;
        font-size: 13px;
        margin: 4px 0;
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
        padding: 6px 0;
        border-radius: 32px;
        background-color: #000;
        color: #fff;
        font-size: 15px;
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
        padding: 6px 12px;
        border-radius: 4px;
        cursor: pointer;
        background-color: #3394fb;
        color: #fff;
        font-size: 14px;
        position: fixed;
        top: 12px;
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

      .text-warning {
        color: #db6161;
      }

    </style>
  </head>
  <body>
    <header>
      <form name="search" method="GET" action="<?php echo $config->webui->url->base; ?>/search.php">
        <h1><a href="<?php echo $config->webui->url->base; ?>"><?php echo _('Yo!') ?></a></h1>
        <input type="text" name="q" placeholder="<?php echo $placeholder ?>" value="" />
        <button type="submit">
            <sub>
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="white" class="bi bi-search" viewBox="0 0 16 16">
                <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0"/>
              </svg>
            </sub>
        </button>
      </form>
    </header>
    <main>
      <?php if ($document) { ?>
        <div>
          <?php if (empty($document->title) && empty($document->description) && empty($document->keywords)) { ?>
            <div>
              <?php echo _('Document pending for crawler queue') ?>
            </div>
          <?php } else { ?>
            <?php if (!empty($document->title)) { ?>
              <h2>
                <?php echo htmlentities($document->title) ?>
              </h2>
            <?php } ?>
            <?php if (!empty($document->description)) { ?>
              <div>
                <?php echo htmlentities($document->description) ?>
              </div>
            <?php } ?>
            <?php if (!empty($document->keywords)) { ?>
              <div>
                <?php echo htmlentities($document->keywords) ?>
              </div>
            <?php } ?>
          <?php } ?>
            <div>
              <a href="<?php echo $document->url ?>"><?php echo htmlentities(urldecode($document->url)) ?></a>
            </div>
        </div>
        <div>
          <div>
            <img src="<?php echo $icon ?>" title="<?php echo $hostname ?>" alt="identicon" />
          </div>
          <?php if (!empty($document->code)) { ?>
            <h3><?php echo _('HTTP') ?></h3>
            <?php if ($document->code == 200) { ?>
              <div>
                <?php echo $document->code ?>
              </div>
            <?php } else { ?>
              <div class="text-warning">
                <?php echo $document->code ?>
              </div>
            <?php } ?>
          <?php } ?>
          <?php if (!empty($document->mime)) { ?>
            <h3><?php echo _('MIME') ?></h3>
            <div><?php echo $document->mime ?></div>
          <?php } ?>
          <?php if (!empty($document->size)) { ?>
            <h3><?php echo _('Size') ?></h3>
            <div><?php echo $document->size ?></div>
          <?php } ?>
          <?php if (!empty($document->time)) { ?>
            <h3><?php echo _('Time') ?></h3>
            <div><?php echo date('c', $document->time) ?> / <?php echo $document->time ?></div>
          <?php } ?>
          <?php if ($snaps) { ?>
            <h3><?php echo _('Snaps') ?></h3>
            <?php foreach ($snaps as $server => $snap) { ?>
              <div>
                <!--<a href="<?php echo WEBSITE_DOMAIN . '/api.php?action=snap&method=download&time=url=' . $url ?>">-->
                  <?php echo date('c', $snap->time) ?> / <?php echo $snap->time ?>
                <!--</a>-->
              </div>
            <?php } ?>
          <?php } ?>
        </div>
      <?php } else { ?>
        <div>
          <?php echo _('Index not found') ?>
        </div>
      <?php } ?>
    </main>
  </body>
</html>