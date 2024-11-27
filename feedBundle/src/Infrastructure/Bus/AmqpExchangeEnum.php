<?php

namespace FeedBundle\Infrastructure\Bus;

enum AmqpExchangeEnum: string
{
    case SendNotification = 'send_notification';
}
