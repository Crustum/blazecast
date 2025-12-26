<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Pusher\Channel\Trait;

use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Logger\BlazeCastLogger;
use Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager;
use Crustum\BlazeCast\WebSocket\Pusher\Exception\ConnectionUnauthorizedException;

/**
 * Private Channels
 *
 * Provides authentication for private channels using Pusher protocol.
 */
trait PrivateChannelsTrait
{
    /**
     * Application manager for signature verification
     *
     * @var \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager|null
     */
    protected ?ApplicationManager $applicationManager = null;

    /**
     * Subscribe connection to private channel with authentication
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @param string|null $auth Authentication token
     * @param string|null $data Channel data
     * @return void
     * @throws \Crustum\BlazeCast\WebSocket\Pusher\Exception\ConnectionUnauthorizedException If authentication fails
     */
    public function subscribe(Connection $connection, ?string $auth = null, ?string $data = null): void
    {
        $this->verify($connection, $auth, $data);
        parent::subscribe($connection, $auth, $data);

            BlazeCastLogger::info(__('PrivateChannelsTrait: Connection {0} subscribed to private Pusher channel {1}', $connection->getId(), $this->getName()), [
            'scope' => ['socket.channel', 'socket.channel.private'],
            ]);
    }

    /**
     * Verify authentication for private channel
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection Connection
     * @param string|null $auth Authentication token
     * @param string|null $data Channel data
     * @return bool
     * @throws \Crustum\BlazeCast\WebSocket\Pusher\Exception\ConnectionUnauthorizedException If authentication fails
     */
    protected function verify(Connection $connection, ?string $auth = null, ?string $data = null): bool
    {
        $signature = "{$connection->getId()}:{$this->getName()}";
        if ($data) {
            $signature .= ":{$data}";
        }

        $authParts = explode(':', $auth ?? '', 2);
        if (count($authParts) !== 2) {
            throw new ConnectionUnauthorizedException('Invalid authentication format');
        }

        [$key, $providedSignature] = $authParts;
        $secret = $this->getApplicationSecret($key);

        $expectedSignature = hash_hmac('sha256', $signature, $secret);

        if (!hash_equals($expectedSignature, $providedSignature)) {
            throw new ConnectionUnauthorizedException('Invalid authentication signature');
        }

        return true;
    }

    /**
     * Get application secret for key
     *
     * @param string $key Application key
     * @return string Application secret
     * @throws \Crustum\BlazeCast\WebSocket\Pusher\Exception\ConnectionUnauthorizedException If application not found
     */
    protected function getApplicationSecret(string $key): string
    {
        if (!$this->applicationManager) {
            throw new ConnectionUnauthorizedException('Application manager not available');
        }

        $app = $this->applicationManager->getApplicationByKey($key);
        if (!$app) {
            throw new ConnectionUnauthorizedException('Invalid application key');
        }

        return $app['secret'];
    }

    /**
     * Set application manager
     *
     * @param \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager $applicationManager Application manager
     * @return void
     */
    public function setApplicationManager(ApplicationManager $applicationManager): void
    {
        $this->applicationManager = $applicationManager;
    }

    /**
     * Get application manager
     *
     * @return \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager|null
     */
    public function getApplicationManager(): ?ApplicationManager
    {
        return $this->applicationManager;
    }

    /**
     * Check if client events are allowed on this channel
     *
     * @return bool
     */
    public function allowsClientEvents(): bool
    {
        return true;
    }

    /**
     * Get channel type
     *
     * @return string
     */
    public function getType(): string
    {
        return 'private';
    }
}
