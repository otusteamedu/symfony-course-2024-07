<?php

namespace App\Infrastructure\Repository;

use App\Domain\Repository\FeedRepositoryInterface;
use GuzzleHttp\Client;

class FeedRepository implements FeedRepositoryInterface
{
    public function __construct(
        private readonly Client $client,
        private readonly string $baseUrl
    ) {
    }

    public function ensureFeed(int $userId, int $count): array
    {
        $response = $this->client->get("{$this->baseUrl}/server-api/v1/get-feed/$userId", [
            'query' => [
                'count' => $count,
            ],
        ]);
        $responseData = json_decode($response->getBody(), true);

        return $responseData['tweets'];
    }
}
