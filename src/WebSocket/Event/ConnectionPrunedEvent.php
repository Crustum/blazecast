<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Event;

use Cake\Event\Event;
use Crustum\BlazeCast\WebSocket\Connection;

/**
 * Event triggered when a connection is pruned
 */
class ConnectionPrunedEvent extends Event
{
    /**
     * Name of the event
     *
     * @var string
     */
    protected const EVENT_NAME = 'BlazeCast.WebSocket.connectionPruned';

    /**
     * Constructor
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection WebSocket connection
     * @param string $reason Reason for pruning
     */
    public function __construct(
        protected Connection $connection,
        protected string $reason,
    ) {
        parent::__construct(self::class, null, [
            'connection' => $connection,
            'reason' => $reason,
        ]);
    }

    /**
     * Get the connection
     *
     * @return \Crustum\BlazeCast\WebSocket\Connection
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * Get the reason for pruning
     *
     * @return string
     */
    public function getReason(): string
    {
        return $this->reason;
    }
}
