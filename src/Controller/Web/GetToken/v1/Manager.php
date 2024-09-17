<?php

namespace App\Controller\Web\GetToken\v1;

use App\Application\Security\AuthService;
use App\Controller\Exception\AccessDeniedException;
use App\Controller\Exception\UnauthorizedException;
use Symfony\Component\HttpFoundation\Request;

class Manager
{
    public function __construct(private readonly AuthService $authService)
    {
    }

    /**
     * @throws AccessDeniedException
     * @throws UnauthorizedException
     */
    public function getToken(Request $request): string
    {
        $user = $request->getUser();
        $password = $request->getPassword();
        if (!$user || !$password) {
            throw new UnauthorizedException();
        }
        if (!$this->authService->isCredentialsValid($user, $password)) {
            throw new AccessDeniedException();
        }

        return $this->authService->getToken($user);
    }
}
