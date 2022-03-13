<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordRecoveryMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public int $code;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(User $user, int $recoveryCode)
    {
        $this->user = $user;
        $this->code = $recoveryCode;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('mail.user.recoveryPassword')->subject("Recuperação de senha");
    }
}
