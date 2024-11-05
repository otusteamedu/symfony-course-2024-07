<?php

namespace UnitTests\Controller\Cli;

use App\Controller\Cli\AddFollowersCommand;
use App\Domain\Entity\User;
use App\Domain\Service\FollowerService;
use App\Domain\Service\UserService;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Tester\CommandTester;

class AddFollowersCommandTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const TEST_AUTHOR_ID = 1;
    private const DEFAULT_FOLLOWERS_COUNT = 10;

    /**
     * @dataProvider executeDataProvider
     */
    public function testExecuteReturnsResult(?int $followersCount, string $login, string $expected): void
    {
        $authorId = 1;
        $command = $this->prepareCommand($authorId, $login, $followersCount ?? self::DEFAULT_FOLLOWERS_COUNT);
        $command->setHelperSet(new HelperSet([new QuestionHelper()]));
        $commandTester = new CommandTester($command);
        $params = ['authorId' => self::TEST_AUTHOR_ID, '--login' => $login];
        $inputs = $followersCount === null ? ["\n"] : ["$followersCount\n"];
        $commandTester->setInputs($inputs);
        $commandTester->execute($params);
        $output = $commandTester->getDisplay();
        static::assertStringEndsWith($expected, $output);
    }

    private function prepareCommand(int $authorId, string $login, int $count): AddFollowersCommand
    {
        $mockUser = new User();
        $userService = Mockery::mock(UserService::class);
        $userService->shouldIgnoreMissing()
            ->shouldReceive('findUserById')
            ->andReturn($mockUser)
            ->once();
        $followerService = Mockery::mock(FollowerService::class);
        $followerService->shouldReceive('addFollowersSync')
            ->withArgs([$mockUser, $login.$authorId, $count])
            ->andReturn($count)
            ->times($count >= 0 ? 1 : 0);
        return new AddFollowersCommand($userService, $followerService);
    }

    protected static function executeDataProvider(): array
    {
        return [
            'positive' => [20, 'login', "20 followers were created\n"],
            'zero' => [0, 'other_login', "0 followers were created\n"],
            'default' => [null, 'login3', "10 followers were created\n"],
            'negative' => [-1, 'login_too', "Count should be positive integer\n"],
        ];
    }
}
