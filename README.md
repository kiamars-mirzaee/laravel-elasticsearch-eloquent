# Elasticsearch Eloquent

An elegant Eloquent-style query builder for Elasticsearch in Laravel. Write Elasticsearch queries using familiar Laravel syntax.

[![PHP Version](https://img.shields.io/badge/php-%5E8.1-blue)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/laravel-%5E10.0%20%7C%20%5E11.0-red)](https://laravel.com)
[![Elasticsearch Version](https://img.shields.io/badge/elasticsearch-%5E8.0-orange)](https://www.elastic.co)

## Features

✅ **Eloquent-style Query Builder** - Familiar Laravel syntax for Elasticsearch  
✅ **Comprehensive Where Clauses** - `where`, `whereIn`, `whereNull`, `whereNot`, `whereBetween`  
✅ **Nested Object Support** - Query nested objects with `whereNested()`  
✅ **Full-Text Search** - Powerful search with `search()` and `matchPhrase()`  
✅ **Aggregations** - `termsAgg()`, `sumAgg()`, `avgAgg()`, `minAgg()`, `maxAgg()`  
✅ **Sorting & Pagination** - `orderBy()`, `latest()`, `paginate()`  
✅ **Source Filtering** - Select specific fields with `select()`  
✅ **Type Casting** - Automatic type casting for attributes  
✅ **Model Scopes** - Define reusable query scopes

## Installation

Install via Composer:

```bash
composer require kiamars-mirzaee/elasticsearch-eloquent
```

### Publish Configuration

```bash
php artisan vendor:publish --tag=elasticsearch-config
```

### Environment Variables

Add to your `.env`:

```env
ELASTICSEARCH_HOST=localhost:9200
ELASTICSEARCH_USERNAME=
ELASTICSEARCH_PASSWORD=
```

## Quick Start

### 1. Create Your Model

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

### 2. Query Your Data

```php
// Simple where clause
$products = Product::where('in_stock', true)->get();

// Multiple conditions
$products = Product::where('price', '>', 100)
    ->where('price', '<', 500)
    ->orderBy('price', 'asc')
    ->get();

// Full-text search
$products = Product::search('laptop', ['name', 'description'])
    ->where('in_stock', true)
    ->paginate(20);
```

---

## 🚨 Scout Pitfalls in Production (And How This Package Solves Them)

If you've used **Laravel Scout** in production, you've likely encountered these challenges. Here's what breaks at scale—and how **Elasticsearch Eloquent** handles it differently.

### ❌ Pitfall 1: Dynamic Mapping Surprises

**The Problem:**
Scout relies on Elasticsearch's dynamic mapping. Elasticsearch guesses field types based on the first document it sees:
- A timestamp might become a `text` field instead of `date`
- A float might become `long`, breaking range queries
- Changing a field type later requires full reindexing

**Real Production Scenario:**
```php
// First product has price "99.99" → mapped as text
Product::create(['name' => 'Book', 'price' => '99.99']);

// Later, range query fails silently or returns wrong results
Product::search()->where('price', '>', 100)->get(); // ❌ Broken
```

**How Elasticsearch Eloquent Solves This:**
```php
class Product extends Model
{
    protected array $mapping = [
        'properties' => [
            'price' => ['type' => 'float'],
            'created_at' => ['type' => 'date'],
            'in_stock' => ['type' => 'boolean'],
        ]
    ];
}

// Explicit mappings = predictable behavior
Product::where('price', '>', 100)->get(); // ✅ Works correctly
```

**Why This Matters:**
- Mappings are **source of truth** in your codebase
- No silent failures in production
- Schema evolution is intentional, not accidental

---

### ❌ Pitfall 2: Nested Object Chaos

**The Problem:**
Scout doesn't understand nested objects. Denormalized data (common in Elasticsearch) becomes impossible to query correctly.

**Real Production Scenario:**
```json
// Your Elasticsearch document
{
  "name": "Laptop",
  "categories": [
    {"id": 1, "name": "Electronics"},
    {"id": 5, "name": "Computers"}
  ]
}
```

```php
// Scout can't do this properly
// You end up writing raw Elasticsearch queries in controllers
$client->search([
    'index' => 'products',
    'body' => [
        'query' => [
            'nested' => [
                'path' => 'categories',
                'query' => ['term' => ['categories.id' => 5]]
            ]
        ]
    ]
]);
```

**How Elasticsearch Eloquent Solves This:**
```php
// Clean, readable, testable
Product::whereNested('categories', function ($query) {
    $query->where('categories.id', 5);
})->get();

// Multiple nested conditions
Product::whereNested('categories', function ($query) {
    $query->where('categories.name', 'Electronics')
          ->where('categories.featured', true);
})->get();
```

**Why This Matters:**
- No raw arrays in controllers
- Testable query logic
- Reusable via model scopes

---

### ❌ Pitfall 3: No Aggregation Support

**The Problem:**
Scout is built for search, not analytics. Want to know the average price? Top-selling categories? You're back to raw Elasticsearch queries.

**Real Production Scenario:**
```php
// Scout forces you to drop down to raw queries
$client = app(\Elastic\Elasticsearch\Client::class);
$results = $client->search([
    'index' => 'products',
    'body' => [
        'size' => 0,
        'aggs' => [
            'avg_price' => ['avg' => ['field' => 'price']],
            'categories' => ['terms' => ['field' => 'category_id']]
        ]
    ]
]);
```

**How Elasticsearch Eloquent Solves This:**
```php
$query = Product::where('in_stock', true)
    ->avgAgg('average_price', 'price')
    ->termsAgg('top_categories', 'category_id', 10)
    ->sumAgg('total_inventory_value', 'price');

$products = $query->get();
$stats = $query->getAggregations();

echo $stats['average_price']['value'];
// ["buckets" => [["key" => 1, "doc_count" => 150], ...]]
```

**Why This Matters:**
- Analytics in the same query as results
- No context switching between search and stats
- Stays inside Laravel's mental model

---

### ❌ Pitfall 4: Broken Bulk Updates

**The Problem:**
Scout syncs via model events (`saved`, `deleted`). Bulk updates bypass these events entirely.

**Real Production Scenario:**
```php
// This updates MySQL but NOT Elasticsearch
Product::where('category_id', 5)->update(['featured' => true]);

// Your search results are now stale
Product::search('laptop')->where('featured', true)->get(); // ❌ Missing data
```

**Scout's "Solution":** Manually re-sync thousands of records:
```php
Product::where('category_id', 5)->searchable(); // Slow, blocks requests
```

**How Elasticsearch Eloquent Solves This:**
```php
// Option 1: Trait-based auto-sync (via queues)
use ElasticsearchEloquent\Concerns\Searchable;

class Product extends EloquentModel
{
    use Searchable;
    
    protected static $searchableAs = \App\SearchModels\Product::class;
}

// Option 2: Explicit bulk sync job
Product::where('category_id', 5)
    ->chunk(1000, function ($products) {
        BulkSyncToElasticsearch::dispatch($products);
    });
```

**Why This Matters:**
- Queues prevent blocking user requests
- Explicit control over sync strategy
- Designed for high-throughput systems

---

### ❌ Pitfall 5: No Zero-Downtime Reindexing

**The Problem:**
Scout writes directly to `products` index. To change mappings (e.g., text → keyword), you must:
1. Drop the index → Downtime ❌
2. Recreate with new mappings
3. Re-sync all data → More downtime ❌

**Real Production Scenario:**
```bash
# Your app breaks during this process
curl -X DELETE localhost:9200/products
curl -X PUT localhost:9200/products -d '{"mappings": {...}}'
php artisan scout:import "App\Models\Product" # 30+ minutes
```

**How Elasticsearch Eloquent Solves This:**
```php
// Use aliases (industry standard pattern)
// 1. Create new index
Product::createIndex('products_v2', $newMapping);

// 2. Reindex data (background job)
ReindexJob::dispatch('products_v1', 'products_v2');

// 3. Atomic alias swap (zero downtime)
Product::swapAlias('products', 'products_v1', 'products_v2');

// 4. Delete old index
Product::deleteIndex('products_v1');
```

**Why This Matters:**
- No downtime during schema changes
- Production-safe deployments
- Rollback safety (keep old index until verified)

---

### ❌ Pitfall 6: Limited Query Expressiveness

**The Problem:**
Scout's query builder is intentionally simple. Complex scoring, boosting, or multi-field queries require dropping into raw Elasticsearch DSL.

**Real Production Scenario:**
```php
// Scout can't express this cleanly
$client->search([
    'index' => 'products',
    'body' => [
        'query' => [
            'bool' => [
                'should' => [
                    ['match' => ['name' => ['query' => 'laptop', 'boost' => 3]]],
                    ['match' => ['description' => 'laptop']],
                ],
                'filter' => [
                    ['term' => ['in_stock' => true]],
                    ['range' => ['price' => ['lte' => 2000]]]
                ]
            ]
        ]
    ]
]);
```

**How Elasticsearch Eloquent Solves This:**
```php
// Option 1: Expressive API
Product::search('laptop', ['name^3', 'description'])
    ->where('in_stock', true)
    ->where('price', '<=', 2000)
    ->get();

// Option 2: Model scopes for reusability
Product::smartSearch('title', 'laptop')
    ->inStock()
    ->get();

// Option 3: Raw DSL when needed (escape hatch)
Product::whereRaw([
    'bool' => [
        'should' => [
            ['prefix' => ['name.keyword' => ['value' => 'lap', 'boost' => 10]]],
            ['match_phrase_prefix' => ['name' => 'laptop']],
        ]
    ]
])->get();
```

**Why This Matters:**
- Start simple, grow into complexity
- Escape hatch without leaving the query builder
- Reusable logic via scopes

---

### ❌ Pitfall 7: Analyzer and Tokenization Blindness

**The Problem:**
Scout doesn't expose Elasticsearch's analyzer system. Multi-language search, autocomplete, or stemming requires manual index configuration outside Laravel.

**Real Production Scenario:**
```php
// Persian/Arabic users search for "لپ‌تاپ"
// Scout's default analyzer fails because it doesn't handle:
// - Right-to-left text
// - Diacritics normalization
// - Language-specific stemming
```

**How Elasticsearch Eloquent Solves This:**
```php
class Product extends Model
{
    protected array $settings = [
        'analysis' => [
            'analyzer' => [
                'persian_normalized' => [
                    'type' => 'custom',
                    'tokenizer' => 'standard',
                    'filter' => ['lowercase', 'arabic_normalization', 'persian_normalization']
                ]
            ]
        ]
    ];
    
    protected array $mapping = [
        'properties' => [
            'title' => [
                'type' => 'text',
                'fields' => [
                    'normalized' => ['type' => 'text', 'analyzer' => 'persian_normalized'],
                    'keyword' => ['type' => 'keyword']
                ]
            ]
        ]
    ];
}

// Now search works correctly for Persian users
Product::smartSearch('title', 'لپ‌تاپ')->get();
```

**Why This Matters:**
- Analyzers are code, not external config
- Multi-language apps are first-class
- Autocomplete, stemming, n-grams are accessible

---

## 📋 TL;DR: Scout → Elasticsearch Eloquent Migration Checklist

### When to Migrate

Migrate from Scout if you're experiencing:

- [ ] Incorrect search results due to dynamic mapping issues
- [ ] Need for nested object queries (denormalized data)
- [ ] Requirement for aggregations/analytics alongside search
- [ ] Bulk update sync failures
- [ ] Downtime during mapping changes
- [ ] Multi-language search challenges
- [ ] Complex scoring/boosting needs

### Migration Steps

#### 1. **Install Package**
```bash
composer require kiamars-mirzaee/elasticsearch-eloquent
php artisan vendor:publish --tag=elasticsearch-config
```

#### 2. **Create Search Models** (Separate from Eloquent Models)
```php
// app/SearchModels/Product.php
namespace App\SearchModels;

use ElasticsearchEloquent\Model;

class Product extends Model
{
    protected string $index = 'products';
    
    // ✅ Explicit mappings (no dynamic mapping surprises)
    protected array $mapping = [
        'properties' => [
            'id' => ['type' => 'keyword'],
            'name' => ['type' => 'text'],
            'price' => ['type' => 'float'],
            'in_stock' => ['type' => 'boolean'],
            'created_at' => ['type' => 'date'],
        ]
    ];
    
    // ✅ Custom analyzers for your use case
    protected array $settings = [
        'number_of_shards' => 2,
        'number_of_replicas' => 1,
    ];
    
    protected array $casts = [
        'price' => 'float',
        'in_stock' => 'boolean',
    ];
}
```

#### 3. **Add Searchable Trait to Eloquent Models** (Optional Auto-Sync)
```php
// app/Models/Product.php (your database model)
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use ElasticsearchEloquent\Concerns\Searchable;

class Product extends Model
{
    use Searchable;
    
    // Link to your search model
    protected static $searchableAs = \App\SearchModels\Product::class;
    
    // Optional: customize what data gets indexed
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => $this->price,
            'in_stock' => $this->in_stock,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
```

#### 4. **Create Index with Mappings**
```bash
php artisan tinker
```
```php
\App\SearchModels\Product::createIndex();
```

#### 5. **Initial Data Migration**
```php
// Option 1: Bulk sync via chunk (recommended)
\App\Models\Product::chunk(1000, function ($products) {
    \App\Jobs\BulkSyncToElasticsearch::dispatch($products);
});

// Option 2: One-time manual sync
\App\Models\Product::all()->each(function ($product) {
    $product->searchable(); // If using Searchable trait
});
```

#### 6. **Update Queries in Controllers**

**Before (Scout):**
```php
// Limited expressiveness
$products = Product::search($request->query)
    ->where('in_stock', 1)
    ->paginate();
```

**After (Elasticsearch Eloquent):**
```php
// Full power of Elasticsearch
$products = \App\SearchModels\Product::search($request->query, ['name', 'description'])
    ->where('in_stock', true)
    ->whereBetween('price', [$minPrice, $maxPrice])
    ->whereNested('categories', function ($q) use ($categoryId) {
        $q->where('categories.id', $categoryId);
    })
    ->termsAgg('top_brands', 'brand.name', 10)
    ->orderBy('_score', 'desc')
    ->paginate(20);
```

#### 7. **Verify Sync is Working**
```php
// Create a product
$product = Product::create([...]);

// Check it appears in Elasticsearch (within queue delay)
sleep(2); // Wait for queue to process
$found = \App\SearchModels\Product::where('id', $product->id)->first();
dd($found); // Should show your product
```

#### 8. **Remove Scout (Optional)**
```bash
composer remove laravel/scout
```

Remove Scout config and service provider references.

---

### Common Migration Patterns

#### Pattern 1: Keep Both (Gradual Migration)

```php
// Keep Scout for simple searches
use Laravel\Scout\Searchable as ScoutSearchable;

// Use Elasticsearch Eloquent for complex searches
use ElasticsearchEloquent\Concerns\Searchable as ElasticSearchable;

class Product extends Model
{
    use ScoutSearchable, ElasticSearchable;
    
    protected static $searchableAs = \App\SearchModels\Product::class;
}
```

#### Pattern 2: Separate Search Logic

```php
// app/Services/ProductSearchService.php
class ProductSearchService
{
    public function search(array $filters)
    {
        $query = SearchProduct::query();
        
        if ($filters['query'] ?? null) {
            $query->search($filters['query'], ['name', 'description']);
        }
        
        if ($filters['price_min'] ?? null) {
            $query->where('price', '>=', $filters['price_min']);
        }
        
        return $query->paginate(20);
    }
}
```

#### Pattern 3: Model Scopes for Reusability

```php
// app/SearchModels/Product.php
public function scopeInStock($query)
{
    return $query->where('in_stock', true);
}

public function scopePriceRange($query, $min, $max)
{
    return $query->whereBetween('price', [$min, $max]);
}

// Usage
Product::inStock()->priceRange(100, 500)->get();
```

---

### Performance Checklist

After migration:

- [ ] Verify mappings are correct (`GET /products/_mapping`)
- [ ] Test query performance (`/_search?explain=true`)
- [ ] Monitor slow queries (Elasticsearch slow log)
- [ ] Set up index aliases for zero-downtime updates
- [ ] Configure replica count based on read load
- [ ] Add monitoring (Kibana or similar)
- [ ] Test sync under load (queue monitoring)

---

## Documentation

### Basic Where Clauses

```php
// Equal
Product::where('name', 'Laptop')->get();
Product::where('price', 999.99)->get();

// Operators
Product::where('price', '>', 500)->get();
Product::where('price', '<=', 1000)->get();

// Multiple conditions (AND)
Product::where('in_stock', true)
    ->where('price', '>', 100)
    ->get();

// Array of conditions
Product::where([
    'in_stock' => true,
    'featured' => true,
])->get();

// OR conditions
Product::where('category', 'Electronics')
    ->orWhere('category', 'Computers')
    ->get();
```

### Where In / Not In

```php
// Where in
Product::whereIn('cat_id', [1, 5, 12])->get();

// Where not in
Product::whereNotIn('cat_id', [99, 100])->get();

// Or where in
Product::where('in_stock', true)
    ->orWhereIn('category_id', [1, 2, 3])
    ->get();
```

### Where Null / Not Null

```php
// Where null
Product::whereNull('description')->get();

// Where not null
Product::whereNotNull('description')->get();

// Or where null
Product::where('price', 0)
    ->orWhereNull('price')
    ->get();
```

### Where Not

```php
// Where not equal
Product::whereNot('status', 'archived')->get();

// Where not with operator
Product::whereNot('price', '>', 5000)->get();

// Multiple where not
Product::whereNot([
    'archived' => true,
    'deleted' => true,
])->get();
```

### Where Between

```php
// Between
Product::whereBetween('price', [100, 500])->get();

// Not between
Product::whereNotBetween('price', [0, 10])->get();

// Date range
Product::whereBetween('created_at', [
    '2024-01-01',
    '2024-12-31',
])->get();
```

### Nested Object Queries

Perfect for denormalized data structures:

```php
// Your Elasticsearch document structure:
{
  "name": "Laptop Pro",
  "brand": {
    "id": 10,
    "name": "TechBrand",
    "country": "USA"
  },
  "categories": [
    {"id": 1, "name": "Electronics"},
    {"id": 5, "name": "Computers"}
  ],
  "tags": [
    {"id": 20, "name": "laptop"},
    {"id": 21, "name": "professional"}
  ]
}

// Query nested brand
Product::whereNested('brand', function ($query) {
    $query->where('brand.name', 'TechBrand')
          ->where('brand.country', 'USA');
})->get();

// Query nested categories array
Product::whereNested('categories', function ($query) {
    $query->where('categories.name', 'Electronics');
})->get();

// Query nested tags
Product::whereNested('tags', function ($query) {
    $query->whereIn('tags.name', ['laptop', 'gaming']);
})->get();
```

### Full-Text Search

```php
// Search across all fields
Product::search('gaming laptop')->get();

// Search specific fields
Product::search('laptop', ['name', 'description'])->get();

// Search with filters
Product::search('laptop', ['name', 'description'])
    ->where('price', '<', 2000)
    ->where('in_stock', true)
    ->paginate(20);

// Match phrase (exact phrase)
Product::matchPhrase('description', 'high performance')->get();

// Minimum score threshold
Product::search('laptop')
    ->minScore(1.5)
    ->get();
```

### Sorting

```php
// Order by
Product::orderBy('price', 'asc')->get();
Product::orderBy('created_at', 'desc')->get();

// Order by descending
Product::orderByDesc('price')->get();

// Multiple sorts
Product::orderBy('in_stock', 'desc')
    ->orderBy('price', 'asc')
    ->get();

// Latest/Oldest helpers
Product::latest('created_at')->get();
Product::oldest('created_at')->get();
```

### Limiting & Pagination

```php
// Take/Limit
Product::take(10)->get();
Product::limit(10)->get();

// Skip/Offset
Product::skip(20)->take(10)->get();
Product::offset(20)->limit(10)->get();

// First result
$product = Product::where('name', 'Laptop')->first();

// Pagination
$products = Product::where('in_stock', true)
    ->orderBy('price', 'desc')
    ->paginate(15);

// Custom page
$products = Product::paginate(20, ['*'], 'page', 2);
```

### Selecting Fields

```php
// Select specific fields (source filtering)
Product::select(['name', 'price', 'brand'])->get();

// Get with columns
Product::where('in_stock', true)->get(['name', 'price']);
```

### Aggregations

```php
// Terms aggregation (category distribution)
$aggs = Product::query()
    ->termsAgg('categories', 'cat_id', 20)
    ->getAggregations();

// Sum aggregation
$aggs = Product::query()
    ->sumAgg('total_value', 'price')
    ->getAggregations();

// Average
$aggs = Product::query()
    ->avgAgg('average_price', 'price')
    ->getAggregations();

// Min & Max
$aggs = Product::query()
    ->minAgg('min_price', 'price')
    ->maxAgg('max_price', 'price')
    ->getAggregations();

// Multiple aggregations
$aggs = Product::query()
    ->where('in_stock', true)
    ->termsAgg('top_brands', 'brand.name', 10)
    ->avgAgg('avg_price', 'price')
    ->sumAgg('total_value', 'price')
    ->getAggregations();
```

### Count

```php
// Count all
$count = Product::count();

// Count with conditions
$count = Product::where('in_stock', true)->count();
$count = Product::whereIn('cat_id', [1, 2, 3])->count();
```

### Model Scopes

Define reusable query logic:

```php
class Product extends Model
{
    public function scopeInStock($query)
    {
        return $query->where('in_stock', true);
    }
    
    public function scopeByBrand($query, string $brandName)
    {
        return $query->whereNested('brand', function ($q) use ($brandName) {
            $q->where('brand.name', $brandName);
        });
    }
    
    public function scopeInCategory($query, int $categoryId)
    {
        return $query->whereIn('cat_id', [$categoryId]);
    }
}

// Use scopes
Product::inStock()->get();
Product::byBrand('TechBrand')->get();
Product::inCategory(5)->get();

// Chain scopes
Product::inStock()
    ->byBrand('TechBrand')
    ->orderBy('price', 'asc')
    ->get();
```

## Complex Examples

### Example 1: E-commerce Product Search

```php
$products = Product::search('laptop', ['name', 'description'])
    ->where('in_stock', true)
    ->whereBetween('price', [500, 2000])
    ->whereNested('brand', function ($query) {
        $query->where('brand.country', 'USA');
    })
    ->whereNotIn('cat_id', [99, 100]) // Exclude archived
    ->orderBy('price', 'asc')
    ->paginate(20);
```

### Example 2: Search with Analytics

```php
$query = Product::search('electronics')
    ->whereIn('cat_id', [1, 5, 12])
    ->where('price', '>', 100)
    ->termsAgg('top_brands', 'brand.name', 10)
    ->avgAgg('average_price', 'price')
    ->sumAgg('total_inventory', 'stock_count');

$products = $query->get();
$stats = $query->getAggregations();

// Access aggregations
$topBrands = $stats['top_brands']['buckets'];
$avgPrice = $stats['average_price']['value'];
```

### Example 3: Multi-Condition Filtering

```php
$products = Product::where('in_stock', true)
    ->whereNested('categories', function ($query) {
        $query->where('categories.slug', 'electronics');
    })
    ->whereNested('tags', function ($query) {
        $query->whereIn('tags.name', ['featured', 'bestseller']);
    })
    ->whereNotNull('description')
    ->whereBetween('price', [100, 1000])
    ->latest('created_at')
    ->select(['name', 'price', 'brand', 'categories'])
    ->paginate(25);
```

## Working with Results

```php
$products = Product::where('in_stock', true)->get();

foreach ($products as $product) {
    // Access attributes
    echo $product->name;
    echo $product->price;
    
    // Access nested objects
    echo $product->brand['name'];
    
    // Access nested arrays
    foreach ($product->categories as $category) {
        echo $category['name'];
    }
}

// Convert to array
$array = $product->toArray();

// Check existence
if ($product->exists) {
    // Product exists in Elasticsearch
}
```

## Type Casting

```php
class Product extends Model
{
    protected array $casts = [
        'price' => 'float',
        'in_stock' => 'boolean',
        'cat_id' => 'array',
        'categories' => 'array',
        'tags' => 'array',
        'metadata' => 'json',
    ];
}
```

## Performance Tips

```php
// ✅ Use select() for better performance
Product::select(['name', 'price'])
    ->where('in_stock', true)
    ->get();

// ✅ Use count() instead of get()->count()
$count = Product::where('in_stock', true)->count();

// ✅ Use pagination for large datasets
$products = Product::paginate(50);

// ✅ Use aggregations for analytics
$stats = Product::query()
    ->avgAgg('avg_price', 'price')
    ->getAggregations();
```

## Advance search with whereRaw

How to Use (Model Scope)
Don't write that massive array in your Controller. Use a Model Scope to encapsulate the logic. This keeps your code clean and reusable.

Add this method to your Product model (or whatever Model you are searching):

```php

// In App/SearchModels/Product.php

public function scopeSmartSearch($query, string $fieldName, string $searchTerm)
{
    // Define the complex raw query logic here
    $rawQuery = [
        'bool' => [
            'should' => [
                [
                    'prefix' => [
                        $fieldName . '.kw' => [
                            'value' => $searchTerm,
                            'boost' => 10,
                        ],
                    ],
                ],
                [
                    'match_phrase_prefix' => [
                        $fieldName . '.normalized' => [
                            'query' => $searchTerm,
                            'analyzer' => 'persian_normalized_analyzer',
                            'boost' => 8,
                        ],
                    ],
                ],
                [
                    'match_phrase' => [
                        $fieldName . '.normalized' => [
                            'query' => $searchTerm,
                            'analyzer' => 'persian_normalized_analyzer',
                            'boost' => 7,
                        ],
                    ],
                ],
                // ... add other clauses here ...
                [
                    'match' => [
                        $fieldName . '.normalized' => [
                            'query' => $searchTerm,
                            'analyzer' => 'persian_normalized_analyzer',
                            'boost' => 2,
                            'operator' => 'and',
                        ],
                    ],
                ],
            ],
            'minimum_should_match' => 1,
        ],
    ];

    // Pass it to the new whereRaw method
    return $query->whereRaw($rawQuery);
}
```

**Now you can use it cleanly in your application:**

```php
$products = Product::smartSearch('title', 'my search query')
    ->where('in_stock', true)
    ->paginate(20);
```


## Advice for Senior Engineers

* **Mappings** as Code (Source of Truth): Elasticsearch tries to be smart with "Dynamic Mapping," but in production, this is dangerous (e.g., it might guess a timestamp is just a string, or a float is an integer). Always define your mappings explicitly in your Model. This keeps your codebase as the "Source of Truth," similar to Laravel Migrations.


* **Zero-Downtime** Reindexing (Aliases):
  The Problem: You cannot change the type of an existing field in Elasticsearch (e.g., text to keyword) without reindexing.
  The Solution: Never write directly to an index named products.
  Create an index products_v1.
  Create an alias products pointing to products_v1.
  Your app reads/writes to products.
  When mapping changes: Create products_v2, reindex data, switch alias products to products_v2, delete products_v1.
  Note: The implementation below handles direct index creation for simplicity, but you can extend IndexManager to handle alias swapping later.


* **Settings Matter**: Don't forget settings. This is where you define your Analyzers (e.g., n-grams for partial matching) and Replicas (for high availability).

## Sync Database Model with Elastic

when you need to sync a Relational Database (SQL) with a NoSQL search engine like Elasticsearch, you must solve for Consistency, Throughput, and Reliability.


For a Laravel-based portfolio, the most professional architecture isn't just "calling a function on save." It's a Trait-based Observer pattern using the Outbox pattern (Queues).

Here is the implementation plan to automate this, including support for bulk updates.
1. The Searchable Trait
   We create a trait for your Eloquent models (the database models) that automatically hooks into Laravel's model events.
2. The High-Performance Sync Job
   This job handles the actual communication with your Elasticsearch Model. It uses ShouldQueue to ensure database transactions aren't blocked by network latency.
3. Handling Bulk Updates (The "Senior" Way)
   Laravel's saved event doesn't fire for bulk queries like Product::where('active', 1)->update(['price' => 10]). To solve this, we add a Bulk helper to your Builder.php.

## Why This Exists (And How It Differs from Laravel Scout)

Laravel Scout is an excellent starting point for search in Laravel.
It's simple, familiar, and works well for basic full-text search and syncing models to search engines.

However, as applications grow, teams often hit Scout's natural limits:

* Limited control over Elasticsearch mappings and analyzers

* Minimal support for nested objects and complex queries

* No first-class story for zero-downtime reindexing

* Difficult to express advanced scoring, aggregations, or analytics queries

* Sync logic that can become fragile under heavy load or queue failures


**Elasticsearch Eloquent exists for teams that have outgrown Scout.**

This package does not try to replace Scout's simplicity.
Instead, it provides a lower-level, production-focused abstraction for Elasticsearch that gives you full control while keeping Laravel ergonomics.

**Key Differences**

* **Query-first, not sync-first**
* Scout focuses on syncing Eloquent models to search engines. Elasticsearch Eloquent focuses on querying Elasticsearch as a primary data source, using an Eloquent-style API.

* **Explicit mappings and analyzers**
* Field types, analyzers, and nested structures are defined intentionally. No reliance on dynamic mapping or hidden defaults.

* **First-class support for complex queries**
* Nested queries, aggregations, scoring, and raw Elasticsearch DSL are supported without escaping into controllers.

* **Zero-downtime indexing patterns**
* The architecture encourages alias-based indexing and reindexing, making schema evolution safe in production.

* **Designed for stronger consistency guarantees**
* The package is built to integrate cleanly with queue-based syncing and Outbox-pattern workflows for systems where data correctness matters.

**In short:**

Scout is great for getting search working.
Elasticsearch Eloquent is for keeping search working as your system scales.

If you need deep Elasticsearch control while staying inside Laravel's mental model, this package is built for that stage of growth.


| Feature / Concern                    | Laravel Scout                       | Elasticsearch Eloquent                         |
| ------------------------------------ | ----------------------------------- | ---------------------------------------------- |
| Primary Goal                         | Simple model syncing & basic search | Production-grade Elasticsearch querying        |
| Learning Curve                       | Very low                            | Moderate (intentional control)                 |
| Query Style                          | Limited, engine-dependent           | Eloquent-style, expressive query builder       |
| Elasticsearch DSL Access             | Minimal / indirect                  | Full access via `whereRaw()`                   |
| Nested Object Queries                | Limited                             | First-class support                            |
| Aggregations & Analytics             | ❌ Not supported                     | ✅ Fully supported                              |
| Custom Scoring & Boosting            | Limited                             | ✅ Supported                                    |
| Explicit Mappings                    | ❌ No                                | ✅ Yes (source of truth)                        |
| Custom Analyzers (e.g. multilingual) | Limited                             | ✅ First-class support                          |
| Dynamic Mapping Reliance             | High                                | None (explicit by design)                      |
| Zero-Downtime Reindexing (Aliases)   | ❌ Manual                            | ✅ Architecture-friendly                        |
| Query as Primary Data Source         | ❌ Not intended                      | ✅ Designed for it                              |
| Sync Strategy                        | Model events                        | Queue & Outbox-friendly                        |
| Bulk Update Handling                 | ❌ Limited                           | ✅ Designed for it                              |
| Controller-Free Complex Search       | ❌ Difficult                         | ✅ Model scopes & raw queries                   |
| Best For                             | Small–medium apps, simple search    | Large apps, complex search, production systems |



## Requirements

- PHP 8.1 or higher
- Laravel 10.0 or 11.0
- Elasticsearch 8.0 or higher

## Testing

```bash
composer test
```


## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.


## License

This package is open-sourced software licensed under the [MIT license](LICENSE).

## Credits

Created by Kiamars mirzaee

## Support

- 📧 Email: kiamars-mirzaee@gmail.com
- 🐛 Issues: [GitHub Issues](https://github.com/kiamars-mirzaee/elasticsearch-eloquent/issues)
- 📖 Documentation: [Full Documentation](https://github.com/kiamars-mirzaee/elasticsearch-eloquent/wiki)
