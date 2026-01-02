<?php
/**
 * BlazeCast Connections Widget Template
 *
 * This template follows the Rhythm pattern with Chart.js integration.
 *
 * @var \App\View\AppView $this
 * @var array $config
 * @var object $widget
 * @var string $widgetName
 */
$this->set('widget', $widget);
$this->set('config', $config);
$this->set('widgetName', $widgetName);

$period = (int) $this->getRequest()->getQuery('period', 60);
$options = ['period' => $period];
$sort = $this->get('sort');
if ($sort !== null) {
    $options['sort'] = $sort;
}
$data = $widget->getData($options);
$this->set('data', $data);

$this->extend('Crustum/Rhythm.widgets/widget_base');
$this->start('widget_body');

$hasError = isset($data['error']);
$empty = $data['empty'] ?? true;
$chartData = $data['chartData'] ?? [];
$colors = $data['colors'] ?? [];

$metricGroups = [
    'connections' => [
        'title' => 'Connection Activity',
        'icon' => 'fas fa-plug',
    ],
];

if ($hasError): ?>
    <?= $this->element('Crustum/Rhythm.components/widget_error', ['error' => $data['error']]) ?>
<?php elseif ($empty): ?>
    <?= $this->element('Crustum/Rhythm.components/widget_placeholder', ['message' => 'No BlazeCast connection data recorded.']) ?>
<?php else: ?>
    <?php $allChartConfigs = []; ?>
    <?php foreach ($metricGroups as $group => $meta): ?>
        <?php
        $connections = array_filter(array_keys($chartData), function ($conn) use ($chartData, $group) {
            return array_key_exists($group, $chartData[$conn]) && $chartData[$conn][$group] !== null;
        });
        if (empty($connections)) {
            continue;
        }
        ?>
        <div class="rhythm-mb-lg">
            <h3 class="rhythm-text-lg rhythm-font-bold rhythm-mb-md">
                <i class="<?= $meta['icon'] ?> rhythm-mr-sm"></i><?= $meta['title'] ?>
            </h3>
            <?php foreach ($connections as $connection):
                $chart = $chartData[$connection][$group];
                if (!$chart) continue;
                $hasSeries = false;
                if (!empty($chart['chartData'])) {
                    foreach ($chart['chartData'] as $label => $series) {
                        if (!empty($series)) {
                            $hasSeries = true;
                            break;
                        }
                    }
                }
                $groupColors = $colors[$group] ?? $colors;
            ?>
                <div class="rhythm-mb-md">
                    <h4 class="rhythm-font-semibold rhythm-mb-sm"><?= h($connection) ?></h4>
                    <div class="rhythm-h-32 rhythm-bg-gray-50 rhythm-rounded rhythm-p-2">
                        <div class="chart-container" data-vdom-ignore="true">
                            <?php if ($hasSeries): ?>
                                <canvas data-chart-name="<?= h($chart['chartId']) ?>"></canvas>
                                <?php
                                $chartConfig = $this->Chart->createLineChart(
                                    $chart['chartId'],
                                    $chart['chartData'],
                                    ['colors' => $groupColors]
                                );
                                $allChartConfigs[] = $chartConfig;
                                ?>
                            <?php else: ?>
                                <div class="rhythm-text-sm rhythm-text-secondary rhythm-p-2">No time series data for this metric.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (!empty($chart['legendData'])): ?>
                        <div class="rhythm-flex rhythm-gap-md rhythm-items-center rhythm-flex-wrap rhythm-mt-sm">
                            <?php foreach ($chart['legendData'] as $metricKey => $legend): ?>
                                <?php
                                $metricColor = $groupColors[$metricKey] ?? $groupColors[$legend['label']] ?? $colors['primary'] ?? '#3b82f6';
                                ?>
                                <div class="rhythm-flex rhythm-items-center rhythm-gap-xs">
                                    <span class="rhythm-badge badge-sm" style="background: <?= h($metricColor) ?>; color: #374151;">&nbsp;</span>
                                    <span class="rhythm-text-sm rhythm-text-secondary">
                                        <?= h($legend['label']) ?>: <?= h($legend['total']) ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
    <?php if (!empty($allChartConfigs)): ?>
        <?= $this->Rhythm->renderCharts($allChartConfigs); ?>
    <?php endif; ?>
<?php endif; ?>
<?php $this->end(); ?>
