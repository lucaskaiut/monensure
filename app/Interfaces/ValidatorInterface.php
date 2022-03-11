<?php 

namespace App\Interfaces;

interface ValidatorInterface
{

    public function getRules(): array;
    public function setRules(array $rules);
    public function getMessages(): array;
    public function setMessages(array $messages);

}