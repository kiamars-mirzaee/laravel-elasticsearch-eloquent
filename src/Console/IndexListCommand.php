<?php

namespace ElasticsearchEloquent\Console;
use Elastic\Elasticsearch\Client;
use Illuminate\Console\Command;

class IndexListCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'elasticsearch:index:list';

    /**
     * The console command description.
     */
    protected $description = 'List all Elasticsearch indices';

    /**
     * Execute the console command.
     */
    public function handle(Client $client): int
    {
        try {
            $indices = $client->cat()->indices(['format' => 'json'])->asArray();

            if (empty($indices)) {
                $this->info('No indices found.');
                return self::SUCCESS;
            }

            $headers = ['Index', 'Health', 'Status', 'Docs Count', 'Store Size'];
            $rows = [];

            foreach ($indices as $index) {
                $rows[] = [
                    $index['index'] ?? '',
                    $index['health'] ?? '',
                    $index['status'] ?? '',
                    $index['docs.count'] ?? '0',
                    $index['store.size'] ?? '0',
                ];
            }

            $this->table($headers, $rows);

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to list indices: {$e->getMessage()}");
            return self::FAILURE;
        }
    }
}
