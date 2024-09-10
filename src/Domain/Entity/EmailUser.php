<?php

namespace App\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'email_user')]
#[ORM\Entity]
class EmailUser extends User
{
    #[ORM\Column(type: 'string', nullable: false)]
    private string $email;

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function toArray(): array
    {
        return parent::toArray() + ['email' => $this->email];
    }
}
