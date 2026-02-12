#  1. Product Data Structure Example
  
``` json {
   "id": 1,
   "title": "کتاب برنامه‌نویسی لاراول",
   "slug": "laravel-programming-book",
   "sku": "BOOK-001",
   "isbn_without_dash": "9781234567890",
   "state": "published",
   "can_purchase": true,
   "variant_type": "simple",
   "entity_type": "product.book.programming",

"brand": {
"id": 10,
"name": "انتشارات نشر نو",
"slug": "nashr-now"
},

"main_category": {
"id": 5,
"title": "کتاب",
"slug": "book"
},

"categories": [
{
"id": 15,
"title": "برنامه‌نویسی",
"slug": "programming",
"ancestor_ids": [5],
"grandchildren": [25, 35, 45]
},
{
"id": 25,
"title": "PHP",
"slug": "php",
"ancestor_ids": [5, 15],
"grandchildren": [35, 45]
},
{
"id": 35,
"title": "Laravel",
"slug": "laravel",
"ancestor_ids": [5, 15, 25],
"grandchildren": []
}
],

"tags": [
{
"id": 100,
"name": "پرفروش",
"slug": "bestseller"
},
{
"id": 101,
"name": "جدید",
"slug": "new"
}
],

"collections": [
{
"id": 50,
"name": "کتاب‌های برنامه‌نویسی",
"slug": "programming-books"
}
],

"series": [
{
"id": 200,
"title": "مجموعه آموزش فریمورک‌ها",
"name_in_series": "جلد سوم",
"sort_in_series": 3,
"type": "series",
"slug": "framework-series"
}
],

"product_type_v2": {
"id": 1,
"name": "کتاب فیزیکی",
"code": "physical_book",
"parent_id": null,
"hierarchy": "book > physical_book",
"ancestor_ids": [],
"grandchildren": []
},

"price": 250000,
"sale_price": 200000,
"stock": 50,
"out_of_stock": false,
"rating": 4.5,
"total_rating": 150,
"num_review": 45,
"sale_count": 320,
"view_count": 1500,
"preparation_time_in_day": 2,

"created_at": "2024-01-15T10:30:00Z",
"updated_at": "2024-02-10T15:45:00Z"
}
```

2. Basic Search Examples
   Example 1: Simple Product Search

```php

namespace App\Http\Controllers\Api\V1;

use App\Models\StoreProduct;
use Illuminate\Http\Request;

class ProductController extends Controller
{
/**
* Simple product listing
*/
public function index(Request $request)
{
$results = StoreProduct::search()
// Base filters
->where('store_id', 1)
->where('state', 'published')
->where('can_purchase', true)

            // Sort by newest
            ->orderByDesc('created_at')
            
            ->paginate(20);
        
        return response()->json([
            'products' => $results->items(),
            'total' => $results->total(),
            'current_page' => $results->currentPage(),
            'per_page' => $results->perPage()
        ]);
    }
}
```

## Example 2: Search with Text Query

```php

/**
* Search products by title
  */
  public function search(Request $request)
  {
  $searchTerm = $request->input('q'); // "کتاب لاراول"

  $results = StoreProduct::search()
  ->where('store_id', 1)
  ->where('state', 'published')

       // Apply sophisticated Persian search
       ->when($searchTerm, fn($q) => 
           $q->defaultSearch($searchTerm, 'title')
       )
       
       // Sort by relevance, then popularity
       ->orderByDesc('_score')
       ->orderByDesc('sale_count')
       
       ->paginate(20);

  return response()->json($results);
  }

  ```

##  Example 3: Filter by Brand
  
```php


/**
* Filter products by brand
  */
  public function filterByBrand(Request $request)
  {
  $brandIds = $request->input('brand_id'); // [10, 20, 30]

  $results = StoreProduct::search()
  ->where('store_id', 1)
  ->where('state', 'published')

       // Filter by brand (object field - direct access)
       ->whenHasAny($brandIds ?? [], fn($q, $ids) => 
           $q->whereIn('brand.id', $ids)
       )
       
       ->orderByDesc('created_at')
       ->paginate(20);

  return response()->json($results);
  }

 ``` 
  
