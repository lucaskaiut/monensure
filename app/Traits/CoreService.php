<?php

namespace App\Traits;

use Spatie\QueryBuilder\QueryBuilder;
use Illuminate\Database\Eloquent\Model;

trait CoreService
{

    private $model;

    public function create(array $data): Model
    {
        $model = $this->model::create($data);

        return $model;
    }

    public function list()
    {
        $models = QueryBuilder::for($this->model)->allowedFilters(['firstname'])->get();
 
        return $models;
    }

    public function find(string $id)
    {
        $model = $this->model::findOrFail($id);

        return $model;
    }

    public function update(string $id, array $data)
    {
        $model = $this->model::findOrFail($id);

        $model->update($data);

        return $model;
    }

    public function delete(string $id)
    {
        $model = $this->model::findOrFail($id);

        return $model->delete();
    }

    public function findOneBy(string $key, mixed $value): ?Model
    {
        $model = $this->model::where($key, $value)->first();

        return $model;
    }

    public function getModel()
    {
        return $this->model;
    }
}