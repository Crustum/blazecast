<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Recorder;

use Cake\Event\EventListenerInterface;
use Crustum\BlazeCast\WebSocket\Logger\BlazeCastLogger;
use Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager;
use Crustum\BlazeCast\WebSocket\Pusher\Manager\ChannelConnectionManager;
use Rhythm\Event\SharedBeat;
use Rhythm\Recorder\BaseRecorder;
use Rhythm\Recorder\Trait\IgnoresTrait;
use Rhythm\Recorder\Trait\SamplingTrait;
use Rhythm\Rhythm;

/**
 * BlazeCast Connections Recorder
 *
 * Records active connection counts for Rhythm metrics.
 * Listens to Rhythm\Event\SharedBeat for periodic recording every 15 seconds.
 * Works directly with WebSocket server objects instead of HTTP requests.
 *
 * @phpstan-import-type ApplicationConfig from \Crustum\BlazeCast\WebSocket\Pusher\ApplicationManager
 * @phpstan-type BlazeCastConnectionsRecorderConfig array{
 *   enabled: bool,
 *   sample_rate: float,
 *   throttle_seconds: int,
 *   className: string,
 * }
 */
class BlazeCastConnectionsRecorder extends BaseRecorder implements EventListenerInterface
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
     * @param BlazeCastConnectionsRecorderConfig|array{} $config Configuration array
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

        if ($data instanceof SharedBeat) {
            $this->recordConnectionCount($data);
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
            SharedBeat::class => 'record',
        ];
    }

    /**
     * Record connection count with throttling.
     *
     * @param \Rhythm\Event\SharedBeat $event SharedBeat event
     * @return void
     */
    protected function recordConnectionCount(SharedBeat $event): void
    {
        $throttle = $this->config['throttle_seconds'] ?? 15;
        if ($event->getTimestamp()->getTimestamp() % $throttle !== 0) {
            return;
        }

        $allAppStats = $this->connectionManager->getAllAppStats();

        $allAppStatsJson = json_encode($allAppStats);
        BlazeCastLogger::info(sprintf('BlazeCastConnectionsRecorder: Recording per-app stats: all_app_stats=%s', $allAppStatsJson), [
            'scope' => ['rhythm.recorder', 'rhythm.recorder.connections'],
        ]);

        foreach ($allAppStats as $appId => $appStats) {
            if ($this->shouldIgnore($appId)) {
                continue;
            }

            $connectionsJson = json_encode($appStats['connections']);
            BlazeCastLogger::info(sprintf('BlazeCastConnectionsRecorder: Recording for app %s with connections %s', $appId, $connectionsJson), [
                'scope' => ['rhythm.recorder', 'rhythm.recorder.connections'],
            ]);

            $this->rhythm->record(
                'blazecast_connections',
                $appId,
                $appStats['connections'],
                $event->getTimestamp()->getTimestamp(),
            )
               ->onlyBuckets()
               ->avg()
               ->max();
        }

        $this->rhythm->ingest();

        $allAppStatsJson = json_encode($allAppStats);
        BlazeCastLogger::info(sprintf('BlazeCastConnectionsRecorder: Recorded connection counts for all applications %s', $allAppStatsJson), [
            'scope' => ['rhythm.recorder', 'rhythm.recorder.connections'],
        ]);
    }
}
