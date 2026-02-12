<?php

namespace ElasticsearchEloquent;

use Elastic\Elasticsearch\Client;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;


class ElasticsearchQueryBuilder
{

    public static function new(Client $client, string $index): static
    {
        return new static($client, $index);
    }

    protected Client $client;
    protected string $index;
    protected array $query = [];
    protected array $must = [];
    protected array $should = [];
    protected array $filter = [];
    protected array $mustNot = [];
    protected array $sort = [];
    protected array $aggregations = [];
    protected ?int $size = null;
    protected int $from = 0;
    protected array $source = [];
    protected ?int $minScore = null;
    protected array $highlight = [];
    protected array $scopes = [];
    protected ?string $searchAfter = null;
    protected array $collapse = [];

    public function __construct(Client $client, string $index)
    {
        $this->client = $client;
        $this->index = $index;
    }

    /**
     * Add a basic where clause
     */
    public function where(string $field, mixed $operator = null, mixed $value = null): self
    {
        // Handle two arguments: where('field', 'value')
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        return match ($operator) {
            '=', '==' => $this->whereTerm($field, $value),
            '!=' => $this->whereNot($field, $value),
            '>' => $this->whereGreaterThan($field, $value),
            '>=' => $this->whereGreaterThanOrEqual($field, $value),
            '<' => $this->whereLessThan($field, $value),
            '<=' => $this->whereLessThanOrEqual($field, $value),
            'like' => $this->whereLike($field, $value),
            default => throw new \InvalidArgumentException("Operator {$operator} not supported")
        };
    }

    public function when(mixed $condition, callable $callback, ?callable $default = null): self
    {

        $value = $condition instanceof \Closure ? $condition($this) : $condition;
        if ($value) {
            $callback($this, $value);
        } elseif ($default) {
            $default($this, $value);
        }

        return $this;
    }


    /**
     * Add a term query (exact match)
     */
    public function whereTerm(string $field, mixed $value): self
    {
        $this->filter[] = ['term' => [$field => $value]];
        return $this;
    }

    /**
     * Add a terms query (IN clause)
     */
    public function whereIn(string $field, array $values): self
    {
        if (empty($values)) {
            return $this;
        }

        $this->filter[] = ['terms' => [$field => array_values($values)]];
        return $this;
    }

    /**
     * Add a NOT IN clause
     */
    public function whereNotIn(string $field, array $values): self
    {
        if (empty($values)) {
            return $this;
        }

        $this->mustNot[] = ['terms' => [$field => array_values($values)]];
        return $this;
    }

    /**
     * Add a null check (field doesn't exist or is null)
     */
    public function whereNull(string $field): self
    {
        $this->mustNot[] = ['exists' => ['field' => $field]];
        return $this;
    }

    /**
     * Add a not null check
     */
    public function whereNotNull(string $field): self
    {
        $this->filter[] = ['exists' => ['field' => $field]];
        return $this;
    }

    /**
     * Add a where not clause
     */
    public function whereNot(string $field, mixed $value): self
    {
        $this->mustNot[] = ['term' => [$field => $value]];
        return $this;
    }

    /**
     * Add a between clause
     */
    public function whereBetween(string $field, array $values): self
    {
        [$min, $max] = $values;

        $this->filter[] = [
            'range' => [
                $field => [
                    'gte' => $min,
                    'lte' => $max
                ]
            ]
        ];

        return $this;
    }

    /**
     * Add a not between clause
     */
    public function whereNotBetween(string $field, array $values): self
    {
        [$min, $max] = $values;

        $this->mustNot[] = [
            'range' => [
                $field => [
                    'gte' => $min,
                    'lte' => $max
                ]
            ]
        ];

        return $this;
    }

    /**
     * Greater than
     */
    public function whereGreaterThan(string $field, mixed $value): self
    {
        $this->filter[] = ['range' => [$field => ['gt' => $value]]];
        return $this;
    }

    /**
     * Greater than or equal
     */
    public function whereGreaterThanOrEqual(string $field, mixed $value): self
    {
        $this->filter[] = ['range' => [$field => ['gte' => $value]]];
        return $this;
    }

    /**
     * Less than
     */
    public function whereLessThan(string $field, mixed $value): self
    {
        $this->filter[] = ['range' => [$field => ['lt' => $value]]];
        return $this;
    }

