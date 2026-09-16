<?php

namespace App\Filament\Widgets;

use App\Models\Quotation;
use Filament\Widgets\ChartWidget;

class WinLoseCategoryChart extends ChartWidget
{
    protected static ?string $heading = 'Evaluasi Menang vs Kalah per Kategori';
    protected static ?int $sort = 7;
    protected int | string | array $columnSpan = [
        'default' => 'full',
        'lg' => 1,
    ];

    protected function getData(): array
    {
        $categories = [
            'SPAREPART_JUNGHEINRICH' => 'Jungheinrich',
            'TYRE_FORKLIFT' => 'Tyre Forklift',
            'TYRE_TRUCK_TIRON' => 'Ban Tiron',
            'MIXED' => 'Campuran',
        ];

        $winData = [];
        $loseData = [];
        $labels = [];

        foreach ($categories as $key => $label) {
            $labels[] = $label;
            $winData[] = Quotation::where('category', $key)->where('status', 'WIN')->count();
            $loseData[] = Quotation::where('category', $key)->where('status', 'LOSE')->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Menang (PO)',
                    'data' => $winData,
                    'backgroundColor' => 'rgba(16, 185, 129, 0.85)',
                    'borderColor' => 'rgb(16, 185, 129)',
                    'borderRadius' => 6,
                ],
                [
                    'label' => 'Kalah (LOSE)',
                    'data' => $loseData,
                    'backgroundColor' => 'rgba(239, 68, 68, 0.85)',
                    'borderColor' => 'rgb(239, 68, 68)',
                    'borderRadius' => 6,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
