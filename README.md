# Yo! Micro Web Crawler in PHP & Manticore

Next generation of [YGGo!](https://github.com/YGGverse/YGGo) project with goal to reduce server requirements and make deployment process simpler

 - Index model changed to the distributed cluster model, and oriented to aggregate search results from different instances trough API
 - Refactored data exchange model with drop all primary keys dependencies
 - Snaps now using tar.gz compression to reduce storage requirements and still supporting remote mirrors, FTP including
 - Codebase following minimalism principles everywhere

## Implementation

Engine written in PHP and uses [Manticore](https://github.com/manticoresoftware) on backend.

Default build inspired and adapted for [Yggdrasil](https://github.com/yggdrasil-network) eco-system but could be used to make own search project.

## Components

* CLI tools for index operations
* JS-less frontend to make search web portal
* API tools to make search index distributed

## Features

* MIME-based crawler with flexible filter settings
* Page snap history with local and remote mirrors support

## Documentation

### Install

1. Install php and manticore
2. Grab latest version `git clone https://github.com/YGGverse/Yo.git`
3. Check `src/config.json` for any customizations
4. Run indexes init script `php src/cli/index/init.php`
5. [Start crawling!](https://github.com/YGGverse/Yo#add)

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