<?php

namespace App\Filament\Widgets;

use App\Models\Quotation;
use Filament\Widgets\ChartWidget;

class WinLoseCategoryChart extends ChartWidget
{
    protected static ?string $heading = 'Analisis Rasio Win vs Lose per Kategori Part';
    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $categories = [
            'SPAREPART_JUNGHEINRICH' => 'Sparepart Jungheinrich',
            'TYRE_FORKLIFT' => 'Tyre Forklift',
            'TYRE_TRUCK_TIRON' => 'Ban Truk Tiron',
            'MIXED' => 'Mixed / Campuran',
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
                    'label' => 'Menang (WIN / PO)',
                    'data' => $winData,
                    'backgroundColor' => 'rgba(16, 185, 129, 0.8)',
                    'borderColor' => 'rgb(16, 185, 129)',
                ],
                [
                    'label' => 'Kalah (LOSE)',
                    'data' => $loseData,
                    'backgroundColor' => 'rgba(239, 68, 68, 0.8)',
                    'borderColor' => 'rgb(239, 68, 68)',
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
