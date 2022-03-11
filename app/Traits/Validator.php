<?php

namespace App\Traits;

trait Validator
{

    // comentario de teste do git

    private $rules;
    private $messages;

    public function __construct()
    {
        $this->setRules([]);

        $this->setMessages([]);
    }

    public function setRules(array $rules)
    {
        $this->rules = $rules;
    }

    public function getRules(): array
    {
        return $this->rules;
    }

    public function setMessages(array $messages)
    {
        $this->messages = $messages;
    }

    public function getMessages(): array
    {
        return $this->messages;
    }
}