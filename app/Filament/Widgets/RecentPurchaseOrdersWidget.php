<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\PurchaseOrderResource;
use App\Models\PurchaseOrder;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentPurchaseOrdersWidget extends BaseWidget
{
    protected static ?string $heading = '🏆 Realisasi Order: PO Customer Menang & Status Pengiriman Part';
    protected static ?int $sort = 6;
    protected int | string | array $columnSpan = [
        'default' => 'full',
        'lg' => 2,
    ];

    public function table(Table $table): Table
    {
        return $table
            ->query(
                PurchaseOrder::query()
                    ->with(['quotation.customer'])
                    ->latest('po_date')
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('po_number')
                    ->label('No. PO Customer')
                    ->weight('bold')
                    ->searchable(),

                Tables\Columns\TextColumn::make('quotation.customer.company_name')
                    ->label('Perusahaan / PT')
                    ->weight('semibold')
                    ->searchable(),

                Tables\Columns\TextColumn::make('po_date')
                    ->label('Tanggal PO')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('po_amount')
                    ->label('Nilai PO')
                    ->money('IDR', locale: 'id')
                    ->weight('bold')
                    ->color('success'),

                Tables\Columns\TextColumn::make('delivery_status')
                    ->label('Status Pengiriman')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'PENDING' => 'warning',
                        'PARTIAL' => 'info',
                        'DELIVERED', 'COMPLETED' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'PENDING' => 'Menunggu Pengiriman',
                        'PARTIAL' => 'Kirim Sebagian',
                        'DELIVERED' => 'Terkirim Lengkap',
                        'COMPLETED' => 'Selesai & Lunas',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('delivery_due_date')
                    ->label('Target Kirim')
                    ->date('d M Y')
                    ->placeholder('Belum ditentukan'),

                Tables\Columns\TextColumn::make('surat_jalan_ref')
                    ->label('No. Surat Jalan')
                    ->placeholder('Menunggu Gudang'),
            ])
            ->actions([
                Tables\Actions\Action::make('view_po')
                    ->label('Buka PO')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(fn (PurchaseOrder $record): string => PurchaseOrderResource::getUrl('edit', ['record' => $record])),
            ])
            ->emptyStateHeading('Belum ada PO tercatat')
            ->emptyStateDescription('Tandai penawaran yang berhasil sebagai WIN untuk mencatat nomor PO customer.')
            ->emptyStateIcon('heroicon-o-shopping-bag');
    }
}
