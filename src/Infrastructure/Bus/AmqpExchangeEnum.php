<?php

namespace App\Infrastructure\Bus;

enum AmqpExchangeEnum: string
{
    case AddFollowers = 'add_followers';
    case PublishTweet = 'publish_tweet';
    case UpdateFeed = 'update_feed';
}
