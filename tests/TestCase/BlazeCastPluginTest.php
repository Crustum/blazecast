<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Tests\TestCase;

use Cake\Core\Configure;
use Cake\Core\PluginApplicationInterface;
use Cake\Log\Log;
use Cake\TestSuite\TestCase;
use Crustum\BlazeCast\BlazeCastPlugin;

class BlazeCastPluginTest extends TestCase
{
    protected mixed $previousConfig;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousConfig = Configure::read('BlazeCast');
    }

    protected function tearDown(): void
    {
        if (Log::getConfig('blazecast') !== null) {
            Log::drop('blazecast');
        }

        Configure::delete('BlazeCast');
        if ($this->previousConfig !== null) {
            Configure::write('BlazeCast', $this->previousConfig);
        }

        parent::tearDown();
    }

    public function testBootstrapPreservesApplicationConfig(): void
    {
        Configure::write('BlazeCast', [
            'applications' => [
                [
                    'id' => 'host-app',
                    'activity_timeout' => 120,
                ],
            ],
        ]);

        $plugin = new BlazeCastPlugin();
        $plugin->bootstrap($this->createStub(PluginApplicationInterface::class));

        $this->assertSame(120, Configure::read('BlazeCast.applications.0.activity_timeout'));
    }

    public function testBootstrapLoadsPluginDefaultsWhenApplicationConfigIsMissing(): void
    {
        Configure::delete('BlazeCast');

        $plugin = new BlazeCastPlugin();
        $plugin->bootstrap($this->createStub(PluginApplicationInterface::class));

        $this->assertTrue(Configure::check('BlazeCast'));
    }
}
