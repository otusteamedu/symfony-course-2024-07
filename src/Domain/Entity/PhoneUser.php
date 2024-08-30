<?php

namespace App\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'phone_user')]
#[ORM\Entity]
class PhoneUser extends User
{
    #[ORM\Column(type: 'string', nullable: false)]
    private string $phone;

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): void
    {
        $this->phone = $phone;
    }

    public function toArray(): array
    {
        return parent::toArray() + ['phone' => $this->phone];
    }
}
