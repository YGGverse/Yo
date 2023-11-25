# Yo!

Micro Web Crawler in PHP & Manticore

Yo! is the super thin layer for Manticore search server that extends official [manticoresearch-php](https://github.com/manticoresoftware/manticoresearch-php) client with CLI tools and simple JS-less WebUI.

## Components

* CLI tools for index operations
* JS-less frontend to run local or public search web portal
* API tools to make search index distributed

## Features

* MIME-based crawler with flexible filter settings by regular expressions, selectors, external links etc
* Page snap history with local and remote mirrors support (including FTP protocol)

### Install

1. Install `manticore`, `composer` and `php`
2. Grab latest `Yo` version `git clone https://github.com/YGGverse/Yo.git`
3. Run `composer update` inside the project directory
4. Copy and customize config file `cp example/config.json config.json`
5. Make sure `storage` folder writable
6. Run indexes initiation script `php src/cli/index/init.php`
7. Announce new URL `php src/cli/document/add.php URL`
8. Run crawler to grab the data `php src/cli/document/crawl.php`
9. Test search results `php src/cli/document/search.php '*'`

#### Web UI

1. `cd src/webui`
2. `php -S 127.0.0.1:8080`
3. open `127.0.0.1:8080` in browser

## Documentation

### CLI

#### Index

##### Init

Create initial index

```
php src/cli/index/init.php [reset]
```
* `reset` - optional, reset existing index

#### Document

##### Add

```
php src/cli/document/add.php URL
```
* `URL` - add new URL to the crawl queue

##### Crawl

```
php src/cli/document/crawl.php
```

##### Search

```
php src/cli/document/search.php '@title "*"' [limit]
```
* `query` - required
* `limit` - optional search results limit

##### Migration

###### YGGo

Import index from YGGo database

```
php src/cli/yggo/import.php 'host' 'port' 'user' 'password' 'database' [unique=off] [start=0] [limit=100]
```

Source DB fields required:

* `host`
* `port`
* `user`
* `password`
* `database`
* `unique` - optional, check for unique URL (takes more time)
* `start` - optional, offset to start queue
* `limit` - optional, limit queue

## Instances

### Yggdrasil

* `http://[201:23b4:991a:634d:8359:4521:5576:15b7]/yo/`