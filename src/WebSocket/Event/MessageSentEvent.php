<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Event;

use Cake\Event\Event;
use Crustum\BlazeCast\WebSocket\Connection;

/**
 * Event triggered when a message is sent to a connection
 */
class MessageSentEvent extends Event
{
    /**
     * Name of the event
     *
     * @var string
     */
    protected const EVENT_NAME = 'BlazeCast.WebSocket.messageSent';

    /**
     * Constructor
     *
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection WebSocket connection
     * @param string $message Message that was sent
     */
    public function __construct(
        protected Connection $connection,
        protected string $message,
    ) {
        parent::__construct(self::class, null, [
            'connection' => $connection,
            'message' => $message,
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
     * Get the message
     *
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }
}
