<?php

namespace App\Modules\Financial\Mcp\Tools\Suppliers;

use Laravel\Mcp\Request;

/**
 * Mesmo tratamento do prepareForValidation dos FormRequests de fornecedor:
 * remove a máscara do CPF/CNPJ antes de validar.
 */
trait NormalizesDocument
{
    protected function normalizeDocument(Request $request): void
    {
        if (filled($request->get('document'))) {
            $request->merge([
                'document' => (string) preg_replace('/\D+/', '', (string) $request->get('document')),
            ]);
        }
    }
}
