<?php

namespace App\Controller\Web\GetFeed\v1\Output;

use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;

class Response
{
    /**
     * @param TweetDTO[] $tweets
     */
    public function __construct(
        #[OA\Property(type: 'array', items: new OA\Items(ref: new Model(type: TweetDTO::class)))]
        public array $tweets,
    ) {
    }
}