    /**
     * Less than or equal
     */
    public function whereLessThanOrEqual(string $field, mixed $value): self
    {
        $this->filter[] = ['range' => [$field => ['lte' => $value]]];
        return $this;
    }

    /**
     * Wildcard search (LIKE)
     */
    public function whereLike(string $field, string $value): self
    {
        $value = str_replace('%', '*', $value);
        $this->filter[] = ['wildcard' => [$field => $value]];
        return $this;
    }

    /**
     * Full-text search
     */
    public function search(string $field, string $query, array $options = []): self
    {
        $matchQuery = [
            'match' => [
                $field => array_merge([
                    'query' => $query
                ], $options)
            ]
        ];

        $this->must[] = $matchQuery;
        return $this;
    }

    /**
     * Multi-field search
     */
    public function multiMatch(array $fields, string $query, array $options = []): self
    {
        $this->must[] = [
            'multi_match' => array_merge([
                'query' => $query,
                'fields' => $fields
            ], $options)
        ];

        return $this;
    }

    /**
     * Nested query
     */
    public function whereNested(string $path, callable $callback): self
    {
        $nestedBuilder = new static($this->client, $this->index);
        $callback($nestedBuilder);

        $this->filter[] = [
            'nested' => [
                'path' => $path,
                'query' => $nestedBuilder->buildBoolQuery()
            ]
        ];

        return $this;
    }

    /**
     * Exclude products that have matching nested documents
     * (Equivalent to MySQL's whereDoesntHave)
     */
    public function whereNotNested(string $path, callable $callback): self
    {
        $nestedBuilder = new static($this->client, $this->index);
        $callback($nestedBuilder);

        $this->mustNot[] = [
            'nested' => [
                'path' => $path,
                'query' => $nestedBuilder->buildBoolQuery()
            ]
        ];

        return $this;
    }

    /**
     * Alias for whereNotNested (more semantic)
     */
    public function whereDoesntHaveNested(string $path, callable $callback): self
    {
        return $this->whereNotNested($path, $callback);
    }


    /**
     * OR conditions
     */
    public function orWhere(callable $callback): self
    {
        $orBuilder = new static($this->client, $this->index);
        $callback($orBuilder);

        $this->should[] = $orBuilder->buildBoolQuery();

        return $this;
    }

    /**
     * Add sorting
     */
    public function orderBy(string $field, string $direction = 'asc'): self
    {
        $this->sort[] = [$field => ['order' => strtolower($direction)]];
        return $this;
    }

    /**
     * Order by descending
     */
    public function orderByDesc(string $field): self
    {
        return $this->orderBy($field, 'desc');
    }

    /**
     * Limit results
     */
    public function limit(int $limit): self
    {
        $this->size = $limit;
        return $this;
    }

    /**
     * Alias for limit
     */
    public function take(int $limit): self
    {
        return $this->limit($limit);
    }

    /**
     * Offset results
     */
    public function offset(int $offset): self
    {
        $this->from = $offset;
        return $this;
    }

    /**
     * Alias for offset
     */
    public function skip(int $offset): self
    {
        return $this->offset($offset);
    }

    /**
     * Select specific fields
     */
    public function select(array $fields): self
    {
        $this->source = $fields;
        return $this;
    }

    /**
     * Minimum score filter
     */
    public function minScore(float $score): self
    {
        $this->minScore = $score;
        return $this;
    }

    /**
     * Add aggregation
     */
    public function aggregate(string $name, array $aggregation): self
    {
        $this->aggregations[$name] = $aggregation;
        return $this;
    }

    /**
     * Terms aggregation
     */
    public function aggregateTerms(string $name, string $field, int $size = 10): self
    {
        return $this->aggregate($name, [
            'terms' => [
                'field' => $field,
                'size' => $size
            ]
        ]);
    }

    /**
     * Stats aggregation
     */
    public function aggregateStats(string $name, string $field): self
    {
        return $this->aggregate($name, [
            'stats' => ['field' => $field]
        ]);
    }

    /**
     * Add highlight
     */
    public function highlight(array $fields, array $options = []): self
    {
        $this->highlight = array_merge([
            'fields' => array_fill_keys($fields, new \stdClass())
        ], $options);

        return $this;
    }

    /**
     * Collapse results by field
     */
    public function collapse(string $field, array $options = []): self
    {
        $this->collapse = array_merge(['field' => $field], $options);
        return $this;
    }

