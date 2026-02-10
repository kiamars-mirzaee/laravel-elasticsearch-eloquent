<?php

namespace ElasticsearchEloquent\test;


use App\ApiService\Lib\ToSearchable\ElasticsearchEloquent\Builder;
use App\ApiService\Lib\ToSearchable\ElasticsearchEloquent\Model;
use PHPUnit\Framework\TestCase;
use Mockery;

class BuilderTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_compiles_basic_where_clause()
    {
        $model = new class extends Model {
            protected string $index = 'test';
        };

        $builder = new Builder($model);
        $builder->where('name', 'Laptop');

        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('compileWheres');
        $method->setAccessible(true);

        $result = $method->invoke($builder);

        $this->assertArrayHasKey('bool', $result);
        $this->assertArrayHasKey('filter', $result['bool']);
    }

    /** @test */
    public function it_compiles_where_in_clause()
    {
        $model = new class extends Model {
            protected string $index = 'test';
        };

        $builder = new Builder($model);
        $builder->whereIn('cat_id', [1, 2, 3]);

        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('compileWheres');
        $method->setAccessible(true);

        $result = $method->invoke($builder);

        $this->assertArrayHasKey('bool', $result);
    }

    /** @test */
    public function it_compiles_where_null_clause()
    {
        $model = new class extends Model {
            protected string $index = 'test';
        };

        $builder = new Builder($model);
        $builder->whereNull('description');

        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('compileWheres');
        $method->setAccessible(true);

        $result = $method->invoke($builder);

        $this->assertArrayHasKey('bool', $result);
        $this->assertArrayHasKey('must', $result['bool']);
    }

    /** @test */
    public function it_compiles_where_between_clause()
    {
        $model = new class extends Model {
            protected string $index = 'test';
        };

        $builder = new Builder($model);
        $builder->whereBetween('price', [100, 500]);

        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('compileWheres');
        $method->setAccessible(true);

        $result = $method->invoke($builder);

        $this->assertArrayHasKey('bool', $result);
    }

    /** @test */
    public function it_compiles_where_not_clause()
    {
        $model = new class extends Model {
            protected string $index = 'test';
        };

        $builder = new Builder($model);
        $builder->whereNot('status', 'archived');

        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('compileWheres');
        $method->setAccessible(true);

        $result = $method->invoke($builder);

        $this->assertArrayHasKey('bool', $result);
    }

    /** @test */
    public function it_supports_method_chaining()
    {
        $model = new class extends Model {
            protected string $index = 'test';
        };

        $builder = new Builder($model);

        $result = $builder->where('in_stock', true)
            ->where('price', '>', 100)
            ->orderBy('price', 'asc')
            ->limit(10);

        $this->assertInstanceOf(Builder::class, $result);
    }

    /** @test */
    public function it_compiles_search_query()
    {
        $model = new class extends Model {
            protected string $index = 'test';
        };

        $builder = new Builder($model);
        $builder->search('laptop', ['name', 'description']);

        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('compileWheres');
        $method->setAccessible(true);

        $result = $method->invoke($builder);

        $this->assertArrayHasKey('bool', $result);
        $this->assertArrayHasKey('must', $result['bool']);
    }

    /** @test */
    public function it_compiles_aggregations()
    {
        $model = new class extends Model {
            protected string $index = 'test';
        };

        $builder = new Builder($model);
        $builder->termsAgg('categories', 'cat_id', 10);
        $builder->avgAgg('avg_price', 'price');

        $reflection = new \ReflectionClass($builder);
        $method = $reflection->getMethod('compileAggregations');
        $method->setAccessible(true);

        $result = $method->invoke($builder);

        $this->assertArrayHasKey('categories', $result);
        $this->assertArrayHasKey('avg_price', $result);
    }
}
