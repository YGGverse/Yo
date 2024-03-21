# Yo!

Micro Web Crawler in PHP & Manticore

Yo! is the super thin layer for Manticore search server that extends official [manticoresearch-php](https://github.com/manticoresoftware/manticoresearch-php) client with CLI tools and simple JS-less WebUI.

## Features

* MIME-based crawler with flexible filter settings by regular expressions, selectors, external links etc
* Page snap history with local and remote mirrors support (including FTP protocol)
* CLI tools for index administration and crontab tasks
* JS-less frontend to run local or public search web portal
* API tools to make search index distributed

## Components

* [Manticore Server](https://github.com/manticoresoftware/manticoresearch)
* [PHP library for Manticore](https://github.com/manticoresoftware/manticoresearch-php)
* [Symfony DOM crawler](https://github.com/symfony/dom-crawler)
* [Symfony CSS selector](https://github.com/symfony/css-selector)
* [FTP client for snap mirrors](https://github.com/YGGverse/ftp-php)
* [Hostname ident icons](https://github.com/dmester/jdenticon-php)
* [Bootstrap icons](https://icons.getbootstrap.com/)

### Install

#### Environment

##### Debian

* `wget https://repo.manticoresearch.com/manticore-repo.noarch.deb`
* `dpkg -i manticore-repo.noarch.deb`
* `apt update`
* `apt install git composer manticore manticore-extra php-fpm php-curl php-mysql php-mbstring`

Yo search engine uses Manticore as the primary database. If your server sensitive to power down,
change default [binlog flush strategy](https://manual.manticoresearch.com/Logging/Binary_logging#Binary-flushing-strategies) to `binlog_flush = 1`

#### Deployment

Project in development, use `dev-main` branch:

* `composer create-project yggverse/yo:dev-main`

#### Development

* `git clone https://github.com/YGGverse/Yo.git`
* `cd Yo`
* `composer update`
* `git checkout -b pr-branch`
* `git commit -m 'new fix'`
* `git push`

#### Update

* `cd Yo`
* `git pull`
* `composer update`

#### Init

* `cp example/config.json config.json`
* `php src/cli/index/init.php`

#### Usage

* `php src/cli/document/add.php URL`
* `php src/cli/document/crawl.php`
* `php src/cli/document/search.php '*'`

#### Web UI

1. `cd src/webui`
2. `php -S 127.0.0.1:8080`
3. open `http://127.0.0.1:8080` in browser

## Documentation

### CLI

#### Index

##### Init

Create initial index

```
php src/cli/index/init.php [reset]
```
* `reset` - optional, reset existing index

##### Alter

Change existing index

```
php src/cli/index/alter.php {operation} {column} {type}
```
* `operation` - operation name, supported values: `add`|`drop`
* `column` - target column name
* `type` - target column type, supported values: `text`|`integer`

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

##### Clean

Make index optimization, apply new configuration rules

```
php src/cli/document/clean.php [limit]
```

* `limit` - integer, documents quantity per queue

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

### Backup

#### Logical

SQL text dumps could be useful for public index distribution, but requires more computing resources.

[Read more](https://manual.manticoresearch.com/Securing_and_compacting_a_table/Backup_and_restore#Backup-and-restore-with-mysqldump)

#### Physical

Better for infrastructure administration and includes original data binaries.

[Read more](https://manual.manticoresearch.com/Securing_and_compacting_a_table/Backup_and_restore#Using-manticore-backup-command-line-tool)

## Instances

### [Yggdrasil](https://github.com/yggdrasil-network)

* `http://[201:23b4:991a:634d:8359:4521:5576:15b7]/yo/` - IPv6 `0200::/7` addresses only | [index](http://[201:23b4:991a:634d:8359:4521:5576:15b7]/yo/index.sql)

### [Alfis DNS](https://github.com/Revertron/Alfis)

* `http://yo.ygg` - `.ygg` domain zone search only | [index](http://yo.ygg/index.sql)
* `http://ygg.yo.index` - alias of `http://yo.ygg` | [index](http://ygg.yo.index/index.sql)

_*`*.yo.index` reserved for domain-oriented instances e.g. `.btn`, `.conf`, `.mirror` - feel free to request the address_