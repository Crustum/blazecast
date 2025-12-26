<?php
declare(strict_types=1);

namespace Crustum\BlazeCast;

use Cake\Console\CommandCollection;
use Cake\Core\BasePlugin;
use Cake\Core\Configure;
use Cake\Core\ContainerInterface;
use Cake\Core\PluginApplicationInterface;
use Cake\Event\EventManager;
use Cake\Http\MiddlewareQueue;
use Crustum\BlazeCast\Command\RestartServerCommand;
use Crustum\BlazeCast\Command\ServerStartCommand;
use Crustum\BlazeCast\Service\EventDispatcherService;
use Crustum\BlazeCast\WebSocket\ConnectionRegistry;
use Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager;
use Crustum\PluginManifest\Manifest\ManifestInterface;
use Crustum\PluginManifest\Manifest\ManifestTrait;

/**
 * Plugin for BlazeCast WebSocket server
 *
 * @uses \Crustum\PluginManifest\Manifest\ManifestTrait
 */
class BlazeCastPlugin extends BasePlugin implements ManifestInterface
{
    use ManifestTrait;

    /**
     * Register container services.
     * Following Phase 2 plan: only app layer services that developers use.
     *
     * @param \Cake\Core\ContainerInterface $container The container to register services with
     * @return void
     */
    public function services(ContainerInterface $container): void
    {
        $container->addShared('blazecast.config', function () {
            return Configure::read('BlazeCast', []);
        });

        $container->addShared(ChannelConnectionManager::class);

        $container->addShared(ApplicationManager::class)
            ->addArgument('blazecast.config');

        $container->addShared(ConnectionRegistry::class)
            ->addArgument(ChannelConnectionManager::class)
            ->addArgument(EventManager::class);

        $container->addShared(EventDispatcherService::class)
            ->addArgument(EventManager::class);

        $container->addShared(EventManager::class, function () {
            return EventManager::instance();
        });
    }

    /**
     * Load all the plugin configuration and bootstrap logic.
     *
     * @param \Cake\Core\PluginApplicationInterface $app The host application
     * @return void
     */
    public function bootstrap(PluginApplicationInterface $app): void
    {
        parent::bootstrap($app);

        Configure::load('Crustum/BlazeCast.blazecast', 'default', false);
    }

    /**
     * Add middleware for the plugin.
     *
     * @param \Cake\Http\MiddlewareQueue $middlewareQueue The middleware queue to update.
     * @return \Cake\Http\MiddlewareQueue
     */
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        return $middlewareQueue;
    }

    /**
     * Add commands for the plugin.
     *
     * @param \Cake\Console\CommandCollection $commands The command collection to update
     * @return \Cake\Console\CommandCollection
     */
    public function console(CommandCollection $commands): CommandCollection
    {
        $commands->add('blazecast server', ServerStartCommand::class);
        $commands->add('blazecast restart_server', RestartServerCommand::class);

        return $commands;
    }

    /**
     * Get the manifest for the plugin.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function manifest(): array
    {
        $pluginPath = dirname(__DIR__);

        return array_merge(
            static::manifestConfig(
                $pluginPath . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'blazecast.php',
                CONFIG . 'blazecast.php',
                false,
            ),
            static::manifestBootstrapAppend(
                "if (file_exists(CONFIG . 'blazecast.php')) {\n    Configure::load('blazecast', 'default');\n}",
                '// BlazeCast Plugin Configuration',
            ),
            static::manifestEnvVars(
                [
                    'BLAZECAST_SERVER' => 'blazecast',
                    'BLAZECAST_SERVER_HOST' => '0.0.0.0',
                    'BLAZECAST_SERVER_PORT' => '8080',
                    'BLAZECAST_APP_ID' => 'app-id',
                    'BLAZECAST_APP_KEY' => 'app-key',
                    'BLAZECAST_APP_SECRET' => 'app-secret',
                    'BLAZECAST_APP_NAME' => 'Default BlazeCast App',
                ],
                '# BlazeCast Configuration',
            ),
            static::manifestStarRepo('Crustum/blazecast'),
        );
    }
}
