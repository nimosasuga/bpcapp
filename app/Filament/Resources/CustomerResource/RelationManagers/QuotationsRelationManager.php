<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class QuotationsRelationManager extends RelationManager
{
    protected static string $relationship = 'quotations';
    protected static ?string $title = 'Riwayat Penawaran Harga (Quotations)';
    protected static ?string $modelLabel = 'Penawaran';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('quotation_number')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('quotation_number')
            ->defaultSort('quotation_date', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('quotation_number')
                    ->label('No. Penawaran')
                    ->weight('bold')
                    ->searchable(),
                Tables\Columns\TextColumn::make('category')
                    ->label('Kategori')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'SPAREPART_JUNGHEINRICH' => 'Jungheinrich',
                        'TYRE_FORKLIFT' => 'Ban Forklift',
                        'TYRE_TRUCK_TIRON' => 'Ban Truk Tiron',
                        default => 'Mixed',
                    }),
                Tables\Columns\TextColumn::make('quotation_date')
                    ->label('Tgl Quote')
                    ->date('d/m/Y'),
                Tables\Columns\TextColumn::make('valid_until')
                    ->label('Berlaku s/d')
                    ->date('d/m/Y')
                    ->description(fn ($record): string => $record->validity_status_text),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Nilai (IDR)')
                    ->money('IDR', locale: 'id')
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
