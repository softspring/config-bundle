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
        $this->configureRoot($treeBuilder->getRootNode());

        return $treeBuilder;
    }

    private function configureRoot(NodeDefinition $root): void
    {
        if (!$root instanceof ArrayNodeDefinition) {
            throw new LogicException('The configuration root must be an array node.');
        }
        $root->children()->arrayNode('defaults')->normalizeKeys(false)->useAttributeAsKey('name')->variablePrototype();
        $group = $root->children()->arrayNode('groups')->normalizeKeys(false)->useAttributeAsKey('key')->arrayPrototype()->children();
        $group->scalarNode('label')->isRequired()->cannotBeEmpty();
        $group->scalarNode('description')->defaultNull();
        $group->scalarNode('translation_domain')->defaultValue('sfs_config')->cannotBeEmpty();
        $group->integerNode('priority')->defaultValue(0);
        $definition = $root->children()->arrayNode('definitions')->normalizeKeys(false)->useAttributeAsKey('key')->arrayPrototype()->children();
        $definition->enumNode('type')->values(['string', 'text', 'boolean', 'integer', 'float', 'json'])->defaultValue('string');
        $definition->scalarNode('label')->defaultValue('Value');
        $definition->scalarNode('group')->defaultValue('general')->cannotBeEmpty();
        $definition->scalarNode('help')->defaultNull();
        $definition->booleanNode('sensitive')->defaultFalse();
        $definition->booleanNode('nullable')->defaultFalse();
        $definition->integerNode('max_length')->min(1)->defaultValue(20000);
        $permissions = $definition->arrayNode('permissions')->children();
        foreach (['read', 'update', 'delete'] as $action) {
            $permissions->scalarNode($action)->cannotBeEmpty();
        }
        $admin = $root->children()->arrayNode('admin')->addDefaultsIfNotSet()->children();
        $admin->booleanNode('enabled')->defaultFalse();
        $adminPermissions = $admin->arrayNode('permissions')->addDefaultsIfNotSet()->children();
        foreach (['list', 'read', 'update', 'delete'] as $action) {
            $adminPermissions->scalarNode($action)->defaultValue('ROLE_ADMIN')->cannotBeEmpty();
        }
    }
}
