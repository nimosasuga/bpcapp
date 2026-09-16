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
            'customer_address' => null,
            'customer_phone' => null,
            'contact_name' => null,
            'category' => 'SPAREPART_JUNGHEINRICH',
            'payment_terms' => 'Net 30 Hari',
            'lead_time' => 'Ready Stock',
            'sales_person' => null,
            'sub_total' => 0,
            'vat' => 0,
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

            $this->extractQuotationData($text, $result);

        } catch (Throwable $e) {
            $result['success'] = false;
            $result['message'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Extract fields from text.
     */
    public function extractQuotationData(string $text, array &$result): void
    {
        // 1. Extract Quotation Number
        if (preg_match('/(?:([A-Za-z0-9\-\/]+)[\t\s]+Quote\s*Num|Quote\s*Num\s*[:\-]?[\t\s]*([A-Za-z0-9\-\/]+))/i', $text, $matches)) {
            $result['quotation_number'] = trim($matches[1] ?: $matches[2]);
        } elseif (preg_match('/(?:Quote(?:\s*#|\s*No\.?|\s*Number)?|Quotation\s*(?:No\.?|Number)?|No\.?\s*Penawaran)\s*[:\-]?\s*([A-Za-z0-9\-\/]+)/i', $text, $matches)) {
            $result['quotation_number'] = trim($matches[1]);
        }

        // 2. Extract Quote Date
        if (preg_match('/(?:Quote\s*Date\s*[:\-]?[\t\s]*([0-9]{1,2}[\/\-\.][0-9]{1,2}[\/\-\.][0-9]{2,4})|([0-9]{1,2}[\/\-\.][0-9]{1,2}[\/\-\.][0-9]{2,4})[\t\s]+Quote\s*Date)/i', $text, $matches)) {
            try {
                $rawDate = trim($matches[1] ?: $matches[2]);
                $result['quotation_date'] = Carbon::parse($rawDate)->format('Y-m-d');
            } catch (Throwable $e) {
                $result['quotation_date'] = Carbon::now()->format('Y-m-d');
            }
        } elseif (preg_match('/(?:Tanggal\s*Penawaran|Date|Tanggal)\s*[:\-]?\s*([0-9]{1,2}[\/\-\.][0-9]{1,2}[\/\-\.][0-9]{2,4}|[0-9]{4}[\/\-\.][0-9]{1,2}[\/\-\.][0-9]{1,2})/i', $text, $matches)) {
            try {
                $result['quotation_date'] = Carbon::parse(trim($matches[1]))->format('Y-m-d');
            } catch (Throwable $e) {
                $result['quotation_date'] = Carbon::now()->format('Y-m-d');
            }
        } else {
            $result['quotation_date'] = Carbon::now()->format('Y-m-d');
        }

        // 3. Extract Quote Expired / Valid Until
        if (preg_match('/(?:Quote\s*Expired\s*[:\-]?[\t\s]*([0-9]{1,2}[\/\-\.][0-9]{1,2}[\/\-\.][0-9]{2,4})|([0-9]{1,2}[\/\-\.][0-9]{1,2}[\/\-\.][0-9]{2,4})[\t\s]+Quote\s*Expired)/i', $text, $matches)) {
            try {
                $rawExp = trim($matches[1] ?: $matches[2]);
                $result['valid_until'] = Carbon::parse($rawExp)->format('Y-m-d');
            } catch (Throwable $e) {
                $result['valid_until'] = Carbon::parse($result['quotation_date'])->addDays(14)->format('Y-m-d');
            }
        } elseif (preg_match('/(?:Valid\s*Until|Validity|Valid\s*Thru|Masa\s*Berlaku)\s*[:\-]?\s*(?:s\/?d)?\s*([0-9]{1,2}[\/\-\.][0-9]{1,2}[\/\-\.][0-9]{2,4})/i', $text, $matches)) {
            try {
                $result['valid_until'] = Carbon::parse(trim($matches[1]))->format('Y-m-d');
            } catch (Throwable $e) {
                $result['valid_until'] = Carbon::parse($result['quotation_date'])->addDays(14)->format('Y-m-d');
            }
        } else {
            $result['valid_until'] = Carbon::parse($result['quotation_date'])->addDays(14)->format('Y-m-d');
        }

        // 4. Extract Currency
        if (preg_match('/\b(IDR|USD|EUR)\b/i', $text, $matches)) {
            $result['currency'] = strtoupper($matches[1]);
        } elseif (preg_match('/Currency\s*[:\-]?\s*([A-Za-z]+)/i', $text, $matches)) {
            $result['currency'] = strtoupper(trim($matches[1]));
        } else {
            $result['currency'] = (stripos($text, 'USD') !== false || stripos($text, '$') !== false) ? 'USD' : 'IDR';
        }

        // 5. Extract Payment Term
        if (preg_match('/\bN2\b/i', $text)) {
            $result['payment_terms'] = 'Net 30 Hari';
        } elseif (preg_match('/\b(CBD|Cash Before Delivery)\b/i', $text)) {
            $result['payment_terms'] = 'Cash Before Delivery';
        } elseif (preg_match('/\bN1\b/i', $text)) {
            $result['payment_terms'] = 'Net 14 Hari';
        } elseif (preg_match('/Term\s*[:\-]?\s*([^\r\n]+)/i', $text, $matches)) {
            $termRaw = trim($matches[1]);
            if (stripos($termRaw, 'CBD') !== false || stripos($termRaw, 'Cash') !== false) {
                $result['payment_terms'] = 'Cash Before Delivery';
            } elseif (preg_match('/N(?:et)?\s*(\d+)/i', $termRaw, $tm)) {
                $result['payment_terms'] = "Net {$tm[1]} Hari";
            } else {
                $result['payment_terms'] = $termRaw ?: 'Net 30 Hari';
            }
        }

        // 6. Extract Customer (Company Name, Address, Phone)
        if (preg_match('/(PT\.?\s+[^\r\n,]+)/i', $text, $matches)) {
            $result['customer_name'] = trim(preg_replace('/\s+/', ' ', $matches[1]));
        } elseif (preg_match('/(CV\.?\s+[^\r\n,]+)/i', $text, $matches)) {
            $result['customer_name'] = trim(preg_replace('/\s+/', ' ', $matches[1]));
        } elseif (preg_match('/(?:To|Customer|Customer\s*Name|Kepada\s*Yth\.?|Bill\s*To)\s*[:\-]?\s*(PT\.?|CV\.?|UD\.?)?\s*([A-Za-z0-9\s\.,&]+)/i', $text, $matches)) {
            $result['customer_name'] = trim(($matches[1] ?? '') . ' ' . explode("\n", $matches[2])[0]);
        }

        // Phone number in customer block: e.g. 081911121991 or (021) ...
        if (preg_match('/(08[0-9]{8,12}|\+62[0-9]{8,12}|021\s*[\-\.]?\s*[0-9]{6,8})/i', $text, $matches)) {
            $result['customer_phone'] = trim($matches[1]);
        }

        // Address lines
        if (preg_match('/(Jl\.?\s+[^\r\n]+(?:[\r\n]+[^\r\n]+){1,3})/i', $text, $matches)) {
            $rawAddr = trim($matches[1]);
            $rawAddr = preg_replace('/\bS$/', '', $rawAddr);
            if (!empty($result['customer_phone'])) {
                $rawAddr = str_replace($result['customer_phone'], '', $rawAddr);
            }
            $result['customer_address'] = trim(preg_replace('/\s+/', ' ', $rawAddr));
            $result['customer_address'] = rtrim($result['customer_address'], " \t\n\r\0\x0B,S");
        }

        // 7. Extract Lead Time
        if (stripos($text, 'INDENT') !== false) {
            $result['lead_time'] = 'Indent 4-6 Minggu';
        } elseif (stripos($text, 'READY STOCK') !== false || stripos($text, 'STOCK') !== false) {
            $result['lead_time'] = 'Ready Stock';
        }

        // 8. Extract Authorized Signature (Sales person)
        if (preg_match('/([A-Za-z\s]+?)\s*-\s*[0-9]{6,}/i', $text, $matches)) {
            $result['sales_person'] = trim($matches[1]);
        } elseif (preg_match('/Authorized\s*Signature\s*(?:[\r\n]+)\s*([A-Za-z\s]+?)(?:\s*-\s*[0-9]+)?(?:[\r\n]|$)/i', $text, $matches)) {
            $result['sales_person'] = trim($matches[1]);
        }

        // 9. Extract Items Table
        // Primary Pattern for Epicor:
        // No. Part Number Description Part Interchange Qty Qty Avb Unit Price Discount Amount
        // 1 50421067\tOn-board computer\t1\t281,110,000.00\t267,054,500.00\tEA\t5.00 %\t0\tEA
        $itemRegex = '/(\d+)\s+([0-9A-Za-z\-_]+)[\t\s]+([^\t\r\n]+?)[\t\s]+(\d+(?:\.\d+)?)[\t\s]+([0-9\.,]+)[\t\s]+([0-9\.,]+)[\t\s]+([A-Za-z]+)[\t\s]+([0-9\.,]+)\s*%[\t\s]+([0-9]+)[\t\s]+([A-Za-z]+)/i';
        if (preg_match_all($itemRegex, $text, $itemMatches, PREG_SET_ORDER)) {
            foreach ($itemMatches as $m) {
                $partNumber = trim($m[2]);
                $description = trim($m[3]);
                $qty = (int) $m[4];
                $unitPrice = $this->parseMoney($m[5]);
                $totalPrice = $this->parseMoney($m[6]);
                $uom = strtoupper(trim($m[7]));
                $discountPct = (float) str_replace(',', '.', $m[8]);

                // Map UOM
                $uomMapped = match ($uom) {
                    'EA', 'PCS', 'PC' => 'Pcs',
                    'UNT', 'UNIT' => 'Unit',
                    'SET' => 'Set',
                    'PR', 'PAIR' => 'Pair',
                    default => 'Pcs',
                };

                // Detect item category
                $itemCategory = 'OTHER';
                if (preg_match('/^(504|500|510|520|530|540|550|2\d{7}|\d{8})$/', $partNumber) || stripos($description, 'jungheinrich') !== false || stripos($description, 'wheel') !== false || stripos($description, 'computer') !== false) {
                    $itemCategory = 'JUNGHEINRICH_PART';
                } elseif (stripos($description, 'solid') !== false || stripos($description, 'tyre') !== false || stripos($description, 'ban') !== false) {
                    $itemCategory = 'TYRE_COUNTERBALANCE';
                } elseif (stripos($description, 'radial') !== false || stripos($partNumber, 'tiron') !== false) {
                    $itemCategory = 'TIRON_RADIAL';
                } elseif (stripos($description, 'bias') !== false) {
                    $itemCategory = 'TIRON_BIAS';
                }

                $result['items'][] = [
                    'part_number' => $partNumber,
                    'description' => $description,
                    'category' => $itemCategory,
                    'qty' => $qty,
                    'uom' => $uomMapped,
                    'unit_price' => $unitPrice,
                    'discount_pct' => $discountPct,
                    'total_price' => $totalPrice,
                ];
            }
        }

        // Secondary / fallback pattern
        if (empty($result['items'])) {
            $fallbackRegex = '/(\d+)\s+([0-9A-Za-z\-_]+)\s+(.+?)\s+(\d+(?:\.\d+)?)\s+([A-Za-z]+)\s+(\d+(?:\.\d+)?)\s+([A-Za-z]+)\s+([0-9\.,]+)\s+([0-9\.,]+)\s*%\s+([0-9\.,]+)/i';
            if (preg_match_all($fallbackRegex, $text, $fbMatches, PREG_SET_ORDER)) {
                foreach ($fbMatches as $m) {
                    $result['items'][] = [
                        'part_number' => trim($m[2]),
                        'description' => trim($m[3]),
                        'category' => 'JUNGHEINRICH_PART',
                        'qty' => (int) $m[4],
                        'uom' => 'Pcs',
                        'unit_price' => $this->parseMoney($m[8]),
                        'discount_pct' => (float) str_replace(',', '.', $m[9]),
                        'total_price' => $this->parseMoney($m[10]),
                    ];
                }
            }
        }

        // 10. Extract GrandTotal, Sub Total, VAT
        if (preg_match('/Terms\s+and\s+Condition\s*:\s*([0-9\.,]+)\s+([0-9\.,]+)\s+([0-9\.,]+)/i', $text, $m)) {
            $result['total_amount'] = $this->parseMoney($m[1]);
            $result['sub_total'] = $this->parseMoney($m[2]);
            $result['vat'] = $this->parseMoney($m[3]);
        } elseif (preg_match('/GrandTotal\s*[:\-]?\s*(?:Rp\.?|IDR)?\s*([0-9]{1,3}(?:,[0-9]{3})+(?:\.[0-9]{2})?)/i', $text, $matches)) {
            $result['total_amount'] = $this->parseMoney($matches[1]);
        } elseif (preg_match('/(?:Total\s*Amount|Total\s*Nilai|Grand\s*Total)\s*[:\-]?\s*(?:Rp\.?|IDR)?\s*([0-9]{1,3}(?:,[0-9]{3})+(?:\.[0-9]{2})?)/i', $text, $matches)) {
            $result['total_amount'] = $this->parseMoney($matches[1]);
        }

        if ($result['total_amount'] == 0 && !empty($result['items'])) {
            $sum = array_sum(array_column($result['items'], 'total_price'));
            $result['sub_total'] = $sum;
            $result['vat'] = round($sum * 0.11, 2);
            $result['total_amount'] = round($sum * 1.11, 2);
        }

        // Determine main category
        if (!empty($result['items'])) {
            $categories = array_unique(array_column($result['items'], 'category'));
            if (count($categories) > 1) {
                $result['category'] = 'MIXED';
            } elseif (in_array('JUNGHEINRICH_PART', $categories)) {
                $result['category'] = 'SPAREPART_JUNGHEINRICH';
            } elseif (in_array('TYRE_COUNTERBALANCE', $categories)) {
                $result['category'] = 'TYRE_FORKLIFT';
            } elseif (in_array('TIRON_RADIAL', $categories) || in_array('TIRON_BIAS', $categories)) {
                $result['category'] = 'TYRE_TRUCK_TIRON';
            }
        }
    }

    /**
     * Clean and parse number with commas and dots.
     */
    protected function parseMoney(string $raw): float
    {
        $cleaned = trim($raw);
        if (str_contains($cleaned, '.') && str_contains($cleaned, ',')) {
            if (strrpos($cleaned, '.') > strrpos($cleaned, ',')) {
                $cleaned = str_replace(',', '', $cleaned);
            } else {
                $cleaned = str_replace('.', '', $cleaned);
                $cleaned = str_replace(',', '.', $cleaned);
            }
        } elseif (str_contains($cleaned, '.')) {
            $parts = explode('.', $cleaned);
            if (count($parts) > 2 || (count($parts) === 2 && strlen($parts[1]) === 3)) {
                $cleaned = str_replace('.', '', $cleaned);
            }
        } elseif (str_contains($cleaned, ',')) {
            $parts = explode(',', $cleaned);
            if (count($parts) > 2 || (count($parts) === 2 && strlen($parts[1]) === 3)) {
                $cleaned = str_replace(',', '', $cleaned);
            } else {
                $cleaned = str_replace(',', '.', $cleaned);
            }
        }
        return (float) preg_replace('/[^0-9.]/', '', $cleaned);
    }
}
