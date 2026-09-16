<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QuotationResource\Pages;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\PurchaseOrder;
use App\Models\Quotation;
use App\Services\EmailTemplateService;
use App\Services\EpicorPdfParserService;
use App\Services\GoogleCalendarService;
use App\Services\GoogleDriveService;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class QuotationResource extends Resource
{
    protected static ?string $model = Quotation::class;

    protected static ?string $modelLabel = 'Penawaran Harga (Quote)';
    protected static ?string $pluralModelLabel = 'Daftar Penawaran (Quotations)';
    protected static ?string $navigationGroup = 'Quotation Pipeline';
    protected static ?string $navigationIcon = 'heroicon-o-document-currency-dollar';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Ekstraksi PDF Epicor (Semi-Automated Parser)')
                    ->description('Tarik & letakkan file PDF hasil export dari ERP Epicor untuk ekstraksi instan data penawaran.')
                    ->schema([
                        Forms\Components\FileUpload::make('pdf_upload')
                            ->label('Unggah Berkas PDF Epicor (Semua Form Terisi Otomatis)')
                            ->helperText('Tarik file PDF penawaran Epicor ke sini. Nomor, Customer, Tanggal, Masa Berlaku, Part Items, dan Harga akan otomatis terisi seketika.')
                            ->acceptedFileTypes(['application/pdf'])
                            ->disk('public')
                            ->directory('temp_uploads')
                            ->live()
                            ->afterStateUpdated(function ($state, Set $set) {
                                if (!$state) {
                                    return;
                                }

                                try {
                                    $filePath = null;
                                    if ($state instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                                        $filePath = $state->getRealPath();
                                    } elseif (is_array($state) && count($state) > 0) {
                                        $first = reset($state);
                                        if ($first instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                                            $filePath = $first->getRealPath();
                                        } elseif (is_string($first)) {
                                            $filePath = Storage::disk('public')->path($first);
                                        }
                                    } elseif (is_string($state)) {
                                        if (Storage::disk('public')->exists($state)) {
                                            $filePath = Storage::disk('public')->path($state);
                                        } else {
                                            $filePath = storage_path('app/' . $state);
                                        }
                                    }

                                    if (!$filePath || !file_exists($filePath)) {
                                        return;
                                    }

                                    $parser = app(EpicorPdfParserService::class);
                                    $extracted = $parser->parsePdf($filePath);

                                    if ($extracted['success']) {
                                        // 1. Auto-fill Quotation Number
                                        if (!empty($extracted['quotation_number'])) {
                                            $set('quotation_number', $extracted['quotation_number']);
                                        }

                                        // 2. Auto-fill Dates
                                        if (!empty($extracted['quotation_date'])) {
                                            $set('quotation_date', $extracted['quotation_date']);
                                        }
                                        if (!empty($extracted['valid_until'])) {
                                            $set('valid_until', $extracted['valid_until']);
                                        }

                                        // 3. Auto-fill Terms & Lead Time
                                        if (!empty($extracted['payment_terms'])) {
                                            $set('payment_terms', $extracted['payment_terms']);
                                        }
                                        if (!empty($extracted['lead_time'])) {
                                            $set('lead_time', $extracted['lead_time']);
                                        }

                                        // 4. Auto-fill Customer & Contact
                                        if (!empty($extracted['customer_name'])) {
                                            $customer = Customer::firstOrCreate([
                                                'company_name' => $extracted['customer_name'],
                                            ], [
                                                'branch_area' => 'Cikarang',
                                                'customer_type' => 'NEW_CUSTOMER',
                                                'address' => $extracted['customer_address'] ?? null,
                                            ]);

                                            // Update address if was null
                                            if (!empty($extracted['customer_address']) && empty($customer->address)) {
                                                $customer->update(['address' => $extracted['customer_address']]);
                                            }

                                            $set('customer_id', $customer->id);

                                            $contactName = $extracted['contact_name'] ?: ($extracted['sales_person'] ? 'Purchasing PIC' : 'PIC Bagian Pengadaan');
                                            $contact = CustomerContact::firstOrCreate([
                                                'customer_id' => $customer->id,
                                                'name' => $contactName,
                                            ], [
                                                'position' => 'Purchasing / Maintenance',
                                                'phone_number' => $extracted['customer_phone'] ?? null,
                                                'is_primary' => true,
                                            ]);

                                            if (!empty($extracted['customer_phone']) && empty($contact->phone_number)) {
                                                $contact->update(['phone_number' => $extracted['customer_phone']]);
                                            }

                                            $set('contact_id', $contact->id);
                                        }

                                        // 5. Auto-fill Items Repeater!
                                        if (!empty($extracted['items'])) {
                                            $formattedItems = [];
                                            foreach ($extracted['items'] as $item) {
                                                $formattedItems[\Illuminate\Support\Str::uuid()->toString()] = $item;
                                            }
                                            $set('items', $formattedItems);
                                        }

                                        // 6. Auto-fill Totals & Category
                                        if (!empty($extracted['total_amount'])) {
                                            $set('total_amount', $extracted['total_amount']);
                                        }
                                        if (!empty($extracted['currency'])) {
                                            $set('currency', $extracted['currency']);
                                        }
                                        if (!empty($extracted['category'])) {
                                            $set('category', $extracted['category']);
                                        }

                                        $set('status', 'SENT');
                                        $set('pdf_file_path', is_string($state) ? $state : null);

                                        $itemCount = count($extracted['items']);
                                        Notification::make()
                                            ->title('Ekstraksi PDF Epicor Berhasil (Otomatis Terisi)!')
                                            ->body("No Quote: {$extracted['quotation_number']} | Customer: {$extracted['customer_name']} | {$itemCount} item part | Total: Rp " . number_format($extracted['total_amount'], 0, ',', '.'))
                                            ->success()
                                            ->duration(8000)
                                            ->send();
                                    }
                                } catch (\Throwable $e) {
                                    Notification::make()
                                        ->title('Gagal Membaca PDF Epicor')
                                        ->body($e->getMessage())
                                        ->danger()
                                        ->send();
                                }
                            })
                            ->columnSpanFull(),
                    ])->collapsible(),

                Forms\Components\Section::make('Informasi Utama Penawaran')
                    ->schema([
                        Forms\Components\TextInput::make('quotation_number')
                            ->label('Nomor Penawaran (Epicor)')
                            ->placeholder('Contoh: Q-2026-0891 atau 100982')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(100),

                        Forms\Components\Select::make('customer_id')
                            ->label('Nama Customer (PT)')
                            ->relationship('customer', 'company_name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('company_name')
                                    ->label('Nama Perusahaan / PT')
                                    ->required(),
                                Forms\Components\Select::make('branch_area')
                                    ->label('Area / Cabang')
                                    ->options([
                                        'Cikarang' => 'Cikarang',
                                        'Karawang' => 'Karawang',
                                        'Jakarta' => 'Jakarta',
                                        'Bekasi' => 'Bekasi',
                                        'Tangerang' => 'Tangerang',
                                        'Surabaya' => 'Surabaya',
                                    ]),
                            ])
                            ->afterStateUpdated(fn (Set $set) => $set('contact_id', null)),

                        Forms\Components\Select::make('contact_id')
                            ->label('PIC Pelanggan')
                            ->options(function (Get $get) {
                                $customerId = $get('customer_id');
                                if (!$customerId) {
                                    return [];
                                }
                                return CustomerContact::where('customer_id', $customerId)->pluck('name', 'id');
                            })
                            ->searchable()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nama PIC')
                                    ->required(),
                                Forms\Components\TextInput::make('position')
                                    ->label('Jabatan'),
                                Forms\Components\TextInput::make('email')
                                    ->label('Email')
                                    ->email(),
                                Forms\Components\TextInput::make('phone_number')
                                    ->label('No. HP / WA'),
                            ])
                            ->createOptionUsing(function (array $data, Get $get) {
                                $customerId = $get('customer_id');
                                if (!$customerId) {
                                    return null;
                                }
                                $contact = CustomerContact::create(array_merge($data, ['customer_id' => $customerId]));
                                return $contact->id;
                            }),

                        Forms\Components\Select::make('category')
                            ->label('Rumpun Portofolio Part')
                            ->options([
                                'SPAREPART_JUNGHEINRICH' => '1. Sparepart Unit Jungheinrich',
                                'TYRE_FORKLIFT' => '2. Tyre Forklift (Semua Brand)',
                                'TYRE_TRUCK_TIRON' => '3. Ban Truk Komersial Tiron',
                                'MIXED' => '4. Penawaran Campuran (Mixed)',
                            ])
                            ->default('SPAREPART_JUNGHEINRICH')
                            ->required(),

                        Forms\Components\DatePicker::make('quotation_date')
                            ->label('Tanggal Penawaran')
                            ->default(now())
                            ->required(),

                        Forms\Components\DatePicker::make('valid_until')
                            ->label('Masa Berlaku s/d (Validity)')
                            ->helperText('Default 14 hari. Auto-reminder H-3 sebelum expired.')
                            ->default(now()->addDays(14))
                            ->required(),

                        Forms\Components\Select::make('payment_terms')
                            ->label('Syarat Pembayaran')
                            ->options([
                                'Cash Before Delivery' => 'Cash Before Delivery (CBD)',
                                'Cash on Delivery' => 'Cash on Delivery (COD)',
                                'Net 14 Hari' => 'Net 14 Hari',
                                'Net 30 Hari' => 'Net 30 Hari',
                                'Net 45 Hari' => 'Net 45 Hari',
                                'Net 60 Hari' => 'Net 60 Hari',
                            ])
                            ->default('Net 30 Hari')
                            ->searchable(),

                        Forms\Components\Select::make('lead_time')
                            ->label('Lead Time Pengiriman')
                            ->options([
                                'Ready Stock' => 'Ready Stock (1-2 Hari)',
                                'Indent 1-2 Minggu' => 'Indent 1-2 Minggu',
                                'Indent 3-4 Minggu' => 'Indent 3-4 Minggu',
                                'Indent 4-6 Minggu' => 'Indent 4-6 Minggu',
                                'Indent 8-12 Minggu' => 'Indent 8-12 Minggu (Import)',
                            ])
                            ->default('Ready Stock')
                            ->searchable(),

                        Forms\Components\Select::make('status')
                            ->label('Status Penawaran')
                            ->options([
                                'DRAFT' => 'Draft',
                                'SENT' => 'Terkirim (Sent)',
                                'FOLLOW_UP' => 'Follow Up',
                                'WIN' => 'Menang (WIN / PO)',
                                'LOSE' => 'Kalah (LOSE)',
                                'EXPIRED' => 'Expired',
                                'CANCELLED' => 'Dibatalkan',
                            ])
                            ->default('SENT')
                            ->required()
                            ->live(),
                    ])->columns(3),

                Forms\Components\Section::make('Rincian Item Sparepart / Ban')
                    ->schema([
                        Forms\Components\Repeater::make('items')
                            ->relationship('items')
                            ->schema([
                                Forms\Components\TextInput::make('part_number')
                                    ->label('Nomor Part (P/N)')
                                    ->placeholder('51023849')
                                    ->required()
                                    ->columnSpan(2),
                                Forms\Components\TextInput::make('description')
                                    ->label('Deskripsi Barang')
                                    ->columnSpan(3),
                                Forms\Components\Select::make('category')
                                    ->label('Kategori')
                                    ->options([
                                        'JUNGHEINRICH_PART' => 'Jungheinrich Part',
                                        'TYRE_COUNTERBALANCE' => 'Ban Forklift',
                                        'TIRON_RADIAL' => 'Tiron Radial',
                                        'TIRON_BIAS' => 'Tiron Bias',
                                        'OTHER' => 'Lain-lain',
                                    ])
                                    ->default('JUNGHEINRICH_PART')
                                    ->columnSpan(2),
                                Forms\Components\TextInput::make('qty')
                                    ->label('Qty')
                                    ->numeric()
                                    ->default(1)
                                    ->live()
                                    ->afterStateUpdated(function (Get $get, Set $set) {
                                        $qty = (int) $get('qty');
                                        $price = (float) $get('unit_price');
                                        $disc = (float) $get('discount_pct');
                                        $set('total_price', $qty * $price * (1 - ($disc / 100)));
                                    })
                                    ->columnSpan(1),
                                Forms\Components\Select::make('uom')
                                    ->label('Satuan')
                                    ->options(['Pcs' => 'Pcs', 'Unit' => 'Unit', 'Set' => 'Set', 'Pair' => 'Pair'])
                                    ->default('Pcs')
                                    ->columnSpan(1),
                                Forms\Components\TextInput::make('unit_price')
                                    ->label('Harga Satuan')
                                    ->numeric()
                                    ->default(0)
                                    ->live()
                                    ->afterStateUpdated(function (Get $get, Set $set) {
                                        $qty = (int) $get('qty');
                                        $price = (float) $get('unit_price');
                                        $disc = (float) $get('discount_pct');
                                        $set('total_price', $qty * $price * (1 - ($disc / 100)));
                                    })
                                    ->columnSpan(2),
                                Forms\Components\TextInput::make('discount_pct')
                                    ->label('Disc %')
                                    ->numeric()
                                    ->default(0)
                                    ->live()
                                    ->afterStateUpdated(function (Get $get, Set $set) {
                                        $qty = (int) $get('qty');
                                        $price = (float) $get('unit_price');
                                        $disc = (float) $get('discount_pct');
                                        $set('total_price', $qty * $price * (1 - ($disc / 100)));
                                    })
                                    ->columnSpan(1),
                            ])
                            ->columns(12)
                            ->defaultItems(1)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Total Nilai & Link Berkas Cloud')
                    ->schema([
                        Forms\Components\TextInput::make('total_amount')
                            ->label('Total Nilai Penawaran (Termasuk PPN)')
                            ->numeric()
                            ->prefix('Rp')
                            ->required(),
                        Forms\Components\Select::make('currency')
                            ->label('Mata Uang')
                            ->options(['IDR' => 'IDR', 'USD' => 'USD'])
                            ->default('IDR'),
                        Forms\Components\TextInput::make('pdf_drive_path')
                            ->label('Link File di Google Drive')
                            ->placeholder('https://drive.google.com/...')
                            ->url()
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Evaluasi Kekalahan (Loss Analysis)')
                    ->description('Wajib diisi jika status penawaran adalah LOSE')
                    ->schema([
                        Forms\Components\Select::make('loss_reason')
                            ->label('Alasan Utama Kekalahan')
                            ->options([
                                'PRICE_TOO_HIGH' => '1. Harga Lebih Tinggi dibanding Kompetitor',
                                'COMPETITOR' => '2. Kalah Brand / Preferensi Kompetitor',
                                'STOCK_UNAVAILABLE' => '3. Ketersediaan Barang / Lead Time Terlalu Lama',
                                'BUDGET_CANCELLED' => '4. Anggaran Pelanggan Ditunda / Dibatalkan',
                                'OTHER' => '5. Unit Telah Diafkir / Diganti Unit Baru / Lainnya',
                            ]),
                        Forms\Components\Textarea::make('loss_note')
                            ->label('Catatan Rinci / Nama Kompetitor')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (Get $get): bool => $get('status') === 'LOSE'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('quotation_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('quotation_number')
                    ->label('No. Quote')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                Tables\Columns\TextColumn::make('customer.company_name')
                    ->label('Customer (PT)')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('category')
                    ->label('Kategori')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'SPAREPART_JUNGHEINRICH' => 'Jungheinrich',
                        'TYRE_FORKLIFT' => 'Ban Forklift',
                        'TYRE_TRUCK_TIRON' => 'Ban Truk Tiron',
                        default => 'Campuran',
                    }),

                Tables\Columns\TextColumn::make('quotation_date')
                    ->label('Tgl Quote')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('valid_until')
                    ->label('Berlaku s/d')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('validity_status_text')
                    ->label('Masa Berlaku')
                    ->badge()
                    ->color(fn (Quotation $record): string => $record->validity_badge_color),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total Nilai')
                    ->money('IDR', locale: 'id')
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'DRAFT' => 'gray',
                        'SENT' => 'info',
                        'FOLLOW_UP' => 'warning',
                        'WIN' => 'success',
                        'LOSE' => 'danger',
                        'EXPIRED' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label('Kategori Part')
                    ->options([
                        'SPAREPART_JUNGHEINRICH' => 'Sparepart Jungheinrich',
                        'TYRE_FORKLIFT' => 'Ban Forklift',
                        'TYRE_TRUCK_TIRON' => 'Ban Truk Tiron',
                        'MIXED' => 'Campuran',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'DRAFT' => 'Draft',
                        'SENT' => 'Terkirim (Sent)',
                        'FOLLOW_UP' => 'Follow Up',
                        'WIN' => 'Menang (WIN)',
                        'LOSE' => 'Kalah (LOSE)',
                        'EXPIRED' => 'Expired',
                    ]),
                Tables\Filters\Filter::make('expiring_soon')
                    ->label('Perlu Follow Up Segera (≤ 3 Hari)')
                    ->query(fn (Builder $query): Builder => $query->whereIn('status', ['SENT', 'FOLLOW_UP'])
                        ->where('valid_until', '>=', now())
                        ->where('valid_until', '<=', now()->addDays(3))),
            ])
            ->actions([
                Tables\Actions\Action::make('email_template')
                    ->label('Email')
                    ->icon('heroicon-m-envelope')
                    ->color('primary')
                    ->modalHeading(fn (Quotation $record): string => "Template Email: {$record->quotation_number}")
                    ->modalDescription('Format email standar PT Kobexindo Equipment untuk Thunderbird.')
                    ->modalSubmitActionLabel('Buka di Thunderbird')
                    ->form(function (Quotation $record) {
                        $emailService = app(EmailTemplateService::class);
                        return [
                            Forms\Components\TextInput::make('subject')
                                ->label('Subject Email')
                                ->default($emailService->generateSubject($record))
                                ->readOnly(),
                            Forms\Components\Textarea::make('body')
                                ->label('Isi Email (Plaintext)')
                                ->default($emailService->generateBody($record))
                                ->rows(14)
                                ->readOnly(),
                        ];
                    })
                    ->action(function (Quotation $record) {
                        $emailService = app(EmailTemplateService::class);
                        return redirect()->away($emailService->generateMailtoLink($record));
                    }),

                Tables\Actions\Action::make('mark_win')
                    ->label('WIN')
                    ->icon('heroicon-m-trophy')
                    ->color('success')
                    ->visible(fn (Quotation $record): bool => in_array($record->status, ['SENT', 'FOLLOW_UP', 'DRAFT']))
                    ->modalHeading('Pencatatan Purchase Order (WIN)')
                    ->form(function (Quotation $record) {
                        return [
                            Forms\Components\TextInput::make('po_number')
                                ->label('Nomor PO Resmi Customer')
                                ->required(),
                            Forms\Components\DatePicker::make('po_date')
                                ->label('Tanggal PO')
                                ->default(now())
                                ->required(),
                            Forms\Components\TextInput::make('po_amount')
                                ->label('Nilai PO (Rp)')
                                ->default($record->total_amount)
                                ->numeric()
                                ->required(),
                            Forms\Components\FileUpload::make('po_file')
                                ->label('Unggah Scan / PDF PO')
                                ->disk('public')
                                ->directory('po_documents'),
                            Forms\Components\DatePicker::make('delivery_due_date')
                                ->label('Target Tanggal Pengiriman')
                                ->default(now()->addDays(7)),
                            Forms\Components\TextInput::make('surat_jalan_ref')
                                ->label('Referensi Surat Jalan / Invoice'),
                            Forms\Components\Textarea::make('notes')
                                ->label('Catatan'),
                        ];
                    })
                    ->action(function (Quotation $record, array $data) {
                        $po = PurchaseOrder::create([
                            'quotation_id' => $record->id,
                            'po_number' => $data['po_number'],
                            'po_date' => $data['po_date'],
                            'po_amount' => $data['po_amount'],
                            'po_file_path' => $data['po_file'] ?? null,
                            'delivery_status' => 'PENDING',
                            'delivery_due_date' => $data['delivery_due_date'] ?? null,
                            'surat_jalan_ref' => $data['surat_jalan_ref'] ?? null,
                            'notes' => $data['notes'] ?? null,
                        ]);

                        $record->update(['status' => 'WIN']);

                        if (!empty($data['po_file'])) {
                            try {
                                $driveService = app(GoogleDriveService::class);
                                $localPath = Storage::disk('public')->path($data['po_file']);
                                $uploadRes = $driveService->uploadPurchaseOrder(
                                    $localPath,
                                    $record->customer->company_name ?? 'General',
                                    $data['po_number'],
                                    date('Y')
                                );
                                $po->update(['po_file_drive_path' => $uploadRes['drive_path']]);
                            } catch (\Throwable $e) {
                                // Fallback
                            }
                        }

                        Notification::make()
                            ->title('Penawaran Berhasil Dimenangkan (WIN)!')
                            ->body("PO No: {$data['po_number']} berhasil dicatat.")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('mark_lose')
                    ->label('LOSE')
                    ->icon('heroicon-m-x-circle')
                    ->color('danger')
                    ->visible(fn (Quotation $record): bool => in_array($record->status, ['SENT', 'FOLLOW_UP', 'DRAFT']))
                    ->modalHeading('Evaluasi Kekalahan (Loss Analysis)')
                    ->form([
                        Forms\Components\Select::make('loss_reason')
                            ->label('Alasan Utama Kekalahan')
                            ->options([
                                'PRICE_TOO_HIGH' => '1. Harga Lebih Tinggi dibanding Kompetitor',
                                'COMPETITOR' => '2. Kalah Brand / Preferensi Kompetitor Spesifik',
                                'STOCK_UNAVAILABLE' => '3. Ketersediaan Barang / Lead Time Terlalu Lama',
                                'BUDGET_CANCELLED' => '4. Anggaran Pelanggan Ditunda / Dibatalkan',
                                'OTHER' => '5. Unit Telah Diafkir / Diganti Unit Baru / Lainnya',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('competitor_name')
                            ->label('Nama Kompetitor (Jika Ada)'),
                        Forms\Components\Textarea::make('loss_note')
                            ->label('Catatan Evaluasi')
                            ->required(),
                    ])
                    ->action(function (Quotation $record, array $data) {
                        $note = $data['loss_note'];
                        if (!empty($data['competitor_name'])) {
                            $note = "[Kompetitor: {$data['competitor_name']}] " . $note;
                        }

                        $record->update([
                            'status' => 'LOSE',
                            'loss_reason' => $data['loss_reason'],
                            'loss_note' => $note,
                        ]);

                        Notification::make()
                            ->title('Status Diperbarui: LOSE')
                            ->warning()
                            ->send();
                    }),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQuotations::route('/'),
            'create' => Pages\CreateQuotation::route('/create'),
            'view' => Pages\ViewQuotation::route('/{record}'),
            'edit' => Pages\EditQuotation::route('/{record}/edit'),
        ];
    }
}
