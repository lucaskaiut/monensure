<?php

namespace App\Exceptions;

use Exception;

class CustomValidationException extends Exception
{
    protected $code = 422;
}
