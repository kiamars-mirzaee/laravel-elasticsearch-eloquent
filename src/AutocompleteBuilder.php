<?php

namespace ElasticsearchEloquent;


class AutocompleteBuilder extends ElasticsearchQueryBuilder
{
    /**
     * Build autocomplete query with multi-strategy approach
     */
    public function autocomplete(string $field, string $searchTerm, array $options = []): self
    {
        $boost = $options['boost'] ?? [
            'prefix' => 3,
            'phrase' => 2,
            'bool' => 1
        ];

        $analyzer = $options['analyzer'] ?? 'persian_normalized_analyzer';

        // Prefix match on keyword field (highest priority)
        $this->shouldRaw([
            'prefix' => [
                "{$field}.kw" => [
                    'value' => $searchTerm,
                    'boost' => $boost['prefix'],
                ]
            ]
        ]);

        // Phrase prefix on normalized field (medium priority)
        $this->shouldRaw([
            'match_phrase_prefix' => [
                "{$field}.normalized" => [
                    'query' => $searchTerm,
                    'analyzer' => $analyzer,
                    'boost' => $boost['phrase'],
                ]
            ]
        ]);

        // Bool prefix on main field (lower priority)
        $this->shouldRaw([
            'match_bool_prefix' => [
                $field => [
                    'query' => $searchTerm,
                    'boost' => $boost['bool'],
                ]
            ]
        ]);

        return $this;
    }

    /**
     * Build suggestions with collapse
     */
    public function suggestions(
        string $field,
        string $searchTerm,
        int $maxResults = 10,
        array $options = []
    ): self {
        return $this
            ->autocomplete($field, $searchTerm, $options)
            ->select([$field])
            ->collapse("{$field}.kw")
            ->limit($maxResults);
    }
}
