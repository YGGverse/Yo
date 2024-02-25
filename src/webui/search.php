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

$response = false;

// Request
$q = !empty($_GET['q']) ? trim($_GET['q']) : '';
$p = !empty($_GET['p']) ? (int) $_GET['p'] : 1;

// Register new URL by request on enabled
if ($config->webui->search->index->request->url->enabled && filter_var($q, FILTER_VALIDATE_URL))
{
  if (preg_match($config->webui->search->index->request->url->regex, $q))
  {
    // Prepare URL
    $url      = $q;
    $crc32url = crc32($url);

    // Check URL for exist
    $exist = $index->search('')
                   ->filter('id', $crc32url)
                   ->limit(1)
                   ->get()
                   ->getTotal();

    if ($exist)
    {
        /* disable as regular search request possible
        $response = sprintf(
            _('URL "%s" exists in search index'),
            htmlentities($q)
        );
        */
    }

    // Add URL
    else
    {
      // @TODO check http code

      $index->addDocument(
        [
          'url'  => $url,
          'rank' => (int) mb_strlen(
            (string)
              urldecode(
                (string)
                  parse_url(
                      $url,
                      PHP_URL_PATH
                  )
              )
          )
        ],
        $crc32url
      );

      $response = sprintf(
        _('URL "%s" added to the crawl queue!'),
        htmlentities($q)
      );
    }
  }

  else {
    $response = sprintf(
      _('URL "%s" does not match node settings!'),
      htmlentities($q)
    );
  }
}

// Extended corrections
switch (true)
{
  case empty($q):

    $query = $index->search('')->sort('RAND()');

  break;

  case filter_var($q, FILTER_VALIDATE_URL):

    $query = $index->search('')->filter('id', crc32($q));

  break;

  default:

    // Allow raw requests on extended syntax mode requested
    // http://sphinxsearch.com/docs/current/extended-syntax.html
    if (isset($_GET['e']) && $config->webui->search->extended->enabled)
    {
      $query = $index->search($q);
    }

    // Escape reserved chars
    // http://sphinxsearch.com/docs/current/extended-syntax.html
    else
    {
      // Remove separator duplicates
      $q = trim(
        preg_replace(
          '/[\s]+/ui',
          ' ',
          $q
        )
      );

      // Escape special chars
      $q = @\Manticoresearch\Utils::escape(
        $q
      );

      // Explode search phrase
      $words = [];
      foreach ((array) explode(' ', $q) as $word)
      {
        $words[] = trim(
          $word
        );
      }

      // Build combined query
      $query = $index->search(
        sprintf(
          '"%s"|%s',
          $q,
          implode(
            '|',
            $words
          )
        )
      );
    }
}

// Get found
$found = empty($q) ? $total : $query->get()->getTotal();

// Search request begin
$results = $query->offset($p * $config->webui->pagination->limit - $config->webui->pagination->limit)
                 ->limit($config->webui->pagination->limit)
                 ->get();

?>

