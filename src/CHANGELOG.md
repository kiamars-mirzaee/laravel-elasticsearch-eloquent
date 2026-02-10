Changelog
All notable changes to this project will be documented in this file.
The format is based on Keep a Changelog,
and this project adheres to Semantic Versioning.
[Unreleased]
Added

Initial release
Eloquent-style query builder for Elasticsearch
Basic where clauses (where, orWhere)
Where In/Not In clauses (whereIn, whereNotIn, orWhereIn, orWhereNotIn)
Where Null/Not Null clauses (whereNull, whereNotNull, orWhereNull, orWhereNotNull)
Where Not clause (whereNot)
Where Between/Not Between clauses (whereBetween, whereNotBetween)
Nested object queries (whereNested)
Full-text search (search, matchPhrase)
Minimum score filtering (minScore)
Sorting (orderBy, orderByDesc, latest, oldest)
Limiting and pagination (limit, take, skip, offset, paginate)
Source filtering (select)
Aggregations (termsAgg, sumAgg, avgAgg, minAgg, maxAgg)
Count functionality
Model scopes support
Type casting
Laravel service provider
Comprehensive documentation
Usage examples
Unit tests

Features

PHP 8.1+ support
Laravel 10.x and 11.x support
Elasticsearch 8.x support
Method chaining
Collection results
Pagination support

[1.0.0] - 2025-12-05
Added

Initial stable release

[1.0.1] - 2026-01-10
Added

Raw Query capability to your Builder.


[1.1.0] - 2026-02-05
Added

IndexManager class to handle the logic and updated your Model to bridge the connection.
Define the schema and manage the index easily.
