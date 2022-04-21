<?php

namespace App\Http;

use Illuminate\Pagination\LengthAwarePaginator;

class Responses
{

    public static function created($content, array $additional = [])
    {
        $response = [
            'message' => 'Registro criado com sucesso',
            'data' => $content
        ];

        if(sizeof($additional)){
            $response['additional'] = $additional;
        }

        return response()->json($response, 201);
    }

    public static function updated($content, array $additional = [])
    {
        $response = [
            'message' => 'Registro atualizado com sucesso',
            'data' => $content
        ];

        if(sizeof($additional)){
            $response['additional'] = $additional;
        }

        return response()->json($response, 202);
    }

    public static function deleted(array $additional = [])
    {
        $response = ['data' => [], 'message' => 'Registro apagado com successo'];

        if(sizeof($additional)){
            $response['additional'] = $additional;
        }

        return response()->json($response, 202);
    }

    public static function ok($content, array $additional = [])
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

        if(sizeof($additional)){
            $response['additional'] = $additional;
        }

        return response()->json($response);
    }

    public static function error($message, $code, array $additional = [])
    {
        $response = [
            'message' => $message ?? 'Algo deu errado, tente novamente mais tarde',
        ];

        if(sizeof($additional)){
            $response['additional'] = $additional;
        }

        return response()->json($response, $code);
    }
}