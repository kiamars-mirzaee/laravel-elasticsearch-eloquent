# Integrating Elasticsearch Eloquent into Your Laravel Project

This guide shows you how to integrate the Elasticsearch Eloquent package into your existing Laravel project located at `laravel-web/`.

## Directory Structure

Your final structure will look like:

```
laravel-web/
├── app/
├── bootstrap/
├── config/
├── database/
├── packages/                           # ← New directory
│   └── ElasticsearchEloquent/         # ← Your package
│       ├── src/
│       │   ├── Builder.php
│       │   ├── Model.php
│       │   ├── ElasticsearchServiceProvider.php
│       │   ├── Console/
│       │   │   ├── IndexCreateCommand.php
│       │   │   ├── IndexDeleteCommand.php
│       │   │   └── IndexListCommand.php
│       │   ├── Jobs/
│       │   │   └── SyncWithElasticsearch.php
│       │   └── Traits/
│       │       └── Searchable.php
│       ├── config/
│       │   └── elasticsearch.php
│       ├── composer.json
│       ├── README.md
│       └── docs/
├── public/
├── resources/
├── routes/
├── storage/
├── tests/
├── vendor/
└── composer.json                       # ← Modify this
```

## Step-by-Step Integration

### Step 1: Create Package Directory

```bash
cd laravel-web
mkdir -p packages/ElasticsearchEloquent
```

### Step 2: Copy Your Package Files

Copy all your library files to the new package directory:

```bash
# Assuming your lib files are in a directory called 'lib'
cp lib/Builder.php packages/ElasticsearchEloquent/src/
cp lib/Model.php packages/ElasticsearchEloquent/src/
cp lib/IndexManager.php packages/ElasticsearchEloquent/src/  # If you have this
cp -r lib/Traits packages/ElasticsearchEloquent/src/
cp -r lib/job packages/ElasticsearchEloquent/src/Jobs  # Rename 'job' to 'Jobs'

# Copy documentation
cp lib/readme.md packages/ElasticsearchEloquent/README.md
cp -r lib/docs packages/ElasticsearchEloquent/
cp -r lib/example packages/ElasticsearchEloquent/examples
```

### Step 3: Create Package composer.json

Create `packages/ElasticsearchEloquent/composer.json`:

```json
{
    "name": "yourname/elasticsearch-eloquent",
    "description": "Eloquent-style query builder for Elasticsearch in Laravel",
    "type": "library",
    "license": "MIT",
    "authors": [
        {
            "name": "Your Name",
            "email": "your.email@example.com"
        }
    ],
    "require": {
        "php": "^8.1",
        "illuminate/support": "^10.0|^11.0",
        "illuminate/database": "^10.0|^11.0",
        "elasticsearch/elasticsearch": "^8.0"
    },
    "autoload": {
        "psr-4": {
            "ElasticsearchEloquent\\": "src/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "ElasticsearchEloquent\\ElasticsearchServiceProvider"
            ]
        }
    }
}
```

### Step 4: Update Root composer.json

Edit `laravel-web/composer.json` and add:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "./packages/ElasticsearchEloquent",
            "options": {
                "symlink": true
            }
        }
    ],
    "require": {
        "yourname/elasticsearch-eloquent": "@dev"
    }
}
```

### Step 5: Install the Package

```bash
cd laravel-web
composer update yourname/elasticsearch-eloquent
```

You should see output like:
```
  - Installing yourname/elasticsearch-eloquent (dev-main): Symlinking from ./packages/ElasticsearchEloquent
```

### Step 6: Create Service Provider

Create `packages/ElasticsearchEloquent/src/ElasticsearchServiceProvider.php`:

```php
<?php

namespace ElasticsearchEloquent;

use Elasticsearch\ClientBuilder;
use Illuminate\Support\ServiceProvider;

class ElasticsearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/elasticsearch.php',
            'elasticsearch'
        );

        $this->app->singleton('elasticsearch', function ($app) {
            $config = $app['config']['elasticsearch'];
            
            $clientBuilder = ClientBuilder::create()
                ->setHosts($config['hosts']);

            if (!empty($config['username']) && !empty($config['password'])) {
                $clientBuilder->setBasicAuthentication(
                    $config['username'],
                    $config['password']
                );
            }

            return $clientBuilder->build();
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/elasticsearch.php' => config_path('elasticsearch.php'),
            ], 'elasticsearch-config');

            $this->commands([
                Console\IndexCreateCommand::class,
                Console\IndexDeleteCommand::class,
                Console\IndexListCommand::class,
            ]);
        }
    }
}
```

### Step 7: Create Configuration File

Create `packages/ElasticsearchEloquent/config/elasticsearch.php`:

```php
<?php

return [
    'hosts' => [
        env('ELASTICSEARCH_HOST', 'localhost:9200'),
    ],

    'username' => env('ELASTICSEARCH_USERNAME'),
    'password' => env('ELASTICSEARCH_PASSWORD'),

    'default_settings' => [
        'number_of_shards' => env('ELASTICSEARCH_SHARDS', 1),
        'number_of_replicas' => env('ELASTICSEARCH_REPLICAS', 0),
    ],

    'queue' => [
        'enabled' => env('ELASTICSEARCH_QUEUE_ENABLED', true),
        'connection' => env('ELASTICSEARCH_QUEUE_CONNECTION', 'default'),
        'queue' => env('ELASTICSEARCH_QUEUE_NAME', 'elasticsearch'),
    ],
];
```

### Step 8: Publish Configuration

```bash
php artisan vendor:publish --tag=elasticsearch-config
```

This creates `config/elasticsearch.php` in your Laravel app.

### Step 9: Update .env

Add to `laravel-web/.env`:

```env
ELASTICSEARCH_HOST=localhost:9200
ELASTICSEARCH_USERNAME=
ELASTICSEARCH_PASSWORD=
ELASTICSEARCH_QUEUE_ENABLED=true
```

### Step 10: Create Your First Search Model

Create `app/SearchModels/Product.php`:

```php
<?php

