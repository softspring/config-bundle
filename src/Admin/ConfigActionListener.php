<?php

declare(strict_types=1);

namespace Softspring\Bundle\ConfigBundle\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Softspring\Bundle\ConfigBundle\Config\ConfigProviderInterface;
use Softspring\Bundle\ConfigBundle\Config\DefinitionRegistry;
use Softspring\Bundle\ConfigBundle\Entity\ConfigOverride;
use Softspring\Bundle\ConfigBundle\Exception\ConfigNotFoundException;
use Softspring\Component\CrudlController\Event\FormPrepareEvent;
use Softspring\Component\CrudlController\Event\InitializeEvent;
use Softspring\Component\CrudlController\Event\LoadEntityEvent;
use Softspring\Component\CrudlController\Event\SuccessEvent;
use Softspring\Component\CrudlController\Event\ViewEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Environment;

final class ConfigActionListener
{
    public function __construct(
        private readonly DefinitionRegistry $definitions,
        private readonly ConfigProviderInterface $provider,
        private readonly EntityManagerInterface $em,
        private readonly AuthorizationCheckerInterface $security,
        private readonly Environment $twig,
        private readonly array $permissions,
        private readonly UrlGeneratorInterface $router,
    ) {
    }

    #[AsEventListener(event: 'sfs_config.admin.list.initialize')]
    public function onList(InitializeEvent $event): void
    {
        // CRUDL initialization runs before its common access check.
        $this->denyUnlessGranted($event->getConfig()['is_granted']);
        $page = $this->page($event->getRequest());
        $groups = [];
        foreach ($this->definitions->groups() as $key => $group) {
            if (null !== $page['group'] && $key !== $page['group']) {
                continue;
            }
            $groups[$key] = ['key' => $key, 'config' => $group, 'settings' => []];
        }
        foreach ($this->definitions->all() as $key => $definition) {
            if (!isset($groups[$definition['group']]) || $definition['sensitive'] || !$this->allowed('read', $definition)) {
                continue;
            }
            $override = $this->em->find(ConfigOverride::class, $key);
            $groups[$definition['group']]['settings'][] = ['key' => $key, 'definition' => $definition,
                'value' => $this->provider->get($key), 'overridden' => null !== $override,
                'canUpdate' => $this->allowed('update', $definition),
                'canDelete' => null !== $override && $this->allowed('delete', $definition)];
        }
        $groups = array_filter($groups, static fn (array $group): bool => [] !== $group['settings']);
        $event->setResponse(new Response($this->twig->render('@SfsConfig/admin/list.html.twig', ['groups' => $groups, 'page' => $page])));
    }

    #[AsEventListener(event: 'sfs_config.admin.read.load')]
    #[AsEventListener(event: 'sfs_config.admin.update.load')]
    #[AsEventListener(event: 'sfs_config.admin.delete.load')]
    public function onLoad(LoadEntityEvent $event): void
    {
        $page = $this->page($event->getRequest());
        $key = (string) $event->getRequest()?->attributes->get('id', '');
        try {
            $definition = $this->definitions->get($key);
        } catch (ConfigNotFoundException $exception) {
            throw new NotFoundHttpException('Configuration key is not registered.', $exception);
        }
        if (null !== $page['group'] && $definition['group'] !== $page['group']) {
            throw new NotFoundHttpException('Configuration key does not belong to this page.');
        }
        $action = $event->getConfig()['action'];
        $this->denyUnlessGranted($event->getConfig()['is_granted']);
        if ($definition['sensitive'] || !$this->allowed('read', $definition) || !$this->allowed($action, $definition)) {
            throw new AccessDeniedHttpException('Configuration access is not allowed.');
        }
        $entity = $this->em->find(ConfigOverride::class, $key);
        if ('delete' === $action && null === $entity) {
            throw new NotFoundHttpException('This setting has no stored override.');
        }
        $event->setEntity($entity ?? new ConfigOverride($key, $this->provider->get($key)));
    }

    #[AsEventListener(event: 'sfs_config.admin.update.form_prepare')]
    public function onFormPrepare(FormPrepareEvent $event): void
    {
        $entity = $event->getEntity();
        if ($entity instanceof ConfigOverride) {
            $event->setFormOptions(array_merge($event->getFormOptions(), ['definition' => $this->definitions->get($entity->getId())]));
        }
    }

    #[AsEventListener(event: 'sfs_config.admin.read.view')]
    #[AsEventListener(event: 'sfs_config.admin.update.view')]
    #[AsEventListener(event: 'sfs_config.admin.delete.view')]
    public function onView(ViewEvent $event): void
    {
        $entity = $event->getData()['setting'];
        $definition = $this->definitions->get($entity->getId());
        $event->getData()['definition'] = $definition;
        $event->getData()['page'] = $this->page($event->getRequest());
    }

    #[AsEventListener(event: 'sfs_config.admin.update.success')]
    #[AsEventListener(event: 'sfs_config.admin.delete.success')]
    public function onSuccess(SuccessEvent $event): void
    {
        $page = $this->page($event->getRequest());
        if ('sfs_config_admin_list' !== $page['routes']['list']) {
            $event->setResponse(new RedirectResponse($this->router->generate($page['routes']['list'])));
        }
    }

    private function page(?Request $request): array
    {
        $group = $request?->attributes->get('_sfs_config_group');
        $groups = $this->definitions->groups();
        if (null !== $group && (!is_string($group) || !isset($groups[$group]))) {
            throw new NotFoundHttpException('Configuration group is not registered.');
        }
        $route = (string) $request?->attributes->get('_route', 'sfs_config_admin_list');
        $prefix = substr($route, 0, strrpos($route, '_') + 1);
        $routes = [];
        foreach (['list', 'read', 'update', 'delete'] as $action) {
            $routes[$action] = $prefix.$action;
        }

        return ['group' => $group, 'groupConfig' => null === $group ? null : $groups[$group], 'routes' => $routes];
    }

    private function allowed(string $action, array $definition): bool
    {
        return $this->security->isGranted($this->permissions[$action])
            && $this->security->isGranted($definition['permissions'][$action] ?? $this->permissions[$action]);
    }

    private function denyUnlessGranted(string $permission): void
    {
        if (!$this->security->isGranted($permission)) {
            throw new AccessDeniedHttpException('Configuration access is not allowed.');
        }
    }
}
