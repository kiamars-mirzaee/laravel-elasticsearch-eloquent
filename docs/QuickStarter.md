# 🚀 Quick Start Guide

Get up and running with Elasticsearch Eloquent in 5 minutes!

## Installation

```bash
composer require yourname/elasticsearch-eloquent
php artisan vendor:publish --tag=elasticsearch-config
```

## Configuration

Add to `.env`:
```env
ELASTICSEARCH_HOST=localhost:9200
ELASTICSEARCH_USERNAME=
ELASTICSEARCH_PASSWORD=
```

## Create Your Model

```php
<?php

namespace App\Models;

use ElasticsearchEloquent\Model;

class Product extends Model
{
    protected string $index = 'products';
    
    protected array $casts = [
        'price' => 'float',
        'in_stock' => 'boolean',
        'cat_id' => 'array',
    ];
}
```

## Start Querying!

### Basic Queries

```php
use App\Models\Product;

// Get all products
$products = Product::all();

// Where clause
$products = Product::where('in_stock', true)->get();

// Multiple conditions
$products = Product::where('price', '>', 100)
    ->where('in_stock', true)
    ->get();
```

### Where In/Not In

```php
// Products in categories 1, 5, 12
$products = Product::whereIn('cat_id', [1, 5, 12])->get();

// Exclude categories
$products = Product::whereNotIn('cat_id', [99, 100])->get();
```

### Null Checks

```php
// Products without description
$products = Product::whereNull('description')->get();

// Products with description
$products = Product::whereNotNull('description')->get();
```

### Search

```php
// Full-text search
$products = Product::search('laptop', ['name', 'description'])
    ->where('in_stock', true)
    ->paginate(20);
```

### Nested Objects

```php
// Query nested brand
$products = Product::whereNested('brand', function ($query) {
    $query->where('brand.name', 'TechBrand');
})->get();

// Query nested categories
$products = Product::whereNested('categories', function ($query) {
    $query->where('categories.name', 'Electronics');
})->get();
```

### Sorting & Pagination

```php
// Sort by price
$products = Product::orderBy('price', 'asc')->get();

// Latest products
$products = Product::latest('created_at')->get();

// Paginate
$products = Product::where('in_stock', true)
    ->paginate(15);
```

### Aggregations

```php
// Get statistics
$stats = Product::query()
    ->avgAgg('avg_price', 'price')
    ->minAgg('min_price', 'price')
    ->maxAgg('max_price', 'price')
    ->getAggregations();

echo $stats['avg_price']['value'];
```

## Real-World Example

```php
// E-commerce product search
$products = Product::search('laptop', ['name', 'description'])
    ->where('in_stock', true)
    ->whereBetween('price', [500, 2000])
    ->whereNested('brand', function ($query) {
        $query->where('brand.country', 'USA');
    })
    ->whereNested('tags', function ($query) {
        $query->whereIn('tags.name', ['professional', 'gaming']);
    })
    ->orderBy('price', 'asc')
    ->paginate(20);

foreach ($products as $product) {
    echo $product->name;
    echo $product->price;
    echo $product->brand['name'];
}
```

## Available Methods

### Where Clauses
- `where($column, $operator, $value)`
- `orWhere($column, $operator, $value)`
- `whereIn($column, $values)`
- `whereNotIn($column, $values)`
- `whereNull($column)`
- `whereNotNull($column)`
- `whereNot($column, $operator, $value)`
- `whereBetween($column, [$min, $max])`
- `whereNotBetween($column, [$min, $max])`
- `whereNested($path, $callback)`

### Search
- `search($query, $fields)`
- `matchPhrase($column, $value)`
- `minScore($score)`

### Sorting
- `orderBy($column, $direction)`
- `orderByDesc($column)`
- `latest($column)`
- `oldest($column)`

### Limiting
- `limit($count)`
- `take($count)`
- `offset($count)`
- `skip($count)`
- `paginate($perPage)`

### Retrieval
- `get($columns)`
- `first($columns)`
- `count()`

### Aggregations
- `termsAgg($name, $field, $size)`
- `sumAgg($name, $field)`
- `avgAgg($name, $field)`
- `minAgg($name, $field)`
- `maxAgg($name, $field)`
- `getAggregations()`

### Source Filtering
- `select($columns)`

## Need Help?

- 📖 Full docs: [README.md](../../README.md)
- 💡 Examples: [examples/usage_examples.php](examples/usage_examples.php)
- 🔧 Installation: [INSTALLATION.md](INSTALLATION.md)
- 🏗️ Structure: [STRUCTURE.md](STRUCTURE.md)

## Next Steps

1. Create your Elasticsearch index
2. Define your models
3. Start querying!
4. Add model scopes for reusable queries
5. Explore aggregations for analytics

Happy searching! 🎉
