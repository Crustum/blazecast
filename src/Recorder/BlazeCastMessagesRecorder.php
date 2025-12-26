<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Recorder;

use Cake\Event\EventListenerInterface;
use Crustum\BlazeCast\WebSocket\ConnectionInterface;
use Crustum\BlazeCast\WebSocket\Event\HttpApiEvent;
use Crustum\BlazeCast\WebSocket\Event\MessageReceivedEvent;
use Crustum\BlazeCast\WebSocket\Event\MessageSentEvent;
use Crustum\BlazeCast\WebSocket\Logger\BlazeCastLogger;
use Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager;
use Rhythm\Event\SharedBeat;
use Rhythm\Recorder\BaseRecorder;
use Rhythm\Recorder\Trait\IgnoresTrait;
use Rhythm\Recorder\Trait\SamplingTrait;
use Rhythm\Rhythm;

/**
 * BlazeCast Messages Recorder
 *
 * Records message sent/received events for Rhythm metrics.
 * Listens to BlazeCast message events and records metrics with application ID as key.
 *
 * @phpstan-import-type ApplicationConfig from \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager
 * @phpstan-type BlazeCastMessagesRecorderConfig array{
 *   enabled: bool,
 *   sample_rate: float,
 *   className: string,
 * }
 */
class BlazeCastMessagesRecorder extends BaseRecorder implements EventListenerInterface
{
    use SamplingTrait;
    use IgnoresTrait;

    /**
     * Application manager.
     *
     * @var \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager
     */
    protected ApplicationManager $applicationManager;

    /**
     * Channel connection manager.
     *
     * @var \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager
     */
    protected ChannelConnectionManager $connectionManager;

    /**
     * Constructor.
     *
     * @param \Rhythm\Rhythm $rhythm Rhythm instance
     * @param \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager $applicationManager Application manager
     * @param \Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager $connectionManager Connection manager
     * @param BlazeCastMessagesRecorderConfig|array{} $config Configuration array
     */
    public function __construct(Rhythm $rhythm, ApplicationManager $applicationManager, ChannelConnectionManager $connectionManager, array $config = [])
    {
        parent::__construct($rhythm, $config);
        $this->applicationManager = $applicationManager;
        $this->connectionManager = $connectionManager;
    }

    /**
     * Record metric data.
     *
     * @param mixed $data Data to record
     * @return void
     */
    public function record(mixed $data): void
    {
        if (!$this->shouldSample()) {
            return;
        }

        if ($data instanceof MessageSentEvent) {
            $this->recordMessageSent($data);
        } elseif ($data instanceof MessageReceivedEvent) {
            $this->recordMessageReceived($data);
        } elseif ($data instanceof HttpApiEvent) {
            $this->recordHttpApiEvent($data);
        } elseif ($data instanceof SharedBeat) {
            $this->recordHttpRequests($data);
        }
    }

    /**
     * Events this listener is interested in.
     *
     * @return array<string, mixed>
     */
    public function implementedEvents(): array
    {
        return [
            MessageSentEvent::class => 'record',
            MessageReceivedEvent::class => 'record',
            HttpApiEvent::class => 'record',
            // SharedBeat::class => 'record',
        ];
    }

    /**
     * Record message sent event.
     *
     * @param \Crustum\BlazeCast\WebSocket\Event\MessageSentEvent $event Event instance
     * @return void
     */
    protected function recordMessageSent(MessageSentEvent $event): void
    {
        $this->recordMessage('sent', $event->getConnection());
    }

    /**
     * Record message received event.
     *
     * @param \Crustum\BlazeCast\WebSocket\Event\MessageReceivedEvent $event Event instance
     * @return void
     */
    protected function recordMessageReceived(MessageReceivedEvent $event): void
    {
        $this->recordMessage('received', $event->getConnection());
    }

    /**
     * Record HTTP API event.
     *
     * @param \Crustum\BlazeCast\WebSocket\Event\HttpApiEvent $event Event instance
     * @return void
     */
    protected function recordHttpApiEvent(HttpApiEvent $event): void
    {
        BlazeCastLogger::info(sprintf('BlazeCastMessagesRecorder: Record HTTP API event. app_id=%s, event_type=%s, bytes=%d', $event->getAppId(), $event->getEventType(), $event->getBytes()), [
            'scope' => ['rhythm.recorder', 'rhythm.recorder.messages'],
        ]);

        $this->recordMessageForApp($event->getAppId(), 'received', $event->getBytes());
    }

    /**
     * Record a message event with direction.
     *
     * @param string $direction 'sent' or 'received'
     * @param \Crustum\BlazeCast\WebSocket\ConnectionInterface $connection Connection instance
     * @return void
     */
    protected function recordMessage(string $direction, ConnectionInterface $connection): void
    {
        $appId = $this->getApplicationId($connection);

        if (!$appId || $appId === 'unknown') {
            return;
        }

        $messageType = 'blazecast_message:' . $direction;

        if ($this->shouldIgnore($messageType)) {
            return;
        }

        $this->rhythm->record(
            type: $messageType,
            key: $appId,
            timestamp: time(),
        )
           ->onlyBuckets()
           ->count();
    }

    /**
     * Record a message event for a specific app.
     *
     * @param string $appId Application ID
     * @param string $direction 'sent' or 'received'
     * @param int $bytes Number of bytes
     * @return void
     */
    protected function recordMessageForApp(string $appId, string $direction, int $bytes): void
    {
        $messageType = 'blazecast_message:' . $direction;

        if ($this->shouldIgnore($messageType)) {
            return;
        }

        $this->rhythm->record(
            type: $messageType,
            key: $appId,
            timestamp: time(),
        )
           ->onlyBuckets()
           ->count();
    }

    /**
     * Record HTTP requests for all apps.
     *
     * @param \Rhythm\Event\SharedBeat $event SharedBeat event
     * @return void
     */
    protected function recordHttpRequests(SharedBeat $event): void
    {
        $throttle = $this->config['throttle_seconds'] ?? 15;
        if ($event->getTimestamp()->getTimestamp() % $throttle !== 0) {
            return;
        }

        $allAppStats = $this->connectionManager->getAllAppStats();

        foreach ($allAppStats as $appId => $appStats) {
            if ($this->shouldIgnore($appId)) {
                continue;
            }

            $httpRequests = $appStats['http_requests'];

            if ($httpRequests > 0) {
                $this->rhythm->record(
                    'blazecast_message:sent',
                    $appId,
                    $event->getTimestamp()->getTimestamp(),
                )
                   ->onlyBuckets()
                   ->count();
            }
        }
    }

    /**
     * Get application ID from connection.
     *
     * @param \Crustum\BlazeCast\WebSocket\ConnectionInterface $connection Connection instance
     * @return string Application ID
     */
    protected function getApplicationId(ConnectionInterface $connection): string
    {
        $appId = $connection->getAttribute('app_id');

        if ($appId) {
            return (string)$appId;
        }

        return 'unknown';
    }
}
