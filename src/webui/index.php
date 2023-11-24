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

/* @TODO show totals in placeholder

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

*/

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
        font-size: 32px;
        margin: 16px 0
      }

      form {
        display: block;
        max-width: 640px;
        margin: 16% auto;
        text-align: center;
      }

      input {
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
        padding: 10px 16px;
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

      footer > a, a:visited,
      footer > a:active {
        color: #9ba2ac;
        font-size: 12px;
      }

      footer > a:hover {
        color: #54a3f7;
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
      <form name="search" method="GET" action="<?php echo $config->webui->url->base; ?>/search.php">
        <h1><?php echo _('Yo!') ?></h1>
        <input type="text" name="q" placeholder="<?php echo ('request...') ?>" value="" />
        <button type="submit"><?php echo _('search') ?></button>
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
      <a href="https://github.com/YGGverse/Yo"><?php echo _('GitHub') ?></a>
    </footer>
  </body>
</html>