namespace App\SearchModels;

use ElasticsearchEloquent\Model;

class Product extends Model
{
    protected string $index = 'products';
    
    protected array $mapping = [
        'properties' => [
            'id' => ['type' => 'keyword'],
            'name' => ['type' => 'text'],
            'description' => ['type' => 'text'],
            'price' => ['type' => 'float'],
            'in_stock' => ['type' => 'boolean'],
            'category_id' => ['type' => 'integer'],
            'created_at' => ['type' => 'date'],
        ]
    ];
    
    protected array $casts = [
        'price' => 'float',
        'in_stock' => 'boolean',
    ];
}
```

### Step 11: Add Searchable Trait to Eloquent Model

Edit `app/Models/Product.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use ElasticsearchEloquent\Traits\Searchable;

class Product extends Model
{
    use Searchable;
    
    protected static $searchableAs = \App\SearchModels\Product::class;
    
    protected $fillable = [
        'name', 'description', 'price', 'in_stock', 'category_id'
    ];

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => (float) $this->price,
            'in_stock' => (bool) $this->in_stock,
            'category_id' => $this->category_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
```

### Step 12: Create the Index

```bash
php artisan elasticsearch:index:create "App\SearchModels\Product"
```

### Step 13: Test It

Create a test route in `routes/web.php`:

```php
use App\SearchModels\Product;

Route::get('/test-search', function () {
    // Search
    $results = Product::search('laptop')
        ->where('in_stock', true)
        ->get();
    
    return $results;
});
```

## Usage Examples

### Basic Queries

```php
use App\SearchModels\Product;

// Simple search
$products = Product::search('laptop')->get();

// Where clauses
$products = Product::where('in_stock', true)
    ->where('price', '>', 100)
    ->get();

// Pagination
$products = Product::search('phone')
    ->where('in_stock', true)
    ->orderBy('price', 'asc')
    ->paginate(20);
```

### In Controllers

```php
<?php

namespace App\Http\Controllers;

use App\SearchModels\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function search(Request $request)
    {
        $query = Product::query();

        if ($search = $request->input('q')) {
            $query->search($search, ['name', 'description']);
        }

        if ($minPrice = $request->input('min_price')) {
            $query->where('price', '>=', $minPrice);
        }

        if ($maxPrice = $request->input('max_price')) {
            $query->where('price', '<=', $maxPrice);
        }

        if ($request->input('in_stock')) {
            $query->where('in_stock', true);
        }

        return $query->paginate(20);
    }
}
```

### Auto-Syncing

Once you've added the `Searchable` trait, your models will automatically sync:

```php
// This will automatically index to Elasticsearch
$product = Product::create([
    'name' => 'Gaming Laptop',
    'price' => 1299.99,
    'in_stock' => true,
]);

// This will update in Elasticsearch
$product->update(['price' => 999.99]);

// This will delete from Elasticsearch
$product->delete();
```

## Testing the Integration

### 1. Check Elasticsearch Connection

```bash
php artisan elasticsearch:index:list
```

### 2. Create Some Test Data

```php
php artisan tinker

Product::create([
    'name' => 'Test Laptop',
    'description' => 'A great laptop for testing',
    'price' => 999.99,
    'in_stock' => true,
    'category_id' => 1
]);
```

### 3. Search for It

```php
$results = \App\SearchModels\Product::search('laptop')->get();
dd($results);
```

## Common Issues & Solutions

### Issue: Class not found

**Solution:** Run composer dump-autoload:
```bash
composer dump-autoload
```

### Issue: Config not found

**Solution:** Clear config cache:
```bash
php artisan config:clear
php artisan cache:clear
```

### Issue: Queue not processing

**Solution:** Start queue worker:
```bash
php artisan queue:work
```

Or disable queue temporarily:
```env
ELASTICSEARCH_QUEUE_ENABLED=false
```

### Issue: Symlink not created

**Solution:** Manually create symlink:
```bash
cd vendor
ln -s ../packages/ElasticsearchEloquent yourname
```

## Next Steps

1. **Add More Models**: Create search models for other entities
2. **Customize Mappings**: Fine-tune your Elasticsearch mappings
3. **Add Aggregations**: Use aggregations for analytics
4. **Set Up Monitoring**: Monitor Elasticsearch performance
5. **Production Setup**: Configure replicas, backups, etc.

## File Checklist

Make sure you have these files in place:

- [ ] `packages/ElasticsearchEloquent/src/Model.php`
- [ ] `packages/ElasticsearchEloquent/src/Builder.php`
- [ ] `packages/ElasticsearchEloquent/src/ElasticsearchServiceProvider.php`
- [ ] `packages/ElasticsearchEloquent/src/Traits/Searchable.php`
- [ ] `packages/ElasticsearchEloquent/src/Jobs/SyncWithElasticsearch.php`
- [ ] `packages/ElasticsearchEloquent/src/Console/` (all command files)
- [ ] `packages/ElasticsearchEloquent/config/elasticsearch.php`
- [ ] `packages/ElasticsearchEloquent/composer.json`
- [ ] Root `composer.json` updated with repository and require
- [ ] `.env` updated with Elasticsearch settings
- [ ] `config/elasticsearch.php` published

## Support

If you encounter issues:

1. Check the [Troubleshooting Guide](installation.md#troubleshooting)
2. Review Elasticsearch logs
3. Check Laravel logs in `storage/logs/`
4. Verify queue is running if using async sync