    /**
     * Apply a scope
     */
    public function scope(string $name, ...$args): self
    {
        $this->scopes[$name] = $args;
        return $this;
    }



    /**
     * Build the complete query
     */
    protected function buildQuery(): array
    {
        $body = [
            'query' => $this->buildBoolQuery()
        ];

        if (!empty($this->sort)) {
            $body['sort'] = $this->sort;
        }

        if ($this->size !== null) {
            $body['size'] = $this->size;
        }

        if ($this->from > 0) {
            $body['from'] = $this->from;
        }

        if (!empty($this->source)) {
            $body['_source'] = $this->source;
        }

        if ($this->minScore !== null) {
            $body['min_score'] = $this->minScore;
        }

        if (!empty($this->aggregations)) {
            $body['aggs'] = $this->aggregations;
        }

        if (!empty($this->highlight)) {
            $body['highlight'] = $this->highlight;
        }

        if (!empty($this->collapse)) {
            $body['collapse'] = $this->collapse;
        }

        return $body;
    }

    /**
     * Execute the query and get results
     */
    public function get(): Collection
    {
        $response = $this->client->search([
            'index' => $this->index,
            'body' => $this->buildQuery()
        ]);

        $hits = $response['hits']['hits'] ?? [];

        return collect($hits)->map(function ($hit) {
            $source = $hit['_source'] ?? [];
            $source['_id'] = $hit['_id'] ?? null;
            $source['_score'] = $hit['_score'] ?? null;

            if (isset($hit['highlight'])) {
                $source['_highlight'] = $hit['highlight'];
            }

            return $source;
        });
    }

    /**
     * Get first result
     */
    public function first(): ?array
    {
        $results = $this->limit(1)->get();
        return $results->first();
    }

    /**
     * Get count
     */
    public function count(): int
    {
        $response = $this->client->count([
            'index' => $this->index,
            'body' => [
                'query' => $this->buildBoolQuery()
            ]
        ]);

        return $response['count'] ?? 0;
    }

