<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\CustomerFleet;
use App\Models\PurchaseOrder;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Default Login Users
        User::firstOrCreate([
            'email' => 'sales@kobexindo-equipment.co.id',
        ], [
            'name' => 'Sales Part Consultant',
            'password' => Hash::make('password'),
        ]);

        User::firstOrCreate([
            'email' => 'admin@bpcapp.com',
        ], [
            'name' => 'Administrator',
            'password' => Hash::make('password'),
        ]);

        // 2. Demo Customers
        $c1 = Customer::create([
            'company_name' => 'PT Logistik Nusantara Sejahtera',
            'branch_area' => 'Cikarang',
            'industry_type' => 'Logistik',
            'customer_type' => 'EXISTING_CUSTOMER',
            'address' => 'Kawasan Industri GIIC Blok AA No. 12, Cikarang Pusat, Bekasi',
        ]);

        $c2 = Customer::create([
            'company_name' => 'PT Indofood CBP Sukses Makmur',
            'branch_area' => 'Karawang',
            'industry_type' => 'F&B',
            'customer_type' => 'EXISTING_CUSTOMER',
            'address' => 'Kawasan Industri KIIC Lot C-4, Karawang Barat',
        ]);

        $c3 = Customer::create([
            'company_name' => 'PT Astra Otoparts Tbk',
            'branch_area' => 'Jakarta',
            'industry_type' => 'Otomotif',
            'customer_type' => 'NEW_CUSTOMER',
            'address' => 'Jl. Pegangsaan Dua Km. 2.2, Kelapa Gading, Jakarta Utara',
        ]);

        // 3. Customer Contacts (PIC)
        $pic1 = CustomerContact::create([
            'customer_id' => $c1->id,
            'name' => 'Bpk. Bambang Wijaya',
            'position' => 'Purchasing Manager',
            'email' => 'bambang.wijaya@logistiknusantara.co.id',
            'phone_number' => '0812-8899-7711',
            'is_primary' => true,
        ]);

        $pic2 = CustomerContact::create([
            'customer_id' => $c1->id,
            'name' => 'Bpk. Hendra Gunawan',
            'position' => 'Maintenance & Service Head',
            'email' => 'hendra.g@logistiknusantara.co.id',
            'phone_number' => '0813-2233-4455',
            'is_primary' => false,
        ]);

        $pic3 = CustomerContact::create([
            'customer_id' => $c2->id,
            'name' => 'Ibu Siti Nurhaliza',
            'position' => 'Warehouse Manager',
            'email' => 'siti.nurhaliza@indofood.co.id',
            'phone_number' => '0811-9922-3344',
            'is_primary' => true,
        ]);

        $pic4 = CustomerContact::create([
            'customer_id' => $c3->id,
            'name' => 'Bpk. Rudi Hartono',
            'position' => 'Procurement Specialist',
            'email' => 'rudi.h@component.astra.co.id',
            'phone_number' => '0857-1122-3344',
            'is_primary' => true,
        ]);

        // 4. Customer Fleets
        CustomerFleet::create([
            'customer_id' => $c1->id,
            'fleet_type' => 'FORKLIFT_JUNGHEINRICH',
            'brand' => 'Jungheinrich',
            'model_type' => 'EFG 216',
            'serial_number' => 'JH-EFG-99201',
            'tyre_size_front' => '18x7-8',
            'tyre_size_rear' => '16x6-8',
            'notes' => 'Forklift Counterbalance Elektrik 3-Roda di Gudang A',
        ]);

        CustomerFleet::create([
            'customer_id' => $c1->id,
            'fleet_type' => 'FORKLIFT_JUNGHEINRICH',
            'brand' => 'Jungheinrich',
            'model_type' => 'ETV 214',
            'serial_number' => 'JH-ETV-88310',
            'tyre_size_front' => 'Drive Wheel 343x140',
            'tyre_size_rear' => 'Load Wheel 285x100',
            'notes' => 'Reach Truck High Bay Racking 10 Meter',
        ]);

        CustomerFleet::create([
            'customer_id' => $c1->id,
            'fleet_type' => 'TRUCK',
            'brand' => 'Mitsubishi Fuso',
            'model_type' => 'Canter FE 74 HD',
            'serial_number' => 'MHFE74-2023-019',
            'tyre_size_front' => '7.50-16',
            'tyre_size_rear' => '7.50-16 Double',
            'notes' => 'Armada Truk Logistik Distribusi Jabodetabek',
        ]);

        CustomerFleet::create([
            'customer_id' => $c2->id,
            'fleet_type' => 'FORKLIFT_OTHER',
            'brand' => 'Toyota',
            'model_type' => '8FD25',
            'serial_number' => 'TY-8FD-30112',
            'tyre_size_front' => '7.00-12 Solid',
            'tyre_size_rear' => '6.00-9 Solid',
            'notes' => 'Forklift Diesel 2.5 Ton area Loading Dock',
        ]);

        // 5. Quotations & Items
        // Quote 1: SENT (Hijau, 10 hari lagi)
        $q1 = Quotation::create([
            'quotation_number' => 'Q-2026-0101',
            'customer_id' => $c1->id,
            'contact_id' => $pic1->id,
            'category' => 'SPAREPART_JUNGHEINRICH',
            'quotation_date' => Carbon::now()->subDays(4),
            'valid_until' => Carbon::now()->addDays(10),
            'total_amount' => 28500000,
            'currency' => 'IDR',
            'payment_terms' => 'Net 30 Hari',
            'lead_time' => 'Ready Stock',
            'status' => 'SENT',
        ]);
        QuotationItem::create([
            'quotation_id' => $q1->id,
            'part_number' => '51023849',
            'description' => 'Drive Wheel Polyurethane 343x140 Jungheinrich ETV 214',
            'category' => 'JUNGHEINRICH_PART',
            'qty' => 2,
            'uom' => 'Pcs',
            'unit_price' => 8500000,
            'discount_pct' => 5,
            'total_price' => 16150000,
        ]);
        QuotationItem::create([
            'quotation_id' => $q1->id,
            'part_number' => '50462198',
            'description' => 'Load Wheel Tandem Polyurethane 285x100',
            'category' => 'JUNGHEINRICH_PART',
            'qty' => 4,
            'uom' => 'Pcs',
            'unit_price' => 3250000,
            'discount_pct' => 5,
            'total_price' => 12350000,
        ]);

        // Quote 2: FOLLOW_UP (Kuning, sisa 2 hari!)
        $q2 = Quotation::create([
            'quotation_number' => 'Q-2026-0102',
            'customer_id' => $c2->id,
            'contact_id' => $pic3->id,
            'category' => 'TYRE_FORKLIFT',
            'quotation_date' => Carbon::now()->subDays(12),
            'valid_until' => Carbon::now()->addDays(2),
            'total_amount' => 19800000,
            'currency' => 'IDR',
            'payment_terms' => 'Cash Before Delivery',
            'lead_time' => 'Ready Stock',
            'status' => 'FOLLOW_UP',
        ]);
        QuotationItem::create([
            'quotation_id' => $q2->id,
            'part_number' => 'TY-SOL-70012',
            'description' => 'Solid Tyre / Ban Mati Forklift 7.00-12 Non-Marking White',
            'category' => 'TYRE_COUNTERBALANCE',
            'qty' => 2,
            'uom' => 'Pcs',
            'unit_price' => 5900000,
            'discount_pct' => 0,
            'total_price' => 11800000,
        ]);
        QuotationItem::create([
            'quotation_id' => $q2->id,
            'part_number' => 'TY-SOL-6009',
            'description' => 'Solid Tyre / Ban Mati Forklift 6.00-9 Non-Marking White',
            'category' => 'TYRE_COUNTERBALANCE',
            'qty' => 2,
            'uom' => 'Pcs',
            'unit_price' => 4000000,
            'discount_pct' => 0,
            'total_price' => 8000000,
        ]);

        // Quote 3: WIN (Order PO Resmi Masuk)
        $q3 = Quotation::create([
            'quotation_number' => 'Q-2026-0098',
            'customer_id' => $c1->id,
            'contact_id' => $pic1->id,
            'category' => 'TYRE_TRUCK_TIRON',
            'quotation_date' => Carbon::now()->subDays(20),
            'valid_until' => Carbon::now()->addDays(10),
            'total_amount' => 45000000,
            'currency' => 'IDR',
            'payment_terms' => 'Net 30 Hari',
            'lead_time' => 'Ready Stock',
            'status' => 'WIN',
        ]);
        QuotationItem::create([
            'quotation_id' => $q3->id,
            'part_number' => 'TIRON-75016-RAD',
            'description' => 'Ban Truk Komersial Tiron Radial 7.50R16 14PR Set (Ban Luar, Ban Dalam, Marset)',
            'category' => 'TIRON_RADIAL',
            'qty' => 18,
            'uom' => 'Set',
            'unit_price' => 2500000,
            'discount_pct' => 0,
            'total_price' => 45000000,
        ]);
        PurchaseOrder::create([
            'quotation_id' => $q3->id,
            'po_number' => 'PO-LNS/2026/041',
            'po_date' => Carbon::now()->subDays(5),
            'po_amount' => 45000000,
            'delivery_status' => 'PARTIAL',
            'delivery_due_date' => Carbon::now()->addDays(3),
            'surat_jalan_ref' => 'SJ/KBX/2026/0192',
            'notes' => 'Tahap 1 kirim 10 set, tahap 2 kirim 8 set ke Pool Cikarang',
        ]);

        // Quote 4: LOSE (Alasan: Harga Lebih Tinggi)
        Quotation::create([
            'quotation_number' => 'Q-2026-0085',
            'customer_id' => $c3->id,
            'contact_id' => $pic4->id,
            'category' => 'SPAREPART_JUNGHEINRICH',
            'quotation_date' => Carbon::now()->subDays(25),
            'valid_until' => Carbon::now()->subDays(11),
            'total_amount' => 15200000,
            'currency' => 'IDR',
            'payment_terms' => 'Net 30 Hari',
            'lead_time' => 'Indent 4-6 Minggu',
            'status' => 'LOSE',
            'loss_reason' => 'PRICE_TOO_HIGH',
            'loss_note' => '[Kompetitor: PT Sumber Jaya Teknik] Selisih harga kompetitor lebih murah 12% dan lead time 1 minggu.',
        ]);

        // Quote 5: EXPIRED (Merah)
        Quotation::create([
            'quotation_number' => 'Q-2026-0070',
            'customer_id' => $c2->id,
            'contact_id' => $pic3->id,
            'category' => 'MIXED',
            'quotation_date' => Carbon::now()->subDays(30),
            'valid_until' => Carbon::now()->subDays(5),
            'total_amount' => 32000000,
            'currency' => 'IDR',
            'payment_terms' => 'Net 30 Hari',
            'lead_time' => 'Ready Stock',
            'status' => 'EXPIRED',
        ]);
    }
}
