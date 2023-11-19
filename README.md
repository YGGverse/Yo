# Yo!

Micro Web Crawler in PHP & Manticore

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