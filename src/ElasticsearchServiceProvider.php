<?php

namespace ElasticsearchEloquent;

use Elastic\Elasticsearch\ClientBuilder;
use Illuminate\Support\ServiceProvider;

class ElasticsearchServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge config
        $this->mergeConfigFrom(
            __DIR__.'/../config/elasticsearch.php',
            'elasticsearch'
        );

        // Register Elasticsearch client as singleton
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

            if (!empty($config['api_key'])) {
                $clientBuilder->setApiKey($config['api_key']);
            }

            if (!empty($config['cloud_id'])) {
                $clientBuilder->setElasticCloudId($config['cloud_id']);
            }

            return $clientBuilder->build();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish config
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/elasticsearch.php' => config_path('elasticsearch.php'),
            ], 'elasticsearch-config');
        }

        // Register commands if running in console
        if ($this->app->runningInConsole()) {
            $this->commands([
                Console\IndexCreateCommand::class,
                Console\IndexDeleteCommand::class,
                Console\IndexListCommand::class,
            ]);
        }
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return ['elasticsearch'];
    }
}
