<?php

namespace App\Controller;

use App\Domain\Service\GreeterService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;

class WorldController extends AbstractController
{
    public function __construct(private readonly GreeterService $greeterService)
    {
    }

    public function hello(): Response
    {
        return new Response("<html><body>{$this->greeterService->greet('world')}</body></html>");
    }
}
