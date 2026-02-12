<?php


namespace ElasticsearchEloquent\Exmaple;

use App\Models\StoreProduct;
use Illuminate\Support\Carbon;

class AdvancedQueryBuilderPattern
{
    /**
     * Sales summary by brand
     */
    public function salesByBrand(int $storeId, ?Carbon $startDate = null, ?Carbon $endDate = null)
    {
        $query = StoreProduct::search()
            ->where('store_id', $storeId)
            ->where('state', 'published');

        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [
                $startDate->toDateString(),
                $endDate->toDateString()
            ]);
        }

        return $query
            ->groupByWithMetrics('brands', 'brand.id', [
                'total_products' => 'value_count:id',
                'total_revenue' => 'sum:price',
                'total_sales' => 'sum:sale_count',
                'avg_price' => 'avg:price',
                'min_price' => 'min:price',
                'max_price' => 'max:price',
                'avg_rating' => 'avg:rating',
                'total_views' => 'sum:view_count',
            ], 100, ['order' => ['total_revenue' => 'desc']])
            ->limit(0)
            ->getAggregations();
    }

    /**
     * Monthly sales trend
     */
    public function monthlySalesTrend(int $storeId, int $months = 12)
    {
        $startDate = now()->subMonths($months)->startOfMonth();

        return StoreProduct::search()
            ->where('store_id', $storeId)
            ->whereGreaterThanOrEqual('created_at', $startDate->toDateString())
            ->groupByDate('monthly_sales', 'created_at', 'month', [
                'revenue' => ['sum' => ['field' => 'price']],
                'sales' => ['sum' => ['field' => 'sale_count']],
                'products_added' => ['value_count' => ['field' => 'id']],
                'avg_price' => ['avg' => ['field' => 'price']],
            ])
            ->limit(0)
            ->getAggregations();
    }

    /**
     * Category performance
     */
    public function categoryPerformance(int $storeId)
    {
        return StoreProduct::search()
            ->where('store_id', $storeId)
            ->where('state', 'published')
            ->aggregateNested('categories', 'categories', [
                'by_category' => [
                    'terms' => [
                        'field' => 'categories.id',
                        'size' => 100,
                        'order' => ['products.revenue' => 'desc']
                    ],
                    'aggs' => [
                        'products' => [
                            'reverse_nested' => new \stdClass(),
                            'aggs' => [
                                'revenue' => ['sum' => ['field' => 'price']],
                                'sales' => ['sum' => ['field' => 'sale_count']],
                                'count' => ['value_count' => ['field' => 'id']],
                                'avg_price' => ['avg' => ['field' => 'price']],
                                'avg_rating' => ['avg' => ['field' => 'rating']],
                            ]
                        ]
                    ]
                ]
            ])
            ->limit(0)
            ->getAggregations();
    }
}
