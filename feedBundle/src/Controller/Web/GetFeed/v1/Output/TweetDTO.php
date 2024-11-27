<?php

namespace FeedBundle\Controller\Web\GetFeed\v1\Output;

use OpenApi\Attributes as OA;

class TweetDTO
{
    public function __construct(
        #[OA\Property(type: 'integer')]
        public int $id,
        #[OA\Property(type: 'string')]
        public string $author,
        #[OA\Property(type: 'string')]
        public string $text,
        #[OA\Property(type: 'string', format: '\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}')]
        public string $createdAt,
    ) {
    }
}
