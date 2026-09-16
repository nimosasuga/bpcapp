<?php

namespace App\Services;

use App\Models\Quotation;
use Carbon\Carbon;
use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;
use Illuminate\Support\Facades\Log;
use Throwable;

class GoogleCalendarService
{
    protected ?Client $client = null;
    protected ?Calendar $service = null;
    protected bool $isConfigured = false;
    protected string $calendarId = 'primary';

    public function __construct()
    {
        $jsonPath = env('GOOGLE_SERVICE_ACCOUNT_JSON');
        $this->calendarId = env('GOOGLE_CALENDAR_ID', 'primary');

        if ($jsonPath) {
            $fullPath = base_path($jsonPath);
            if (file_exists($fullPath)) {
                try {
                    $this->client = new Client();
                    $this->client->setAuthConfig($fullPath);
                    $this->client->addScope(Calendar::CALENDAR);
                    $this->service = new Calendar($this->client);
                    $this->isConfigured = true;
                } catch (Throwable $e) {
                    Log::error('Google Calendar Init Error: ' . $e->getMessage());
                }
            }
        }
    }

    public function isConfigured(): bool
    {
        return $this->isConfigured;
    }

    /**
     * Create follow-up reminder event on Google Calendar at H-3 before valid_until.
     *
     * @param Quotation $quotation
     * @return string|null Event ID if created
     */
    public function createFollowUpEvent(Quotation $quotation): ?string
    {
        if (!$this->isConfigured || !$this->service) {
            Log::info("Google Calendar not configured. Follow-up reminder for Quote {$quotation->quotation_number} skipped.");
            return null;
        }

        try {
            $customerName = $quotation->customer->company_name ?? 'Unknown Customer';
            $contactName = $quotation->contact->name ?? '-';
            $contactPhone = $quotation->contact->phone_number ?? '-';
            $totalFormatted = number_format($quotation->total_amount, 0, ',', '.');

            // H-3 before valid_until
            $reminderDate = Carbon::parse($quotation->valid_until)->subDays(3);
            if ($reminderDate->isPast()) {
                $reminderDate = Carbon::now();
            }

            // Summary: [FOLLOW UP QUOTE] [Nama PT] - [Nomor Quote] - Nilai Rp [Total]
            $summary = "[FOLLOW UP QUOTE] {$customerName} - {$quotation->quotation_number} - Nilai Rp {$totalFormatted}";

            // Description items
            $itemsSummary = $quotation->items->map(function ($item) {
                return "- {$item->part_number}: {$item->description} ({$item->qty} {$item->uom})";
            })->implode("\n");

            $appUrl = config('app.url') . "/admin/quotations/{$quotation->id}/edit";

            $description = "REMINDER FOLLOW-UP PENAWARAN (H-3 EXPIRED)\n\n"
                . "Customer: {$customerName}\n"
                . "PIC: {$contactName} ({$contactPhone})\n"
                . "No. Penawaran: {$quotation->quotation_number}\n"
                . "Masa Berlaku s/d: {$quotation->valid_until->format('d/m/Y')}\n"
                . "Total Nilai: Rp {$totalFormatted}\n\n"
                . "Ringkasan Part:\n{$itemsSummary}\n\n"
                . "Buka di Web App: {$appUrl}";

            $start = new EventDateTime();
            $start->setDate($reminderDate->format('Y-m-d'));

            $end = new EventDateTime();
            $end->setDate($reminderDate->copy()->addDay()->format('Y-m-d'));

            $event = new Event([
                'summary' => $summary,
                'description' => $description,
                'start' => $start,
                'end' => $end,
            ]);

            $createdEvent = $this->service->events->insert($this->calendarId, $event);
            return $createdEvent->getId();
        } catch (Throwable $e) {
            Log::error('Google Calendar Event Creation Failed: ' . $e->getMessage());
            return null;
        }
    }
}
