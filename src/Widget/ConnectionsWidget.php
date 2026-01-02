<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Widget;

use Crustum\Rhythm\Widget\BaseWidget;
use Crustum\Rhythm\Widget\Trait\WidgetChartFormattingTrait;
use Crustum\Rhythm\Widget\Trait\WidgetSamplingTrait;

class ConnectionsWidget extends BaseWidget
{
    use WidgetSamplingTrait;
    use WidgetChartFormattingTrait;

    /**
     * Get data for the widget
     *
     * @param array<string, mixed> $options Widget options (period, sort, etc.)
     * @return array<string, mixed>
     */
    public function getData(array $options = []): array
    {
        $period = $options['period'] ?? 60;

        return $this->remember(function () use ($period) {
            $avgConnections = $this->rhythm->getStorage()->graph(
                ['blazecast_connections'],
                'avg',
                $period,
            );

            $maxConnections = $this->rhythm->getStorage()->graph(
                ['blazecast_connections'],
                'max',
                $period,
            );

            $labels = [
                'connections' => [
                    'blazecast_connections:avg' => 'Active Connections',
                    'blazecast_connections:max' => 'Peak Connections',
                ],
            ];

            $this->unpackGraphData($avgConnections);
            $this->unpackGraphData($maxConnections);

            $seriesData = [
                'connections' => $this->combineConnectionData($avgConnections, $maxConnections),
            ];

            $chartData = $this->prepareWidgetChartData($seriesData, $labels, 'blazecast_connections');
            $mappedColors = $this->mapColorsWithLabels($this->getChartColors(), $labels);

            $empty = empty($avgConnections) && empty($maxConnections);

            return [
                'empty' => $empty,
                'chartData' => $chartData,
                'colors' => $mappedColors,
                'period' => $period,
            ];
        }, 'blazecast_connections_widget_' . $period, $this->getRefreshInterval());
    }

    /**
     * Combine avg and max connection data for chart display
     *
     * @param mixed $avgData Average connection data
     * @param mixed $maxData Maximum connection data
     * @return array<string, mixed> Combined data
     */
    protected function combineConnectionData(mixed $avgData, mixed $maxData): array
    {
        $combined = [];
        $allKeys = array_unique(array_merge(array_keys($avgData), array_keys($maxData)));

        foreach ($allKeys as $key) {
            $combined[$key] = [];

            if (isset($avgData[$key])) {
                $avgKeyData = $avgData[$key];
                if (isset($avgKeyData['blazecast_connections'])) {
                    $combined[$key]['blazecast_connections:avg'] = $avgKeyData['blazecast_connections'];
                }
            }

            if (isset($maxData[$key])) {
                $maxKeyData = $maxData[$key];
                if (isset($maxKeyData['blazecast_connections'])) {
                    $combined[$key]['blazecast_connections:max'] = $maxKeyData['blazecast_connections'];
                }
            }
        }

        return $combined;
    }

    /**
     * Get chart colors configuration
     *
     * @return array<string, array<string, string>>
     */
    protected function getChartColors(): array
    {
        return [
            'connections' => [
                'blazecast_connections:avg' => '#10b981',
                'blazecast_connections:max' => '#9333ea',
            ],
        ];
    }

    /**
     * Get recorder name for sampling
     *
     * @return string
     */
    public function getRecorderName(): string
    {
        return 'connections';
    }

    /**
     * Get template name
     *
     * @return string
     */
    public function getTemplate(): string
    {
        return 'Crustum/BlazeCast.widgets/connections';
    }

    /**
     * Get refresh interval in seconds
     *
     * @return int
     */
    public function getRefreshInterval(): int
    {
        return 60;
    }

    /**
     * Get default icon for this widget
     *
     * @return string|null
     */
    protected function getDefaultIcon(): ?string
    {
        return 'fas fa-plug';
    }
}
