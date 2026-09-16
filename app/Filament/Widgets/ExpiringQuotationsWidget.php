<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\QuotationResource;
use App\Models\PurchaseOrder;
use App\Models\Quotation;
use App\Services\EmailTemplateService;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class ExpiringQuotationsWidget extends BaseWidget
{
    protected static ?string $heading = '⚡ Prioritas Tindak Lanjut: Penawaran Segera Berakhir & Expired (H-3)';
    protected static ?int $sort = 5;
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
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Nomor quote disalin'),

                Tables\Columns\TextColumn::make('customer.company_name')
                    ->label('Customer / PT')
                    ->weight('semibold')
                    ->description(fn (Quotation $record) => $record->category ? str_replace('_', ' ', $record->category) : null)
                    ->searchable(),

                Tables\Columns\TextColumn::make('contact.name')
                    ->label('PIC Attn')
                    ->description(fn (Quotation $record) => $record->contact?->phone_number ? '📞 ' . $record->contact->phone_number : '-'),

                Tables\Columns\TextColumn::make('valid_until')
                    ->label('Berlaku s/d')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('validity_status_text')
                    ->label('Status Sisa Waktu')
                    ->badge()
                    ->color(fn (Quotation $record): string => $record->validity_badge_color),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Nilai Penawaran')
                    ->money('IDR', locale: 'id')
                    ->weight('bold')
                    ->color('warning'),
            ])
            ->actions([
                // 1. Template Email Quick Modal
                Tables\Actions\Action::make('email_template')
                    ->label('Template Email')
                    ->icon('heroicon-m-envelope')
                    ->color('info')
                    ->modalHeading('Template Email Penawaran ke PIC')
                    ->modalDescription('Salin teks resmi ini atau klik Buka di Thunderbird untuk langsung mengirimkan penawaran.')
                    ->modalSubmitActionLabel('Buka di Mozilla Thunderbird')
                    ->form([
                        Forms\Components\TextInput::make('email_to')
                            ->label('Tujuan Email (PIC)')
                            ->default(fn (Quotation $record) => $record->contact?->email ?? '')
                            ->placeholder('purchasing@customer.co.id'),
                        Forms\Components\TextInput::make('email_subject')
                            ->label('Subject Email')
                            ->default(fn (Quotation $record) => app(EmailTemplateService::class)->generateSubject($record)),
                        Forms\Components\Textarea::make('email_body')
                            ->label('Isi Pesan Email')
                            ->rows(10)
                            ->default(fn (Quotation $record) => app(EmailTemplateService::class)->generateBody($record)),
                    ])
                    ->action(function (array $data, Quotation $record, Tables\Actions\Action $action) {
                        $service = app(EmailTemplateService::class);
                        $mailto = $service->generateMailtoLink($record, $data['email_to']);
                        $action->redirect($mailto);
                    }),

                // 2. Mark WIN Action
                Tables\Actions\Action::make('mark_win')
                    ->label('Menang (PO)')
                    ->icon('heroicon-m-trophy')
                    ->color('success')
                    ->modalHeading('Konfirmasi Menang (PO Masuk)')
                    ->modalDescription('Catat nomor PO customer resmi untuk mengunci kemenangan deal ini.')
                    ->form([
                        Forms\Components\TextInput::make('po_number')
                            ->label('Nomor PO Resmi Customer')
                            ->placeholder('PO-HTI-2026-0901')
                            ->required(),
                        Forms\Components\DatePicker::make('po_date')
                            ->label('Tanggal PO Masuk')
                            ->default(now())
                            ->required(),
                        Forms\Components\TextInput::make('po_amount')
                            ->label('Nilai Total PO (Rp)')
                            ->numeric()
                            ->default(fn (Quotation $record) => $record->total_amount)
                            ->required(),
                        Forms\Components\DatePicker::make('delivery_due_date')
                            ->label('Target Pengiriman Barang')
                            ->default(now()->addDays(14)),
                    ])
                    ->action(function (array $data, Quotation $record) {
                        $record->update(['status' => 'WIN']);
                        PurchaseOrder::create([
                            'quotation_id' => $record->id,
                            'po_number' => $data['po_number'],
                            'po_date' => $data['po_date'],
                            'po_amount' => $data['po_amount'],
                            'delivery_due_date' => $data['delivery_due_date'] ?? null,
                            'delivery_status' => 'PENDING',
                        ]);
                        Notification::make()
                            ->title('Selamat! Penawaran Menang (WIN)')
                            ->body("PO {$data['po_number']} sebesar Rp " . number_format($data['po_amount'], 0, ',', '.') . " berhasil dicatat.")
                            ->success()
                            ->send();
                    }),

                // 3. Mark LOSE Action
                Tables\Actions\Action::make('mark_lose')
                    ->label('Kalah')
                    ->icon('heroicon-m-x-circle')
                    ->color('danger')
                    ->modalHeading('Evaluasi Penawaran Kalah (LOSE)')
                    ->modalDescription('Pilih alasan utama kekalahan untuk laporan analisa sales.')
                    ->form([
                        Forms\Components\Select::make('loss_reason')
                            ->label('Penyebab Kalah')
                            ->options([
                                'PRICE_TOO_HIGH' => 'Harga Kalah Bersaing (Lebih Mahal)',
                                'COMPETITOR' => 'Dimenangkan Kompetitor',
                                'STOCK_UNAVAILABLE' => 'Lead Time Indent Terlalu Lama',
                                'BUDGET_CANCELLED' => 'Anggaran Customer Dibatalkan / Ditunda',
                                'OTHER' => 'Unit Rusak Berat / Diafkir / Lainnya',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('loss_note')
                            ->label('Catatan Nama Kompetitor / Keterangan Tambahan')
                            ->placeholder('cth: Kalah oleh CV Aneka Part selisih 7%'),
                    ])
                    ->action(function (array $data, Quotation $record) {
                        $record->update([
                            'status' => 'LOSE',
                            'loss_reason' => $data['loss_reason'],
                            'loss_note' => $data['loss_note'] ?? null,
                        ]);
                        Notification::make()
                            ->title('Status Diperbarui: LOSE')
                            ->body("Alasan kekalahan telah dicatat untuk evaluasi CRM.")
                            ->warning()
                            ->send();
                    }),

                // 4. View Detail
                Tables\Actions\Action::make('view_quote')
                    ->label('Buka')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(fn (Quotation $record): string => QuotationResource::getUrl('edit', ['record' => $record])),
            ])
            ->emptyStateHeading('Tidak ada penawaran mendesak saat ini')
            ->emptyStateDescription('Seluruh penawaran aktif masih memiliki masa berlaku yang cukup atau telah difollow-up.')
            ->emptyStateIcon('heroicon-o-check-badge');
    }
}
