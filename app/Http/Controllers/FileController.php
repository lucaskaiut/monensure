<?php

namespace App\Http\Controllers;

use App\Exceptions\CustomValidationException;
use App\Http\Responses;
use App\Services\FileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    public function __construct()
    {
        $this->service = app(FileService::class);
    }

    public function upload(Request $request)
    {
        throw_unless($request->hasFile('file'), new CustomValidationException("O arquivo é obrigatório"));

        $file_path = $this->service->upload($request->file);

        return Responses::created(['file' => asset('storage/' . $file_path)]);
    }
}
