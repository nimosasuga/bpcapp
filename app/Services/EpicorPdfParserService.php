<?php

namespace App\Services;

use Carbon\Carbon;
use Smalot\PdfParser\Parser;
use Throwable;

class EpicorPdfParserService
{
    protected Parser $parser;

    public function __construct()
    {
        $this->parser = new Parser();
    }

    /**
     * Parse text from PDF file and extract quotation data.
     *
     * @param string $filePath Absolute or relative path to PDF file
     * @return array
     */
    public function parsePdf(string $filePath): array
    {
        $result = [
            'success' => false,
            'quotation_number' => null,
            'quotation_date' => null,
            'valid_until' => null,
            'customer_name' => null,
            'contact_name' => null,
            'total_amount' => 0,
            'currency' => 'IDR',
            'items' => [],
            'raw_text' => '',
            'message' => '',
        ];

        try {
            if (!file_exists($filePath)) {
                $result['message'] = "File not found: {$filePath}";
                return $result;
            }

            $pdf = $this->parser->parseFile($filePath);
            $text = $pdf->getText();
            $result['raw_text'] = $text;
            $result['success'] = true;

            // 1. Extract Quotation Number
            // Matches patterns like "Quote #: 12345", "Quotation No: Q-2026-001", "Quote Number: 10098"
            if (preg_match('/(?:Quote(?:\s*#|\s*No\.?|\s*Number)?|Quotation\s*(?:No\.?|Number)?|No\.?\s*Penawaran)\s*[:\-]?\s*([A-Za-z0-9\-\/]+)/i', $text, $matches)) {
                $result['quotation_number'] = trim($matches[1]);
            }

            // 2. Extract Dates
            // Matches "Date: 12/03/2026", "Tanggal: 2026-03-12", "Quote Date: 12-03-2026"
            if (preg_match('/(?:Quote\s*Date|Quotation\s*Date|Tanggal\s*Penawaran|Date|Tanggal)\s*[:\-]?\s*([0-9]{1,2}[\/\-\.][0-9]{1,2}[\/\-\.][0-9]{2,4}|[0-9]{4}[\/\-\.][0-9]{1,2}[\/\-\.][0-9]{1,2})/i', $text, $matches)) {
                try {
                    $rawDate = trim($matches[1]);
                    $date = Carbon::parse($rawDate);
                    $result['quotation_date'] = $date->format('Y-m-d');
                    // Default valid_until: 14 days after quote date
                    $result['valid_until'] = $date->copy()->addDays(14)->format('Y-m-d');
                } catch (Throwable $e) {
                    // Fallback
                    $result['quotation_date'] = Carbon::now()->format('Y-m-d');
                    $result['valid_until'] = Carbon::now()->addDays(14)->format('Y-m-d');
                }
            } else {
                $result['quotation_date'] = Carbon::now()->format('Y-m-d');
                $result['valid_until'] = Carbon::now()->addDays(14)->format('Y-m-d');
            }

            // Check if there is an explicit "Valid Until" or "Masa Berlaku"
            if (preg_match('/(?:Valid\s*Until|Validity|Valid\s*Thru|Masa\s*Berlaku)\s*[:\-]?\s*(?:s\/?d)?\s*([0-9]{1,2}[\/\-\.][0-9]{1,2}[\/\-\.][0-9]{2,4}|[0-9]{4}[\/\-\.][0-9]{1,2}[\/\-\.][0-9]{1,2})/i', $text, $matches)) {
                try {
                    $result['valid_until'] = Carbon::parse(trim($matches[1]))->format('Y-m-d');
                } catch (Throwable $e) {
                    // keep default
                }
            }

            // 3. Extract Customer Name (Company)
            // Matches "To: PT ...", "Customer: PT ...", "Kepada: PT ..."
            if (preg_match('/(?:To|Customer|Customer\s*Name|Kepada\s*Yth\.?|Bill\s*To)\s*[:\-]?\s*(PT\.?|CV\.?|UD\.?)?\s*([A-Za-z0-9\s\.,&]+)/i', $text, $matches)) {
                $company = trim($matches[2]);
                $prefix = trim($matches[1] ?? '');
                $firstLine = explode("\n", $company)[0];
                $result['customer_name'] = trim($prefix . ' ' . $firstLine);
            } elseif (preg_match('/(PT\s+[A-Za-z0-9\s&]+(?:\s+Tbk)?)/i', $text, $matches)) {
                $result['customer_name'] = trim($matches[1]);
            }

            // 4. Extract Contact Person (Attn)
            if (preg_match('/(?:Attn|Attention|U\.?p\.?|Contact\s*Person|Bpk\/Ibu)\s*[:\-]?\s*([A-Za-z0-9\s\.\',]+)/i', $text, $matches)) {
                $attn = explode("\n", trim($matches[1]))[0];
                $result['contact_name'] = trim($attn);
            }

            // 5. Extract Total Amount
            // Matches "Grand Total: Rp 12.500.000", "Total Amount: 12,500,000.00", "Total: 15000000"
            if (preg_match('/(?:Grand\s*Total|Total\s*Amount|Total\s*Nilai|Total)\s*[:\-]?\s*(?:Rp\.?|IDR|USD)?\s*([0-9\.,]+)/i', $text, $matches)) {
                $rawAmount = trim($matches[1]);
                // Clean thousand and decimal separators
                // If contains comma and dot, e.g. 1,234,567.89 or 1.234.567,89
                if (str_contains($rawAmount, '.') && str_contains($rawAmount, ',')) {
                    if (strrpos($rawAmount, '.') > strrpos($rawAmount, ',')) {
                        // 1,234.56 format
                        $cleaned = str_replace(',', '', $rawAmount);
                    } else {
                        // 1.234,56 format (Indonesian)
                        $cleaned = str_replace('.', '', $rawAmount);
                        $cleaned = str_replace(',', '.', $cleaned);
                    }
                } elseif (str_contains($rawAmount, '.')) {
                    // Could be 15.000.000 (Indonesian thousands)
                    $parts = explode('.', $rawAmount);
                    if (count($parts) > 2 || (count($parts) === 2 && strlen($parts[1]) === 3)) {
                        $cleaned = str_replace('.', '', $rawAmount);
                    } else {
                        $cleaned = $rawAmount;
                    }
                } elseif (str_contains($rawAmount, ',')) {
                    // Could be 15,000,000 or 150,00
                    $parts = explode(',', $rawAmount);
                    if (count($parts) > 2 || (count($parts) === 2 && strlen($parts[1]) === 3)) {
                        $cleaned = str_replace(',', '', $rawAmount);
                    } else {
                        $cleaned = str_replace(',', '.', $rawAmount);
                    }
                } else {
                    $cleaned = $rawAmount;
                }

                $result['total_amount'] = (float) preg_replace('/[^0-9.]/', '', $cleaned);
            }

            // Currency check
            if (stripos($text, 'USD') !== false || stripos($text, '$') !== false) {
                $result['currency'] = 'USD';
            } else {
                $result['currency'] = 'IDR';
            }

        } catch (Throwable $e) {
            $result['success'] = false;
            $result['message'] = $e->getMessage();
        }

        return $result;
    }
}
