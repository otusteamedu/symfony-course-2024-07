<?php

namespace App\Controller\Web\UpdateUserAvatarLink\v1;

use App\Domain\Entity\User;
use App\Domain\Service\FileService;
use App\Domain\Service\UserService;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class Manager
{
    public function __construct(
        private readonly FileService $fileService,
        private readonly UserService $userService,
    ) {
    }

    public function updateUserAvatarLink(User $user, UploadedFile $file): void
    {
        $this->fileService->storeUploadedFile($file);
        $this->userService->updateAvatarLink($user, $file->getRealPath());
    }
}
