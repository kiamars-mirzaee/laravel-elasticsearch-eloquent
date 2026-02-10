<?php


use Elastic\Elasticsearch\Client;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

class Builder
{
    /**
     * The model being queried.
     */
    protected \ElasticsearchEloquent\Model $model;

    /**
     * The Elasticsearch client.
     */
    protected Client $client;

    /**
     * The where constraints for the query.
     */
    protected array $wheres = [];

    /**
     * The orderings for the query.
     */
    protected array $orders = [];

    /**
     * The maximum number of records to return.
     */
    protected ?int $limit = null;

    /**
     * The number of records to skip.
     */
    protected int $offset = 0;

    /**
     * The columns to select.
     */
    protected array $columns = [];

    /**
     * The aggregations for the query.
     */
    protected array $aggregations = [];

    /**
     * The search query.
     */
    protected ?array $searchQuery = null;

    /**
     * Minimum score threshold.
     */
    protected ?float $minScore = null;

    /**
     * Create a new query builder instance.
     */
    public function __construct(\ElasticsearchEloquent\Model $model)
    {
        $this->model = $model;
        $this->client = $model::getClient();
    }

    /**
     * Add a basic where clause to the query.
     */
    public function where(string|array $column, mixed $operator = null, mixed $value = null, string $boolean = 'and'): static
    {
        if (is_array($column)) {
            foreach ($column as $key => $val) {
                $this->where($key, '=', $val, $boolean);
            }
            return $this;
        }

        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $type = 'Basic';
        $this->wheres[] = compact('type', 'column', 'operator', 'value', 'boolean');

        return $this;
    }

    /**
     * Add a raw DSL clause to the query.
     * This is useful for complex queries that aren't supported by the fluent API.
     */
    public function whereRaw(array $body, string $boolean = 'and'): static
    {
        $type = 'Raw';
        $this->wheres[] = compact('type', 'body', 'boolean');

        return $this;
    }

    /**
     * Add an "or where raw" clause to the query.
     */
    public function orWhereRaw(array $body): static
    {
        return $this->whereRaw($body, 'or');
    }

    /**
     * Add an "or where" clause to the query.
     */
    public function orWhere(string|array $column, mixed $operator = null, mixed $value = null): static
    {
        if (is_array($column)) {
            foreach ($column as $key => $val) {
                $this->where($key, '=', $val, 'or');
            }
            return $this;
        }

        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        return $this->where($column, $operator, $value, 'or');
    }

    public function whereIn(string $column, array $values, string $boolean = 'and'): static
    {
        $type = 'In';
        $this->wheres[] = compact('type', 'column', 'values', 'boolean');
        return $this;
    }

    public function orWhereIn(string $column, array $values): static
    {
        return $this->whereIn($column, $values, 'or');
    }

    public function whereNotIn(string $column, array $values, string $boolean = 'and'): static
    {
        $type = 'NotIn';
        $this->wheres[] = compact('type', 'column', 'values', 'boolean');
        return $this;
    }

    public function orWhereNotIn(string $column, array $values): static
    {
        return $this->whereNotIn($column, $values, 'or');
    }

    public function whereNull(string $column, string $boolean = 'and'): static
    {
        $type = 'Null';
        $this->wheres[] = compact('type', 'column', 'boolean');
        return $this;
    }

    public function orWhereNull(string $column): static
    {
        return $this->whereNull($column, 'or');
    }

    public function whereNotNull(string $column, string $boolean = 'and'): static
    {
        $type = 'NotNull';
        $this->wheres[] = compact('type', 'column', 'boolean');
        return $this;
    }

    public function orWhereNotNull(string $column): static
    {
        return $this->whereNotNull($column, 'or');
    }

    public function whereBetween(string $column, array $values, string $boolean = 'and'): static
    {
        $type = 'Between';
        $this->wheres[] = compact('type', 'column', 'values', 'boolean');
        return $this;
    }

