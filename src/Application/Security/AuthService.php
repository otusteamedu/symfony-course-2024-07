<?php

namespace App\Application\Security;

use App\Domain\Service\UserService;
use Lexik\Bundle\JWTAuthenticationBundle\Encoder\JWTEncoderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Exception\JWTEncodeFailureException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AuthService
{
    public function __construct(
        private readonly UserService $userService,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JWTEncoderInterface $jwtEncoder,
        private readonly int $tokenTTL,
    ) {
    }

    public function isCredentialsValid(string $login, string $password): bool
    {
        $user = $this->userService->findUserByLogin($login);
        if ($user === null) {
            return false;
        }

        return $this->passwordHasher->isPasswordValid($user, $password);
    }

    /**
     * @throws JWTEncodeFailureException
     */
    public function getToken(string $login): string
    {
        $user = $this->userService->findUserByLogin($login);
        $refreshToken = $this->userService->updateUserToken($login);
        $tokenData = [
            'username' => $login,
            'roles' => $user?->getRoles() ?? [],
            'exp' => time(),
            'refresh_token' => $refreshToken,
        ];

        return $this->jwtEncoder->encode($tokenData);
    }
}
