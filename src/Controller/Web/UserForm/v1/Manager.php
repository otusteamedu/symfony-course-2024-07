<?php

namespace App\Controller\Web\UserForm\v1;

use App\Controller\Form\UserType;
use App\Controller\Web\UserForm\v1\Input\CreateUserDTO;
use App\Domain\Entity\EmailUser;
use App\Domain\Entity\PhoneUser;
use App\Domain\Entity\User;
use App\Domain\Model\CreateUserModel;
use App\Domain\Service\ModelFactory;
use App\Domain\Service\UserService;
use App\Domain\ValueObject\CommunicationChannelEnum;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;

class Manager
{
    public function __construct(
        private readonly UserService $userService,
        private readonly FormFactoryInterface $formFactory,
        private readonly ModelFactory $modelFactory,
    ) {
    }

    public function getFormData(Request $request, ?User $user = null): array
    {
        $isNew = $user === null;
        $formData = $isNew ? null : new CreateUserDTO(
            $user->getLogin(),
            $user instanceof EmailUser ? $user->getEmail() : null,
            $user instanceof PhoneUser ? $user->getPhone() : null,
            $user->getPassword(),
            $user->getAge(),
            $user->isActive(),
        );
        $form = $this->formFactory->create(UserType::class, $formData, ['isNew' => $isNew]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var CreateUserDTO $createUserDTO */
            $createUserDTO = $form->getData();
            $communicationMethod = $createUserDTO->phone ?? $createUserDTO->email;
            $communicationChannel = $createUserDTO->phone === null ? CommunicationChannelEnum::Email : CommunicationChannelEnum::Phone;
            $createUserModel = $this->modelFactory->makeModel(
                CreateUserModel::class,
                $createUserDTO->login,
                $communicationMethod,
                $communicationChannel,
                $createUserDTO->password,
                $createUserDTO->age,
                $createUserDTO->isActive,
            );
            $user = $this->userService->create($createUserModel);
        }

        return [
            'form' => $form,
            'isNew' => $isNew,
            'user' => $user,
        ];
    }
}
