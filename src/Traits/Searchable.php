<?php

namespace ElasticsearchEloquent\Traits;


use ElasticsearchEloquent\Jobs\SyncWithElasticsearch;
use Illuminate\Database\Eloquent\Model;

trait Searchable
{
    /**
     * Boot the trait and register model observers.
     */
    public static function bootSearchable(): void
    {
        // Handle single create/update
        static::saved(function (Model $model) {
            $model->syncToSearch();
        });

        // Handle single delete
        static::deleted(function (Model $model) {
            $model->deleteFromSearch();
        });
    }

    /**
     * Dispatch the sync job to the queue.
     */
    public function syncToSearch(): void
    {
        // Senior Tip: Always use Queues for external API calls to avoid
        // slowing down the user's request.
        SyncWithElasticsearch::dispatch($this, 'update');
    }

    public function deleteFromSearch(): void
    {
        SyncWithElasticsearch::dispatch($this, 'delete');
    }

    /**
     * Define which data should actually go to Elasticsearch.
     * Overwrite this in your model to control the "Elastic schema".
     */
    public function toSearchArray(): array
    {
        return $this->toArray();
    }
}
