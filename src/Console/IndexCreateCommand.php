<?php

namespace ElasticsearchEloquent\Console;

use Illuminate\Console\Command;

class IndexCreateCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'elasticsearch:index:create {model : The model class}';

    /**
     * The console command description.
     */
    protected $description = 'Create an Elasticsearch index for a model';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $modelClass = $this->argument('model');

        if (!class_exists($modelClass)) {
            $this->error("Model class {$modelClass} not found.");
            return self::FAILURE;
        }

        try {
            $model = new $modelClass;
            $index = $model->getIndex();

            if ($model::indexExists()) {
                $this->warn("Index '{$index}' already exists.");

                if (!$this->confirm('Do you want to delete and recreate it?')) {
                    return self::SUCCESS;
                }

                $model::deleteIndex();
                $this->info("Deleted existing index '{$index}'.");
            }

            $model::createIndex();
            $this->info("Successfully created index '{$index}'.");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to create index: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}
