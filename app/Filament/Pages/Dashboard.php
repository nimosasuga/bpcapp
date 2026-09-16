<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static ?string $title = 'Sales Executive Dashboard';

    public function getSubheading(): ?string
    {
        return 'Monitoring real-time pipeline penawaran, konversi PO menang, dan tindak lanjut H-3 PT Kobexindo Equipment.';
    }

    public function getColumns(): int | string | array
    {
        return [
            'default' => 1,
            'md' => 2,
            'lg' => 3,
        ];
    }
}
