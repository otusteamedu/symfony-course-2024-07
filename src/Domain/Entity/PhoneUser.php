<?php

namespace App\Domain\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\Controller\Web\CreateUser\v2\Input\CreateUserDTO;
use App\Controller\Web\CreateUser\v2\Output\CreatedUserDTO;
use App\Domain\ApiPlatform\State\UserProcessor;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'phone_user')]
#[ORM\Entity]
#[ApiResource]
#[Post(input: CreateUserDTO::class, output: CreatedUserDTO::class, processor: UserProcessor::class)]
class PhoneUser extends User
{
    #[ORM\Column(type: 'string', length: 20, nullable: false)]
    private string $phone;

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): self
    {
        $this->phone = $phone;

        return $this;
    }

    public function toArray(): array
    {
        return parent::toArray() + ['phone' => $this->phone];
    }
}
