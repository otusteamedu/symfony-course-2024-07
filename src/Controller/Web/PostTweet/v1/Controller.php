<?php

namespace App\Controller\Web\PostTweet\v1;

use App\Controller\Web\PostTweet\v1\Input\PostTweetDTO;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
class Controller
{
    public function __construct(private readonly Manager $manager) {
    }

    #[Route(path: 'api/v1/post-tweet', methods: ['POST'])]
    public function __invoke(#[MapRequestPayload]PostTweetDTO $postTweetDTO): Response
    {
        return new JsonResponse(['success' => $this->manager->postTweet($postTweetDTO)]);
    }
}
