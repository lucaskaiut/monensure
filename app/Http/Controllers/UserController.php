<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterUserRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses;
use App\Http\Validators\UserValidator;
use App\Interfaces\ControllerInterface;
use App\Services\GroupService;
use App\Services\UserService;
use App\Traits\CoreController;
use Illuminate\Support\Facades\DB;

class UserController extends Controller implements ControllerInterface
{
    use CoreController;

    public function __construct()
    {
        $this->service = app(UserService::class);
        $this->resource = UserResource::class;
        $this->requestValidator = new UserValidator();
    }

    public function register(RegisterUserRequest $request)
    {
        return DB::transaction(function() use ($request) {
            $group_name = $request->group_name;
            $group = (new GroupService)->create(['name' => $group_name]);

            $user = $this->service->create(array_merge(['group_id' => $group->id], $request->except('group_name', 'password_confirmation')));

            $content = new $this->resource($user);

            return Responses::created($content);
        });
    }
}
