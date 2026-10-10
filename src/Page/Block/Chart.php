<?php

declare(strict_types=1);

namespace NeoPHP\NeoAdmin\Page\Block;

use NeoPHP\NeoAdmin\Page\Column;
use NeoPHP\NeoAdmin\Page\PageContext;

class Chart extends AbstractBlock
{
    public const TICKS = 7;

    public function __construct(
        protected string $title,
        protected array $rows,
        protected array $series,
        protected ?string $icon = 'chart',
        protected ?string $empty = null,
        protected ?string $axisFormat = null,
        protected ?string $tooltipFormat = null,
    ) {
    }

    public function dates(string $axis = 'd/m', string $tooltip = 'D d/m'): static
    {
        $this->axisFormat = $axis;
        $this->tooltipFormat = $tooltip;

        return $this;
    }

    protected function getTemplate(): string
    {
        return '@admin/components/chart.html.twig';
    }

    protected function getContext(PageContext $context): array
    {
        $keys = array_map('strval', array_keys($this->series));
        $main = $keys[0] ?? 'value';
        $second = $keys[1] ?? null;
        $count = count($this->rows);
        $max = 0;

        foreach ($this->rows as $row) {
            $max = max($max, (float) (is_array($row) || is_object($row) ? Column::read($row, $main) : $row));
        }

        $unit = $max >= 1 ? 10 ** (strlen((string) (int) ceil($max)) - 1) : 1;
        $top = (int) (ceil($max / $unit) * $unit);
        $top = max(2, $top % 2 === 1 ? $top + $unit : $top);
        $step = max(1, (int) ceil($count / self::TICKS));
        $dates = $this->axisFormat !== null || ($count > 0 && PageContext::isDate((string) array_key_first($this->rows)));
        $axisFormat = $this->axisFormat ?? 'd/m';
        $tooltipFormat = $this->tooltipFormat ?? 'D d/m';
        $bars = [];
        $index = 0;

        foreach ($this->rows as $label => $row) {
            ++$index;
            $value = (float) (is_array($row) || is_object($row) ? Column::read($row, $main) : $row);
            $other = $second !== null ? (float) Column::read($row, $second) : null;
            $values = [['label' => (string) $this->series[$main], 'value' => $context->formatNumber($value, floor($value) == $value ? 0 : 1), 'second' => false]];

            if ($second !== null) {
                $values[] = ['label' => (string) $this->series[$second], 'value' => $context->formatNumber((float) $other, floor((float) $other) == $other ? 0 : 1), 'second' => true];
            }

            $bars[] = [
                'height' => round($value / $top * 100, 2),
                'second' => $second !== null && $value > 0 ? round(min($value, (float) $other) / $value * 100, 2) : 0,
                'title' => $dates ? $context->formatDate($label, $tooltipFormat) : (string) $label,
                'axis' => ($count - $index) % $step === 0 ? ($dates ? $context->formatDate($label, $axisFormat) : (string) $label) : '',
                'values' => $values,
            ];
        }

        return [
            'title' => $this->title,
            'icon' => $this->icon,
            'legend' => array_values(array_map('strval', $this->series)),
            'bars' => $bars,
            'dense' => $count > 30,
            'ticks' => [
                ['label' => $context->formatNumber($top), 'position' => 100],
                ['label' => $context->formatNumber($top / 2, ($top / 2) == (int) ($top / 2) ? 0 : 1), 'position' => 50],
                ['label' => '0', 'position' => 0],
            ],
            'is_empty' => $max <= 0,
            'empty' => $this->empty ?? 'Nothing here yet.',
            'framed' => $context->isFramed(),
        ];
    }
}