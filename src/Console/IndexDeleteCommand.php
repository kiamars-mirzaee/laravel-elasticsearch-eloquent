<?php

namespace ElasticsearchEloquent\Console;

use Illuminate\Console\Command;

class IndexDeleteCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'elasticsearch:index:delete {model : The model class}';

    /**
     * The console command description.
     */
    protected $description = 'Delete an Elasticsearch index';

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

            if (!$model::indexExists()) {
                $this->warn("Index '{$index}' does not exist.");
                return self::SUCCESS;
            }

            if (!$this->confirm("Are you sure you want to delete index '{$index}'?")) {
                return self::SUCCESS;
            }

            $model::deleteIndex();
            $this->info("Successfully deleted index '{$index}'.");

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to delete index: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}
