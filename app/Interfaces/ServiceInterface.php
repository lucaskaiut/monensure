<?php

namespace App\Interfaces;

interface ServiceInterface
{
    public function create(array $data);

    public function list();

    public function find(string $id);

    public function update(string $id, array $data);

    public function delete(string $id);
}