<?php

namespace App\Controller\Web\PhoneUserForm\v1;

use App\Controller\Form\PhoneUserType;
use App\Domain\Entity\User;
use App\Domain\Service\UserService;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;

class Manager
{
    public function __construct(
        private readonly UserService $userService,
        private readonly FormFactoryInterface $formFactory,
    ) {
    }

    public function getFormData(Request $request, ?User $user = null): array
    {
        $isNew = $user === null;
        $form = $this->formFactory->create(PhoneUserType::class, $user, ['isNew' => $isNew]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var User $user */
            $user = $form->getData();
            $this->userService->processFromForm($user);
        }

        return [
            'form' => $form,
            'isNew' => $isNew,
            'user' => $user,
        ];
    }
}
