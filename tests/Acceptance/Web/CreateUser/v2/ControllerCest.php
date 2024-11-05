<?php

namespace AcceptanceTests\Web\CreateUser\v2;

use App\Tests\Support\AcceptanceTester;
use Codeception\Util\HttpCode;

class ControllerCest
{
    public function testAddUserActionAsAdmin(AcceptanceTester $I): void
    {
        $I->amAdmin();
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('/api/v2/user', $this->getMethodParams());
        $I->canSeeResponseCodeIs(HttpCode::OK);
        $I->canSeeResponseMatchesJsonType(['id' => 'integer:>0']);
    }

    public function testAddUserActionAsUser(AcceptanceTester $I): void
    {
        $I->amUser();
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('/api/v2/user', $this->getMethodParams());
        $I->canSeeResponseCodeIs(HttpCode::FORBIDDEN);
    }

    private function getMethodParams(): array
    {
        return [
            'login' => 'my_user2',
            'password' => 'my_password',
            'roles' => ['ROLE_USER'],
            'age' => 23,
            'isActive' => true,
            'phone' => '+0123456789',
        ];
    }
}
