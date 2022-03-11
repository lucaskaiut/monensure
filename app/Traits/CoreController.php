<?php

namespace App\Traits;

use App\Exceptions\CustomValidationException;
use App\Http\Responses;
use App\Http\Validators\RequestValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

trait CoreController
{
    private $requestValidator;
    private $service;
    private $resource;

    public function store(Request $request)
    {
        $data = $this->validateOrFail($request->all());

        return DB::transaction(function () use ($data) {
            $model = $this->service->create($data);

            $content = new $this->resource($model);

            return Responses::created($content);
        });
    }

    public function index()
    {
        $models = $this->service->list();

        $content = $this->resource::collection($models);

        return Responses::ok($content);
    }

    public function show(string $id)
    {
        $model = $this->service->find($id);

        $content = new $this->resource($model);

        return Responses::ok($content);
    }

    public function update(string $id, Request $request)
    {
        $data = $this->validateOrFail($request->all());

        return DB::transaction(function () use ($id, $data){
            $model = $this->service->update($id, $data);

            $content = new $this->resource($model);

            return Responses::updated($content);
        });
    }

    public function destroy(string $id)
    {
        return DB::transaction(function () use ($id){
            $this->service->delete($id);

            return Responses::deleted();
        });
    }

    private function validateOrFail(array $data): array
    {
        $requestValidator = $this->getRequestValidator();

        $rules = $requestValidator->getRules();
        $messages = $requestValidator->getMessages();

        $validator = Validator::make($data, $rules, $messages);

        if($validator->fails())
            $this->throwsValidationErrorsResponse($validator->errors()->getMessages());

        return $rules ? $validator->validated() : $data;
    }

    private function throwsValidationErrorsResponse(array $errors)
    {
        $message = $this->getValidationErrorMessage($errors);

        throw new CustomValidationException($message);
    }

    private function getValidationErrorMessage(array $errors): string
    {
        $message = '';

        foreach($errors as $error){
            $message .= "{$error[0]} ";
        }

        return trim($message);
    }

    private function getRequestValidator()
    {
        return $this->requestValidator ?? new RequestValidator();
    }

}