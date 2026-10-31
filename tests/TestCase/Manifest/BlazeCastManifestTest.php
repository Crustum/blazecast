<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\Manifest;

use Cake\TestSuite\TestCase;
use Crustum\BlazeCast\BlazeCastPlugin;
use Crustum\BlazeCast\Manifest\BlazeCastManifest;
use Crustum\PluginManifest\Manifest\ManifestResolver;

/**
 * Covers the standalone manifest provider: identical operations to the
 * former plugin-class manifest, discoverable without the interface.
 */
class BlazeCastManifestTest extends TestCase
{
    /**
     * The provider emits config, bootstrap, env and star operations.
     *
     * @return void
     */
    public function testManifestOperations(): void
    {
        $ops = BlazeCastManifest::manifest();
        $tags = array_column($ops, 'tag');

        $this->assertContains('config', $tags);
        $this->assertContains('bootstrap', $tags);
        $this->assertContains('envs', $tags);
        $this->assertContains('star-repo', $tags);
    }

    /**
     * The resolver finds the provider for the plugin class.
     *
     * @return void
     */
    public function testResolverFindsProvider(): void
    {
        $this->assertSame(
            BlazeCastManifest::class,
            ManifestResolver::providerClass(BlazeCastPlugin::class),
        );
        $this->assertSame(BlazeCastManifest::manifest(), ManifestResolver::manifestFor(BlazeCastPlugin::class));
    }

    /**
     * The plugin class itself carries no manifest contract.
     *
     * @return void
     */
    public function testPluginClassHasNoManifestContract(): void
    {
        $this->assertFalse(method_exists(BlazeCastPlugin::class, 'manifest'));
    }
}
