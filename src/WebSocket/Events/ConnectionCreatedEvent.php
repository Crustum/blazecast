<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Events;

use Cake\Event\Event;
use Crustum\BlazeCast\WebSocket\Connection;

/**
 * Connection Created Event
 *
 * Fired when a new WebSocket connection is established
 */
class ConnectionCreatedEvent extends Event
{
    /**
     * Constructor
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection The connection that was created
     */
    public function __construct(Connection $connection)
    {
        parent::__construct('BlazeCast.ConnectionCreated', $connection, [
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
