Migrating from Laravel Scout to Elasticsearch Eloquent

This package is designed to feel familiar to teams already using Laravel Scout.
A typical migration can be done incrementally — without breaking production search.

Example Scenario

You currently use Scout like this:

```php
use Laravel\Scout\Searchable;

class Product extends Model
{
use Searchable;

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'in_stock' => $this->in_stock,
        ];
    }
}
```


And query it like this:

```php

$products = Product::search('laptop')
->where('in_stock', true)
->paginate(20);
```


This works well — until you need more control.

Step 1: Introduce an Elasticsearch Search Model

Instead of coupling search directly to your database model, create a dedicated Elasticsearch model:

```php
use ElasticsearchEloquent\Model;

class ProductSearch extends Model
{
protected string $index = 'products';

    protected array $casts = [
        'price' => 'float',
        'in_stock' => 'boolean',
    ];
}

```

This decouples search concerns from persistence concerns — a key architectural upgrade.

Step 2: Replace Scout Queries with Eloquent-Style Search Queries

Scout:

Product::search('laptop')
->where('in_stock', true)
->paginate(20);


Elasticsearch Eloquent:

ProductSearch::search('laptop', ['name', 'description'])
->where('in_stock', true)
->paginate(20);


Same readability.
Significantly more control.

Step 3: Add Explicit Mappings (Optional but Recommended)

Scout relies heavily on dynamic mapping.
Elasticsearch Eloquent encourages explicit mappings:

class ProductSearch extends Model
{
protected string $index = 'products';

    protected array $mappings = [
        'properties' => [
            'name' => [
                'type' => 'text',
                'fields' => [
                    'kw' => ['type' => 'keyword'],
                ],
            ],
            'description' => [
                'type' => 'text',
            ],
            'price' => ['type' => 'float'],
            'in_stock' => ['type' => 'boolean'],
        ],
    ];
}


This gives you predictable behavior and safer deployments.

Step 4: Introduce Nested Data and Advanced Queries

Scout struggles with nested structures.

With Elasticsearch Eloquent:

ProductSearch::whereNested('categories', function ($query) {
$query->where('categories.name', 'Electronics');
})->get();


No DSL leaks.
No controller complexity.

Step 5: Move Sync Logic Out of Model Events

Scout syncs automatically on model events.
This is convenient, but fragile under load.

With Elasticsearch Eloquent, you can migrate to a queue-based sync:

Observe DB model events

Store search updates in an Outbox table

Process updates asynchronously

Retry safely on failure

This improves throughput and guarantees consistency for critical systems.

Step 6: Enable Zero-Downtime Reindexing

Once your mappings evolve, introduce aliases:

products_v1

products_v2

Alias: products

Your application code never changes — only the alias does.

Scout does not provide this out of the box.
Elasticsearch Eloquent is built with this workflow in mind.

Incremental Adoption Strategy

You don’t have to migrate everything at once:

Keep Scout for simple models

Use Elasticsearch Eloquent for complex search-heavy features

Gradually move advanced queries over

This minimizes risk while unlocking advanced capabilities where you need them most.

Summary

Scout is great for getting started

Elasticsearch Eloquent is built for scaling search safely

Migration is incremental, not a rewrite

If your search requirements are growing faster than Scout can handle, this package gives you a clear, production-safe upgrade path.
