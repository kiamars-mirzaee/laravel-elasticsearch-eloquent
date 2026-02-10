# Installation & Setup Guide

This guide will walk you through installing and configuring Elasticsearch Eloquent in your Laravel application.

## Prerequisites

Before you begin, ensure you have:

- PHP 8.1 or higher
- Laravel 10.x or 11.x
- Elasticsearch 8.x running and accessible
- Composer installed

## Step 1: Install via Composer

```bash
composer require yourname/elasticsearch-eloquent
```

## Step 2: Publish Configuration

Publish the configuration file to your Laravel application:

```bash
php artisan vendor:publish --tag=elasticsearch-config
```

This will create `config/elasticsearch.php` in your Laravel application.

## Step 3: Configure Environment Variables

Add the following to your `.env` file:

```env
ELASTICSEARCH_HOST=localhost:9200
ELASTICSEARCH_USERNAME=
ELASTICSEARCH_PASSWORD=
```

For production or cloud Elasticsearch:

```env
ELASTICSEARCH_HOST=https://your-cluster.es.cloud:9243
ELASTICSEARCH_USERNAME=elastic
ELASTICSEARCH_PASSWORD=your-password
```

## Step 4: Create Your First Model

Create a new model that extends `ElasticsearchEloquent\Model`:

```php
<?php

namespace App\Models;

use ElasticsearchEloquent\Model;

class Product extends Model
{
    /**
     * The Elasticsearch index name.
     */
    protected string $index = 'products';

    /**
     * The attributes that should be cast.
     */
    protected array $casts = [
        'price' => 'float',
        'in_stock' => 'boolean',
        'cat_id' => 'array',
        'categories' => 'array',
        'tags' => 'array',
    ];
}
```

## Step 5: Ensure Your Index Exists

Before querying, make sure your Elasticsearch index exists. You can create it using:

### Option A: Using Elasticsearch REST API

```bash
curl -X PUT "localhost:9200/products" -H 'Content-Type: application/json' -d'
{
  "mappings": {
    "properties": {
      "name": { "type": "text" },
      "description": { "type": "text" },
      "price": { "type": "float" },
      "in_stock": { "type": "boolean" },
      "cat_id": { "type": "integer" },
      "brand": {
        "type": "nested",
        "properties": {
          "id": { "type": "integer" },
          "name": { "type": "keyword" },
          "country": { "type": "keyword" }
        }
      },
      "categories": {
        "type": "nested",
        "properties": {
          "id": { "type": "integer" },
          "name": { "type": "keyword" },
          "slug": { "type": "keyword" }
        }
      },
      "tags": {
        "type": "nested",
        "properties": {
          "id": { "type": "integer" },
          "name": { "type": "keyword" }
        }
      },
      "created_at": { "type": "date" },
      "updated_at": { "type": "date" }
    }
  }
}
'
```

### Option B: Using Kibana Dev Tools

```json
PUT /products
{
  "mappings": {
    "properties": {
      "name": { "type": "text" },
      "description": { "type": "text" },
      "price": { "type": "float" },
      "in_stock": { "type": "boolean" },
      "cat_id": { "type": "integer" },
      "brand": {
        "type": "nested",
        "properties": {
          "id": { "type": "integer" },
          "name": { "type": "keyword" },
          "country": { "type": "keyword" }
        }
      },
      "categories": {
        "type": "nested"
      },
      "tags": {
        "type": "nested"
      }
    }
  }
}
```

## Step 6: Start Querying

You're all set! Now you can start querying:

```php
use App\Models\Product;

// Get all in-stock products
$products = Product::where('in_stock', true)->get();

// Search for laptops
$products = Product::search('laptop', ['name', 'description'])
    ->where('price', '>', 500)
    ->paginate(20);

// Query nested objects
$products = Product::whereNested('brand', function ($query) {
    $query->where('brand.name', 'TechBrand');
})->get();
```

## Troubleshooting

### Connection Issues

If you can't connect to Elasticsearch:

1. Verify Elasticsearch is running:
   ```bash
   curl http://localhost:9200
   ```

2. Check your `.env` configuration
3. Ensure firewall allows connection
4. For cloud Elasticsearch, verify credentials

### Index Not Found

If you get "index_not_found_exception":

1. Create the index (see Step 5)
2. Verify the index name matches your model
3. Check index exists:
   ```bash
   curl http://localhost:9200/_cat/indices
   ```

### Type Mapping Issues

If data types don't match:

1. Review your Elasticsearch mapping
2. Ensure `$casts` in your model match field types
3. Re-index data if needed

## Advanced Configuration

### Multiple Connections

In `config/elasticsearch.php`:

```php
'connections' => [
    'default' => [
        'hosts' => [env('ELASTICSEARCH_HOST', 'localhost:9200')],
        'username' => env('ELASTICSEARCH_USERNAME', ''),
        'password' => env('ELASTICSEARCH_PASSWORD', ''),
    ],
    'analytics' => [
        'hosts' => [env('ANALYTICS_ES_HOST', 'analytics.es:9200')],
        'username' => env('ANALYTICS_ES_USERNAME', ''),
        'password' => env('ANALYTICS_ES_PASSWORD', ''),
    ],
],
```

### SSL/TLS Configuration

For production with SSL:

```php
use Elasticsearch\ClientBuilder;

$client = ClientBuilder::create()
    ->setHosts(['https://your-cluster.es:9200'])
    ->setBasicAuthentication('username', 'password')
    ->setSSLVerification(true) // Enable SSL verification
    ->build();
```

