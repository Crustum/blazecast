<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Test\TestCase\WebSocket\Pusher;

use Cake\Core\Configure;
use Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ApplicationManager
 */
class ApplicationManagerTest extends TestCase
{
    /**
     * @var ApplicationManager
     */
    private ApplicationManager $manager;

    /**
     * @var array<string, mixed>
     */
    private array $originalConfig = [];

    /**
     * Test application data
     *
     * @var array<int, array<string, mixed>>
     */
    private array $testAppData = [
        [
            'id' => 'app1',
            'key' => 'test_key_1',
            'secret' => 'test_secret_1',
            'name' => 'Test App 1',
        ],
        [
            'id' => 'app2',
            'key' => 'test_key_2',
            'secret' => 'test_secret_2',
            'name' => 'Test App 2',
        ],
        [
            'id' => 'app3',
            'key' => 'test_key_3',
            'secret' => 'test_secret_3',
            'name' => 'Test App 3',
            'enable_client_messages' => false,
        ],
    ];

    /**
     * Setup method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $originalConfig = Configure::read('BlazeCast.apps');
        if ($originalConfig !== null) {
            $this->originalConfig = $originalConfig;
        }

        Configure::write('BlazeCast.apps', $this->testAppData);

        $this->manager = new ApplicationManager();
    }

    /**
     * Teardown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        Configure::write('BlazeCast.apps', $this->originalConfig);

        parent::tearDown();
    }

    #[Test]
    public function managerCanBeCreated(): void
    {
        $manager = new ApplicationManager();
        $this->assertInstanceOf(ApplicationManager::class, $manager);
    }

    #[Test]
    public function managerCanGetAllApplications(): void
    {
        $apps = $this->manager->getApplications();

        $this->assertCount(3, $apps);
        $this->assertArrayHasKey('app1', $apps);
        $this->assertArrayHasKey('app2', $apps);
        $this->assertArrayHasKey('app3', $apps);
    }

    #[Test]
    public function managerCanGetApplicationById(): void
    {
        $app1 = $this->manager->getApplication('app1');
        $app2 = $this->manager->getApplication('app2');
        $nonExistent = $this->manager->getApplication('non-existent');

        $this->assertIsArray($app1);
        $this->assertEquals('test_key_1', $app1['key']);
        $this->assertEquals('test_secret_1', $app1['secret']);
        $this->assertEquals('Test App 1', $app1['name']);

        $this->assertIsArray($app2);
        $this->assertEquals('test_key_2', $app2['key']);
        $this->assertEquals('test_secret_2', $app2['secret']);

        $this->assertNull($nonExistent);
    }

    #[Test]
    public function managerCanGetApplicationByKey(): void
    {
        $app1 = $this->manager->getApplicationByKey('test_key_1');
        $app2 = $this->manager->getApplicationByKey('test_key_2');
        $nonExistent = $this->manager->getApplicationByKey('non-existent-key');

        $this->assertIsArray($app1);
        $this->assertEquals('app1', $app1['id']);
        $this->assertEquals('test_secret_1', $app1['secret']);

        $this->assertIsArray($app2);
        $this->assertEquals('app2', $app2['id']);
        $this->assertEquals('test_secret_2', $app2['secret']);

        $this->assertNull($nonExistent);
    }

    #[Test]
    public function managerCanCheckIfApplicationExists(): void
    {
        $this->assertTrue($this->manager->hasApplication('app1'));
        $this->assertTrue($this->manager->hasApplication('app2'));
        $this->assertTrue($this->manager->hasApplication('app3'));
        $this->assertFalse($this->manager->hasApplication('non-existent'));
    }

    #[Test]
    public function managerCanValidateCredentials(): void
    {
        $this->assertTrue($this->manager->validateCredentials('app1', 'test_key_1', 'test_secret_1'));
        $this->assertTrue($this->manager->validateCredentials('app2', 'test_key_2', 'test_secret_2'));

        $this->assertFalse($this->manager->validateCredentials('app1', 'wrong_key', 'test_secret_1'));
        $this->assertFalse($this->manager->validateCredentials('app1', 'test_key_1', 'wrong_secret'));
        $this->assertFalse($this->manager->validateCredentials('non-existent', 'test_key_1', 'test_secret_1'));
    }

    #[Test]
    public function managerCanValidateSignature(): void
    {
        $body = json_encode(['event' => 'test', 'data' => 'test_data']);
        $path = '/apps/app1/events';
        $method = 'POST';

        $stringToSign = implode("\n", [
            strtoupper($method),
            $path,
            $body,
        ]);

        $signature = hash_hmac('sha256', $stringToSign, 'test_secret_1');

        $this->assertTrue($this->manager->validateSignature(
            'app1',
            $method,
            $path,
            $body,
            $signature,
        ));

        $this->assertFalse($this->manager->validateSignature(
            'app1',
            $method,
            $path,
            $body,
            'invalid-signature',
        ));

        $this->assertFalse($this->manager->validateSignature(
            'non-existent',
            $method,
            $path,
            $body,
            $signature,
        ));
    }

    #[Test]
    public function managerCanRegisterAndRemoveApplications(): void
    {
        $newApp = [
            'id' => 'new-app',
            'key' => 'new-key',
            'secret' => 'new-secret',
            'name' => 'New Test App',
        ];

        $this->manager->registerApplication($newApp);

        $app = $this->manager->getApplication('new-app');
        $this->assertNotNull($app);
        $this->assertEquals('new-key', $app['key']);

        $this->assertTrue($this->manager->removeApplication('new-app'));
        $this->assertNull($this->manager->getApplication('new-app'));

        $this->assertFalse($this->manager->removeApplication('non-existent'));
    }

    #[Test]
    public function managerHandlesEmptyConfiguration(): void
    {
        Configure::write('BlazeCast.apps', []);
        $emptyManager = new ApplicationManager();

        $this->assertEmpty($emptyManager->getApplications());
        $this->assertNull($emptyManager->getApplicationByKey('any_key'));
        $this->assertNull($emptyManager->getApplication('any_id'));
    }

    #[Test]
    public function managerHandlesMissingConfiguration(): void
    {
        Configure::delete('BlazeCast.apps');
        $missingManager = new ApplicationManager();

        $this->assertEmpty($missingManager->getApplications());
        $this->assertNull($missingManager->getApplicationByKey('any_key'));
        $this->assertNull($missingManager->getApplication('any_id'));
    }

    #[Test]
    public function managerCanGetApplicationCount(): void
    {
        $this->assertEquals(3, $this->manager->getApplicationCount());

        $this->manager->registerApplication([
            'id' => 'new-app',
            'key' => 'new-key',
            'secret' => 'new-secret',
        ]);

        $this->assertEquals(4, $this->manager->getApplicationCount());

        $this->manager->removeApplication('new-app');
        $this->assertEquals(3, $this->manager->getApplicationCount());
    }
}
