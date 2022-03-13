<?php

namespace App\Services;

use App\Interfaces\ServiceInterface;
use App\Models\Category;
use App\Traits\CoreService;

class CategoryService implements ServiceInterface
{
    use CoreService;

    public function __construct()
    {
        $this->model = Category::class;
    }
}