    public function orWhereBetween(string $column, array $values): static
    {
        return $this->whereBetween($column, $values, 'or');
    }

    public function whereNotBetween(string $column, array $values, string $boolean = 'and'): static
    {
        $type = 'NotBetween';
        $this->wheres[] = compact('type', 'column', 'values', 'boolean');
        return $this;
    }

    public function whereNot(string|array $column, mixed $operator = null, mixed $value = null, string $boolean = 'and'): static
    {
        if (is_array($column)) {
            foreach ($column as $key => $val) {
                $this->whereNot($key, '=', $val, $boolean);
            }
            return $this;
        }

        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $type = 'Not';
        $this->wheres[] = compact('type', 'column', 'operator', 'value', 'boolean');
        return $this;
    }

    public function whereNested(string $path, \Closure $callback, string $boolean = 'and'): static
    {
        $query = new static($this->model);
        call_user_func($callback, $query);

        $type = 'Nested';
        $nestedWheres = $query->wheres;
        $this->wheres[] = compact('type', 'path', 'nestedWheres', 'boolean');
        return $this;
    }

    public function search(string $query, array|string $fields = ['*'], string $boolean = 'and'): static
    {
        if (is_string($fields)) {
            $fields = [$fields];
        }
        $this->searchQuery = ['query' => $query, 'fields' => $fields, 'boolean' => $boolean];
        return $this;
    }

    public function matchPhrase(string $column, string $value, string $boolean = 'and'): static
    {
        $type = 'MatchPhrase';
        $this->wheres[] = compact('type', 'column', 'value', 'boolean');
        return $this;
    }

    public function minScore(float $score): static
    {
        $this->minScore = $score;
        return $this;
    }

    public function select(array|string $columns = ['*']): static
    {
        $this->columns = is_array($columns) ? $columns : func_get_args();
        return $this;
    }

    public function orderBy(string $column, string $direction = 'asc'): static
    {
        $this->orders[] = ['column' => $column, 'direction' => strtolower($direction) === 'asc' ? 'asc' : 'desc'];
        return $this;
    }

    public function orderByDesc(string $column): static
    {
        return $this->orderBy($column, 'desc');
    }

    public function latest(string $column = 'created_at'): static
    {
        return $this->orderBy($column, 'desc');
    }

    public function oldest(string $column = 'created_at'): static
    {
        return $this->orderBy($column, 'asc');
    }

    public function limit(int $value): static
    {
        $this->limit = $value;
        return $this;
    }

    public function take(int $value): static
    {
        return $this->limit($value);
    }

    public function offset(int $value): static
    {
        $this->offset = $value;
        return $this;
    }

    public function skip(int $value): static
    {
        return $this->offset($value);
    }

    public function aggregate(string $name, string $type, string $field, array $options = []): static
    {
        $this->aggregations[$name] = array_merge(['type' => $type, 'field' => $field], $options);
        return $this;
    }

    public function termsAgg(string $name, string $field, int $size = 10): static
    {
        return $this->aggregate($name, 'terms', $field, ['size' => $size]);
    }

    public function sumAgg(string $name, string $field): static
    {
        return $this->aggregate($name, 'sum', $field);
    }

    public function avgAgg(string $name, string $field): static
    {
        return $this->aggregate($name, 'avg', $field);
    }

    public function minAgg(string $name, string $field): static
    {
        return $this->aggregate($name, 'min', $field);
    }

    public function maxAgg(string $name, string $field): static
    {
        return $this->aggregate($name, 'max', $field);
    }

    /**
     * Execute the query and get all results.
     */
    public function get(array $columns = ['*']): Collection
    {
        if (!empty($columns) && $columns !== ['*']) {
            $this->select($columns);
        }

        $response = $this->executeSearch();

        return $this->hydrateModels($response['hits']['hits'] ?? []);
    }

    /**
     * Execute the query and get the first result.
     */
    public function first(array $columns = ['*']): ?\ElasticsearchEloquent\Model
    {
        return $this->take(1)->get($columns)->first();
    }

