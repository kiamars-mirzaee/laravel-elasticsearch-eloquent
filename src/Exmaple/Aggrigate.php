<?php

namespace ElasticsearchEloquent\Exmaple;


use App\Models\StoreProduct;

// Calculate total sales amount
$result = StoreProduct::search()
    ->where('store_id', 1)
    ->where('state', 'published')
    ->aggregateSum('total_revenue', 'price')
    ->aggregateSum('total_sales_count', 'sale_count')
    ->limit(0) // We only want aggregations, not documents
    ->get();

$aggregations = $result->getAggregations();

echo "Total Revenue: " . $aggregations['total_revenue']['value'];
echo "Total Sales: " . $aggregations['total_sales_count']['value'];
