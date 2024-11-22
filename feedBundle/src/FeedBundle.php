<?php

namespace FeedBundle;

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
    }
}
