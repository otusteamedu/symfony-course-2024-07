<?php

namespace FeedBundle;

use FeedBundle\Controller\Amqp\UpdateFeed\Consumer;
use FeedBundle\Domain\DTO\SendNotificationDTO;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class FeedBundle extends AbstractBundle
{
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.yaml');
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->prependExtensionConfig(
            'doctrine',
            [
                'orm' => [
                    'mappings' => [
                        'FeedBundle' => [
                            'type' => 'attribute',
                            'dir' => '%kernel.project_dir%/feedBundle/src/Domain/Entity',
                            'prefix' => 'FeedBundle\Domain\Entity',
                            'alias' => 'FeedBundle'
                        ]
                    ]
                ]
            ]
        );

        $builder->prependExtensionConfig(
            'old_sound_rabbit_mq',
            [
                'producers' => [
                    'send_notification' => [
                        'connection' => 'default',
                        'exchange_options' => ['name' => 'old_sound_rabbit_mq.send_notification', 'type' => 'topic'],
                    ],
                ],
                'consumers' => array_merge(
                    ...array_map(
                        fn(int $number): array => $this->makeUpdateFeedConsumerDefinition($number),
                        range(0, 9),
                    )
                ),
            ]
        );

        $builder->prependExtensionConfig(
            'framework',
            [
                'messenger' => [
                    'transports' => [
                        'send_notification' => [
                            'dsn' => '%env(MESSENGER_AMQP_TRANSPORT_DSN)%',
                            'options' => [
                                'exchange' => ['name' => 'old_sound_rabbit_mq.send_notification', 'type' => 'topic'],
                            ],
                            'serializer' => 'messenger.transport.symfony_serializer',
                        ],
                    ],
                    'routing' => [
                        SendNotificationDTO::class => 'send_notification',
                    ]
                ],
            ],
        );
    }

    private function makeUpdateFeedConsumerDefinition(int $number): array
    {
        return [
            "update_feed_$number" => [
                'connection' => 'default',
                'exchange_options' => ['name' => 'old_sound_rabbit_mq.update_feed', 'type' => 'x-consistent-hash'],
                'queue_options' => [
                    'name' => "old_sound_rabbit_mq.consumer.update_feed_$number",
                    'routing_key' => '20'
                ],
                'callback' => Consumer::class.$number,
                'idle_timeout' => 300,
                'idle_timeout_exit_code' => 0,
                'graceful_max_execution' => ['timeout' => 1800, 'exit_code' => 0],
                'qos_options' => ['prefetch_size' => 0, 'prefetch_count' => 1, 'global' => false],
            ]
        ];
    }
}