## Example 4: Filter by Categories
```php

/**
* Filter products by categories
  */
  public function filterByCategory(Request $request)
  {
  $categoryIds = $request->input('category_id'); // [15, 25, 35]

  $results = StoreProduct::search()
  ->where('store_id', 1)
  ->where('state', 'published')

       // Filter by categories (nested field - need whereNested)
       ->whenHasAny($categoryIds ?? [], fn($q, $ids) => 
           $q->whereNested('categories', fn($query) => 
               $query->whereIn('id', $ids)
           )
       )
       
       ->orderByDesc('created_at')
       ->paginate(20);

  return response()->json($results);
  }
   ``` 
## 3. Advanced Filtering Examples


Example 5: Multiple Filters Combined
```php

/**
* Complex product filtering
  */
  public function advancedFilter(Request $request)
  {
  $query = StoreProduct::search()
  ->where('store_id', 1)
  ->where('state', 'published')
  ->where('can_purchase', true);

  // Text search
  $query->whenFilled('q', fn($q, $searchTerm) =>
  $q->defaultSearch($searchTerm, 'title')
  );

  // Brand filter (Object field)
  $query->whenFilled('brand_id', fn($q, $brandId) =>
  $q->whereIn('brand.id', (array) $brandId)
  );

  // Main category filter (Object field)
  $query->whenFilled('main_category_id', fn($q, $mainCatId) =>
  $q->whereIn('main_category_id', (array) $mainCatId)
  );

  // Categories filter (Nested field)
  $query->whenFilled('category_id', fn($q, $categoryId) =>
  $q->whereNested('categories', fn($cq) =>
  $cq->whereIn('id', (array) $categoryId)
  )
  );

  // Tags filter (Nested field)
  $query->whenFilled('tags', fn($q, $tagIds) =>
  $q->whereNested('tags', fn($tq) =>
  $tq->whereIn('id', (array) $tagIds)
  )
  );

  // Collections filter (Nested field)
  $query->whenFilled('collections', fn($q, $collectionIds) =>
  $q->whereNested('collections', fn($cq) =>
  $cq->whereIn('id', (array) $collectionIds)
  )
  );

  // Series filter (Nested field)
  $query->whenFilled('series_id', fn($q, $seriesIds) =>
  $q->whereNested('series', fn($sq) =>
  $sq->whereIn('id', (array) $seriesIds)
  )
  );

  // Product type filter (Object field)
  $query->whenFilled('product_type_id', fn($q, $typeId) =>
  $q->whereIn('product_type_v2.id', (array) $typeId)
  );

  // Price range filter
  $query->whenHasAll([
  'min_price' => $request->input('min_price'),
  'max_price' => $request->input('max_price')
  ], fn($q, $values) =>
  $q->whereBetween('price', [$values['min_price'], $values['max_price']])
  );

  // Stock filter
  $query->whenBoolean('in_stock_only', fn($q) =>
  $q->where('out_of_stock', false)
  ->whereGreaterThan('stock', 0)
  );

  // Rating filter
  $query->whenNotNull($request->input('min_rating'), fn($q, $rating) =>
  $q->whereGreaterThanOrEqual('rating', $rating)
  );

  // Discount filter
  $query->whenBoolean('on_sale', fn($q) =>
  $q->whereNotNull('sale_price')
  ->where(function($subQ) {
  // sale_price must be less than price
  $subQ->shouldRaw([
  'script' => [
  'script' => [
  'source' => "doc['sale_price'].value < doc['price'].value"
  ]
  ]
  ]);
  })
  );

  // Sorting
  $this->applySorting($query, $request);

  return $query->paginate($request->input('per_page', 20));
  }

protected function applySorting($query, Request $request): void
{
$query->switch($request->input('sort', 'relevant'), [
'relevant' => fn($q) => $q->orderByDesc('_score')->orderByDesc('sale_count'),
'newest' => fn($q) => $q->orderByDesc('created_at'),
'most_viewed' => fn($q) => $q->orderByDesc('view_count'),
'best_selling' => fn($q) => $q->orderByDesc('sale_count'),
'cheapest' => fn($q) => $q->orderBy('price', 'asc'),
'most_expensive' => fn($q) => $q->orderByDesc('price'),
'fastest_delivery' => fn($q) => $q->orderBy('preparation_time_in_day', 'asc'),
'buyer_recommended' => fn($q) => $q->orderByDesc('rating')->orderByDesc('total_rating'),
], fn($q) => $q->orderByDesc('created_at'));
}
   ``` 
