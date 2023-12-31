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

?>

<!DOCTYPE html>
<html lang="<?php echo _('en-US') ?>">
  <head>
    <title><?php echo _('Yo! Web Search Engine') ?></title>
    <meta charset="utf-8" />
    <meta name="description" content="<?php echo _('Yo! Micro Web Crawler in PHP & Manticore') ?>" />
    <meta name="keywords" content="<?php echo _('web, search, engine, crawler, manticore, yggdrasil, js-less, open source') ?>" />
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
      }

      h1 {
        color: #fff;
        font-weight: normal;
        font-size: 36px;
        margin: 16px 0
      }

      form {
        display: block;
        max-width: 640px;
        margin: 16% auto;
        text-align: center;
      }

      input,
      input:-webkit-autofill,
      input:-webkit-autofill:focus {
        transition: background-color 0s 600000s, color 0s 600000s; /* chrome */
        width: 100%;
        margin: 8px 0;
        padding: 12px 0;
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
        color: #090808;
      }

      button {
        margin: 22px 0;
        padding: 6px 12px;
        border-radius: 4px;
        cursor: pointer;
        background-color: #3394fb;
        color: #fff;
        font-size: 14px;
      }

      button:hover {
        background-color: #4b9df4;
      }

      footer {
        position: fixed;
        bottom: 0;
        left:0;
        right: 0;
        text-align: center;
        padding: 24px;
        color: #9ba2ac;
        font-size: 12px;
      }

      footer > a,
      footer > a:visited,
      footer > a:active {
        color: #9ba2ac;
        font-size: 12px;
      }

      footer > a > svg,
      footer > a:visited > svg,
      footer > a:active > svg {
        fill: #9ba2ac;
      }

      footer > a:hover {
        color: #54a3f7;
      }

      footer > a:hover svg {
        fill: #54a3f7;
      }

      footer > a,
      footer > a:visited,
      footer > a:active {
        text-decoration: none;
      }

      /*
       * CSS animation
       * by https://codepen.io/alvarotrigo/pen/GRvYNax
       */

      main {
        background: #2e3436;
        background: -webkit-linear-gradient(to left, #8f94fb, #4e54c8);
        width: 100%;
      }

      ul {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        overflow: hidden;
        z-index:-1
      }

      li {
        position: absolute;
        display: block;
        list-style: none;
        width: 20px;
        height: 20px;
        background: rgba(255, 255, 255, 0.2);
        animation: animate 25s linear infinite;
          bottom: -150px;
      }

      li:nth-child(1) {
        left: 25%;
        width: 80px;
        height: 80px;
        animation-delay: 0s;
      }

      li:nth-child(2) {
        left: 10%;
        width: 20px;
        height: 20px;
        animation-delay: 2s;
        animation-duration: 12s;
      }

      li:nth-child(3) {
        left: 70%;
        width: 20px;
        height: 20px;
        animation-delay: 4s;
      }

      li:nth-child(4) {
        left: 40%;
        width: 60px;
        height: 60px;
        animation-delay: 0s;
        animation-duration: 18s;
      }

      li:nth-child(5) {
        left: 65%;
        width: 20px;
        height: 20px;
        animation-delay: 0s;
      }

      li:nth-child(6) {
        left: 75%;
        width: 110px;
        height: 110px;
        animation-delay: 3s;
      }

      li:nth-child(7) {
        left: 35%;
        width: 150px;
        height: 150px;
        animation-delay: 7s;
      }

      li:nth-child(8) {
        left: 50%;
        width: 25px;
        height: 25px;
        animation-delay: 15s;
        animation-duration: 45s;
      }

      li:nth-child(9) {
        left: 20%;
        width: 15px;
        height: 15px;
        animation-delay: 2s;
        animation-duration: 35s;
      }

      li:nth-child(10) {
        left: 85%;
        width: 150px;
        height: 150px;
        animation-delay: 0s;
        animation-duration: 11s;
      }

      @keyframes animate {
        0%{
          transform: translateY(0) rotate(0deg);
          opacity: 1;
          border-radius: 0;
        }

        100%{
          transform: translateY(-1000px) rotate(720deg);
          opacity: 0;
          border-radius: 50%;
        }
      }

    </style>
  </head>
  <body>
    <header>
      <form name="search" method="GET" action="search.php">
        <h1><?php echo _('Yo!') ?></h1>
        <input type="text" name="q" placeholder="<?php echo $placeholder ?>" value="" />
        <button type="submit">
          <sub>
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="white" class="bi bi-search" viewBox="0 0 16 16">
              <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0"/>
            </svg>
          </sub>
          &nbsp;
          <?php echo _('Search'); ?>
        </button>
      </form>
    </header>
    <!-- css animation : begin -->
    <main>
      <ul>
        <li></li>
        <li></li>
        <li></li>
        <li></li>
        <li></li>
        <li></li>
        <li></li>
        <li></li>
        <li></li>
        <li></li>
      </ul>
    </main>
    <!-- css animation : end -->
    <footer>
      <?php foreach ($config->webui->footer->links as $i => $link) { ?>
        <?php if ($i) echo '|' ?>
        <a <?php foreach ($link->attributes as $name => $value) { echo sprintf(' %s="%s"', $name, $value); } ?>>
          <?php echo _($link->text) ?>
        </a>
        <?php foreach ($link->index as $index) { ?>
          <a rel="nofollow" href="<?php echo $index ?>" title="<?php echo sprintf(_('Download %s database'), $link->text) ?>">
            <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 16 16">
              <path d="M12.5 9a3.5 3.5 0 1 1 0 7 3.5 3.5 0 0 1 0-7m.354 5.854 1.5-1.5a.5.5 0 0 0-.708-.708l-.646.647V10.5a.5.5 0 0 0-1 0v2.793l-.646-.647a.5.5 0 0 0-.708.708l1.5 1.5a.5.5 0 0 0 .708 0ZM8 1c-1.573 0-3.022.289-4.096.777C2.875 2.245 2 2.993 2 4s.875 1.755 1.904 2.223C4.978 6.711 6.427 7 8 7s3.022-.289 4.096-.777C13.125 5.755 14 5.007 14 4s-.875-1.755-1.904-2.223C11.022 1.289 9.573 1 8 1"/>
              <path d="M2 7v-.839c.457.432 1.004.751 1.49.972C4.722 7.693 6.318 8 8 8s3.278-.307 4.51-.867c.486-.22 1.033-.54 1.49-.972V7c0 .424-.155.802-.411 1.133a4.51 4.51 0 0 0-4.815 1.843A12.31 12.31 0 0 1 8 10c-1.573 0-3.022-.289-4.096-.777C2.875 8.755 2 8.007 2 7m6.257 3.998L8 11c-1.682 0-3.278-.307-4.51-.867-.486-.22-1.033-.54-1.49-.972V10c0 1.007.875 1.755 1.904 2.223C4.978 12.711 6.427 13 8 13h.027a4.552 4.552 0 0 1 .23-2.002m-.002 3L8 14c-1.682 0-3.278-.307-4.51-.867-.486-.22-1.033-.54-1.49-.972V13c0 1.007.875 1.755 1.904 2.223C4.978 15.711 6.427 16 8 16c.536 0 1.058-.034 1.555-.097a4.507 4.507 0 0 1-1.3-1.905"/>
            </svg>
          </a>
        <?php } ?>
      <?php } ?>
    </footer>
  </body>
</html>