    /**
     * Paginate results
     */
    public function paginate(int $perPage = 15, int $page = 1): LengthAwarePaginator
    {
        $this->size = $perPage;
        $this->from = ($page - 1) * $perPage;

        $response = $this->client->search([
            'index' => $this->index,
            'body' => $this->buildQuery(),
            'track_total_hits' => true
        ]);

        $hits = $response['hits']['hits'] ?? [];
        $total = $response['hits']['total']['value'] ?? 0;

        $items = collect($hits)->map(function ($hit) {
            $source = $hit['_source'] ?? [];
            $source['_id'] = $hit['_id'] ?? null;
            $source['_score'] = $hit['_score'] ?? null;
            return $source;
        });

        return new Paginator($items, $total, $perPage, $page, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }

    /**
     * Get aggregations
     */
    public function getAggregations(): array
    {
        $response = $this->client->search([
            'index' => $this->index,
            'body' => $this->buildQuery()
        ]);

        return $response['aggregations'] ?? [];
    }

    /**
     * Debug - get the raw query
     */
    public function toArray(): array
    {
        return $this->buildQuery();
    }

    /**
     * Debug - get query as JSON
     */
    public function toJson(): string
    {
        return json_encode($this->buildQuery(), JSON_PRETTY_PRINT);
    }




// Add these methods to ElasticsearchQueryBuilder class

    /**
     * Prefix query
     */
    public function wherePrefix(string $field, string $value, array $options = []): self
    {
        $this->should[] = [
            'prefix' => [
                $field => array_merge(['value' => $value], $options)
            ]
        ];

        return $this;
    }



    /**
     * Match bool prefix query
     */
    public function matchBoolPrefix(string $field, string $query, array $options = []): self
    {
        $this->should[] = [
            'match_bool_prefix' => [
                $field => array_merge(['query' => $query], $options)
            ]
        ];

        return $this;
    }

    /**
     * Add a should clause (OR condition) without wrapping
     */
    public function shouldRaw(array $query): self
    {
        $this->should[] = $query;
        return $this;
    }

    /**
     * Set minimum should match for should clauses
     */
    protected int $minimumShouldMatch = 1;

    public function minimumShouldMatch(int $value): self
    {
        $this->minimumShouldMatch = $value;
        return $this;
    }


    /**
     * Apply callback when condition is false (opposite of when)
     *
     * @param mixed $condition The condition to evaluate
     * @param callable $callback Callback to execute when false
     * @param callable|null $default Optional callback when true
     * @return self
     */
    public function unless(mixed $condition, callable $callback, ?callable $default = null): self
    {
        $value = $condition instanceof \Closure ? $condition($this) : $condition;

        if (!$value) {
            $callback($this, $value);
        } elseif ($default) {
            $default($this, $value);
        }

        return $this;
    }

    /**
     * Apply callback when value is not null
     */
    public function whenNotNull(mixed $value, callable $callback): self
    {
        return $this->when($value !== null, fn($q) => $callback($q, $value));
    }

    /**
     * Apply callback when value is not empty
     */
    public function whenNotEmpty(mixed $value, callable $callback): self
    {
        return $this->when(!empty($value), fn($q) => $callback($q, $value));
    }

    /**
     * Apply callback when array/collection is not empty
     */
    public function whenHasAny(array $values, callable $callback): self
    {
        return $this->when(count($values) > 0, fn($q) => $callback($q, $values));
    }

    /**
     * Apply callback when all values are present
     */
    public function whenHasAll(array $values, callable $callback): self
    {
        $allPresent = !in_array(null, $values, true) && !in_array('', $values, true);
        return $this->when($allPresent, fn($q) => $callback($q, $values));
    }

    /**
     * Apply callback when value is within range
     */
    public function whenBetween(mixed $value, array $range, callable $callback): self
    {
        [$min, $max] = $range;
        $inRange = $value >= $min && $value <= $max;
        return $this->when($inRange, fn($q) => $callback($q, $value));
    }

    /**
     * Apply callback based on value comparison
     */
    public function whenEquals(mixed $value, mixed $expected, callable $callback, ?callable $default = null): self
    {
        return $this->when($value === $expected, $callback, $default);
    }

    /**
     * Apply callback when value is in array
     */
    public function whenIn(mixed $value, array $options, callable $callback): self
    {
        return $this->when(in_array($value, $options, true), fn($q) => $callback($q, $value));
    }

    /**
     * Conditional chaining - apply first matching condition
     */
    public function match(array $conditions): self
    {
        foreach ($conditions as $condition => $callback) {
            if ($condition instanceof \Closure) {
                $result = $condition($this);
            } else {
                $result = $condition;
            }

            if ($result) {
                $callback($this);
                break;
            }
        }

        return $this;
    }

    /**
     * Apply different callbacks based on value (like switch statement)
     */
    public function switch(mixed $value, array $cases, ?callable $default = null): self
    {
        if (isset($cases[$value])) {
            $cases[$value]($this, $value);
        } elseif ($default) {
            $default($this, $value);
        }

        return $this;
    }

    /**
     * Tap into the query without affecting it (for debugging/logging)
     */
    public function tap(callable $callback): self
    {
        $callback($this);
        return $this;
    }

    /**
     * Apply callback and return its result instead of query builder
     */
    public function pipe(callable $callback): mixed
    {
        return $callback($this);
    }



// Replace the conflicting methods in ElasticsearchQueryBuilder class

    /**
     * Match phrase query
     */
    public function matchPhrase(string $field, string $query, array $options = []): self
    {
        $this->should[] = [
            'match_phrase' => [
                $field => array_merge(['query' => $query], $options)
            ]
        ];

        return $this;
    }

    /**
     * Match query - renamed to avoid conflict with conditional match()
     */
    public function matchQuery(string $field, string $query, array $options = []): self
    {
        $this->should[] = [
            'match' => [
                $field => array_merge(['query' => $query], $options)
            ]
        ];

        return $this;
    }

    /**
     * Match phrase prefix query
     */
    public function matchPhrasePrefix(string $field, string $query, array $options = []): self
    {
        $this->should[] = [
            'match_phrase_prefix' => [
                $field => array_merge(['query' => $query], $options)
            ]
        ];

        return $this;
    }


    /**
     * Add raw query to must clause
     */
    public function mustRaw(array $query): self
    {
        $this->must[] = $query;
        return $this;
    }

    /**
     * Add raw query to filter clause
     */
    public function filterRaw(array $query): self
    {
        $this->filter[] = $query;
        return $this;
    }

    /**
     * Add raw query to must_not clause
     */
    public function mustNotRaw(array $query): self
    {
        $this->mustNot[] = $query;
        return $this;
    }




// Update buildBoolQuery to use minimumShouldMatch
    protected function buildBoolQuery(): array
    {
        $bool = [];


        if (!empty($this->must)) {
            $bool['must'] = $this->must;
        }

        if (!empty($this->should)) {
            $bool['should'] = $this->should;
            $bool['minimum_should_match'] = $this->minimumShouldMatch;
        }

        if (!empty($this->filter)) {
            $bool['filter'] = $this->filter;
        }

        if (!empty($this->mustNot)) {
            $bool['must_not'] = $this->mustNot;
        }

        return empty($bool) ? ['match_all' => new \stdClass()] : ['bool' => $bool];
    }









// Add these methods to ElasticsearchQueryBuilder class

    /**
     * Sum aggregation
     */
    public function aggregateSum(string $name, string $field): self
    {
        return $this->aggregate($name, [
            'sum' => ['field' => $field]
        ]);
    }

    /**
     * Average aggregation
     */
    public function aggregateAvg(string $name, string $field): self
    {
        return $this->aggregate($name, [
            'avg' => ['field' => $field]
        ]);
    }

    /**
     * Min aggregation
     */
    public function aggregateMin(string $name, string $field): self
    {
        return $this->aggregate($name, [
            'min' => ['field' => $field]
        ]);
    }

    /**
     * Max aggregation
     */
    public function aggregateMax(string $name, string $field): self
    {
        return $this->aggregate($name, [
            'max' => ['field' => $field]
        ]);
    }

    /**
     * Count (value_count) aggregation
     */
    public function aggregateCount(string $name, string $field): self
    {
        return $this->aggregate($name, [
            'value_count' => ['field' => $field]
        ]);
    }

    /**
     * Cardinality (distinct count) aggregation
     */
    public function aggregateCardinality(string $name, string $field, int $precisionThreshold = 3000): self
    {
        return $this->aggregate($name, [
            'cardinality' => [
                'field' => $field,
                'precision_threshold' => $precisionThreshold
            ]
        ]);
    }

    /**
     * Percentiles aggregation
     */
    public function aggregatePercentiles(string $name, string $field, array $percents = [25, 50, 75, 95, 99]): self
    {
        return $this->aggregate($name, [
            'percentiles' => [
                'field' => $field,
                'percents' => $percents
            ]
        ]);
    }

    /**
     * Extended stats aggregation (includes sum, avg, min, max, variance, std_deviation, etc.)
     */
    public function aggregateExtendedStats(string $name, string $field): self
    {
        return $this->aggregate($name, [
            'extended_stats' => ['field' => $field]
        ]);
    }

    /**
     * Group by aggregation (like SQL GROUP BY)
     *
     * @param string $name Aggregation name
     * @param string $field Field to group by
     * @param int $size Number of buckets to return
     * @param array $subAggregations Optional sub-aggregations (metrics for each group)
     * @param array $options Additional options (order, missing, etc.)
     */
    public function groupBy(string $name, string $field, int $size = 10, array $subAggregations = [], array $options = []): self
    {
        $termAgg = array_merge([
            'field' => $field,
            'size' => $size
        ], $options);

        $aggregation = ['terms' => $termAgg];

        // Add sub-aggregations (metrics for each group)
        if (!empty($subAggregations)) {
            $aggregation['aggs'] = $subAggregations;
        }

        return $this->aggregate($name, $aggregation);
    }

    /**
     * Group by with sum (like SQL: SELECT field, SUM(sumField) GROUP BY field)
     */
    public function groupByWithSum(string $name, string $groupField, string $sumField, int $size = 10, array $options = []): self
    {
        return $this->groupBy($name, $groupField, $size, [
            'total' => [
                'sum' => ['field' => $sumField]
            ]
        ], $options);
    }

    /**
     * Group by with multiple metrics
     */
    public function groupByWithMetrics(string $name, string $groupField, array $metrics, int $size = 10, array $options = []): self
    {
        $subAggs = [];

        foreach ($metrics as $metricName => $metric) {
            if (is_string($metric)) {
                // Simple format: ['total_sales' => 'sum:sale_count']
                [$type, $field] = explode(':', $metric);
                $subAggs[$metricName] = [$type => ['field' => $field]];
            } else {
                // Full format: ['total_sales' => ['sum' => ['field' => 'sale_count']]]
                $subAggs[$metricName] = $metric;
            }
        }

        return $this->groupBy($name, $groupField, $size, $subAggs, $options);
    }

    /**
     * Date histogram aggregation (group by date intervals)
     */
    public function groupByDate(string $name, string $field, string $interval = 'day', array $subAggregations = [], array $options = []): self
    {
        $dateHistogram = array_merge([
            'field' => $field,
            'calendar_interval' => $interval, // day, week, month, quarter, year
            'format' => 'yyyy-MM-dd',
            'min_doc_count' => 0
        ], $options);

        $aggregation = ['date_histogram' => $dateHistogram];

        if (!empty($subAggregations)) {
            $aggregation['aggs'] = $subAggregations;
        }

        return $this->aggregate($name, $aggregation);
    }

    /**
     * Range aggregation (group by ranges)
     */
    public function groupByRange(string $name, string $field, array $ranges, array $subAggregations = []): self
    {
        $aggregation = [
            'range' => [
                'field' => $field,
                'ranges' => $ranges
            ]
        ];

        if (!empty($subAggregations)) {
            $aggregation['aggs'] = $subAggregations;
        }

        return $this->aggregate($name, $aggregation);
    }

    /**
     * Histogram aggregation (group by numeric intervals)
     */
    public function groupByHistogram(string $name, string $field, int $interval, array $subAggregations = [], array $options = []): self
    {
        $histogram = array_merge([
            'field' => $field,
            'interval' => $interval,
            'min_doc_count' => 0
        ], $options);

        $aggregation = ['histogram' => $histogram];

        if (!empty($subAggregations)) {
            $aggregation['aggs'] = $subAggregations;
        }

        return $this->aggregate($name, $aggregation);
    }

    /**
     * Nested aggregation (for nested fields)
     */
    public function aggregateNested(string $name, string $path, array $subAggregations): self
    {
        return $this->aggregate($name, [
            'nested' => ['path' => $path],
            'aggs' => $subAggregations
        ]);
    }

    /**
     * Filter aggregation (aggregate only matching documents)
     */
    public function aggregateFilter(string $name, array $filter, array $subAggregations): self
    {
        return $this->aggregate($name, [
            'filter' => $filter,
            'aggs' => $subAggregations
        ]);
    }

    /**
     * Filters aggregation (multiple named filters)
     */
    public function aggregateFilters(string $name, array $filters, array $subAggregations = []): self
    {
        $aggregation = [
            'filters' => [
                'filters' => $filters
            ]
        ];

        if (!empty($subAggregations)) {
            $aggregation['aggs'] = $subAggregations;
        }

        return $this->aggregate($name, $aggregation);
    }

    /**
     * Top hits aggregation (get top documents per bucket)
     */
    public function aggregateTopHits(string $name, int $size = 1, array $options = []): self
    {
        return $this->aggregate($name, [
            'top_hits' => array_merge([
                'size' => $size
            ], $options)
        ]);
    }

    /**
     * Bucket sort aggregation (sort and limit buckets)
     */
    public function aggregateBucketSort(string $name, array $sort, int $size = null, int $gap_policy = null): self
    {
        $config = ['sort' => $sort];

        if ($size !== null) {
            $config['size'] = $size;
        }

        if ($gap_policy !== null) {
            $config['gap_policy'] = $gap_policy;
        }

        return $this->aggregate($name, [
            'bucket_sort' => $config
        ]);
    }

    /**
     * Pipeline aggregation - bucket selector
     */
    public function aggregateBucketSelector(string $name, array $bucketsPath, string $script): self
    {
        return $this->aggregate($name, [
            'bucket_selector' => [
                'buckets_path' => $bucketsPath,
                'script' => $script
            ]
        ]);
    }

    /**
     * Global aggregation (ignore query filters)
     */
    public function aggregateGlobal(string $name, array $subAggregations): self
    {
        return $this->aggregate($name, [
            'global' => new \stdClass(),
            'aggs' => $subAggregations
        ]);
    }

    /**
     * Reverse nested aggregation
     */
    public function aggregateReverseNested(string $name, ?string $path = null, array $subAggregations = []): self
    {
        $aggregation = ['reverse_nested' => $path ? ['path' => $path] : new \stdClass()];

        if (!empty($subAggregations)) {
            $aggregation['aggs'] = $subAggregations;
        }

        return $this->aggregate($name, $aggregation);
    }
}
