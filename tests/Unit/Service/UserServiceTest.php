<?php

namespace UnitTests\Service;

use App\Domain\Entity\EmailUser;
use App\Domain\Entity\PhoneUser;
use App\Domain\Model\CreateUserModel;
use App\Domain\Service\UserService;
use App\Domain\ValueObject\CommunicationChannelEnum;
use App\Infrastructure\Repository\UserRepository;
use Codeception\Test\Unit;
use Generator;
use Mockery;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserServiceTest extends Unit
{
    private const PASSWORD_HASH = 'my_hash';
    private const DEFAULT_AGE = 18;
    private const DEFAULT_IS_ACTIVE = true;
    private const DEFAULT_ROLES = ['ROLE_USER'];

    /**
     * @dataProvider createTestCases
     */
    public function testCreate(CreateUserModel $createUserModel, array $expectedData): void
    {
        $userService = $this->prepareUserService();

        $user = $userService->create($createUserModel);

        $actualData = [
            'class' => get_class($user),
            'login' => $user->getLogin(),
            'email' => ($user instanceof EmailUser) ? $user->getEmail() : null,
            'phone' => ($user instanceof PhoneUser) ? $user->getPhone() : null,
            'passwordHash' => $user->getPassword(),
            'age' => $user->getAge(),
            'isActive' => $user->isActive(),
            'roles' => $user->getRoles(),
        ];
        static::assertSame($expectedData, $actualData);
    }

    protected function createTestCases(): Generator
    {
        yield [
            new CreateUserModel(
                'someLogin',
                'somePhone',
                CommunicationChannelEnum::Phone
            ),
            [
                'class' => PhoneUser::class,
                'login' => 'someLogin',
                'email' => null,
                'phone' => 'somePhone',
                'passwordHash' => self::PASSWORD_HASH,
                'age' => self::DEFAULT_AGE,
                'isActive' => self::DEFAULT_IS_ACTIVE,
                'roles' => self::DEFAULT_ROLES,
            ]
        ];

        yield [
            new CreateUserModel(
                'otherLogin',
                'someEmail',
                CommunicationChannelEnum::Email
            ),
            [
                'class' => EmailUser::class,
                'login' => 'otherLogin',
                'email' => 'someEmail',
                'phone' => null,
                'passwordHash' => self::PASSWORD_HASH,
                'age' => self::DEFAULT_AGE,
                'isActive' => self::DEFAULT_IS_ACTIVE,
                'roles' => self::DEFAULT_ROLES,
            ]
        ];
    }

    private function prepareUserService(): UserService
    {
        $userRepository = Mockery::mock(UserRepository::class);
        $userRepository->shouldReceive('create')->with(
            Mockery::on(static function($user) {
                $user->setId(1);
                $user->setCreatedAt();
                $user->setUpdatedAt();

                return true;
            })
        );
        $userPasswordHasher = Mockery::mock(UserPasswordHasherInterface::class);
        $userPasswordHasher->shouldReceive('hashPassword')
            ->andReturn(self::PASSWORD_HASH);
        $eventDispatcher = Mockery::mock(EventDispatcherInterface::class);
        $eventDispatcher->shouldIgnoreMissing();

        return new UserService($userRepository, $userPasswordHasher, $eventDispatcher);
    }
}
