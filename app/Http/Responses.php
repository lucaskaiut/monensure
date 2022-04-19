<?php

namespace App\Http;

use Illuminate\Pagination\LengthAwarePaginator;

class Responses
{

    public static function created($content)
    {
        $response = [
            'message' => 'Registro criado com sucesso',
            'data' => $content
        ];

        return response()->json($response, 201);
    }

    public static function updated($content)
    {
        $response = [
            'message' => 'Registro atualizado com sucesso',
            'data' => $content
        ];

        return response()->json($response, 202);
    }

    public static function deleted()
    {
        return response()->json(['data' => [], 'message' => 'Registro apagado com successo'], 202);
    }

    public static function ok($content)
    {
        $response = [
            'message' => 'Sucesso',
            'data' => $content
        ];

        if(is_a($content->resource, LengthAwarePaginator::class)){
            $response['pagination'] = [
                'per_page' => $content->resource->perPage(),
                'total' => $content->resource->total(),
                'last_page' => $content->resource->lastPage(),
                'current_page' => $content->resource->currentPage()
            ];
        } 

        return response()->json($response);
    }

    public static function error($message, $code)
    {
        $response = [
            'message' => $message ?? 'Algo deu errado, tente novamente mais tarde',
        ];

        return response()->json($response, $code);
    }
}