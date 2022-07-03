<?php

namespace App\Http\Controllers;

use App\Exceptions\CustomValidationException;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\RegisterUserRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\UserLoginRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses;
use App\Http\Validators\UserValidator;
use App\Interfaces\ControllerInterface;
use App\Services\GroupService;
use App\Services\UserService;
use App\Traits\CoreController;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
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

        $this->authorizeResource($this->service->model, 'id');
    }

    public function register(RegisterUserRequest $request)
    {
        return DB::transaction(function() use ($request) {
            $group_name = $request->group_name;
            
            $group = (new GroupService)->create(['name' => $group_name]);

            $user = $group->users()->create($request->except('group_name', 'password_confirmation'));

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

            return Responses::created(['token' => $token->plainTextToken, 'user' => new UserResource($user)]);
        });
    }

    public function forgotPassword(ForgotPasswordRequest $request)
    {
        return DB::transaction(function() use ($request) {
            $user = $this->service->findOneBy('email', $request->email);

            throw_unless($user, new ModelNotFoundException("Usuário não encontrado"));

            $code = $this->service->generateRecoveryCode($user);

            $this->service->sendRecoveryMail($user, $code);

            return Responses::ok(['user' => new UserResource($user)]);
        });
    }

    public function resetPassword(ResetPasswordRequest $request)
    {
        return DB::transaction(function() use ($request){
            $user = $this->service->getUserByRecoveryTokenOrFail($request->token, $request->email);

            $user = $this->service->updateUserPassword($user, $request->password);

            $this->service->deleteToken($request->token, $request->email);

            $content = new $this->resource($user);

            return Responses::updated($content);
        });
    }

    public function changePassword(string $id, ChangePasswordRequest $request)
    {
        return DB::transaction(function() use ($id, $request){
            $user = $this->service->find($id);

            throw_unless($user, new ModelNotFoundException("Usuário não encontrado"));

            throw_unless(Hash::check($request->password, $user->password), new CustomValidationException("Senha inválida"));

            $user = $this->service->updateUserPassword($user, $request->new_password);

            $content = new $this->resource($user);

            return Responses::updated($content);
        });
    }

    public function validateToken(Request $request)
    {
        return DB::transaction(function() use ($request){
            $user = $this->service->getUserByRecoveryTokenOrFail($request->token, $request->email);

            throw_unless($user, new ModelNotFoundException("Código inválido ou expirado"));

            return Responses::ok(new UserResource($user));
        });
    }

    public function me()
    {
        return DB::transaction(function(){
            $user = auth('sanctum')->user();

            throw_unless($user, new ModelNotFoundException("Usuário não encontrado"));

            return Responses::ok(new UserResource($user));
        });
    }
}
