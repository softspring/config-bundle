<?php

declare(strict_types=1);

namespace Softspring\Bundle\ConfigBundle\DependencyInjection;

use LogicException;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('sfs_config');
        $this->configureDefaults($treeBuilder->getRootNode());

        return $treeBuilder;
    }

    private function configureDefaults(NodeDefinition $rootNode): void
    {
        if (!$rootNode instanceof ArrayNodeDefinition) {
            throw new LogicException('The configuration root must be an array node.');
        }

        $rootNode
            ->children()
                ->arrayNode('defaults')
                    ->normalizeKeys(false)
                    ->useAttributeAsKey('name')
                    ->variablePrototype();
    }
}
