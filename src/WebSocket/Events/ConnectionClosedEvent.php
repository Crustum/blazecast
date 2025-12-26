<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Events;

use Cake\Event\Event;
use Crustum\BlazeCast\WebSocket\Connection;

/**
 * Connection Closed Event
 *
 * Fired when a WebSocket connection is closed
 */
class ConnectionClosedEvent extends Event
{
    /**
     * Constructor
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection The connection that was closed
     */
    public function __construct(Connection $connection)
    {
        parent::__construct('BlazeCast.ConnectionClosed', $connection, [
            'connection' => $connection,
        ]);
    }

    /**
     * Get the connection
     *
     * @return \Crustum\BlazeCast\WebSocket\Connection
     */
    public function getConnection(): Connection
    {
        return $this->getData('connection');
    }
}
