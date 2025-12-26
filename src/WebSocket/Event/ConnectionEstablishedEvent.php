<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Event;

use Cake\Event\Event;
use Crustum\BlazeCast\WebSocket\Connection;

/**
 * Dispatched when a new WebSocket connection is established.
 */
class ConnectionEstablishedEvent extends Event
{
    /**
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection The new connection.
     */
    public function __construct(public Connection $connection)
    {
        parent::__construct(self::class, $connection, ['connection' => $connection]);
    }
}
