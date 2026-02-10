<?php

namespace ElasticsearchEloquent\Example;



// ============================================================================
// BASIC WHERE CLAUSES
// ============================================================================

// Simple where
$products = Product::where('name', 'Laptop')->get();
$products = Product::where('price', '>', 1000)->get();
$products = Product::where('in_stock', true)->get();

// Multiple where conditions (AND)
$products = Product::where('price', '>', 500)
    ->where('price', '<', 2000)
    ->where('in_stock', true)
    ->get();

// Array of where conditions
$products = Product::where([
    'in_stock' => true,
    'price' => 999.99,
])->get();

// OR where conditions
$products = Product::where('name', 'Laptop')
    ->orWhere('name', 'Desktop')
    ->get();

// ============================================================================
// WHERE IN / WHERE NOT IN
// ============================================================================

// Where in - for array fields like cat_id
$products = Product::whereIn('cat_id', [1, 5, 12])->get();

// Products in specific categories
$categoryIds = [1, 2, 3, 4, 5];
$products = Product::whereIn('cat_id', $categoryIds)->get();

// Where not in
$products = Product::whereNotIn('cat_id', [99, 100])->get();

// Or where in
$products = Product::where('price', '>', 1000)
    ->orWhereIn('cat_id', [5, 10])
    ->get();

// ============================================================================
// WHERE NULL / WHERE NOT NULL
// ============================================================================

// Products without description
$products = Product::whereNull('description')->get();

// Products with description
$products = Product::whereNotNull('description')->get();

// Combine with other conditions
$products = Product::whereNotNull('description')
    ->where('in_stock', true)
    ->get();

// Or where null
$products = Product::where('price', 0)
    ->orWhereNull('price')
    ->get();

// ============================================================================
// WHERE NOT
// ============================================================================

// Where not equal
$products = Product::whereNot('name', 'Obsolete Product')->get();

// Where not with operator
$products = Product::whereNot('price', '>', 5000)->get();

// Multiple where not
$products = Product::whereNot([
    'in_stock' => false,
    'archived' => true,
])->get();

// ============================================================================
// WHERE BETWEEN / WHERE NOT BETWEEN
// ============================================================================

// Price range
$products = Product::whereBetween('price', [100, 500])->get();

// Date range
$products = Product::whereBetween('created_at', [
    '2024-01-01',
    '2024-12-31',
])->get();

// Where not between
$products = Product::whereNotBetween('price', [0, 10])->get();

// Or where between
$products = Product::where('in_stock', true)
    ->orWhereBetween('price', [1000, 2000])
    ->get();

// ============================================================================
// NESTED OBJECT QUERIES
// ============================================================================

// Query nested brand object
$products = Product::whereNested('brand', function ($query) {
    $query->where('brand.name', 'TechBrand');
})->get();

// Query nested brand with multiple conditions
$products = Product::whereNested('brand', function ($query) {
    $query->where('brand.name', 'TechBrand')
        ->where('brand.country', 'USA');
})->get();

// Query nested categories array
$products = Product::whereNested('categories', function ($query) {
    $query->where('categories.name', 'Electronics');
})->get();

// Query nested tags
$products = Product::whereNested('tags', function ($query) {
    $query->whereIn('tags.name', ['laptop', 'professional', 'gaming']);
})->get();

// Complex nested query
$products = Product::where('in_stock', true)
    ->whereNested('brand', function ($query) {
        $query->where('brand.country', 'USA');
    })
    ->whereNested('categories', function ($query) {
        $query->where('categories.slug', 'computers');
    })
    ->get();

// ============================================================================
// FULL-TEXT SEARCH
// ============================================================================

// Search across all fields
$products = Product::search('laptop professional')->get();

// Search specific fields
$products = Product::search('gaming', ['name', 'description'])->get();

// Search with filters
$products = Product::search('laptop', ['name', 'description'])
    ->where('price', '<', 2000)
    ->where('in_stock', true)
    ->get();

// Match phrase (exact phrase search)
$products = Product::matchPhrase('description', 'high performance laptop')->get();

// Minimum score threshold
$products = Product::search('laptop')
    ->minScore(1.5)
    ->get();

// ============================================================================
// SORTING
// ============================================================================

// Order by single field
$products = Product::orderBy('price', 'asc')->get();
$products = Product::orderBy('created_at', 'desc')->get();

// Order by multiple fields
$products = Product::orderBy('in_stock', 'desc')
    ->orderBy('price', 'asc')
    ->get();

// Order by descending (shorthand)
$products = Product::orderByDesc('price')->get();

// Latest/Oldest helpers
$products = Product::latest('created_at')->get();  // DESC
$products = Product::oldest('created_at')->get();  // ASC

// ============================================================================
// LIMITING & PAGINATION
// ============================================================================

// Take first 10 results
$products = Product::take(10)->get();
$products = Product::limit(10)->get(); // Same as take

// Skip and take (offset)
$products = Product::skip(20)->take(10)->get();
$products = Product::offset(20)->limit(10)->get(); // Same

// Get first result
$product = Product::where('name', 'Laptop Pro')->first();

// Pagination
$products = Product::where('in_stock', true)
    ->orderBy('price', 'desc')
    ->paginate(15); // 15 per page

// Pagination with custom page
$products = Product::paginate(20, ['*'], 'page', 2);

// ============================================================================
// SELECTING FIELDS (SOURCE FILTERING)
// ============================================================================

