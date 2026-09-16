<?php

namespace App\Filament\Resources\QuotationResource\Pages;

use App\Filament\Resources\QuotationResource;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\Quotation;
use App\Services\EpicorPdfParserService;
use App\Services\GoogleCalendarService;
use App\Services\GoogleDriveService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;

class ListQuotations extends ListRecords
{
    protected static string $resource = QuotationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('import_epicor')
                ->label('⚡ Import PDF Epicor (Otomatis)')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->modalHeading('Import Quotation Epicor PDF (Otomatis Tanpa Ketik)')
                ->modalDescription('Upload file PDF penawaran dari Epicor Kobexindo. Sistem secara otomatis mengekstrak nomor quote, customer, tanggal, PIC, syarat pembayaran, lead time, dan seluruh rincian part number beserta harga.')
                ->modalSubmitActionLabel('Proses & Buat Penawaran')
                ->form([
                    Forms\Components\FileUpload::make('pdf_file')
                        ->label('Upload File PDF Epicor')
                        ->acceptedFileTypes(['application/pdf'])
                        ->disk('public')
                        ->directory('quotations/pdf')
                        ->preserveFilenames()
                        ->required()
                        ->helperText('Pilih file PDF Epicor Quotation Part Anda.'),
                ])
                ->action(function (array $data, Actions\Action $action) {
                    $filePath = Storage::disk('public')->path($data['pdf_file']);
                    if (!file_exists($filePath)) {
                        Notification::make()
                            ->title('File tidak ditemukan di server')
                            ->danger()
                            ->send();
                        return;
                    }

                    $parser = app(EpicorPdfParserService::class);
                    $extracted = $parser->parsePdf($filePath);

                    if (!$extracted['success']) {
                        Notification::make()
                            ->title('Gagal Membaca PDF Epicor')
                            ->body($extracted['message'])
                            ->danger()
                            ->send();
                        return;
                    }

                    // 1. Customer
                    $customerName = !empty($extracted['customer_name']) ? $extracted['customer_name'] : 'Customer Epicor ' . ($extracted['quotation_number'] ?? date('YmdHis'));
                    $customer = Customer::firstOrCreate([
                        'company_name' => $customerName,
                    ], [
                        'branch_area' => 'Cikarang',
                        'customer_type' => 'NEW_CUSTOMER',
                        'address' => $extracted['customer_address'] ?? null,
                    ]);

                    if (!empty($extracted['customer_address']) && empty($customer->address)) {
                        $customer->update(['address' => $extracted['customer_address']]);
                    }

                    // 2. Primary Contact
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

                    // 3. Create Quotation Record
                    $quotationNumber = !empty($extracted['quotation_number']) ? $extracted['quotation_number'] : ('QT-' . time());
                    $quotation = Quotation::create([
                        'quotation_number' => $quotationNumber,
                        'customer_id' => $customer->id,
                        'contact_id' => $contact->id,
                        'category' => $extracted['category'] ?? 'SPAREPART_JUNGHEINRICH',
                        'quotation_date' => $extracted['quotation_date'],
                        'valid_until' => $extracted['valid_until'],
                        'total_amount' => $extracted['total_amount'],
                        'currency' => $extracted['currency'] ?? 'IDR',
                        'payment_terms' => $extracted['payment_terms'],
                        'lead_time' => $extracted['lead_time'],
                        'status' => 'SENT',
                        'pdf_file_path' => $data['pdf_file'],
                    ]);

                    // 4. Create Line Items
                    if (!empty($extracted['items'])) {
                        foreach ($extracted['items'] as $item) {
                            $quotation->items()->create($item);
                        }
                    }

                    // 5. Google Drive Integration (Automated fallback)
                    try {
                        $driveService = app(GoogleDriveService::class);
                        $uploadRes = $driveService->uploadQuotation(
                            $filePath,
                            $customer->company_name,
                            $quotation->quotation_number,
                            date('Y')
                        );
                        $quotation->update(['pdf_drive_path' => $uploadRes['drive_path']]);
                        if (!empty($uploadRes['folder_id'])) {
                            $customer->update(['drive_folder_id' => $uploadRes['folder_id']]);
                        }
                    } catch (\Throwable $e) {
                        // Safe fallback
                    }

                    // 6. Google Calendar H-3 Follow Up
                    try {
                        $calendarService = app(GoogleCalendarService::class);
                        $eventId = $calendarService->createFollowUpEvent($quotation);
                        if ($eventId) {
                            $quotation->update(['calendar_event_id' => $eventId]);
                        }
                    } catch (\Throwable $e) {
                        // Safe fallback
                    }

                    $itemCount = count($extracted['items']);
                    $totalFormatted = 'Rp ' . number_format($quotation->total_amount, 0, ',', '.');
                    Notification::make()
                        ->title('Quotation Epicor Berhasil Dibuat Otomatis!')
                        ->body("No Quote: {$quotation->quotation_number} | Customer: {$customer->company_name} | {$itemCount} Items | Total: {$totalFormatted}")
                        ->success()
                        ->duration(10000)
                        ->send();

                    // Redirect to edit page
                    $action->redirect(QuotationResource::getUrl('edit', ['record' => $quotation->id]));
                }),

            Actions\CreateAction::make()
                ->label('Buat Manual / Form'),
        ];
    }
}
