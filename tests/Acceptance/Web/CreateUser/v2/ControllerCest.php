<?php

namespace AcceptanceTests\Web\CreateUser\v2;

use App\Tests\Support\AcceptanceTester;
use Codeception\Util\HttpCode;

class ControllerCest
{
    public function testAddUserAction(AcceptanceTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('/api/v2/user', [
            'login' => 'my_user',
            'password' => 'my_password',
            'roles' => ['ROLE_USER'],
            'age' => 23,
            'isActive' => true,
            'phone' => '+0123456789',
        ]);
        $I->canSeeResponseCodeIs(HttpCode::OK);
        $I->canSeeResponseMatchesJsonType(['id' => 'integer:>0']);
    }
}
