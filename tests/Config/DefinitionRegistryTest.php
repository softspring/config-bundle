<?php

declare(strict_types=1);

namespace Softspring\Bundle\ConfigBundle\Tests\Config;

use LogicException;
use PHPUnit\Framework\TestCase;
use Softspring\Bundle\ConfigBundle\Config\DefinitionRegistry;
use Softspring\Bundle\ConfigBundle\DependencyInjection\SfsConfigExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class DefinitionRegistryTest extends TestCase
{
    public function testGroupsAreOrderedByPriorityThenStableKey(): void
    {
        $registry = new DefinitionRegistry([], [
            'mailer' => ['priority' => 10], 'general' => ['priority' => -100],
            'cms' => ['priority' => 100], 'accounts' => ['priority' => 10],
        ]);
        self::assertSame(['cms', 'accounts', 'mailer', 'general'], array_keys($registry->groups()));
    }

    public function testUnknownGroupsAreRejectedWhenTheContainerIsConfigured(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('references unknown configuration group');
        new SfsConfigExtension()->load([[
            'defaults' => ['app.setting' => 'value'],
            'definitions' => ['app.setting' => ['group' => 'unknown']],
        ]], new ContainerBuilder());
    }

    public function testProjectsCanOverrideBundleGroupTranslationsWithoutLosingOtherMetadata(): void
    {
        $container = new ContainerBuilder();
        new SfsConfigExtension()->load([
            ['groups' => ['cms' => ['label' => 'bundle.cms.label', 'priority' => 100]]],
            ['groups' => ['cms' => ['label' => 'project.cms.label', 'translation_domain' => 'project_config']]],
        ], $container);
        $groups = $container->getDefinition(DefinitionRegistry::class)->getArgument('$groups');
        self::assertSame('project.cms.label', $groups['cms']['label']);
        self::assertSame('project_config', $groups['cms']['translation_domain']);
        self::assertSame(100, $groups['cms']['priority']);
        self::assertArrayHasKey('general', $groups);
    }
}
