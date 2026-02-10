<?php

namespace ElasticsearchEloquent\Contracts;

use ElasticsearchEloquent\Model;

interface IElasticsearchEloquent
{
    public function getElasticModelClass():Model;
    public function toSearchArray():void;
    public function deleteFromSearch():array;
}
