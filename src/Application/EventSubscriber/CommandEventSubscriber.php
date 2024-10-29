<?php

namespace App\Application\EventSubscriber;

use App\Controller\Cli\AddFollowersCommand;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleSignalEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CommandEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::SIGNAL => 'handleSignal',
        ];
    }

    public function handleSignal(ConsoleSignalEvent $event): void
    {
        $command = $event->getCommand();
        if ($command !== null && $command->getName() === AddFollowersCommand::FOLLOWERS_ADD_COMMAND_NAME) {
            $signal = $event->getHandlingSignal();
            echo $signal.' via event';
        }

        $event->setExitCode(0);
    }
}
