<?php

namespace App\Services;

use App\Interfaces\ServiceInterface;
use App\Mail\PasswordRecoveryMail;
use App\Models\PasswordReset;
use App\Models\User;
use App\Traits\CoreService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class UserService implements ServiceInterface
{
    use CoreService;

    public function __construct()
    {
        $this->model = User::class;
    }

    public function sendRecoveryMail(User $user, int $code)
    {
        Mail::to($user->email)->send(new PasswordRecoveryMail($user, $code));
    }

    public function generateRecoveryCode(User $user): int
    {
        $code = random_int(100000, 600000);

        PasswordReset::create(['email' => $user->email, 'token' => $code]);

        return $code;
    }

    public function getUserByRecoveryTokenOrFail(int $token, string $email): User
    {
        $passwordReset = PasswordReset::where('token', $token)->where('email', $email)->first();

        throw_unless($passwordReset, new UnprocessableEntityHttpException("Token inválido ou expirado"));

        $now = Carbon::now();

        $tokenValidThrough = Carbon::createFromFormat('Y-m-d H:i:s', $passwordReset->created_at->format('Y-m-d H:i:s'));

        throw_if($tokenValidThrough->addHours() < $now, new UnprocessableEntityHttpException("Token inválido ou expirado"));

        throw_unless($passwordReset, new UnprocessableEntityHttpException("Token inválido ou expirado"));

        $user = User::where('email', $passwordReset->email)->first();

        return $user;
    }

    public function deleteToken(string $token, string $email)
    {
        PasswordReset::where('token', $token)->where('email', $email)->delete();
    }

    public function updateUserPassword(User $user, string $password)
    {
        $user->update(['password' => Hash::make($password)]);

        return $user->fresh();
    }
}