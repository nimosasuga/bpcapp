<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class FleetsRelationManager extends RelationManager
{
    protected static string $relationship = 'fleets';
    protected static ?string $title = 'Populasi Unit & Kendaraan (Fleets)';
    protected static ?string $modelLabel = 'Unit Armada';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('fleet_type')
                    ->label('Kategori Unit')
                    ->options([
                        'FORKLIFT_JUNGHEINRICH' => 'Forklift Jungheinrich',
                        'FORKLIFT_OTHER' => 'Forklift Brand Lain (Toyota, Nichiyu, dll)',
                        'TRUCK' => 'Truk Komersial (Hino, Mitsubishi Canter, dll)',
                    ])
                    ->default('FORKLIFT_JUNGHEINRICH')
                    ->required(),
                Forms\Components\TextInput::make('brand')
                    ->label('Merek (Brand)')
                    ->placeholder('Contoh: Jungheinrich, Toyota, Hino')
                    ->required()
                    ->maxLength(100),
                Forms\Components\TextInput::make('model_type')
                    ->label('Tipe / Model')
                    ->placeholder('Contoh: EFG 216, ETV 214, Canter HD')
                    ->required()
                    ->maxLength(100),
                Forms\Components\TextInput::make('serial_number')
                    ->label('Nomor Seri (Serial No / Chasis)')
                    ->maxLength(100),
                Forms\Components\TextInput::make('tyre_size_front')
                    ->label('Ukuran Ban Depan')
                    ->placeholder('Contoh: 18x7-8, 7.50-16')
                    ->maxLength(50),
                Forms\Components\TextInput::make('tyre_size_rear')
                    ->label('Ukuran Ban Belakang')
                    ->placeholder('Contoh: 16x6-8, 7.50-16')
                    ->maxLength(50),
                Forms\Components\Textarea::make('notes')
                    ->label('Catatan Khusus (Kondisi/Operasional)')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('model_type')
            ->columns([
                Tables\Columns\TextColumn::make('fleet_type')
                    ->label('Kategori')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'FORKLIFT_JUNGHEINRICH' => 'warning',
                        'TRUCK' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'FORKLIFT_JUNGHEINRICH' => 'Jungheinrich',
                        'TRUCK' => 'Truk',
                        default => 'Forklift Lain',
                    }),
                Tables\Columns\TextColumn::make('brand')
                    ->label('Brand')
                    ->weight('bold')
                    ->searchable(),
                Tables\Columns\TextColumn::make('model_type')
                    ->label('Model / Tipe')
                    ->searchable(),
                Tables\Columns\TextColumn::make('serial_number')
                    ->label('No. Seri')
                    ->copyable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('tyre_size_front')
                    ->label('Ban Depan')
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('tyre_size_rear')
                    ->label('Ban Belakang')
                    ->badge()
                    ->color('gray'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
