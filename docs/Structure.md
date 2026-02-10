# Package Structure

```
elasticsearch-eloquent/
│
├── src/                                    # Core source code
│   ├── Model.php                          # Base Eloquent-style model
│   ├── Builder.php                        # Query builder with all methods
│   ├── ElasticsearchServiceProvider.php   # Laravel service provider
│   └── Concerns/                          # Reusable traits (future)
│
├── config/                                 # Configuration files
│   └── elasticsearch.php                  # Main config file
│
├── examples/                               # Usage examples
│   ├── Product.php                        # Example Product model
│   └── usage_examples.php                 # Comprehensive examples
│
├── tests/                                  # PHPUnit tests
│   └── BuilderTest.php                    # Builder tests
│
├── docs/                                   # Documentation (optional)
│
├── .gitignore                             # Git ignore file
├── CHANGELOG.md                           # Version history
├── composer.json                          # Composer configuration
├── CONTRIBUTING.md                        # Contribution guidelines
├── INSTALLATION.md                        # Installation guide
├── LICENSE                                # MIT License
├── phpunit.xml                           # PHPUnit configuration
└── README.md                              # Main documentation
```

## Core Components

### 1. Model.php
The base model class that provides:
- Attribute management
- Type casting
- Elasticsearch client integration
- Model hydration from search results
- Magic methods (__get, __set, __call)

### 2. Builder.php
The query builder providing:
- Where clauses (where, whereIn, whereNull, whereNot, whereBetween)
- Nested object queries (whereNested)
- Search functionality (search, matchPhrase)
- Sorting (orderBy, latest, oldest)
- Pagination (paginate, limit, offset)
- Aggregations (termsAgg, sumAgg, avgAgg, etc.)
- Query compilation to Elasticsearch DSL

### 3. ElasticsearchServiceProvider.php
Laravel service provider that:
- Registers Elasticsearch client singleton
- Publishes configuration files
- Integrates with Laravel container

## Data Flow

```
User Query
    ↓
Model::where('field', 'value')
    ↓
Builder accumulates constraints
    ↓
Builder->get() called
    ↓
Query compiled to Elasticsearch DSL
    ↓
Elasticsearch client executes query
    ↓
Results hydrated into Model instances
    ↓
Collection returned to user
```

## Query Compilation Flow

```
Builder Methods (where, whereIn, etc.)
    ↓
Store constraints in $wheres array
    ↓
compileWheres() called
    ↓
Each constraint compiled to ES DSL
    ↓
Combined into bool query structure
    ↓
Final JSON sent to Elasticsearch
```

## Example: Query to Elasticsearch DSL

**User writes:**
```php
Product::where('in_stock', true)
    ->whereIn('cat_id', [1, 5])
    ->whereBetween('price', [100, 500])
    ->get();
```

**Builder compiles to:**
```json
{
  "query": {
    "bool": {
      "filter": [
        { "term": { "in_stock": true } },
        { "terms": { "cat_id": [1, 5] } },
        { "range": { "price": { "gte": 100, "lte": 500 } } }
      ]
    }
  }
}
```

## Extension Points

You can extend the package by:

1. **Adding custom query methods** to Builder
2. **Creating model scopes** in your models
3. **Adding traits** to the Concerns folder
4. **Custom aggregations** by extending aggregate()
5. **Custom result hydration** by overriding newFromHit()

## Dependencies

```
elasticsearch/elasticsearch: ^8.0  → Official ES client
illuminate/support: ^10.0|^11.0    → Laravel helpers
illuminate/database: ^10.0|^11.0   → Collection & Pagination
```

## Testing Structure

```
tests/
├── BuilderTest.php        # Tests for Builder methods
├── ModelTest.php          # Tests for Model functionality
└── Integration/           # Integration tests with ES
    └── QueryTest.php      # Real ES queries
```
