<?php

namespace App\Http\Validators;

use App\Interfaces\ValidatorInterface;
use App\Traits\Validator;

final class RequestValidator implements ValidatorInterface
{
    use Validator;

    public function __construct()
    {

        $rules = [];

        $messages = [];

        $this->setRules($rules);

        $this->setMessages($messages);
    }
}