<!DOCTYPE html>
<html lang="<?php echo _('en-US'); ?>">
  <head>
  <title><?php echo sprintf(_('Yo! %s'), htmlentities($q)) ?></title>
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
        z-index: 2;
      }

      main {
        margin-top: 80px;
        margin-bottom: 76px;
        padding: 0 32px;
      }

      main > div {
        border-top: 1px #000 dashed;
        font-size: 14px;
        margin: 0 auto;
        max-width: 620px;
        padding: 8px 0;
        position: relative;
      }

      main > div > img {
        left: -24px;
        position: absolute;
        top: 18px;

      }

      main > div > div {
        padding: 8px 0;
        line-height: 16px;
      }

      main > div > div > a {
        font-size: 12px;
      }

      h1 {
        position: fixed;
        top: 2px;
        left: 24px;
      }

      h1 > a,
      h1 > a:visited,
      h1 > a:active,
      h1 > a:hover {
        color: #fff;
        font-weight: normal;
        font-size: 22px;
        margin: 0;
        text-decoration: none;
      }

      h2 {
        display: block;
        font-size: 15px;
        font-weight: normal;
        color: #fff;
      }

      form {
        display: block;
        max-width: 678px;
        margin: 0 auto;
        text-align: center;
      }

      input[type="checkbox"] {
        accent-color: #3394fb;
      }

      input[type="text"],
      input[type="text"]:-webkit-autofill,
      input[type="text"]:-webkit-autofill:focus {
        transition: background-color 0s 600000s, color 0s 600000s; /* chrome */
        width: 100%;
        margin: 12px 0;
        padding: 6px 0;
        border-radius: 32px;
        background-color: #000;
        color: #fff;
        font-size: 15px;
        text-align: center;
      }

      input[type="text"]:hover {
        background-color: #111
      }

      input[type="text"]:focus {
        outline: none;
        background-color: #111
      }

      input[type="text"]:focus::placeholder {
        color: #090808
      }

      label {
        font-size: 14px;
        position: absolute;
        right: 80px;
        top: 18px;
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
      }

      a:hover {
        color: #54a3f7;
      }

      span {
        display: block;
        margin: 8px 0;
      }

      p {
        font-size: 11px;
      }

      .text-warning {
        color: #db6161;
        fill: #db6161;
      }

    </style>
  </head>
  <body>
    <header>
      <form name="search" method="GET" action="search.php">
        <h1><a href="./"><?php echo _('Yo!') ?></a></h1>
        <input type="text" name="q" placeholder="<?php echo $placeholder ?>" value="<?php echo htmlentities($q) ?>" />
        <?php if ($config->webui->search->extended->enabled) { ?>
          <label for="e">
            <input type="checkbox" name="e" id="e" value="true" <?php echo isset($_GET['e']) ? 'checked="checked"': false ?>/>
            <?php echo _('Extended') ?>
          </label>
        <?php } ?>
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
      <?php if (isset($_GET['e']) && $config->webui->search->extended->enabled) { ?>
        <div>
          <p>
            <?php echo _('Extended syntax enabled, follow') ?>
            <a href="http://sphinxsearch.com/docs/current/extended-syntax.html" rel="nofollow" target="_blank"><?php echo _('Documentation') ?></a>
            <sub>
              <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" fill="currentColor" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M8.636 3.5a.5.5 0 0 0-.5-.5H1.5A1.5 1.5 0 0 0 0 4.5v10A1.5 1.5 0 0 0 1.5 16h10a1.5 1.5 0 0 0 1.5-1.5V7.864a.5.5 0 0 0-1 0V14.5a.5.5 0 0 1-.5.5h-10a.5.5 0 0 1-.5-.5v-10a.5.5 0 0 1 .5-.5h6.636a.5.5 0 0 0 .5-.5"/>
                <path fill-rule="evenodd" d="M16 .5a.5.5 0 0 0-.5-.5h-5a.5.5 0 0 0 0 1h3.793L6.146 9.146a.5.5 0 1 0 .708.708L15 1.707V5.5a.5.5 0 0 0 1 0z"/>
              </svg>
            </sub>
          </p>
          <p>
            <?php echo _('Available fields:') ?>
            <i>@title</i>
            <i>@description</i>
            <i>@keywords</i>
            <i>@mime</i>
            <i>@url</i>
          </p>
        </div>
      <?php } ?>
      <?php if ($response) { ?>
        <div>
          <?php echo $response ?>
        </div>
      <?php } ?>
      <div>
        <?php echo sprintf(_('Found: %s'), number_format($found)) ?>
      </div>
      <?php foreach ($results as $result) { ?>
        <div>
          <?php

            $hostname = parse_url(
              $result->url,
              PHP_URL_HOST
            );

            $identicon = new \Jdenticon\Identicon();

            $identicon->setValue(
                $hostname
            );

            $identicon->setSize(14);

            $identicon->setStyle(
              [
                'backgroundColor' => 'rgba(255, 255, 255, 0)',
                'padding' => 0
              ]
            );

            $icon = $identicon->getImageDataUri('webp');

          ?>
          <img src="<?php echo $icon ?>" title="<?php echo $hostname ?>" alt="identicon" />
          <?php if (!empty($result->title)) { ?>
            <div>
              <h2><?php echo $result->title ?></h2>
            </div>
          <?php } ?>
          <?php if (!empty($result->description)) { ?>
            <div><?php echo $result->description ?></div>
          <?php } ?>
          <?php if (!empty($result->keywords)) { ?>
            <div>
              <?php echo $result->keywords ?>
            </div>
          <?php } ?>
          <div>
            <a href="<?php echo $result->url ?>"><?php echo htmlentities(urldecode($result->url)) ?></a>
            <?php if (!in_array($result->get('code'), [0, 200])) { ?>
              <small>&bull;</small>
              <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" class="text-warning" viewBox="0 0 16 16">
                <path d="m9.97 4.88.953 3.811C10.159 8.878 9.14 9 8 9c-1.14 0-2.158-.122-2.923-.309L6.03 4.88C6.635 4.957 7.3 5 8 5s1.365-.043 1.97-.12m-.245-.978L8.97.88C8.718-.13 7.282-.13 7.03.88L6.275 3.9C6.8 3.965 7.382 4 8 4c.618 0 1.2-.036 1.725-.098zm4.396 8.613a.5.5 0 0 1 .037.96l-6 2a.5.5 0 0 1-.316 0l-6-2a.5.5 0 0 1 .037-.96l2.391-.598.565-2.257c.862.212 1.964.339 3.165.339s2.303-.127 3.165-.339l.565 2.257 2.391.598"/>
              </svg>
              <small><?php echo $result->get('code') ?></small>
            <?php } ?>
            <small>&bull;</small>
            <a rel="nofollow" href="explore.php?i=<?php echo $result->getId() ?>"><?php echo _('explore') ?></a>
          </div>
        </div>
      <?php } ?>
      <?php if ($p * $config->webui->pagination->limit <= $results->getTotal()) { ?>
        <div>
          <div>
            <a href="search.php?q=<?php echo urlencode(htmlentities($q)) ?>&p=<?php echo $p + 1 ?>">
              <?php echo _('More') ?>
            </a>
          </div>
        </div>
      <?php } ?>
    </main>
  </body>
</html>