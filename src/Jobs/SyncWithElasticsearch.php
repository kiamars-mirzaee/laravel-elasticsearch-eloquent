<?php

namespace ElasticsearchEloquent\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncWithElasticsearch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected mixed $model,
        protected string $action = 'update'
    ) {}

    public function handle(): void
    {
        // Map the Eloquent Model to your Elasticsearch Model
        $elasticModelClass = $this->getElasticModelClass();

        if ($this->action === 'delete') {
            $elasticModelClass::query()->where('_id', $this->model->id)->delete();
            return;
        }

        // Logic for Create/Update
        $elasticModel = new $elasticModelClass($this->model->toSearchArray());
        $elasticModel->exists = true; // Tell your lib it's an update if ID exists
        $elasticModel->save();
    }

    protected function getElasticModelClass(): string
    {
        // Example mapping: App\Models\Product -> App\Elastic\Product
        return str_replace('Models', 'Elastic', get_class($this->model));
    }
}
