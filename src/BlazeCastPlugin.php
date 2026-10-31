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

/**
 * Plugin for BlazeCast WebSocket server
 */
class BlazeCastPlugin extends BasePlugin
{
    /**
     * Reserved payload key stripped before WebSocket client delivery.
     *
     * @var string
     */
    public const RESERVED_META_KEY = '__crustum';

    /**
     * Cap for connection id samples on after-client-send events.
     *
     * @var int
     */
    public const CLIENT_SEND_CONNECTION_ID_CAP = 5;

    /**
     * Register container services.
     * Following Phase 2 plan: only app layer services that developers use.
     *
     * @param \Cake\Core\ContainerInterface $container The container to register services with
     * @return void
     */
    public function services(ContainerInterface $container): void
    {
        $container->addShared('blazecast.config', fn(): mixed => Configure::read('BlazeCast', []));

        $container->addShared(ChannelConnectionManager::class);

        $container->addShared(ApplicationManager::class)
            ->addArgument('blazecast.config');

        $container->addShared(ConnectionRegistry::class)
            ->addArgument(ChannelConnectionManager::class)
            ->addArgument(EventManager::class);

        $container->addShared(EventDispatcherService::class)
            ->addArgument(EventManager::class);

        $container->addShared(EventManager::class, fn(): EventManager => EventManager::instance());
    }

    /**
     * Load all the plugin configuration and bootstrap logic.
     *
     * @param \Cake\Core\PluginApplicationInterface $app The host application
     * @return void
     */
    public function bootstrap(PluginApplicationInterface $app): void
    {
        if (!Configure::check('BlazeCast')) {
            if (file_exists(CONFIG . 'blazecast.php')) {
                Configure::load('blazecast', 'default');
            } elseif (file_exists($this->getConfigPath() . 'blazecast.php')) {
                Configure::load('Crustum/BlazeCast.blazecast', 'default', false);
            }
        }

        parent::bootstrap($app);
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
}