## Next Steps

- Read the [README.md](../../README.md) for full feature documentation
- Check out [examples/usage_examples.php](examples/usage_examples.php) for more examples
- Join our community for support and discussions

## Support

If you encounter issues:

1. Check the [troubleshooting section](#troubleshooting)
2. Search [existing issues](https://github.com/yourname/elasticsearch-eloquent/issues)
3. Open a new issue with details about your problem

Happy searching! 🔍



# Installation Guide

## Requirements

- PHP 8.1 or higher
- Laravel 10.0 or 11.0
- Elasticsearch 8.0 or higher

## Step 1: Install the Package

### Via Composer (Published Package)

```bash
composer require yourname/elasticsearch-eloquent
```

### Local Development (In Your Laravel Project)

If you're developing the package locally within your Laravel project:

#### 1. Create the Package Directory

```bash
mkdir -p packages/elasticsearch-eloquent
```

#### 2. Copy Package Files

Copy your package files to `packages/elasticsearch-eloquent/`:

```
packages/
└── elasticsearch-eloquent/
    ├── src/
    │   ├── Builder.php
    │   ├── Model.php
    │   ├── ElasticsearchServiceProvider.php
    │   ├── Console/
    │   ├── Jobs/
    │   └── Traits/
    ├── config/
    │   └── elasticsearch.php
    ├── composer.json
    └── README.md
```

#### 3. Update Your Root composer.json

Add the package to your `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "./packages/elasticsearch-eloquent"
        }
    ],
    "require": {
        "yourname/elasticsearch-eloquent": "@dev"
    }
}
```

#### 4. Install the Package

```bash
composer update yourname/elasticsearch-eloquent
```

## Step 2: Publish Configuration

```bash
php artisan vendor:publish --tag=elasticsearch-config
```

This will create `config/elasticsearch.php`.

## Step 3: Configure Environment

Add to your `.env` file:

```env
# Elasticsearch Connection
ELASTICSEARCH_HOST=localhost:9200
ELASTICSEARCH_USERNAME=
ELASTICSEARCH_PASSWORD=

# Optional: Elasticsearch Cloud
ELASTICSEARCH_CLOUD_ID=
ELASTICSEARCH_API_KEY=

# Index Settings
ELASTICSEARCH_SHARDS=1
ELASTICSEARCH_REPLICAS=0

# Queue Settings
ELASTICSEARCH_QUEUE_ENABLED=true
ELASTICSEARCH_QUEUE_CONNECTION=redis
ELASTICSEARCH_QUEUE_NAME=elasticsearch
```

## Step 4: Register Service Provider (If Not Auto-Discovered)

In `config/app.php`:

```php
'providers' => [
    // ...
    ElasticsearchEloquent\ElasticsearchServiceProvider::class,
],
```

## Step 5: Set Up Queue Worker (Optional but Recommended)

If you want background syncing, make sure your queue worker is running:

```bash
php artisan queue:work
```

Or use Supervisor for production:

```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/your/project/artisan queue:work redis --sleep=3 --tries=3
autostart=true
autorestart=true
user=www-data
numprocs=8
redirect_stderr=true
stdout_logfile=/path/to/your/project/storage/logs/worker.log
```

## Step 6: Verify Installation

Test the Elasticsearch connection:

```bash
php artisan elasticsearch:index:list
```

You should see a list of Elasticsearch indices (or an empty table if no indices exist).

## Troubleshooting

### Connection Issues

If you can't connect to Elasticsearch:

1. **Check Elasticsearch is running:**
   ```bash
   curl http://localhost:9200
   ```

2. **Verify credentials:**
    - Check `ELASTICSEARCH_USERNAME` and `ELASTICSEARCH_PASSWORD`
    - Try connecting without credentials if using local development

3. **Check firewall/network:**
    - Ensure port 9200 is accessible
    - If using Docker, verify network configuration

### Service Provider Not Found

If Laravel can't find the service provider:

1. **Clear config cache:**
   ```bash
   php artisan config:clear
   php artisan cache:clear
   ```

2. **Manually register in config/app.php:**
   ```php
   'providers' => [
       ElasticsearchEloquent\ElasticsearchServiceProvider::class,
   ],
   ```

3. **Check composer autoload:**
   ```bash
   composer dump-autoload
   ```

### Queue Not Working

If syncing isn't happening:

1. **Check queue worker is running:**
   ```bash
   ps aux | grep queue:work
   ```

2. **Check queue connection in .env:**
   ```env
   QUEUE_CONNECTION=redis
   ```

3. **Disable queue temporarily for testing:**
   ```env
   ELASTICSEARCH_QUEUE_ENABLED=false
   ```

## Next Steps

- [Quick Starter Guide](quick-starter.md) - Create your first search model
- [Migration from Scout](migrate_from_scout.md) - If you're coming from Laravel Scout
- [API Reference](api-reference.md) - Complete method documentation

## Production Checklist

Before deploying to production:

- [ ] Configure proper replica count (at least 1 for production)
- [ ] Set up monitoring (Kibana or similar)
- [ ] Configure queue workers with Supervisor
- [ ] Set up index aliases for zero-downtime reindexing
- [ ] Enable authentication on Elasticsearch
- [ ] Set up proper backup strategy
- [ ] Configure slow query logging
- [ ] Test failover scenarios
