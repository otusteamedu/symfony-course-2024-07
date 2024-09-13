<?php

namespace App\Application\Security\Voter;

use App\Domain\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class FakeUserVoter extends Voter
{
    protected function supports(string $attribute, $subject): bool
    {
        return $attribute === UserVoter::DELETE && ($subject instanceof User);
    }

    protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
    {
        return false;
    }
}
