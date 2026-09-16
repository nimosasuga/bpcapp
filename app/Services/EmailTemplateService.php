<?php

namespace App\Services;

use App\Models\Quotation;
use Illuminate\Support\Facades\Auth;

class EmailTemplateService
{
    /**
     * Category label mapping.
     */
    public static function getCategoryLabel(string $category): string
    {
        return match ($category) {
            'SPAREPART_JUNGHEINRICH' => 'Sparepart Jungheinrich',
            'TYRE_FORKLIFT' => 'Ban Forklift',
            'TYRE_TRUCK_TIRON' => 'Ban Truk Tiron',
            'MIXED' => 'Sparepart & Ban',
            default => 'Sparepart Unit',
        };
    }

    /**
     * Generate Email Subject.
     */
    public function generateSubject(Quotation $quotation): string
    {
        $categoryLabel = self::getCategoryLabel($quotation->category);
        $quoteNumber = $quotation->quotation_number;
        $customerName = $quotation->customer->company_name ?? 'Pelanggan';

        return "Penawaran Harga {$categoryLabel} - {$quoteNumber} - PT Kobexindo Equipment to {$customerName}";
    }

    /**
     * Generate Email Body Text.
     */
    public function generateBody(Quotation $quotation, ?string $senderName = null, ?string $senderPhone = null): string
    {
        $picName = $quotation->contact->name ?? 'Bapak/Ibu Pimpinan';
        $picPosition = $quotation->contact->position ?? 'Purchasing / Maintenance Head';
        $customerName = $quotation->customer->company_name ?? '';
        $quoteNumber = $quotation->quotation_number;
        $quoteDate = $quotation->quotation_date ? $quotation->quotation_date->format('d/m/Y') : '-';
        $validUntil = $quotation->valid_until ? $quotation->valid_until->format('d/m/Y') : '-';
        $totalAmount = number_format($quotation->total_amount, 0, ',', '.');
        $leadTime = $quotation->lead_time ?: 'Ready Stock / Indent Konfirmasi';
        $paymentTerms = $quotation->payment_terms ?: 'Cash Before Delivery / Net 30';

        // Item summaries: e.g. "Part 1, Part 2, dst."
        $itemsList = $quotation->items->map(function ($item) {
            return $item->description ? "{$item->part_number} ({$item->description})" : $item->part_number;
        })->take(5)->implode(', ');

        if ($quotation->items->count() > 5) {
            $itemsList .= ', dan item lainnya';
        }

        if (empty($itemsList)) {
            $itemsList = 'Sesuai lampiran penawaran';
        }

        $user = Auth::user();
        $senderName = $senderName ?: ($user->name ?? 'Sales Part Consultant');
        $senderPhone = $senderPhone ?: '0812-xxxx-xxxx';

        return "Kepada Yth.\n"
            . "Bapak/Ibu {$picName}\n"
            . "{$picPosition}\n"
            . "{$customerName}\n\n"
            . "Dengan hormat,\n\n"
            . "Menindaklanjuti kebutuhan sparepart/tyre untuk unit di perusahaan Bapak/Ibu, berikut kami sampaikan penawaran harga resmi dengan rincian singkat sebagai berikut:\n\n"
            . "- No. Penawaran : {$quoteNumber}\n"
            . "- Tanggal       : {$quoteDate}\n"
            . "- Masa Berlaku  : s/d {$validUntil}\n"
            . "- Ringkasan Item: {$itemsList}\n"
            . "- Total Nilai   : Rp {$totalAmount} (Termasuk PPN)\n"
            . "- Lead Time     : {$leadTime}\n"
            . "- Syarat Bayar  : {$paymentTerms}\n\n"
            . "Surat penawaran resmi dalam format PDF telah kami lampirkan pada email ini.\n\n"
            . "Mohon dapat direview, dan jika ada hal teknis maupun komersial yang perlu didiskusikan lebih lanjut, kami siap membantu.\n\n"
            . "Hormat kami,\n"
            . "{$senderName}\n"
            . "Part Sales Consultant\n"
            . "PT Kobexindo Equipment\n"
            . "Telp / WA: {$senderPhone}";
    }

    /**
     * Generate mailto: link.
     */
    public function generateMailtoLink(Quotation $quotation): string
    {
        $recipient = $quotation->contact->email ?? '';
        $subject = rawurlencode($this->generateSubject($quotation));
        $body = rawurlencode($this->generateBody($quotation));

        return "mailto:{$recipient}?subject={$subject}&body={$body}";
    }
}
