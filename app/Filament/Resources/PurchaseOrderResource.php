<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PurchaseOrderResource\Pages;
use App\Filament\Resources\PurchaseOrderResource\RelationManagers;
use App\Models\PurchaseOrder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PurchaseOrderResource extends Resource
{
    protected static ?string $model = PurchaseOrder::class;

    protected static ?string $modelLabel = 'Purchase Order (PO)';
    protected static ?string $pluralModelLabel = 'Daftar Purchase Order (PO)';
    protected static ?string $navigationGroup = 'Pipeline & Penawaran';
    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::where('delivery_status', 'PENDING')->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'PO menunggu pengiriman';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Purchase Order')
                    ->schema([
                        Forms\Components\Select::make('quotation_id')
                            ->label('Referensi Penawaran Harga')
                            ->relationship('quotation', 'quotation_number')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\TextInput::make('po_number')
                            ->label('Nomor PO Resmi Pelanggan')
                            ->placeholder('Contoh: PO-LNS/2026/089')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\DatePicker::make('po_date')
                            ->label('Tanggal PO')
                            ->default(now())
                            ->required(),
                        Forms\Components\TextInput::make('po_amount')
                            ->label('Nilai Total PO (Rp)')
                            ->numeric()
                            ->prefix('Rp')
                            ->required(),
                        Forms\Components\Select::make('delivery_status')
                            ->label('Status Pengiriman & Penyelesaian')
                            ->options([
                                'PENDING' => '1. Pending (Menunggu Pengiriman)',
                                'PARTIAL' => '2. Pengiriman Bertahap (Partial)',
                                'DELIVERED' => '3. Terkirim di Lokasi Pelanggan',
                                'COMPLETED' => '4. Selesai (Completed & Invoiced)',
                            ])
                            ->default('PENDING')
                            ->required(),
                        Forms\Components\DatePicker::make('delivery_due_date')
                            ->label('Target Tanggal Pengiriman'),
                        Forms\Components\TextInput::make('surat_jalan_ref')
                            ->label('No. Surat Jalan / Invoicing')
                            ->placeholder('Contoh: DO/KOBEX/2026/0412')
                            ->maxLength(100),
                        Forms\Components\FileUpload::make('po_file_path')
                            ->label('Scan / Berkas PO Customer')
                            ->disk('public')
                            ->directory('po_documents')
                            ->acceptedFileTypes(['application/pdf', 'image/*']),
                        Forms\Components\TextInput::make('po_file_drive_path')
                            ->label('Link Berkas di Google Drive')
                            ->url()
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('notes')
                            ->label('Catatan Pengiriman / Serah Terima')
                            ->columnSpanFull(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('po_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('po_number')
                    ->label('No. PO Customer')
                    ->weight('bold')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('quotation.customer.company_name')
                    ->label('Perusahaan / PT')
                    ->weight('medium')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('quotation.quotation_number')
                    ->label('Ref. Quote')
                    ->badge()
                    ->searchable(),
                Tables\Columns\TextColumn::make('po_date')
                    ->label('Tgl PO')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('po_amount')
                    ->label('Nilai PO')
                    ->money('IDR', locale: 'id')
                    ->weight('bold')
                    ->sortable(),
                Tables\Columns\TextColumn::make('delivery_status')
                    ->label('Status Kirim')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'PENDING' => 'warning',
                        'PARTIAL' => 'info',
                        'DELIVERED' => 'primary',
                        'COMPLETED' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'PENDING' => 'Pending',
                        'PARTIAL' => 'Partial',
                        'DELIVERED' => 'Delivered',
                        'COMPLETED' => 'Completed',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('delivery_due_date')
                    ->label('Target Kirim')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('surat_jalan_ref')
                    ->label('No. SJ / Faktur')
                    ->searchable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('delivery_status')
                    ->label('Status Pengiriman')
                    ->options([
                        'PENDING' => 'Pending',
                        'PARTIAL' => 'Partial',
                        'DELIVERED' => 'Delivered',
                        'COMPLETED' => 'Completed',
                    ]),
            ])
            ->actions([
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
            'index' => Pages\ListPurchaseOrders::route('/'),
            'create' => Pages\CreatePurchaseOrder::route('/create'),
            'view' => Pages\ViewPurchaseOrder::route('/{record}'),
            'edit' => Pages\EditPurchaseOrder::route('/{record}/edit'),
        ];
    }
}
