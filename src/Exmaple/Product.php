<?php

namespace ElasticsearchEloquent\Example;



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
        'tags' => 'array',
        'categories' => 'array',
        'brand' => 'array',
    ];




    // Define explicit mappings
    public function mapping(): array
    {
        return [
            'properties' => [
                'name' => ['type' => 'text'],
                'price' => ['type' => 'float'],
                'created_at' => ['type' => 'date'],
                'brand' => [
                    'type' => 'object', // or 'nested'
                    'properties' => [
                        'name' => ['type' => 'keyword'],
                        'country' => ['type' => 'keyword']
                    ]
                ]
            ]
        ];
    }

    // Define settings (analyzers, etc.)
    public function settings(): array
    {
        return [
            'number_of_shards' => 3,
            'number_of_replicas' => 2,
        ];
    }







    /**
     * Example document structure in Elasticsearch:
     * {
     *   "_id": "product_123",
     *   "_source": {
     *     "name": "Laptop Pro 15",
     *     "description": "High-performance laptop for professionals",
     *     "price": 1299.99,
     *     "in_stock": true,
     *     "cat_id": [1, 5, 12],
     *     "brand": {
     *       "id": 10,
     *       "name": "TechBrand",
     *       "country": "USA"
     *     },
     *     "categories": [
     *       {
     *         "id": 1,
     *         "name": "Electronics",
     *         "slug": "electronics"
     *       },
     *       {
     *         "id": 5,
     *         "name": "Computers",
     *         "slug": "computers"
     *       }
     *     ],
     *     "tags": [
     *       {
     *         "id": 20,
     *         "name": "laptop"
     *       },
     *       {
     *         "id": 21,
     *         "name": "professional"
     *       }
     *     ],
     *     "created_at": "2024-01-15T10:30:00Z",
     *     "updated_at": "2024-01-20T14:45:00Z"
     *   }
     * }
     */

    /**
     * Scope for products in stock.
     */
    public function scopeInStock($query)
    {
        return $query->where('in_stock', true);
    }

    /**
     * Scope for products by brand name.
     */
    public function scopeByBrand($query, string $brandName)
    {
        return $query->whereNested('brand', function ($q) use ($brandName) {
            $q->where('brand.name', $brandName);
        });
    }

    /**
     * Scope for products in category.
     */
    public function scopeInCategory($query, int $categoryId)
    {
        return $query->whereIn('cat_id', [$categoryId]);
    }

    /**
     * Scope for products with tag.
     */
    public function scopeWithTag($query, string $tagName)
    {
        return $query->whereNested('tags', function ($q) use ($tagName) {
            $q->where('tags.name', $tagName);
        });
    }
}

//// --- Management Usage ---
//
//// Create index with mappings
//Product::index()->create();
//
//// Check existence
//if (Product::index()->exists()) {
//    // ...
//}
//
//// Delete index
//Product::index()->delete();
