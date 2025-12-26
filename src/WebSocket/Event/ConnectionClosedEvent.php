<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Event;

use Cake\Event\Event;
use Crustum\BlazeCast\WebSocket\Connection;

/**
 * Dispatched when a WebSocket connection is closed.
 */
class ConnectionClosedEvent extends Event
{
    /**
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection The closed connection.
     */
    public function __construct(public Connection $connection)
    {
        parent::__construct(self::class, $connection, ['connection' => $connection]);
    }
}
