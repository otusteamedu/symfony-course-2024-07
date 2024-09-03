<?php

namespace App\Domain\Service;

use App\Infrastructure\Storage\LocalFileStorage;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileService
{
    public function __construct(private readonly LocalFileStorage $localFileStorage)
    {
    }

    public function storeUploadedFile(UploadedFile $uploadedFile): File
    {
        return $this->localFileStorage->storeUploadedFile($uploadedFile);
    }
}
