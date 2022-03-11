<?php

namespace App\Http;

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