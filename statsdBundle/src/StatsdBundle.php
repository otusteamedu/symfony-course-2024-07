<?php

namespace StatsdBundle;

use StatsdBundle\Storage\MetricsStorageInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class StatsdBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('client')
                    ->children()
                        ->scalarNode('host')
                            ->isRequired()
                            ->cannotBeEmpty()
                            ->end()
                        ->scalarNode('port')
                            ->isRequired()
                            ->cannotBeEmpty()
                            ->end()
                        ->scalarNode('namespace')
                            ->isRequired()
                            ->cannotBeEmpty()
                            ->end()
                    ->end()
                ->end()
            ->end();
    }


    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.yaml');

        $container->services()
            ->get('statsd.metrics_storage')
            ->arg(0, $config['client']['host'])
            ->arg(1, $config['client']['port'])
            ->arg(2, $config['client']['namespace']);
    }
}
