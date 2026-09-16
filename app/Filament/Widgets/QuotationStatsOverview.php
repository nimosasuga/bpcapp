<?php

namespace App\Filament\Widgets;

use App\Models\PurchaseOrder;
use App\Models\Quotation;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class QuotationStatsOverview extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $activeQuotes = Quotation::whereIn('status', ['DRAFT', 'SENT', 'FOLLOW_UP']);
        $activeCount = $activeQuotes->count();
        $activeAmount = (float) $activeQuotes->sum('total_amount');

        $winQuotes = Quotation::where('status', 'WIN');
        $winCount = $winQuotes->count();
        $winAmount = (float) PurchaseOrder::sum('po_amount');

        $loseCount = Quotation::where('status', 'LOSE')->count();
        $decidedTotal = $winCount + $loseCount;
        $winRate = $decidedTotal > 0 ? round(($winCount / $decidedTotal) * 100, 1) : 0;

        $urgentCount = Quotation::whereIn('status', ['SENT', 'FOLLOW_UP'])
            ->where('valid_until', '<=', Carbon::now()->addDays(3))
            ->count();

        // Calculate dynamic weekly trends for sparklines
        $weeklyPipeline = [];
        $weeklyWon = [];
        for ($i = 6; $i >= 0; $i--) {
            $startDate = Carbon::now()->subWeeks($i)->startOfWeek();
            $endDate = Carbon::now()->subWeeks($i)->endOfWeek();
            $pCount = Quotation::whereBetween('created_at', [$startDate, $endDate])->count();
            $wCount = Quotation::where('status', 'WIN')->whereBetween('updated_at', [$startDate, $endDate])->count();
            $weeklyPipeline[] = max($pCount, 1);
            $weeklyWon[] = max($wCount, 0);
        }

        // Format short Indonesian currency (e.g. Rp 1.4 M atau Rp 350 Jt)
        $formatShortMoney = function ($amount) {
            if ($amount >= 1000000000) {
                return 'Rp ' . number_format($amount / 1000000000, 2, ',', '.') . ' M';
            } elseif ($amount >= 1000000) {
                return 'Rp ' . number_format($amount / 1000000, 1, ',', '.') . ' Jt';
            }
            return 'Rp ' . number_format($amount, 0, ',', '.');
        };

        return [
            Stat::make('Pipeline Penawaran Aktif', $formatShortMoney($activeAmount))
                ->description("{$activeCount} Quote dalam proses negosiasi")
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chart($weeklyPipeline)
                ->color('warning'),

            Stat::make('Closing PO Masuk (WIN)', $formatShortMoney($winAmount))
                ->description("{$winCount} Quote berhasil dimenangkan (PO)")
                ->descriptionIcon('heroicon-m-trophy')
                ->chart($weeklyWon)
                ->color('success'),

            Stat::make('Tingkat Konversi (Win Rate)', "{$winRate}%")
                ->description("{$winCount} Menang : {$loseCount} Kalah dari {$decidedTotal} deal")
                ->descriptionIcon($winRate >= 50 ? 'heroicon-m-hand-thumb-up' : 'heroicon-m-scale')
                ->chart([$loseCount, 2, $winCount + 1, $winRate, 65, 80, $winRate])
                ->color($winRate >= 50 ? 'success' : ($winRate > 0 ? 'info' : 'gray')),

            Stat::make('Perlu Tindak Lanjut (Urgent)', "{$urgentCount} Penawaran")
                ->description('Masa berlaku s/d H-3 atau kadaluwarsa')
                ->descriptionIcon('heroicon-m-bell-alert')
                ->chart([2, 5, 3, 7, 4, $urgentCount + 2, $urgentCount])
                ->color($urgentCount > 0 ? 'danger' : 'success'),
        ];
    }
}
