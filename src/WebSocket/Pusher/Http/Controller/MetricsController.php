<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\WebSocket\Pusher\Http\Controller;

use Crustum\BlazeCast\WebSocket\Connection;
use Crustum\BlazeCast\WebSocket\Http\Response;
use Psr\Http\Message\RequestInterface;

/**
 * MetricsController
 *
 * Controller for Prometheus-compatible metrics export
 *
 * @phpstan-import-type RouteParams from \Crustum\BlazeCast\WebSocket\Pusher\Http\Controller\PusherControllerInterface
 */
class MetricsController extends PusherController
{
    /**
     * Handle the request
     *
     * @param \Psr\Http\Message\RequestInterface $request HTTP request
     * @param \Crustum\BlazeCast\WebSocket\Connection $connection WebSocket connection
     * @param RouteParams $params Route parameters
     * @return \Crustum\BlazeCast\WebSocket\Http\Response HTTP response
     */
    public function handle(RequestInterface $request, Connection $connection, array $params): Response
    {
        $metrics = $this->generatePrometheusMetrics();

        return new Response($metrics, 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
        ]);
    }

    /**
     * Generate Prometheus-compatible metrics
     *
     * @return string Prometheus metrics in text format
     */
    protected function generatePrometheusMetrics(): string
    {
        $allDetailedStats = $this->connectionManager->getAllDetailedStats();

        $metrics = [];

        $totalConnections = array_sum(array_column($allDetailedStats, 'connections'));
        $totalHttpRequests = array_sum(array_column($allDetailedStats, 'http_requests'));
        $totalWsMessagesReceived = array_sum(array_column($allDetailedStats, 'ws_messages_received'));
        $totalWsMessagesSent = array_sum(array_column($allDetailedStats, 'ws_messages_sent'));
        $totalBytesReceived = array_sum(array_column($allDetailedStats, 'bytes_received'));
        $totalBytesTransmitted = array_sum(array_column($allDetailedStats, 'bytes_transmitted'));
        $totalHttpBytesReceived = array_sum(array_column($allDetailedStats, 'http_bytes_received'));
        $totalHttpBytesTransmitted = array_sum(array_column($allDetailedStats, 'http_bytes_transmitted'));
        $totalNewConnections = array_sum(array_column($allDetailedStats, 'new_connections'));
        $totalDisconnections = array_sum(array_column($allDetailedStats, 'disconnections'));

        $metrics[] = '# HELP blazecast_connected The number of currently connected sockets';
        $metrics[] = '# TYPE blazecast_connected gauge';
        $metrics[] = 'blazecast_connected ' . $totalConnections;

        $metrics[] = '# HELP blazecast_new_connections_total Total amount of connection requests';
        $metrics[] = '# TYPE blazecast_new_connections_total counter';
        $metrics[] = 'blazecast_new_connections_total ' . $totalNewConnections;

        $metrics[] = '# HELP blazecast_new_disconnections_total Total amount of disconnections';
        $metrics[] = '# TYPE blazecast_new_disconnections_total counter';
        $metrics[] = 'blazecast_new_disconnections_total ' . $totalDisconnections;

        $metrics[] = '# HELP blazecast_socket_received_bytes Total amount of bytes received via WebSocket';
        $metrics[] = '# TYPE blazecast_socket_received_bytes counter';
        $metrics[] = 'blazecast_socket_received_bytes ' . $totalBytesReceived;

        $metrics[] = '# HELP blazecast_socket_transmitted_bytes Total amount of bytes transmitted via WebSocket';
        $metrics[] = '# TYPE blazecast_socket_transmitted_bytes counter';
        $metrics[] = 'blazecast_socket_transmitted_bytes ' . $totalBytesTransmitted;

        $metrics[] = '# HELP blazecast_ws_messages_received_total Total amount of WS messages received from connections';
        $metrics[] = '# TYPE blazecast_ws_messages_received_total counter';
        $metrics[] = 'blazecast_ws_messages_received_total ' . $totalWsMessagesReceived;

        $metrics[] = '# HELP blazecast_ws_messages_sent_total Total amount of WS messages sent to connections';
        $metrics[] = '# TYPE blazecast_ws_messages_sent_total counter';
        $metrics[] = 'blazecast_ws_messages_sent_total ' . $totalWsMessagesSent;

        $metrics[] = '# HELP blazecast_http_received_bytes Total amount of bytes received via HTTP API';
        $metrics[] = '# TYPE blazecast_http_received_bytes counter';
        $metrics[] = 'blazecast_http_received_bytes ' . $totalHttpBytesReceived;

        $metrics[] = '# HELP blazecast_http_transmitted_bytes Total amount of bytes transmitted via HTTP API';
        $metrics[] = '# TYPE blazecast_http_transmitted_bytes counter';
        $metrics[] = 'blazecast_http_transmitted_bytes ' . $totalHttpBytesTransmitted;

        $metrics[] = '# HELP blazecast_http_calls_received_total Total amount of received HTTP API calls';
        $metrics[] = '# TYPE blazecast_http_calls_received_total counter';
        $metrics[] = 'blazecast_http_calls_received_total ' . $totalHttpRequests;

        $metrics[] = '';

        foreach ($allDetailedStats as $appId => $appStats) {
            $appIdEscaped = $this->escapeLabelValue($appId);

            $metrics[] = '# HELP blazecast_connected_per_app The number of currently connected sockets per app';
            $metrics[] = '# TYPE blazecast_connected_per_app gauge';
            $metrics[] = "blazecast_connected_per_app{app_id=\"{$appIdEscaped}\"} " . $appStats['connections'];

            $metrics[] = '# HELP blazecast_new_connections_total_per_app Total amount of connection requests per app';
            $metrics[] = '# TYPE blazecast_new_connections_total_per_app counter';
            $metrics[] = "blazecast_new_connections_total_per_app{app_id=\"{$appIdEscaped}\"} " . $appStats['new_connections'];

            $metrics[] = '# HELP blazecast_new_disconnections_total_per_app Total amount of disconnections per app';
            $metrics[] = '# TYPE blazecast_new_disconnections_total_per_app counter';
            $metrics[] = "blazecast_new_disconnections_total_per_app{app_id=\"{$appIdEscaped}\"} " . $appStats['disconnections'];

            $metrics[] = '# HELP blazecast_socket_received_bytes_per_app Total amount of bytes received via WebSocket per app';
            $metrics[] = '# TYPE blazecast_socket_received_bytes_per_app counter';
            $metrics[] = "blazecast_socket_received_bytes_per_app{app_id=\"{$appIdEscaped}\"} " . $appStats['bytes_received'];

            $metrics[] = '# HELP blazecast_socket_transmitted_bytes_per_app Total amount of bytes transmitted via WebSocket per app';
            $metrics[] = '# TYPE blazecast_socket_transmitted_bytes_per_app counter';
            $metrics[] = "blazecast_socket_transmitted_bytes_per_app{app_id=\"{$appIdEscaped}\"} " . $appStats['bytes_transmitted'];

            $metrics[] = '# HELP blazecast_ws_messages_received_total_per_app Total amount of WS messages received per app';
            $metrics[] = '# TYPE blazecast_ws_messages_received_total_per_app counter';
            $metrics[] = "blazecast_ws_messages_received_total_per_app{app_id=\"{$appIdEscaped}\"} " . $appStats['ws_messages_received'];

            $metrics[] = '# HELP blazecast_ws_messages_sent_total_per_app Total amount of WS messages sent per app';
            $metrics[] = '# TYPE blazecast_ws_messages_sent_total_per_app counter';
            $metrics[] = "blazecast_ws_messages_sent_total_per_app{app_id=\"{$appIdEscaped}\"} " . $appStats['ws_messages_sent'];

            $metrics[] = '# HELP blazecast_http_received_bytes_per_app Total amount of bytes received via HTTP API per app';
            $metrics[] = '# TYPE blazecast_http_received_bytes_per_app counter';
            $metrics[] = "blazecast_http_received_bytes_per_app{app_id=\"{$appIdEscaped}\"} " . $appStats['http_bytes_received'];

            $metrics[] = '# HELP blazecast_http_transmitted_bytes_per_app Total amount of bytes transmitted via HTTP API per app';
            $metrics[] = '# TYPE blazecast_http_transmitted_bytes_per_app counter';
            $metrics[] = "blazecast_http_transmitted_bytes_per_app{app_id=\"{$appIdEscaped}\"} " . $appStats['http_bytes_transmitted'];

            $metrics[] = '# HELP blazecast_http_calls_received_total_per_app Total amount of HTTP API calls per app';
            $metrics[] = '# TYPE blazecast_http_calls_received_total_per_app counter';
            $metrics[] = "blazecast_http_calls_received_total_per_app{app_id=\"{$appIdEscaped}\"} " . $appStats['http_requests'];

            $metrics[] = '# HELP blazecast_subscriptions_total_per_app Total subscriptions per app';
            $metrics[] = '# TYPE blazecast_subscriptions_total_per_app gauge';
            $metrics[] = "blazecast_subscriptions_total_per_app{app_id=\"{$appIdEscaped}\"} " . $appStats['subscriptions'];

            $metrics[] = '';
        }

        return implode("\n", $metrics);
    }

    /**
     * Escape label value for Prometheus format
     *
     * @param string $value Label value to escape
     * @return string Escaped label value
     */
    protected function escapeLabelValue(string $value): string
    {
        return str_replace(['\\', "\n", '"'], ['\\\\', '\\n', '\\"'], $value);
    }
}
