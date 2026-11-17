<?php

declare(strict_types=1);

namespace Softspring\Bundle\ConfigBundle\Tests\Integration;

use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Softspring\Bundle\ConfigBundle\Entity\ConfigOverride;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Security\Core\User\InMemoryUser;

class AdminTest extends TestCase
{
    private AdminKernel $kernel;
    private KernelBrowser $client;
    private mixed $previousExceptionHandler;

    protected function setUp(): void
    {
        $this->previousExceptionHandler = set_exception_handler(null);
        restore_exception_handler();
        $this->kernel = new AdminKernel(sys_get_temp_dir().'/sfs-config-admin-'.bin2hex(random_bytes(8)));
        $this->kernel->boot();
        $em = $this->kernel->getContainer()->get('doctrine')->getManager();
        new SchemaTool($em)->createSchema([$em->getClassMetadata(ConfigOverride::class)]);
        $this->client = new KernelBrowser($this->kernel);
        $this->client->disableReboot();
    }

    protected function tearDown(): void
    {
        $this->kernel->shutdown();
        new Filesystem()->remove(dirname($this->kernel->getCacheDir()));
        $currentExceptionHandler = set_exception_handler(null);
        restore_exception_handler();
        if ($currentExceptionHandler !== $this->previousExceptionHandler) {
            restore_exception_handler();
        }
    }

    public function testAdminCanSaveInstructionsAndResetTheOverride(): void
    {
        $this->client->loginUser(new InMemoryUser('admin', 'unused', ['ROLE_ADMIN']));
        $crawler = $this->client->request('GET', '/settings/instructions/edit');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        $name = $crawler->filter('textarea')->attr('name');
        $this->client->submit($crawler->filter('form')->form(), [$name => 'new instructions']);
        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        self::assertSame('new instructions', $this->kernel->getContainer()->get('test.provider')->get('instructions'));
        $crawler = $this->client->request('GET', '/settings/instructions/reset');
        $this->client->submit($crawler->filter('form')->form());
        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        self::assertSame('default instructions', $this->kernel->getContainer()->get('test.provider')->get('instructions'));
    }

    public function testMissingCsrfDoesNotSave(): void
    {
        $this->client->loginUser(new InMemoryUser('admin', 'unused', ['ROLE_ADMIN']));
        $this->client->request('POST', '/settings/instructions/edit', ['config_value_form' => ['value' => 'forged']]);
        self::assertSame('default instructions', $this->kernel->getContainer()->get('test.provider')->get('instructions'));
    }

