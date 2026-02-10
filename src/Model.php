<?php

namespace ElasticsearchEloquent;

use Builder;
use Elastic\Elasticsearch\Client;
use Illuminate\Support\Str;

abstract class Model
{
    /**
     * The Elasticsearch index name.
     */
    protected string $index;

    /**
     * The primary key for the model.
     */
    protected string $primaryKey = '_id';

    /**
     * The model's attributes.
     */
    protected array $attributes = [];

    /**
     * The attributes that should be cast.
     */
    protected array $casts = [];

    /**
     * Indicates if the model exists in Elasticsearch.
     */
    public bool $exists = false;

    /**
     * The Elasticsearch client instance.
     */
    protected static ?Client $client = null;

    /**
     * Create a new model instance.
     */
    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    /**
     * Fill the model with an array of attributes.
     */
    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }

        return $this;
    }

    /**
     * Set a given attribute on the model.
     */
    public function setAttribute(string $key, mixed $value): static
    {
        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * Get an attribute from the model.
     */
    public function getAttribute(string $key): mixed
    {
        if (!array_key_exists($key, $this->attributes)) {
            return null;
        }

        $value = $this->attributes[$key];

        if (array_key_exists($key, $this->casts)) {
            return $this->castAttribute($key, $value);
        }

        return $value;
    }

    /**
     * Cast an attribute to a native PHP type.
     */
    protected function castAttribute(string $key, mixed $value): mixed
    {
        $castType = $this->casts[$key];

        if ($value === null) {
            return null;
        }

        return match ($castType) {
            'int', 'integer' => (int) $value,
            'real', 'float', 'double' => (float) $value,
            'string' => (string) $value,
            'bool', 'boolean' => (bool) $value,
            'array', 'json' => is_string($value) ? json_decode($value, true) : $value,
            default => $value,
        };
    }

    /**
     * Get the index name for the model.
     */
    public function getIndex(): string
    {
        return $this->index ?? Str::snake(Str::pluralStudly(class_basename($this)));
    }

    /**
     * Get the primary key for the model.
     */
    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    /**
     * Get the value of the model's primary key.
     */
    public function getKey(): mixed
    {
        return $this->getAttribute($this->getKeyName());
    }

    /**
     * Set the Elasticsearch client.
     */
    public static function setClient(Client $client): void
    {
        static::$client = $client;
    }

    /**
     * Get the Elasticsearch client.
     */
    public static function getClient(): Client
    {
        if (static::$client === null) {
            static::$client = app('elasticsearch');
        }

        return static::$client;
    }

    /**
     * Begin querying the model.
     */
    public static function query(): Builder
    {
        return (new static)->newQuery();
    }

    /**
     * Get a new query builder for the model.
     */
    public function newQuery(): Builder
    {
        return new Builder($this);
    }

    // ... (Keep existing newFromHit, toArray, magic methods) ...

    /**
     * Create a new instance of the model from Elasticsearch hit.
     */
    public function newFromHit(array $hit): static
    {
        $instance = new static;

        $instance->exists = true;

        if (isset($hit['_id'])) {
            $instance->setAttribute($instance->getKeyName(), $hit['_id']);
        }

        if (isset($hit['_source'])) {
            $instance->fill($hit['_source']);
        }

        return $instance;
    }

    public function toArray(): array
    {
        $array = [];
        foreach ($this->attributes as $key => $value) {
            $array[$key] = $this->getAttribute($key);
        }
        return $array;
    }

    public function __get(string $key): mixed { return $this->getAttribute($key); }
    public function __set(string $key, mixed $value): void { $this->setAttribute($key, $value); }
    public function __isset(string $key): bool { return isset($this->attributes[$key]); }

    public static function __callStatic(string $method, array $parameters): mixed
    {
        $instance = new static;

        // Handle Static Scopes (e.g., Product::inStock())
        if (method_exists($instance, 'scope' . ucfirst($method))) {
            return $instance->newQuery()->$method(...$parameters);
        }

        return $instance->$method(...$parameters);
    }

    public function __call(string $method, array $parameters): mixed
    {
        // Handle Local Scopes via magic call (forwarding to Builder)
        // Note: The Builder's __call will loop back to check the Model's scope methods
        return $this->newQuery()->$method(...$parameters);
    }


    /**
     * Get an index manager instance for the model.
     */
    public static function index(): IndexManager
    {
        return new IndexManager(new static);
    }

    /**
     * Define the index mappings.
     * Override this method in your model to define fields.
     */
    public function mapping(): array
    {
        return [
            'properties' => [],
        ];
    }

    /**
     * Define the index settings (shards, replicas, analysis).
     * Override this method in your model to define settings.
     */
    public function settings(): array
    {
        return [
            'number_of_shards' => 1,
            'number_of_replicas' => 1,
        ];
    }

}
