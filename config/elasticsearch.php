<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Elasticsearch Hosts
    |--------------------------------------------------------------------------
    |
    | The Elasticsearch hosts to connect to. You can specify multiple hosts
    | for high availability.
    |
    */

    'hosts' => [
        env('ELASTICSEARCH_HOST', 'localhost:9200'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    |
    | Configure authentication credentials for Elasticsearch.
    | You can use either basic auth (username/password) or API key.
    |
    */

    'username' => env('ELASTICSEARCH_USERNAME'),
    'password' => env('ELASTICSEARCH_PASSWORD'),
    'api_key' => env('ELASTICSEARCH_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Elastic Cloud Configuration
    |--------------------------------------------------------------------------
    |
    | If you're using Elastic Cloud, you can specify your Cloud ID here.
    |
    */

    'cloud_id' => env('ELASTICSEARCH_CLOUD_ID'),

    /*
    |--------------------------------------------------------------------------
    | Default Index Settings
    |--------------------------------------------------------------------------
    |
    | Default settings to apply when creating new indices.
    |
    */

    'default_settings' => [
        'number_of_shards' => env('ELASTICSEARCH_SHARDS', 1),
        'number_of_replicas' => env('ELASTICSEARCH_REPLICAS', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for syncing data to Elasticsearch via queues.
    |
    */

    'queue' => [
        'enabled' => env('ELASTICSEARCH_QUEUE_ENABLED', true),
        'connection' => env('ELASTICSEARCH_QUEUE_CONNECTION', 'default'),
        'queue' => env('ELASTICSEARCH_QUEUE_NAME', 'elasticsearch'),
    ],

];
