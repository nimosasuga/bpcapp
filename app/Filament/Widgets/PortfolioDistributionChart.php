<?php

namespace App\Filament\Widgets;

use App\Models\Quotation;
use Filament\Widgets\ChartWidget;

class PortfolioDistributionChart extends ChartWidget
{
    protected static ?string $heading = 'Distribusi Portofolio Penawaran Part';
    protected static ?int $sort = 4;
    protected int | string | array $columnSpan = [
        'default' => 'full',
        'lg' => 1,
    ];

    protected function getData(): array
    {
        $categories = [
            'SPAREPART_JUNGHEINRICH' => 'Sparepart Jungheinrich',
            'TYRE_FORKLIFT' => 'Tyre Forklift (All Brands)',
            'TYRE_TRUCK_TIRON' => 'Ban Truk Tiron',
            'MIXED' => 'Mixed / Campuran',
        ];

        $counts = [];
        $labels = [];

        foreach ($categories as $key => $label) {
            $labels[] = $label;
            $counts[] = Quotation::where('category', $key)->count();
        }

        // If total count is 0, give sample weights
        if (array_sum($counts) === 0) {
            $counts = [5, 3, 2, 1];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Jumlah Penawaran',
                    'data' => $counts,
                    'backgroundColor' => [
                        '#F59E0B', // Amber (Jungheinrich)
                        '#3B82F6', // Blue (Forklift Tyre)
                        '#10B981', // Emerald (Tiron Truck)
                        '#8B5CF6', // Purple (Mixed)
                    ],
                    'borderWidth' => 2,
                    'borderColor' => '#1e293b',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
