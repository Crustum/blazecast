<?php
declare(strict_types=1);

namespace Crustum\BlazeCast\Widget;

use Crustum\Rhythm\Widget\BaseWidget;
use Crustum\Rhythm\Widget\Trait\WidgetChartFormattingTrait;
use Crustum\Rhythm\Widget\Trait\WidgetSamplingTrait;

class MessagesWidget extends BaseWidget
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
            $messages = $this->rhythm->getStorage()->graph(
                ['blazecast_message:sent', 'blazecast_message:received'],
                'count',
                $period,
            );

            $this->unpackGraphData($messages);

            $labels = [
                'messages' => [
                    'blazecast_message:sent' => 'Sent Messages',
                    'blazecast_message:received' => 'Received Messages',
                ],
            ];

            $seriesData = [
                'messages' => $messages,
            ];

            $chartData = $this->prepareWidgetChartData($seriesData, $labels, 'blazecast_messages');
            $mappedColors = $this->mapColorsWithLabels($this->getChartColors(), $labels);

            $empty = empty($messages);

            return [
                'empty' => $empty,
                'chartData' => $chartData,
                'colors' => $mappedColors,
                'period' => $period,
            ];
        }, 'blazecast_messages_widget_' . $period, $this->getRefreshInterval());
    }

    /**
     * Get chart colors configuration
     *
     * @return array<string, array<string, string>>
     */
    protected function getChartColors(): array
    {
        return [
            'messages' => [
                'blazecast_message:sent' => '#9333ea',
                'blazecast_message:received' => '#10b981',
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
        return 'messages';
    }

    /**
     * Get template name
     *
     * @return string
     */
    public function getTemplate(): string
    {
        return 'BlazeCast.widgets/messages';
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
        return 'fas fa-comments';
    }
}
