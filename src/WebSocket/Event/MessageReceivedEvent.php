<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Event;

use Cake\Event\Event;
use Crustum\BlazeCast\WebSocket\Connection;

/**
 * Event triggered when a raw message is received from a client
 */
class MessageReceivedEvent extends Event
{
    /**
     * Constructor
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection WebSocket connection
     * @param string $data Raw message data
     */
    public function __construct(Connection $connection, string $data)
    {
        parent::__construct(self::class, null, [
            'connection' => $connection,
            'data' => $data,
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

    /**
     * Get the raw message data
     *
     * @param string|null $key Not used, kept for compatibility with parent
     * @return mixed
     */
    // public function getData(?string $key = null): mixed
    // {
    //     if ($key === null) {
    //         return parent::getData('data');
    //     }

    //     return parent::getData($key);
    // }
}
