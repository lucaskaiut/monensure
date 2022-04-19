<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;

trait CoreService
{

    public $model;

    public function paginate(?int $items_per_page)
    {
        $models = $this->model::paginate($items_per_page ?? 10);

        return $models;
    }

    public function create(array $data): Model
    {
        $model = $this->model::create($data);

        return $model->refresh();
    }

    public function list()
    {
        $models = $this->model::all();

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

        return $model->refresh();
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