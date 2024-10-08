<?php

namespace App\Controller\Web\CreateUser\v2;

use App\Controller\Web\CreateUser\v2\Input\CreateUserDTO;
use App\Controller\Web\CreateUser\v2\Output\CreatedUserDTO;

interface ManagerInterface
{
    public function create(CreateUserDTO $createUserDTO): CreatedUserDTO;
}
