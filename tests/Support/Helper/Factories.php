<?php

namespace Support\Helper;

use App\Domain\Entity\PhoneUser;
use App\Domain\Entity\Subscription;
use App\Domain\Entity\Tweet;
use Codeception\Module;
use Codeception\Module\DataFactory;
use League\FactoryMuffin\Faker\Facade;

class Factories extends Module
{
    public function _beforeSuite($settings = []): void
    {
        /** @var DataFactory $factory */
        $factory = $this->getModule('DataFactory');

        $factory->_define(
            PhoneUser::class,
            [
                'login' => Facade::text(20),
                'password' => Facade::text(20),
                'age' => Facade::randomNumber(2),
                'roles' => [],
                'isActive' => true,
                'phone' => '+0'.Facade::randomNumber(9, true)(),
            ]
        );
        $factory->_define(
            Tweet::class,
            [
                'text' => Facade::text(100),
            ]
        );
        $factory->_define(Subscription::class, []);
    }
}
