<?php

namespace App\Filament\Widgets;

use App\Models\PurchaseOrder;
use App\Models\Quotation;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;

class MonthlyPipelinePerformanceChart extends ChartWidget
{
    protected static ?string $heading = 'Tren Kinerja Sales Pipeline vs Realisasi PO (6 Bulan Terakhir)';
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = [
        'default' => 'full',
        'lg' => 2,
    ];

    protected function getData(): array
    {
        $months = [];
        $pipelineData = [];
        $wonData = [];

        Carbon::setLocale('id');

        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $monthName = $date->isoFormat('MMM Y');
            $months[] = $monthName;

            $start = $date->copy()->startOfMonth();
            $end = $date->copy()->endOfMonth();

            // Total Nilai Quotation (dalam Juta Rupiah)
            $pipelineAmount = Quotation::whereBetween('quotation_date', [$start, $end])->sum('total_amount');
            $pipelineData[] = round($pipelineAmount / 1000000, 1);

            // Total Nilai PO Masuk (dalam Juta Rupiah)
            $wonAmount = PurchaseOrder::whereBetween('po_date', [$start, $end])->sum('po_amount');
            $wonData[] = round($wonAmount / 1000000, 1);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Total Nilai Penawaran Dikirim (Juta Rp)',
                    'data' => $pipelineData,
                    'borderColor' => 'rgb(245, 158, 11)', // Kobexindo Amber
                    'backgroundColor' => 'rgba(245, 158, 11, 0.15)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
                [
                    'label' => 'Total Realisasi PO Dimenangkan (Juta Rp)',
                    'data' => $wonData,
                    'borderColor' => 'rgb(16, 185, 129)', // Emerald
                    'backgroundColor' => 'rgba(16, 185, 129, 0.15)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
            ],
            'labels' => $months,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
