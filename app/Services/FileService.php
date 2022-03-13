<?php

namespace App\Services;

use App\Exceptions\CustomValidationException;
use App\Interfaces\ServiceInterface;
use App\Traits\CoreService;
use Illuminate\Http\UploadedFile;
use Ramsey\Uuid\Uuid;

class FileService implements ServiceInterface
{
    use CoreService;

    public function upload(UploadedFile $file): string
    {
        throw_unless($file->isValid(), new CustomValidationException("Não foi possível realizar o upload"));

        throw_unless($file->getSize() / 1024 / 1024 < 5, new CustomValidationException("O arquivo deve ter no máximo 5MB"));

        $file_name = Uuid::uuid4() . '.' . $file->extension();

        $file->storeAs('files', $file_name);

        return 'files/' . $file_name;
    }
}