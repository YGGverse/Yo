# Yo!

Micro Web Crawler in PHP & Manticore

Yo! is the next generation of [YGGo!](https://github.com/YGGverse/YGGo) project with goal to reduce server requirements and make deployment process simpler.

Engine written in PHP and uses [Manticore](https://github.com/manticoresoftware) search engine on backend.

Default build adapted for [Yggdrasil](https://github.com/yggdrasil-network) eco-system but could be used to make own search project.

Project contain:

* CLI tools for index operations
* JS-less frontend to make search web portal
* API tools to make search index distributed

Features:

* MIME-based crawler with flexible filter settings
* Page snap history with local and remote mirrors support

## CLI

### Index

#### Init

Create initial index

```
php src/cli/index/init.php [reset]
```
* `reset` - optional, reset existing index

### Document

#### Add

```
php src/cli/document/add.php URL
```
* `URL` - add new URL to the crawl queue

#### Crawl

```
php src/cli/document/crawl.php
```

#### Search

```
php src/cli/document/search.php '@title "*"' [limit]
```
* `query` - required
* `limit` - optional search results limit