// Select specific fields
$products = Product::select(['name', 'price', 'in_stock'])->get();

// Select with conditions
$products = Product::select(['name', 'price'])
    ->where('in_stock', true)
    ->get();

// Get with specific columns
$products = Product::where('price', '>', 100)->get(['name', 'price']);

// ============================================================================
// AGGREGATIONS
// ============================================================================

// Terms aggregation - get category distribution
$products = Product::termsAgg('categories_count', 'cat_id', 20)
    ->get();
$aggregations = $products->first()
    ? Product::query()->termsAgg('categories_count', 'cat_id')->getAggregations()
    : [];

// Sum aggregation
Product::query()
    ->sumAgg('total_value', 'price')
    ->getAggregations();

// Average price
Product::query()
    ->avgAgg('average_price', 'price')
    ->getAggregations();

// Min and Max
Product::query()
    ->minAgg('min_price', 'price')
    ->maxAgg('max_price', 'price')
    ->getAggregations();

// Multiple aggregations
$aggs = Product::query()
    ->where('in_stock', true)
    ->termsAgg('brands', 'brand.name', 10)
    ->avgAgg('avg_price', 'price')
    ->sumAgg('total_inventory_value', 'price')
    ->getAggregations();

// ============================================================================
// COUNT
// ============================================================================

// Count all products
$count = Product::count();

// Count with conditions
$count = Product::where('in_stock', true)->count();
$count = Product::whereIn('cat_id', [1, 2, 3])->count();

// ============================================================================
// COMPLEX REAL-WORLD EXAMPLES
// ============================================================================

// Example 1: Find laptops in stock, priced between $500-$2000, from US brands
$products = Product::search('laptop', ['name', 'description'])
    ->where('in_stock', true)
    ->whereBetween('price', [500, 2000])
    ->whereNested('brand', function ($query) {
        $query->where('brand.country', 'USA');
    })
    ->orderBy('price', 'asc')
    ->paginate(20);

// Example 2: Professional products with specific tags, excluding certain categories
$products = Product::whereNested('tags', function ($query) {
    $query->whereIn('tags.name', ['professional', 'business']);
})
    ->whereNotIn('cat_id', [99, 100]) // Exclude archived categories
    ->whereNotNull('description')
    ->where('in_stock', true)
    ->latest('created_at')
    ->take(50)
    ->get();

// Example 3: Search with aggregations - find electronics, get brand distribution
$query = Product::search('electronics')
    ->whereIn('cat_id', [1, 5, 12])
    ->where('price', '>', 100)
    ->termsAgg('top_brands', 'brand.name', 10)
    ->avgAgg('average_price', 'price');

$products = $query->get();
$aggregations = $query->getAggregations();

// Example 4: Complex multi-condition query
$products = Product::where('in_stock', true)
    ->where(function ($query) {
        $query->where('price', '<', 100)
            ->orWhereBetween('price', [500, 1000]);
    })
    ->whereNested('categories', function ($query) {
        $query->where('categories.slug', 'electronics');
    })
    ->whereNotNull('description')
    ->orderByDesc('created_at')
    ->select(['name', 'price', 'brand', 'categories'])
    ->paginate(25);

// Example 5: Find similar products (by category) excluding current product
$currentProductId = 'product_123';
$currentCategoryIds = [1, 5];

$similarProducts = Product::whereIn('cat_id', $currentCategoryIds)
    ->whereNot('_id', $currentProductId)
    ->where('in_stock', true)
    ->orderByDesc('created_at')
    ->take(5)
    ->get();

// ============================================================================
// USING MODEL SCOPES (from Product model)
// ============================================================================

// Use predefined scopes
$products = Product::inStock()->get();
$products = Product::byBrand('TechBrand')->get();
$products = Product::inCategory(5)->get();
$products = Product::withTag('laptop')->get();

// Combine scopes
$products = Product::inStock()
    ->byBrand('TechBrand')
    ->inCategory(5)
    ->orderBy('price', 'asc')
    ->get();

// ============================================================================
// WORKING WITH RESULTS
// ============================================================================

// Iterate through results
$products = Product::where('in_stock', true)->get();

foreach ($products as $product) {
    echo $product->name;
    echo $product->price;
    echo $product->brand['name'] ?? 'No brand';

    // Access nested categories
    foreach ($product->categories as $category) {
        echo $category['name'];
    }

    // Access tags
    foreach ($product->tags as $tag) {
        echo $tag['name'];
    }
}

// Convert to array
$productArray = $product->toArray();

// Get specific attribute
$price = $product->getAttribute('price');
$brand = $product->brand;

// Check if exists
if ($product->exists) {
    echo "Product exists in Elasticsearch";
}

// ============================================================================
// PERFORMANCE TIPS
// ============================================================================

// Use select() to return only needed fields
$products = Product::select(['name', 'price'])
    ->where('in_stock', true)
    ->get(); // Faster, less data transfer

// Use count() instead of get()->count()
$count = Product::where('in_stock', true)->count(); // More efficient

// Use pagination for large result sets
$products = Product::paginate(50); // Better than ->get() for many results

// Use aggregations for analytics instead of fetching all data
$stats = Product::query()
    ->avgAgg('avg_price', 'price')
    ->minAgg('min_price', 'price')
    ->maxAgg('max_price', 'price')
    ->getAggregations();
