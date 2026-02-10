<?php
namespace ElasticsearchEloquent;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\Exception\ClientResponseException;
use Elastic\Elasticsearch\Exception\MissingParameterException;
use Elastic\Elasticsearch\Response\Elasticsearch;

class IndexManager
{
    /**
     * The model instance.
     */
    protected Model $model;

    /**
     * The Elasticsearch client.
     */
    protected Client $client;

    /**
     * Create a new IndexManager instance.
     */
    public function __construct(Model $model)
    {
        $this->model = $model;
        $this->client = $model::getClient();
    }

    /**
     * Create the index with mappings and settings defined in the model.
     * * @param array $options Additional options to pass to the create method
     * @return array
     */
    public function create(array $options = []): array
    {
        $params = [
            'index' => $this->model->getIndex(),
            'body' => array_filter([
                'settings' => $this->model->settings(),
                'mappings' => $this->model->mapping(),
            ]),
        ];

        // Merge any runtime options
        $params = array_merge($params, $options);

        return $this->client->indices()->create($params)->asArray();
    }

    /**
     * Delete the index if it exists.
     *
     * @return array
     */
    public function delete(): array
    {
        return $this->client->indices()->delete([
            'index' => $this->model->getIndex()
        ])->asArray();
    }

    /**
     * Check if the index exists.
     *
     * @return bool
     */
    public function exists(): bool
    {
        $response = $this->client->indices()->exists([
            'index' => $this->model->getIndex()
        ]);

        // elastic/elasticsearch v8 returns a Response object, use asBool()
        return $response->asBool();
    }

    /**
     * Update the mapping for the index.
     * Note: You generally cannot update existing field types, only add new ones.
     *
     * @return array
     */
    public function putMapping(): array
    {
        $params = [
            'index' => $this->model->getIndex(),
            'body' => $this->model->mapping(),
        ];

        return $this->client->indices()->putMapping($params)->asArray();
    }

    /**
     * Force a refresh of the index.
     * Useful during testing to make documents immediately available for search.
     *
     * @return array
     */
    public function refresh(): array
    {
        return $this->client->indices()->refresh([
            'index' => $this->model->getIndex()
        ])->asArray();
    }

    /**
     * Get the current mapping from Elasticsearch.
     *
     * @return array
     */
    public function getMapping(): array
    {
        return $this->client->indices()->getMapping([
            'index' => $this->model->getIndex()
        ])->asArray();
    }

    /**
     * Get the current settings from Elasticsearch.
     *
     * @return array
     */
    public function getSettings(): array
    {
        return $this->client->indices()->getSettings([
            'index' => $this->model->getIndex()
        ])->asArray();
    }
}
