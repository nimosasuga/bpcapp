<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\QuotationResource;
use App\Models\Quotation;
use Carbon\Carbon;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class ExpiringQuotationsWidget extends BaseWidget
{
    protected static ?string $heading = 'Peringatan Follow-up: Penawaran Expired & Segera Berakhir (≤ 3 Hari)';
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Quotation::query()
                    ->whereIn('status', ['SENT', 'FOLLOW_UP'])
                    ->where('valid_until', '<=', Carbon::now()->addDays(3))
                    ->orderBy('valid_until', 'asc')
            )
            ->columns([
                Tables\Columns\TextColumn::make('quotation_number')
                    ->label('No. Quote')
                    ->weight('bold')
                    ->searchable(),
                Tables\Columns\TextColumn::make('customer.company_name')
                    ->label('Perusahaan / PT')
                    ->weight('medium'),
                Tables\Columns\TextColumn::make('contact.name')
                    ->label('PIC')
                    ->description(fn ($record) => $record->contact?->phone_number ?? '-'),
                Tables\Columns\TextColumn::make('valid_until')
                    ->label('Berlaku s/d')
                    ->date('d/m/Y'),
                Tables\Columns\TextColumn::make('validity_status_text')
                    ->label('Sisa Waktu')
                    ->badge()
                    ->color(fn (Quotation $record): string => $record->validity_badge_color),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total Nilai')
                    ->money('IDR', locale: 'id')
                    ->weight('bold'),
            ])
            ->actions([
                Tables\Actions\Action::make('view_quote')
                    ->label('Tindak Lanjut')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(fn (Quotation $record): string => QuotationResource::getUrl('edit', ['record' => $record])),
            ]);
    }
}
