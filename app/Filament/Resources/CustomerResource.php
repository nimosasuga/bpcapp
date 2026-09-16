<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Filament\Resources\CustomerResource\RelationManagers;
use App\Models\Customer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $modelLabel = 'Pelanggan / PT';
    protected static ?string $pluralModelLabel = 'Data Pelanggan (PT)';
    protected static ?string $navigationGroup = 'CRM & Pelanggan';
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Perusahaan')
                    ->schema([
                        Forms\Components\TextInput::make('company_name')
                            ->label('Nama Perusahaan / PT')
                            ->placeholder('Contoh: PT Logistik Nusantara Sejahtera')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(2),
                        Forms\Components\Select::make('customer_type')
                            ->label('Tipe Pelanggan')
                            ->options([
                                'NEW_CUSTOMER' => 'Pelanggan Baru (New)',
                                'EXISTING_CUSTOMER' => 'Pelanggan Lama (Existing)',
                            ])
                            ->default('NEW_CUSTOMER')
                            ->required(),
                        Forms\Components\Select::make('branch_area')
                            ->label('Wilayah / Cabang')
                            ->options([
                                'Cikarang' => 'Cikarang',
                                'Karawang' => 'Karawang',
                                'Jakarta' => 'Jakarta',
                                'Bekasi' => 'Bekasi',
                                'Tangerang' => 'Tangerang',
                                'Surabaya' => 'Surabaya',
                                'Semarang' => 'Semarang',
                                'Medan' => 'Medan',
                                'Balikpapan' => 'Balikpapan',
                                'Makassar' => 'Makassar',
                                'Lainnya' => 'Lainnya',
                            ])
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('industry_type')
                            ->label('Sektor Industri')
                            ->options([
                                'Logistik' => 'Logistik & Pergudangan',
                                'Manufaktur' => 'Manufaktur / Pabrik',
                                'Cold Storage' => 'Cold Storage',
                                'F&B' => 'Makanan & Minuman (F&B)',
                                'Kimia & Farmasi' => 'Kimia & Farmasi',
                                'Otomotif' => 'Otomotif & Komponen',
                                'Pertambangan' => 'Pertambangan / Kontraktor',
                                'Lainnya' => 'Lainnya',
                            ])
                            ->searchable(),
                        Forms\Components\TextInput::make('drive_folder_id')
                            ->label('Google Drive Folder ID')
                            ->helperText('ID folder arsip penawaran & PO di Google Drive (terisi otomatis saat sync)')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('address')
                            ->label('Alamat Kantor / Site Operasional')
                            ->columnSpanFull()
                            ->rows(3),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('company_name')
                    ->label('Nama PT / Perusahaan')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('branch_area')
                    ->label('Area / Kota')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('industry_type')
                    ->label('Industri')
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                Tables\Columns\TextColumn::make('customer_type')
                    ->label('Tipe')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'EXISTING_CUSTOMER' => 'success',
                        default => 'info',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'EXISTING_CUSTOMER' => 'Existing',
                        default => 'New Customer',
                    }),
                Tables\Columns\TextColumn::make('contacts_count')
                    ->counts('contacts')
                    ->label('PIC')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('fleets_count')
                    ->counts('fleets')
                    ->label('Armada')
                    ->badge()
                    ->color('warning'),
                Tables\Columns\TextColumn::make('quotations_count')
                    ->counts('quotations')
                    ->label('Penawaran')
                    ->badge()
                    ->color('primary'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('customer_type')
                    ->label('Tipe Pelanggan')
                    ->options([
                        'NEW_CUSTOMER' => 'Pelanggan Baru',
                        'EXISTING_CUSTOMER' => 'Pelanggan Lama',
                    ]),
                Tables\Filters\SelectFilter::make('branch_area')
                    ->label('Area / Wilayah')
                    ->options([
                        'Cikarang' => 'Cikarang',
                        'Karawang' => 'Karawang',
                        'Jakarta' => 'Jakarta',
                        'Bekasi' => 'Bekasi',
                        'Tangerang' => 'Tangerang',
                        'Surabaya' => 'Surabaya',
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

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Customer 360° Profile')
                    ->description('Ringkasan data perusahaan dan status akun pelanggan')
                    ->schema([
                        Infolists\Components\TextEntry::make('company_name')
                            ->label('Nama Perusahaan')
                            ->weight('bold')
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large),
                        Infolists\Components\TextEntry::make('customer_type')
                            ->label('Tipe')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'EXISTING_CUSTOMER' => 'success',
                                default => 'info',
                            })
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'EXISTING_CUSTOMER' => 'Existing Customer',
                                default => 'New Customer',
                            }),
                        Infolists\Components\TextEntry::make('branch_area')
                            ->label('Wilayah')
                            ->badge()
                            ->color('primary'),
                        Infolists\Components\TextEntry::make('industry_type')
                            ->label('Sektor Industri')
                            ->badge()
                            ->color('gray'),
                        Infolists\Components\TextEntry::make('drive_folder_id')
                            ->label('ID Folder Google Drive')
                            ->placeholder('Belum tersinkronisasi'),
                        Infolists\Components\TextEntry::make('address')
                            ->label('Alamat')
                            ->columnSpanFull(),
                    ])->columns(3),

                Infolists\Components\Section::make('Metrik Kinerja Penjualan (Win / Lose Ratio)')
                    ->description('Analisis performa penawaran harga terhadap PT ini')
                    ->schema([
                        Infolists\Components\TextEntry::make('total_quotes_count')
                            ->label('Total Penawaran Terkirim')
                            ->state(fn (Customer $record): int => $record->quotations()->count())
                            ->weight('bold')
                            ->color('primary'),
                        Infolists\Components\TextEntry::make('win_quotes_amount')
                            ->label('Total Nilai Menang (WIN / PO)')
                            ->state(function (Customer $record): string {
                                $won = $record->quotations()->where('status', 'WIN')->sum('total_amount');
                                return 'Rp ' . number_format($won, 0, ',', '.');
                            })
                            ->weight('bold')
                            ->color('success'),
                        Infolists\Components\TextEntry::make('lose_quotes_amount')
                            ->label('Total Nilai Kalah (LOSE)')
                            ->state(function (Customer $record): string {
                                $lost = $record->quotations()->where('status', 'LOSE')->sum('total_amount');
                                return 'Rp ' . number_format($lost, 0, ',', '.');
                            })
                            ->weight('bold')
                            ->color('danger'),
                        Infolists\Components\TextEntry::make('conversion_rate')
                            ->label('Rasio Konversi (Win Rate)')
                            ->state(fn (Customer $record): string => $record->win_ratio . ' %')
                            ->badge()
                            ->color(fn (Customer $record): string => $record->win_ratio >= 50 ? 'success' : ($record->win_ratio > 0 ? 'warning' : 'gray')),
                    ])->columns(4),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ContactsRelationManager::class,
            RelationManagers\FleetsRelationManager::class,
            RelationManagers\QuotationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'view' => Pages\ViewCustomer::route('/{record}'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}