## 4. Category Hierarchy Examples
   Example 6: Include Subcategories
```php

/**
* Filter by category including subcategories
  */
  public function filterWithSubcategories(Request $request)
  {
  $categoryId = $request->input('category_id'); // 15 (برنامه‌نویسی)

  $results = StoreProduct::search()
  ->where('store_id', 1)
  ->where('state', 'published')

       // Match products in this category OR its subcategories
       ->whenNotNull($categoryId, function($q, $catId) {
           $q->whereNested('categories', function($query) use ($catId) {
               // Direct match: category.id = 15
               $query->whereIn('id', [$catId])
                     // Or parent match: 15 is in ancestor_ids
                     ->orWhere(function($subQ) use ($catId) {
                         $subQ->shouldRaw([
                             'term' => ['categories.ancestor_ids' => $catId]
                         ]);
                     });
           });
       })
       
       ->orderByDesc('sale_count')
       ->paginate(20);

  return response()->json($results);
  }
  Example 7: Exclude Specific Categories
  php<?php

/**
* Exclude products from specific categories
  */
  public function excludeCategories(Request $request)
  {
  $excludeCategoryIds = [7, 17, 27]; // Categories to exclude

  $results = StoreProduct::search()
  ->where('store_id', 1)
  ->where('state', 'published')

       // Exclude products in these categories
       ->whenHasAny($excludeCategoryIds, fn($q, $ids) => 
           $q->whereNotNested('categories', fn($query) => 
               $query->whereIn('id', $ids)
           )
       )
       
       ->orderByDesc('created_at')
       ->paginate(20);

  return response()->json($results);
  }
``` 
     
## 5. Faceted Search with Aggregations
   Example 8: Search with Facets
```php

/**
* Product search with faceted filters
  */
  public function facetedSearch(Request $request)
  {
  $query = StoreProduct::search()
  ->where('store_id', 1)
  ->where('state', 'published')
  ->where('can_purchase', true);

  // Apply search
  $query->whenFilled('q', fn($q, $searchTerm) =>
  $q->defaultSearch($searchTerm, 'title')
  );

  // Apply filters
  $query->whenFilled('brand_id', fn($q, $brandId) =>
  $q->whereIn('brand.id', (array) $brandId)
  );

  $query->whenFilled('category_id', fn($q, $categoryId) =>
  $q->whereNested('categories', fn($cq) =>
  $cq->whereIn('id', (array) $categoryId)
  )
  );

  // Add aggregations for facets
  $query->aggregateTerms('available_brands', 'brand.id', 50)
  ->aggregate('available_categories', [
  'nested' => ['path' => 'categories'],
  'aggs' => [
  'category_ids' => [
  'terms' => [
  'field' => 'categories.id',
  'size' => 100
  ]
  ]
  ]
  ])
  ->aggregateStats('price_stats', 'price')
  ->aggregate('price_ranges', [
  'range' => [
  'field' => 'price',
  'ranges' => [
  ['key' => 'under_100k', 'to' => 100000],
  ['key' => '100k_500k', 'from' => 100000, 'to' => 500000],
  ['key' => '500k_1m', 'from' => 500000, 'to' => 1000000],
  ['key' => 'over_1m', 'from' => 1000000]
  ]
  ]
  ]);

  // Get results
  $results = $query->paginate(20);
  $aggregations = $query->getAggregations();

  return response()->json([
  'products' => $results->items(),
  'pagination' => [
  'total' => $results->total(),
  'current_page' => $results->currentPage(),
  'per_page' => $results->perPage(),
  ],
  'facets' => [
  'brands' => $this->formatBrandFacets($aggregations['available_brands'] ?? []),
  'categories' => $this->formatCategoryFacets($aggregations['available_categories'] ?? []),
  'price_ranges' => $aggregations['price_ranges']['buckets'] ?? [],
  'price_stats' => $aggregations['price_stats'] ?? [],
  ]
  ]);
  }

protected function formatBrandFacets(array $brandAgg): array
{
$buckets = $brandAgg['buckets'] ?? [];
$brandIds = collect($buckets)->pluck('key')->toArray();

    // Fetch brand details from database or cache
    $brands = \App\Models\Brand::whereIn('id', $brandIds)->get();
    
    return collect($buckets)->map(function($bucket) use ($brands) {
        $brand = $brands->firstWhere('id', $bucket['key']);
        return [
            'id' => $bucket['key'],
            'name' => $brand?->name ?? 'Unknown',
            'slug' => $brand?->slug ?? '',
            'count' => $bucket['doc_count']
        ];
    })->toArray();
}

protected function formatCategoryFacets(array $categoryAgg): array
{
$buckets = $categoryAgg['category_ids']['buckets'] ?? [];
$categoryIds = collect($buckets)->pluck('key')->toArray();

    // Fetch category details
    $categories = \App\Models\Category::whereIn('id', $categoryIds)->get();
    
    return collect($buckets)->map(function($bucket) use ($categories) {
        $category = $categories->firstWhere('id', $bucket['key']);
        return [
            'id' => $bucket['key'],
            'title' => $category?->title ?? 'Unknown',
            'slug' => $category?->slug ?? '',
            'count' => $bucket['doc_count']
        ];
    })->toArray();
}
``` 
## 6. Complete Product Handler Class
   Example 9: Full Implementation
