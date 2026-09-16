<?php

namespace App\Filament\Resources\QuotationResource\Pages;

use App\Filament\Resources\QuotationResource;
use App\Services\GoogleCalendarService;
use App\Services\GoogleDriveService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Storage;

class CreateQuotation extends CreateRecord
{
    protected static string $resource = QuotationResource::class;

    protected function afterCreate(): void
    {
        $record = $this->record;

        // 1. Google Drive Auto Upload
        if (!empty($record->pdf_file_path)) {
            try {
                $driveService = app(GoogleDriveService::class);
                $localPath = Storage::disk('public')->path($record->pdf_file_path);
                if (file_exists($localPath)) {
                    $companyName = $record->customer->company_name ?? 'General';
                    $uploadRes = $driveService->uploadQuotation(
                        $localPath,
                        $companyName,
                        $record->quotation_number,
                        date('Y')
                    );
                    $record->update(['pdf_drive_path' => $uploadRes['drive_path']]);

                    if (!empty($uploadRes['folder_id']) && $record->customer) {
                        $record->customer->update(['drive_folder_id' => $uploadRes['folder_id']]);
                    }
                }
            } catch (\Throwable $e) {
                // Fallback logged
            }
        }

        // 2. Google Calendar H-3 Reminder
        try {
            $calendarService = app(GoogleCalendarService::class);
            $eventId = $calendarService->createFollowUpEvent($record);
            if ($eventId) {
                $record->update(['calendar_event_id' => $eventId]);
                Notification::make()
                    ->title('Pengingat Terjadwal')
                    ->body('Event follow-up H-3 telah ditambahkan ke Google Calendar.')
                    ->info()
                    ->send();
            }
        } catch (\Throwable $e) {
            // Fallback
        }
    }
}
