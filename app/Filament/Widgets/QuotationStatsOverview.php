<?php

namespace App\Filament\Widgets;

use App\Models\PurchaseOrder;
use App\Models\Quotation;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class QuotationStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $activeQuotes = Quotation::whereIn('status', ['DRAFT', 'SENT', 'FOLLOW_UP']);
        $activeCount = $activeQuotes->count();
        $activeAmount = $activeQuotes->sum('total_amount');

        $winQuotes = Quotation::where('status', 'WIN');
        $winCount = $winQuotes->count();
        $winAmount = PurchaseOrder::sum('po_amount');

        $loseCount = Quotation::where('status', 'LOSE')->count();
        $decidedTotal = $winCount + $loseCount;
        $winRate = $decidedTotal > 0 ? round(($winCount / $decidedTotal) * 100, 1) : 0;

        $urgentCount = Quotation::whereIn('status', ['SENT', 'FOLLOW_UP'])
            ->where('valid_until', '<=', Carbon::now()->addDays(3))
            ->count();

        return [
            Stat::make('Pipeline Aktif', "{$activeCount} Quote")
                ->description('Total Nilai: Rp ' . number_format($activeAmount, 0, ',', '.'))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('primary'),

            Stat::make('Penawaran Menang (WIN)', "{$winCount} Quote")
                ->description('Total PO Masuk: Rp ' . number_format($winAmount, 0, ',', '.'))
                ->descriptionIcon('heroicon-m-trophy')
                ->color('success'),

            Stat::make('Win / Lose Ratio', "{$winRate}% Win Rate")
                ->description("{$winCount} Menang vs {$loseCount} Kalah")
                ->descriptionIcon($winRate >= 50 ? 'heroicon-m-hand-thumb-up' : 'heroicon-m-scale')
                ->color($winRate >= 50 ? 'success' : ($winRate > 0 ? 'warning' : 'gray')),

            Stat::make('Perlu Tindak Lanjut', "{$urgentCount} Quote")
                ->description('Masa berlaku ≤ 3 hari / Expired')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color($urgentCount > 0 ? 'danger' : 'success'),
        ];
    }
}