```php

namespace App\Http\ApplicationHandler\V1\Fair\Catalog;

use App\ApiService\Bll\Kiosk\CurrentKioskBll;
use App\Models\StoreProduct;
use Illuminate\Http\Request;

class ProductListHandler
{
/**
* Main product search and filter handler
*/
public function handle(Request $request)
{
$store_id = CurrentKioskBll::getStoreId();
$application = CurrentKioskBll::getKioskApplication();

        // Build query
        $query = $this->buildBaseQuery($store_id);
        
        // Apply application-level restrictions
        $this->applyApplicationRestrictions($query, $application);
        
        // Apply user filters
        $this->applyUserFilters($query, $request);
        
        // Apply sorting
        $this->applySorting($query, $request);
        
        // Add aggregations if needed
        if ($request->boolean('include_facets')) {
            $this->addAggregations($query);
        }
        
        // Add highlighting for search
        if ($request->filled('q')) {
            $query->highlight(['title', 'search_text'], [
                'pre_tags' => ['<mark>'],
                'post_tags' => ['</mark>'],
                'fragment_size' => 150,
                'number_of_fragments' => 3
            ]);
        }
        
        // Execute query
        $results = $query->paginate($request->input('per_page', 20));
        
        // Format response
        return $this->formatResponse($results, $query, $request);
    }
    
    /**
     * Build base query with common filters
     */
    protected function buildBaseQuery(int $storeId)
    {
        return StoreProduct::search()
            ->where('store_id', $storeId)
            ->where('state', 'published')
            ->where('can_purchase', true)
            ->whereIn('type', ['simple', 'parent', 'bundle']);
    }
    
    /**
     * Apply application-level restrictions
     */
    protected function applyApplicationRestrictions($query, $application): void
    {
        // Allowed product types
        $allowedProductTypes = $application->allowed_product_types ?? [];
        $query->whenHasAny($allowedProductTypes, fn($q, $ids) => 
            $q->whereIn('product_type_v2.id', $ids)
        );
        
        // Allowed brands
        $allowedBrands = $application->allowed_brands ?? [];
        $query->whenHasAny($allowedBrands, fn($q, $ids) => 
            $q->whereIn('brand.id', $ids)
        );
        
        // Disallowed brands
        $disallowedBrands = $application->disallowed_brands ?? [];
        $query->whenHasAny($disallowedBrands, fn($q, $ids) => 
            $q->whereNotIn('brand.id', $ids)
        );
        
        // Allowed categories
        $allowedCategories = $application->allowed_categories ?? [];
        $query->whenHasAny($allowedCategories, fn($q, $ids) => 
            $q->whereNested('categories', fn($cq) => $cq->whereIn('id', $ids))
        );
        
        // Disallowed categories
        $disallowedCategories = $application->disallowed_categories ?? [];
        $query->whenHasAny($disallowedCategories, fn($q, $ids) => 
            $q->whereNotNested('categories', fn($cq) => $cq->whereIn('id', $ids))
        );
    }
    
    /**
     * Apply user-selected filters
     */
    protected function applyUserFilters($query, Request $request): void
    {
        // Text search
        $query->whenFilled('q', function($q, $searchTerm) {
            $q->defaultSearch($searchTerm, 'title', [
                'prefix_kw' => 10,
                'phrase_prefix_normalized' => 8,
                'phrase_normalized' => 7,
                'phrase_prefix_main' => 6,
                'phrase_main' => 4,
                'match_and_main' => 3,
                'match_and_normalized' => 2,
            ]);
        });
        
        // Brand filter
        $query->whenFilled('brand_id', fn($q, $brandId) => 
            $q->whereIn('brand.id', (array) $brandId)
        );
        
        // Brand name search
        $query->whenFilled('brand_name', fn($q, $brandName) => 
            $q->matchQuery('brand.name', $brandName, ['boost' => 2])
        );
        
        // Main category filter
        $query->whenFilled('main_category_id', fn($q, $mainCatId) => 
            $q->whereIn('main_category_id', (array) $mainCatId)
        );
        
        // Categories filter
        $query->whenFilled('category_id', fn($q, $categoryId) => 
            $q->whereNested('categories', fn($cq) => 
                $cq->whereIn('id', (array) $categoryId)
            )
        );
        
        // Category with subcategories
        $query->whenBoolean('include_subcategories', function($q) use ($request) {
            if ($request->filled('category_id')) {
                $categoryIds = (array) $request->input('category_id');
                $q->whereNested('categories', function($cq) use ($categoryIds) {
                    $cq->whereIn('id', $categoryIds)
                       ->orWhere(function($subQ) use ($categoryIds) {
                           foreach ($categoryIds as $catId) {
                               $subQ->shouldRaw([
                                   'term' => ['categories.ancestor_ids' => $catId]
                               ]);
                           }
                       });
                });
            }
        });
        
        // Tags filter
        $query->whenFilled('tags', fn($q, $tagIds) => 
            $q->whereNested('tags', fn($tq) => $tq->whereIn('id', (array) $tagIds))
        );
        
        // Collections filter
        $query->whenFilled('collections', fn($q, $collectionIds) => 
            $q->whereNested('collections', fn($cq) => 
                $cq->whereIn('id', (array) $collectionIds)
            )
        );
        
        // Series filter
        $query->whenFilled('series_id', fn($q, $seriesIds) => 
            $q->whereNested('series', fn($sq) => $sq->whereIn('id', (array) $seriesIds))
        );
        
        // Product type filter
        $query->whenFilled('product_type_id', fn($q, $typeId) => 
            $q->whereIn('product_type_v2.id', (array) $typeId)
        );
        
        // Price filters
        $query->whenNotNull($request->input('min_price'), fn($q, $minPrice) => 
            $q->whereGreaterThanOrEqual('price', $minPrice)
        );
        
        $query->whenNotNull($request->input('max_price'), fn($q, $maxPrice) => 
            $q->whereLessThanOrEqual('price', $maxPrice)
        );
        
        $query->whenHasAll([
            'min_price' => $request->input('min_price'),
            'max_price' => $request->input('max_price')
        ], fn($q, $values) => 
            $q->whereBetween('price', [$values['min_price'], $values['max_price']])
        );
        
        // Stock filters
        $query->whenBoolean('in_stock_only', fn($q) => 
            $q->where('out_of_stock', false)->whereGreaterThan('stock', 0)
        );
        
        $query->whenBoolean('low_stock', fn($q) => 
            $q->whereBetween('stock', [1, 10])
        );
        
        // Rating filter
        $query->whenNotNull($request->input('min_rating'), fn($q, $rating) => 
            $q->whereGreaterThanOrEqual('rating', $rating)
        );
        
        // On sale filter
        $query->whenBoolean('on_sale', fn($q) => 
            $q->whereNotNull('sale_price')
        );
        
        // New arrivals (last 30 days)
        $query->whenBoolean('new_arrivals', fn($q) => 
            $q->whereGreaterThan('created_at', now()->subDays(30)->toDateString())
        );
        
        // Best sellers (sale_count > 100)
        $query->whenBoolean('best_sellers', fn($q) => 
            $q->whereGreaterThan('sale_count', 100)
        );
        
        // Featured products
        $query->whenBoolean('featured', fn($q) => 
            $q->whereNested('tags', fn($tq) => $tq->where('slug', 'featured'))
        );
        
        // ISBN search
        $query->whenFilled('isbn', fn($q, $isbn) => 
            $q->where('isbn_without_dash', str_replace('-', '', $isbn))
        );
        
        // SKU search
        $query->whenFilled('sku', fn($q, $sku) => 
            $q->where('sku', $sku)
        );
    }
    
    /**
     * Apply sorting
     */
    protected function applySorting($query, Request $request): void
    {
        $sortBy = $request->input('sort', 'relevant');
        
        // If there's a search query, default to relevance
        if ($request->filled('q') && $sortBy === 'relevant') {
            $query->orderByDesc('_score')->orderByDesc('sale_count');
            return;
        }
        
        $query->switch($sortBy, [
            'relevant' => fn($q) => $q->orderByDesc('_score')->orderByDesc('sale_count'),
            'newest' => fn($q) => $q->orderByDesc('created_at'),
            'oldest' => fn($q) => $q->orderBy('created_at', 'asc'),
            'most_viewed' => fn($q) => $q->orderByDesc('view_count'),
            'best_selling' => fn($q) => $q->orderByDesc('sale_count'),
            'cheapest' => fn($q) => $q->orderBy('price', 'asc'),
            'most_expensive' => fn($q) => $q->orderByDesc('price'),
            'highest_rated' => fn($q) => $q->orderByDesc('rating')->orderByDesc('total_rating'),
            'most_reviewed' => fn($q) => $q->orderByDesc('num_review'),
            'fastest_delivery' => fn($q) => $q->orderBy('preparation_time_in_day', 'asc'),
            'alphabetical' => fn($q) => $q->orderBy('title.kw', 'asc'),
        ], fn($q) => $q->orderByDesc('created_at'));
    }
    
    /**
     * Add aggregations for faceted search
     */
    protected function addAggregations($query): void
    {
        // Brand facets
        $query->aggregateTerms('brands', 'brand.id', 50);
        
        // Main category facets
        $query->aggregateTerms('main_categories', 'main_category_id', 20);
        
        // Categories facets (nested)
        $query->aggregate('categories', [
            'nested' => ['path' => 'categories'],
            'aggs' => [
                'category_ids' => [
                    'terms' => ['field' => 'categories.id', 'size' => 100]
                ]
            ]
        ]);
        
        // Tags facets (nested)
        $query->aggregate('tags', [
            'nested' => ['path' => 'tags'],
            'aggs' => [
                'tag_ids' => [
                    'terms' => ['field' => 'tags.id', 'size' => 50]
                ]
            ]
        ]);
        
        // Product type facets
        $query->aggregateTerms('product_types', 'product_type_v2.id', 20);
        
        // Price statistics
        $query->aggregateStats('price_stats', 'price');
        
        // Price ranges
        $query->aggregate('price_ranges', [
            'range' => [
                'field' => 'price',
                'ranges' => [
                    ['key' => 'under_100k', 'to' => 100000],
                    ['key' => '100k_250k', 'from' => 100000, 'to' => 250000],
                    ['key' => '250k_500k', 'from' => 250000, 'to' => 500000],
                    ['key' => '500k_1m', 'from' => 500000, 'to' => 1000000],
                    ['key' => 'over_1m', 'from' => 1000000]
                ]
            ]
        ]);
        
        // Rating distribution
        $query->aggregate('rating_distribution', [
            'range' => [
                'field' => 'rating',
                'ranges' => [
                    ['key' => '5_star', 'from' => 4.5, 'to' => 5.1],
                    ['key' => '4_star', 'from' => 3.5, 'to' => 4.5],
                    ['key' => '3_star', 'from' => 2.5, 'to' => 3.5],
                    ['key' => '2_star', 'from' => 1.5, 'to' => 2.5],
                    ['key' => '1_star', 'from' => 0, 'to' => 1.5],
                ]
            ]
        ]);
    }
    
    /**
     * Format response
     */
    protected function formatResponse($results, $query, Request $request): array
    {
        $response = [
            'products' => $results->map(function($product) {
                return [
                    'id' => $product['id'],
                    'title' => $product['title'],
                    'slug' => $product['slug'],
                    'sku' => $product['sku'],
                    'img_src' => $product['img_src'],
                    'brand' => $product['brand'],
                    'main_category' => $product['main_category'],
                    'categories' => $product['categories'],
                    'price' => $product['price'],
                    'sale_price' => $product['sale_price'] ?? null,
                    'discount_percent' => $this->calculateDiscount($product),
                    'rating' => $product['rating'],
                    'total_rating' => $product['total_rating'],
                    'num_review' => $product['num_review'],
                    'sale_count' => $product['sale_count'],
                    'stock' => $product['stock'],
                    'out_of_stock' => $product['out_of_stock'],
                    'badges' => $product['badges'] ?? [],
                    'tags' => $product['tags'] ?? [],
                    '_score' => $product['_score'] ?? null,
                    '_highlight' => $product['_highlight'] ?? null,
                ];
            }),
            'pagination' => [
                'total' => $results->total(),
                'count' => $results->count(),
                'per_page' => $results->perPage(),
                'current_page' => $results->currentPage(),
                'total_pages' => $results->lastPage(),
            ],
        ];
        
        // Add facets if requested
        if ($request->boolean('include_facets')) {
            $response['facets'] = $this->formatFacets($query->getAggregations());
        }
        
        return $response;
    }
    
    /**
     * Calculate discount percentage
     */
    protected function calculateDiscount(array $product): ?int
    {
        if (!isset($product['sale_price']) || !$product['sale_price']) {
            return null;
        }
        
        $discount = (($product['price'] - $product['sale_price']) / $product['price']) * 100;
        return (int) round($discount);
    }
    
    /**
     * Format aggregations for response
     */
    protected function formatFacets(array $aggregations): array
    {
        return [
            'brands' => $this->formatBrandFacets($aggregations['brands'] ?? []),
            'main_categories' => $this->formatMainCategoryFacets($aggregations['main_categories'] ?? []),
            'categories' => $this->formatCategoryFacets($aggregations['categories'] ?? []),
            'tags' => $this->formatTagFacets($aggregations['tags'] ?? []),
            'product_types' => $this->formatProductTypeFacets($aggregations['product_types'] ?? []),
            'price_ranges' => $aggregations['price_ranges']['buckets'] ?? [],
            'price_stats' => $aggregations['price_stats'] ?? [],
            'rating_distribution' => $aggregations['rating_distribution']['buckets'] ?? [],
        ];
    }
    
    protected function formatBrandFacets(array $agg): array
    {
        // Implementation from Example 8
        $buckets = $agg['buckets'] ?? [];
        $brandIds = collect($buckets)->pluck('key')->toArray();
        $brands = \App\Models\Brand::whereIn('id', $brandIds)->get();
        
        return collect($buckets)->map(function($bucket) use ($brands) {
            $brand = $brands->firstWhere('id', $bucket['key']);
            return [
                'id' => $bucket['key'],
                'name' => $brand?->name ?? 'Unknown',
                'slug' => $brand?->slug ?? '',
                'count' => $bucket['doc_count']
            ];
        })->toArray();
    }
    
    protected function formatMainCategoryFacets(array $agg): array
    {
        $buckets = $agg['buckets'] ?? [];
        $categoryIds = collect($buckets)->pluck('key')->toArray();
        $categories = \App\Models\MainCategory::whereIn('id', $categoryIds)->get();
        
        return collect($buckets)->map(function($bucket) use ($categories) {
            $category = $categories->firstWhere('id', $bucket['key']);
            return [
                'id' => $bucket['key'],
                'title' => $category?->title ?? 'Unknown',
                'slug' => $category?->slug ?? '',
                'count' => $bucket['doc_count']
            ];
        })->toArray();
    }
    
    protected function formatCategoryFacets(array $agg): array
    {
        $buckets = $agg['category_ids']['buckets'] ?? [];
        $categoryIds = collect($buckets)->pluck('key')->toArray();
        $categories = \App\Models\Category::whereIn('id', $categoryIds)->get();
        
        return collect($buckets)->map(function($bucket) use ($categories) {
            $category = $categories->firstWhere('id', $bucket['key']);
            return [
                'id' => $bucket['key'],
                'title' => $category?->title ?? 'Unknown',
                'slug' => $category?->slug ?? '',
                'count' => $bucket['doc_count']
            ];
        })->toArray();
    }
    
    protected function formatTagFacets(array $agg): array
    {
        $buckets = $agg['tag_ids']['buckets'] ?? [];
        $tagIds = collect($buckets)->pluck('key')->toArray();
        $tags = \App\Models\Tag::whereIn('id', $tagIds)->get();
        
        return collect($buckets)->map(function($bucket) use ($tags) {
            $tag = $tags->firstWhere('id', $bucket['key']);
            return [
                'id' => $bucket['key'],
                'name' => $tag?->name ?? 'Unknown',
                'slug' => $tag?->slug ?? '',
                'count' => $bucket['doc_count']
            ];
        })->toArray();
    }
    
    protected function formatProductTypeFacets(array $agg): array
    {
        $buckets = $agg['buckets'] ?? [];
        $typeIds = collect($buckets)->pluck('key')->toArray();
        $types = \App\Models\ProductType::whereIn('id', $typeIds)->get();
        
        return collect($buckets)->map(function($bucket) use ($types) {
            $type = $types->firstWhere('id', $bucket['key']);
            return [
                'id' => $bucket['key'],
                'name' => $type?->name ?? 'Unknown',
                'code' => $type?->code ?? '',
                'count' => $bucket['doc_count']
            ];
        })->toArray();
    }
}
``` 
## 7. Usage in Routes
```php

// routes/api.php

use App\Http\Controllers\Api\V1\ProductController;

Route::prefix('v1')->group(function () {
// Basic listing
Route::get('products', [ProductController::class, 'index']);

    // Search
    Route::get('products/search', [ProductController::class, 'search']);
    
    // Filter by brand
    Route::get('products/brand/{slug}', [ProductController::class, 'filterByBrand']);
    
    // Filter by category
    Route::get('products/category/{slug}', [ProductController::class, 'filterByCategory']);
    
    // Advanced filters
    Route::post('products/filter', [ProductController::class, 'advancedFilter']);
    
    // Faceted search
    Route::post('products/faceted-search', [ProductController::class, 'facetedSearch']);
});
```

9. Example API Requests
   bash# Simple search
   GET /api/v1/products/search?q=کتاب لاراول

# Filter by brand
```bash
GET /api/v1/products?brand_id[]=10&brand_id[]=20
```
# Filter by category
GET /api/v1/products?category_id[]=15&category_id[]=25
```bash
# Complex filter
POST /api/v1/products/filter
{
"q": "کتاب",
"brand_id": [10, 20],
"category_id": [15, 25, 35],
"min_price": 100000,
"max_price": 500000,
"min_rating": 4.0,
"in_stock_only": true,
"on_sale": true,
"sort": "best_selling",
"per_page": 20,
"include_facets": true
}
```

# Faceted search with aggregations
```bash

POST /api/v1/products/faceted-search
{
"q": "برنامه‌نویسی",
"include_facets": true
}
``
