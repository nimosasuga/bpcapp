<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\PurchaseOrderResource;
use App\Filament\Resources\QuotationResource;
use App\Models\PurchaseOrder;
use App\Models\Quotation;
use Carbon\Carbon;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class SalesHeroBannerWidget extends Widget
{
    protected static string $view = 'filament.widgets.sales-hero-banner';
    protected static ?int $sort = 1;
    protected int | string | array $columnSpan = 'full';

    public function getViewData(): array
    {
        $user = Auth::user();
        $userName = $user ? $user->name : 'Sales Part Consultant';

        $hour = (int) date('H');
        $greeting = match (true) {
            $hour >= 4 && $hour < 11 => 'Selamat Pagi',
            $hour >= 11 && $hour < 15 => 'Selamat Siang',
            $hour >= 15 && $hour < 18 => 'Selamat Sore',
            default => 'Selamat Malam',
        };

        Carbon::setLocale('id');
        $formattedDate = Carbon::now()->isoFormat('dddd, D MMMM Y');

        $activeQuotes = Quotation::whereIn('status', ['DRAFT', 'SENT', 'FOLLOW_UP']);
        $activePipelineCount = $activeQuotes->count();
        $activePipelineAmount = $activeQuotes->sum('total_amount');

        $wonAmount = PurchaseOrder::sum('po_amount');

        $urgentCount = Quotation::whereIn('status', ['SENT', 'FOLLOW_UP'])
            ->where('valid_until', '<=', Carbon::now()->addDays(3))
            ->count();

        return [
            'userName' => $userName,
            'greeting' => $greeting,
            'formattedDate' => $formattedDate,
            'activePipelineCount' => $activePipelineCount,
            'activePipelineAmount' => $activePipelineAmount,
            'wonAmount' => $wonAmount,
            'urgentCount' => $urgentCount,
            'importUrl' => QuotationResource::getUrl('index'),
            'createQuoteUrl' => QuotationResource::getUrl('create'),
            'customersUrl' => CustomerResource::getUrl('index'),
            'poUrl' => PurchaseOrderResource::getUrl('index'),
        ];
    }
}
