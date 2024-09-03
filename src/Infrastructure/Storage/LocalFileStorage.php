<?php

namespace App\Infrastructure\Storage;

use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class LocalFileStorage
{
    public function storeUploadedFile(UploadedFile $uploadedFile): File
    {
        $fileName = sprintf('%s.%s', uniqid('image', true), $uploadedFile->getClientOriginalExtension());

        return $uploadedFile->move('upload', $fileName);
    }
}