    public function testReaderCanSeeTheListButCannotPostEdits(): void
    {
        $this->client->loginUser(new InMemoryUser('reader', 'unused', ['ROLE_CONFIG_READ']));
        $crawler = $this->client->request('GET', '/settings/');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertStringNotContainsString('synthetic secret', $crawler->text());
        self::assertCount(0, $crawler->filter('a[href$="/edit"]'));
        $this->client->request('POST', '/settings/instructions/edit', ['config_value_form' => ['value' => 'forged']]);
        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    public function testPerKeyPermissionsAndSensitiveValuesCannotBeBypassed(): void
    {
        $this->client->loginUser(new InMemoryUser('admin', 'unused', ['ROLE_ADMIN']));
        foreach (['restricted/edit', 'restricted/reset', 'secret', 'secret/edit'] as $path) {
            $this->client->request('GET', '/settings/'.$path);
            self::assertSame(403, $this->client->getResponse()->getStatusCode());
        }
        $this->client->request('GET', '/settings/unknown/edit');
        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    public function testRegularUsersCannotReadConfiguration(): void
    {
        $this->client->loginUser(new InMemoryUser('user', 'unused', ['ROLE_USER']));
        $this->client->request('GET', '/settings/');
        self::assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    public function testTranslatedGroupsKeepSettingsTogetherAndHideInaccessibleGroups(): void
    {
        $this->client->loginUser(new InMemoryUser('admin', 'unused', ['ROLE_ADMIN']));
        $crawler = $this->client->request('GET', '/settings/');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        $groups = $crawler->filter('[data-config-group]');
        self::assertSame(['cms', 'mailer', 'general'], $groups->each(static fn ($node) => $node->attr('data-config-group')));
        $cms = $crawler->filter('[data-config-group="cms"]');
        self::assertSame('Content management', $cms->filter('h2')->text());
        self::assertStringContainsString('Manage content and AI instructions.', $cms->text());
        self::assertCount(1, $cms->filter('[data-config-key="instructions"]'));
        self::assertCount(0, $cms->filter('[data-config-key="mailer.sender_name"]'));
        $mailer = $crawler->filter('[data-config-group="mailer"]');
        self::assertSame('Email delivery', $mailer->filter('h2')->text());
        self::assertCount(1, $mailer->filter('[data-config-key="mailer.sender_name"]'));
        self::assertCount(0, $crawler->filter('[data-config-group="private"], [data-config-group="empty"]'));
    }

    public function testGroupPagesFilterSettingsWithoutChangingGeneralAdministration(): void
    {
        $this->client->loginUser(new InMemoryUser('admin', 'unused', ['ROLE_ADMIN']));
        foreach (['cms' => 'instructions', 'mailer' => 'mailer.sender_name'] as $group => $key) {
            $crawler = $this->client->request('GET', '/settings/'.$group.'/?_sfs_config_group=general');
            self::assertSame(200, $this->client->getResponse()->getStatusCode());
            self::assertCount(1, $crawler->filter('[data-config-group="'.$group.'"]'));
            self::assertCount(1, $crawler->filter('[data-config-key]'));
            self::assertSame($key, $crawler->filter('[data-config-key]')->attr('data-config-key'));
            self::assertSame('/settings/'.$group.'/'.$key, $crawler->filter('[data-config-key] td a')->first()->attr('href'));
        }
        $crawler = $this->client->request('GET', '/settings/');
        self::assertCount(3, $crawler->filter('[data-config-group]'));
        $crawler = $this->client->request('GET', '/settings/empty/');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertCount(0, $crawler->filter('[data-config-group]'));
        $this->client->request('GET', '/settings/missing/');
        self::assertSame(404, $this->client->getResponse()->getStatusCode());
    }

    public function testGroupedEditAndResetKeepTheirBreadcrumbsAndRedirects(): void
    {
        $this->client->loginUser(new InMemoryUser('admin', 'unused', ['ROLE_ADMIN']));
        $crawler = $this->client->request('GET', '/settings/cms/instructions');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertSame('/settings/cms/', $crawler->filter('.breadcrumb a')->first()->attr('href'));
        self::assertSame('Content management configuration', $crawler->filter('.breadcrumb a')->first()->text());
        $crawler = $this->client->request('GET', '/settings/cms/instructions/edit');
        self::assertSame('/settings/cms/instructions', $crawler->filter('.breadcrumb a')->last()->attr('href'));
        $name = $crawler->filter('textarea')->attr('name');
        $this->client->submit($crawler->filter('form')->form(), [$name => 'group instructions']);
        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        self::assertSame('/settings/cms/', $this->client->getResponse()->headers->get('Location'));
        self::assertSame('group instructions', $this->kernel->getContainer()->get('test.provider')->get('instructions'));
        $crawler = $this->client->request('GET', '/settings/cms/instructions/reset');
        self::assertSame('/settings/cms/instructions', $crawler->filter('.breadcrumb a')->last()->attr('href'));
        $this->client->submit($crawler->filter('form')->form());
        self::assertSame(302, $this->client->getResponse()->getStatusCode());
        self::assertSame('/settings/cms/', $this->client->getResponse()->headers->get('Location'));
        self::assertSame('default instructions', $this->kernel->getContainer()->get('test.provider')->get('instructions'));
    }

    public function testGroupPagesRejectOtherGroupsAndRetainPermissions(): void
    {
        $this->client->loginUser(new InMemoryUser('admin', 'unused', ['ROLE_ADMIN']));
        foreach (['', '/edit', '/reset'] as $suffix) {
            foreach (['GET', 'POST'] as $method) {
                if ('POST' === $method && '' === $suffix) {
                    continue;
                }
                $this->client->request($method, '/settings/cms/mailer.sender_name'.$suffix, ['config_value_form' => ['value' => 'forged']]);
                self::assertSame(404, $this->client->getResponse()->getStatusCode());
            }
        }
        self::assertSame('Synthetic sender', $this->kernel->getContainer()->get('test.provider')->get('mailer.sender_name'));
        $this->client->loginUser(new InMemoryUser('reader', 'unused', ['ROLE_CONFIG_READ']));
        $crawler = $this->client->request('GET', '/settings/cms/');
        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertCount(0, $crawler->filter('a[href$="/edit"]'));
        $this->client->request('POST', '/settings/cms/instructions/edit', ['config_value_form' => ['value' => 'forged']]);
        self::assertSame(403, $this->client->getResponse()->getStatusCode());
        self::assertSame('default instructions', $this->kernel->getContainer()->get('test.provider')->get('instructions'));
    }
}