    /**
     * Get a paginator for the query.
     */
    public function paginate(int $perPage = 15, array $columns = ['*'], string $pageName = 'page', ?int $page = null): LengthAwarePaginator
    {
        $page = $page ?: Paginator::resolveCurrentPage($pageName);

        $this->offset(($page - 1) * $perPage)->limit($perPage);

        if (!empty($columns) && $columns !== ['*']) {
            $this->select($columns);
        }

        $response = $this->executeSearch();

        $total = $response['hits']['total']['value'] ?? 0;
        $results = $this->hydrateModels($response['hits']['hits'] ?? []);

        return new LengthAwarePaginator($results, $total, $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => $pageName,
        ]);
    }

    /**
     * Get the count of results.
     */
    public function count(): int
    {
        $params = [
            'index' => $this->model->getIndex(),
            'body' => ['query' => $this->compileWheres()],
        ];

        $response = $this->client->count($params);

        return $response['count'] ?? 0;
    }

    /**
     * Dump the raw Elasticsearch DSL query and die.
     */
    public function dd(): void
    {
        dd($this->toDSL());
    }

    /**
     * Dump the raw Elasticsearch DSL query.
     */
    public function dump(): static
    {
        dump($this->toDSL());
        return $this;
    }

    /**
     * Get the raw Elasticsearch DSL query array.
     */
    public function toDSL(): array
    {
        $body = [];
        $query = $this->compileWheres();
        if (!empty($query)) $body['query'] = $query;
        if (!empty($this->columns) && $this->columns !== ['*']) $body['_source'] = $this->columns;
        if (!empty($this->orders)) $body['sort'] = $this->compileOrders();
        if (!empty($this->aggregations)) $body['aggs'] = $this->compileAggregations();
        if ($this->minScore !== null) $body['min_score'] = $this->minScore;

        return $body;
    }

    /**
     * Execute the search query.
     */
    protected function executeSearch(): array
    {
        $params = [
            'index' => $this->model->getIndex(),
            'body' => $this->toDSL(),
        ];

        if ($this->limit !== null) $params['size'] = $this->limit;
        if ($this->offset > 0) $params['from'] = $this->offset;

        return $this->client->search($params)->asArray();
    }

    /**
     * Dynamically handle calls to the builder.
     * Use to handle Model Scopes.
     */
    public function __call(string $method, array $parameters): mixed
    {
        if (method_exists($this->model, $scope = 'scope' . ucfirst($method))) {
            $this->model->$scope($this, ...$parameters);
            return $this;
        }

        throw new \BadMethodCallException("Method {$method} does not exist.");
    }

    protected function compileWheres(): array
    {
        if (empty($this->wheres) && $this->searchQuery === null) {
            return ['match_all' => new \stdClass()];
        }

        $must = [];
        $should = [];
        $mustNot = [];
        $filter = [];

        if ($this->searchQuery !== null) {
            $searchClause = [
                'multi_match' => [
                    'query' => $this->searchQuery['query'],
                    'fields' => $this->searchQuery['fields'],
                ],
            ];
            if ($this->searchQuery['boolean'] === 'and') $must[] = $searchClause;
            else $should[] = $searchClause;
        }

        foreach ($this->wheres as $where) {
            $compiled = $this->compileWhere($where);
            if ($where['boolean'] === 'and') {
                if (in_array($where['type'], ['Basic', 'In', 'NotIn', 'Between', 'NotBetween'])) {
                    $filter[] = $compiled;
                } else {
                    // Raw queries usually affect scoring, so we put them in 'must' (Query context)
                    // instead of 'filter' (Filter context).
                    $must[] = $compiled;
                }
            } else {
                $should[] = $compiled;
            }
        }

        $bool = [];
        if (!empty($must)) $bool['must'] = $must;
        if (!empty($should)) {
            $bool['should'] = $should;
            if (empty($must)) $bool['minimum_should_match'] = 1;
        }
        if (!empty($mustNot)) $bool['must_not'] = $mustNot;
        if (!empty($filter)) $bool['filter'] = $filter;

        return empty($bool) ? ['match_all' => new \stdClass()] : ['bool' => $bool];
    }

    protected function compileWhere(array $where): array
    {
        $method = "compileWhere{$where['type']}";
        return method_exists($this, $method) ? $this->$method($where) : [];
    }

    protected function compileWhereBasic(array $where): array
    {
        $column = $where['column'];
        $value = $where['value'];
        return match ($where['operator']) {
            '=' => ['term' => [$column => $value]],
            '!=' => ['bool' => ['must_not' => ['term' => [$column => $value]]]],
            '>' => ['range' => [$column => ['gt' => $value]]],
            '>=' => ['range' => [$column => ['gte' => $value]]],
            '<' => ['range' => [$column => ['lt' => $value]]],
            '<=' => ['range' => [$column => ['lte' => $value]]],
            'like' => ['wildcard' => [$column => str_replace('%', '*', $value)]],
            default => ['term' => [$column => $value]],
        };
    }

    /**
     * Compile a raw where clause.
     */
    protected function compileWhereRaw(array $where): array
    {
        return $where['body'];
    }

    protected function compileWhereNot(array $where): array { return ['bool' => ['must_not' => $this->compileWhereBasic($where)]]; }
    protected function compileWhereIn(array $where): array { return ['terms' => [$where['column'] => $where['values']]]; }
    protected function compileWhereNotIn(array $where): array { return ['bool' => ['must_not' => ['terms' => [$where['column'] => $where['values']]]]]; }
    protected function compileWhereNull(array $where): array { return ['bool' => ['must_not' => ['exists' => ['field' => $where['column']]]]]; }
    protected function compileWhereNotNull(array $where): array { return ['exists' => ['field' => $where['column']]]; }
    protected function compileWhereBetween(array $where): array { return ['range' => [$where['column'] => ['gte' => $where['values'][0], 'lte' => $where['values'][1]]]]; }
    protected function compileWhereNotBetween(array $where): array { return ['bool' => ['must_not' => ['range' => [$where['column'] => ['gte' => $where['values'][0], 'lte' => $where['values'][1]]]]]]; }
    protected function compileWhereNested(array $where): array {
        $nestedBuilder = new static($this->model);
        $nestedBuilder->wheres = $where['nestedWheres'];
        return ['nested' => ['path' => $where['path'], 'query' => $nestedBuilder->compileWheres()]];
    }
    protected function compileWhereMatchPhrase(array $where): array { return ['match_phrase' => [$where['column'] => $where['value']]]; }

    protected function compileOrders(): array
    {
        return array_map(function ($order) {
            return [$order['column'] => ['order' => $order['direction']]];
        }, $this->orders);
    }

    protected function compileAggregations(): array
    {
        $aggs = [];
        foreach ($this->aggregations as $name => $aggregation) {
            $aggs[$name] = [$aggregation['type'] => ['field' => $aggregation['field']]];
            if ($aggregation['type'] === 'terms' && isset($aggregation['size'])) {
                $aggs[$name][$aggregation['type']]['size'] = $aggregation['size'];
            }
        }
        return $aggs;
    }

    protected function hydrateModels(array $hits): Collection
    {
        return collect($hits)->map(fn($hit) => $this->model->newFromHit($hit));
    }

    public function getAggregations(): array
    {
        $response = $this->executeSearch();
        return $response['aggregations'] ?? [];
    }



    public function bulkIndex(Collection|array $models): array
    {
        $params = ['body' => []];

        foreach ($models as $model) {
            // Header: Action and Metadata
            $params['body'][] = [
                'index' => [
                    '_index' => $this->model->getIndex(),
                    '_id'    => $model->getKey()
                ]
            ];

            // Body: The actual data
            $params['body'][] = $model->toArray();
        }

        return $this->client->bulk($params)->asArray();
    }
}
