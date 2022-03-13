<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterUserRequest;
use App\Http\Requests\UserLoginRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses;
use App\Http\Validators\UserValidator;
use App\Interfaces\ControllerInterface;
use App\Services\GroupService;
use App\Services\UserService;
use App\Traits\CoreController;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

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

    public function login(UserLoginRequest $request)
    {
        return DB::transaction(function() use ($request){
            $user = $this->service->findOneBy('email', $request->email);

            throw_unless($user, new ModelNotFoundException("Usuário não encontrado"));

            throw_unless(Hash::check($request->password, $user->password), new ModelNotFoundException("Usuário não encontrado"));

            $token = $user->createToken('access_token', []);

            return Responses::created(['token' => $token->plainTextToken]);
        });
    }
}
