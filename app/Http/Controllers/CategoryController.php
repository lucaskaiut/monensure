<?php

namespace App\Http\Controllers;

use App\Http\Resources\CategoryResource;
use App\Http\Validators\CategoryValidator;
use App\Interfaces\ControllerInterface;
use App\Services\CategoryService;
use App\Traits\CoreController;

class CategoryController extends Controller implements ControllerInterface
{
    use CoreController;

    public function __construct()
    {
        $this->service = app(CategoryService::class);
        $this->resource = CategoryResource::class;
        $this->requestValidator = new CategoryValidator();
    }
}
