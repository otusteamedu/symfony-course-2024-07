<?php

namespace App\Domain\Entity;

use Symfony\Component\Serializer\Attribute\Groups;

class EmailUser extends User
{
    #[Groups(['elastica'])]
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
