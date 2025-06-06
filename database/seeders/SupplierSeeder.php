<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $suppliers = [
            // Food & Beverage Suppliers
            [
                'code' => 'SUP001',
                'name' => 'PT Indofood Sukses Makmur',
                'contact_person' => 'Budi Santoso',
                'email' => 'procurement@indofood.co.id',
                'phone' => '021-29088888',
                'address' => 'Sudirman Plaza, Jl. Jenderal Sudirman Kav. 76-78, Jakarta Selatan',
                'city' => 'Jakarta',
                'country' => 'Indonesia',
                'tax_number' => '01.001.234.5-001.000',
                'payment_terms' => 30,
                'status' => 'active',
            ],
            [
                'code' => 'SUP002',
                'name' => 'PT Mayora Indah',
                'contact_person' => 'Siti Nurhaliza',
                'email' => 'supplier@mayora.co.id',
                'phone' => '021-29269999',
                'address' => 'Gedung Mayora, Jl. Tomang Raya No. 21-23, Jakarta Barat',
                'city' => 'Jakarta',
                'country' => 'Indonesia',
                'tax_number' => '01.002.345.6-001.000',
                'payment_terms' => 21,
                'status' => 'active',
            ],
            [
                'code' => 'SUP003',
                'name' => 'PT Tiga Pilar Sejahtera Food',
                'contact_person' => 'Ahmad Wijaya',
                'email' => 'sales@tpsf.co.id',
                'phone' => '021-80827777',
                'address' => 'Jl. Letjen MT Haryono Kav. 12, Jakarta Timur',
                'city' => 'Jakarta',
                'country' => 'Indonesia',
                'tax_number' => '01.003.456.7-001.000',
                'payment_terms' => 14,
                'status' => 'active',
            ],

            // Personal Care & Household Suppliers
            [
                'code' => 'SUP004',
                'name' => 'PT Unilever Indonesia',
                'contact_person' => 'Maria Santika',
                'email' => 'b2b@unilever.co.id',
                'phone' => '021-27985000',
                'address' => 'Graha Unilever, Jl. BSD Boulevard Barat, Green Office Park, Tangerang',
                'city' => 'Tangerang',
                'country' => 'Indonesia',
                'tax_number' => '01.004.567.8-001.000',
                'payment_terms' => 30,
                'status' => 'active',
            ],
            [
                'code' => 'SUP005',
                'name' => 'PT Procter & Gamble Indonesia',
                'contact_person' => 'Robert Chen',
                'email' => 'wholesale@pg.co.id',
                'phone' => '021-29889000',
                'address' => 'World Trade Center 6, Jl. Jenderal Sudirman Kav. 31, Jakarta Selatan',
                'city' => 'Jakarta',
                'country' => 'Indonesia',
                'tax_number' => '01.005.678.9-001.000',
                'payment_terms' => 45,
                'status' => 'active',
            ],
            [
                'code' => 'SUP006',
                'name' => 'PT Johnson & Johnson Indonesia',
                'contact_person' => 'Diana Kusuma',
                'email' => 'supply@jnj.co.id',
                'phone' => '021-57900600',
                'address' => 'Lippo St. Moritz Office Tower, Jl. Puri Indah Raya Blok U1, Jakarta Barat',
                'city' => 'Jakarta',
                'country' => 'Indonesia',
                'tax_number' => '01.006.789.0-001.000',
                'payment_terms' => 30,
                'status' => 'active',
            ],

            // Electronics & Technology Suppliers
            [
                'code' => 'SUP007',
                'name' => 'PT Samsung Electronics Indonesia',
                'contact_person' => 'Kevin Park',
                'email' => 'b2b.sales@samsung.co.id',
                'phone' => '021-29219999',
                'address' => 'Samsung Building, Jl. Jenderal Sudirman Kav. 25, Jakarta Selatan',
                'city' => 'Jakarta',
                'country' => 'Indonesia',
                'tax_number' => '01.007.890.1-001.000',
                'payment_terms' => 30,
                'status' => 'active',
            ],
            [
                'code' => 'SUP008',
                'name' => 'PT Panasonic Gobel Indonesia',
                'contact_person' => 'Takeshi Yamamoto',
                'email' => 'wholesale@panasonic.co.id',
                'phone' => '021-29798888',
                'address' => 'Panasonic Tower, Jl. M.H. Thamrin No. 10, Jakarta Pusat',
                'city' => 'Jakarta',
                'country' => 'Indonesia',
                'tax_number' => '01.008.901.2-001.000',
                'payment_terms' => 21,
                'status' => 'active',
            ],

            // Stationery & Office Supplies
            [
                'code' => 'SUP009',
                'name' => 'PT Faber-Castell Indonesia',
                'contact_person' => 'Lisa Hartono',
                'email' => 'sales@faber-castell.co.id',
                'phone' => '021-89906000',
                'address' => 'Jl. Raya Bekasi Km. 25, Cakung, Jakarta Timur',
                'city' => 'Jakarta',
                'country' => 'Indonesia',
                'tax_number' => '01.009.012.3-001.000',
                'payment_terms' => 14,
                'status' => 'active',
            ],
            [
                'code' => 'SUP010',
                'name' => 'PT Pilot Pen Indonesia',
                'contact_person' => 'Hiroshi Tanaka',
                'email' => 'order@pilot.co.id',
                'phone' => '021-46826888',
                'address' => 'Jl. Raya Serpong Km. 8, Tangerang Selatan',
                'city' => 'Tangerang',
                'country' => 'Indonesia',
                'tax_number' => '01.010.123.4-001.000',
                'payment_terms' => 21,
                'status' => 'active',
            ],
            [
                'code' => 'SUP011',
                'name' => 'CV Cahaya Stationery',
                'contact_person' => 'Andi Setiawan',
                'email' => 'cahaya.stat@gmail.com',
                'phone' => '021-8765432',
                'address' => 'Jl. Pasar Baru No. 45, Jakarta Pusat',
                'city' => 'Jakarta',
                'country' => 'Indonesia',
                'tax_number' => '01.011.234.5-001.000',
                'payment_terms' => 7,
                'status' => 'active',
            ],

            // Health & Pharmacy Suppliers
            [
                'code' => 'SUP012',
                'name' => 'PT Kalbe Farma',
                'contact_person' => 'Dr. Sari Wulandari',
                'email' => 'distribution@kalbe.co.id',
                'phone' => '021-42873888',
                'address' => 'Gedung Kalbe, Jl. Letjen Suprapto Kav. 4, Jakarta Pusat',
                'city' => 'Jakarta',
                'country' => 'Indonesia',
                'tax_number' => '01.012.345.6-001.000',
                'payment_terms' => 30,
                'status' => 'active',
            ],
            [
                'code' => 'SUP013',
                'name' => 'PT Kimia Farma Trading & Distribution',
                'contact_person' => 'Apoteker Rina',
                'email' => 'trading@kimiafarma.co.id',
                'phone' => '021-31927777',
                'address' => 'Jl. Veteran No. 9, Jakarta Pusat',
                'city' => 'Jakarta',
                'country' => 'Indonesia',
                'tax_number' => '01.013.456.7-001.000',
                'payment_terms' => 21,
                'status' => 'active',
            ],

            // Baby & Kids Products
            [
                'code' => 'SUP014',
                'name' => 'PT Sarihusada Generasi Mahardhika',
                'contact_person' => 'Ibu Dewi Sartika',
                'email' => 'b2b@sarihusada.co.id',
                'phone' => '0274-373747',
                'address' => 'Jl. Kusumanegara No. 173, Yogyakarta',
                'city' => 'Yogyakarta',
                'country' => 'Indonesia',
                'tax_number' => '01.014.567.8-001.000',
                'payment_terms' => 30,
                'status' => 'active',
            ],
            [
                'code' => 'SUP015',
                'name' => 'PT Merries Indonesia',
                'contact_person' => 'Yuki Sato',
                'email' => 'sales@merries.co.id',
                'phone' => '021-50998888',
                'address' => 'Jl. TB Simatupang Kav. 88, Jakarta Selatan',
                'city' => 'Jakarta',
                'country' => 'Indonesia',
                'tax_number' => '01.015.678.9-001.000',
                'payment_terms' => 21,
                'status' => 'active',
            ],

            // Local Distributors & Wholesalers
            [
                'code' => 'SUP016',
                'name' => 'CV Maju Bersama Trading',
                'contact_person' => 'Hendra Lim',
                'email' => 'majubersama@yahoo.com',
                'phone' => '021-6543210',
                'address' => 'Jl. Mangga Besar No. 88, Jakarta Barat',
                'city' => 'Jakarta',
                'country' => 'Indonesia',
                'tax_number' => '01.016.789.0-001.000',
                'payment_terms' => 14,
                'status' => 'active',
            ],
            [
                'code' => 'SUP017',
                'name' => 'PT Sukses Makmur Distributor',
                'contact_person' => 'Bambang Wibowo',
                'email' => 'suksesmakmur@gmail.com',
                'phone' => '021-7890123',
                'address' => 'Jl. Gajah Mada No. 156, Jakarta Pusat',
                'city' => 'Jakarta',
                'country' => 'Indonesia',
                'tax_number' => '01.017.890.1-001.000',
                'payment_terms' => 7,
                'status' => 'active',
            ],
            [
                'code' => 'SUP018',
                'name' => 'UD Berkah Jaya',
                'contact_person' => 'Ibu Sari',
                'email' => 'berkahjaya88@gmail.com',
                'phone' => '0361-234567',
                'address' => 'Jl. Sunset Road No. 99, Denpasar, Bali',
                'city' => 'Denpasar',
                'country' => 'Indonesia',
                'tax_number' => '01.018.901.2-001.000',
                'payment_terms' => 10,
                'status' => 'active',
            ],

            // Frozen Food Suppliers
            [
                'code' => 'SUP019',
                'name' => 'PT Charoen Pokphand Indonesia',
                'contact_person' => 'James Tan',
                'email' => 'frozen@cpindo.co.id',
                'phone' => '021-29525555',
                'address' => 'Jl. Ancol Barat No. 1-3, Jakarta Utara',
                'city' => 'Jakarta',
                'country' => 'Indonesia',
                'tax_number' => '01.019.012.3-001.000',
                'payment_terms' => 21,
                'status' => 'active',
            ],
            [
                'code' => 'SUP020',
                'name' => 'PT Belfoods Indonesia',
                'contact_person' => 'Chef Marco',
                'email' => 'supply@belfoods.co.id',
                'phone' => '021-29567777',
                'address' => 'Jl. Pluit Selatan Raya No. 18, Jakarta Utara',
                'city' => 'Jakarta',
                'country' => 'Indonesia',
                'tax_number' => '01.020.123.4-001.000',
                'payment_terms' => 14,
                'status' => 'active',
            ],
        ];

        foreach ($suppliers as $supplierData) {
            Supplier::create($supplierData);
        }

        $this->command->info('Suppliers seeded successfully!');
        $this->command->info('Created ' . count($suppliers) . ' supplier records with realistic Indonesian supplier data.');
        $this->command->line('');
        $this->command->line('Supplier Categories:');
        $this->command->line('- Food & Beverage: Indofood, Mayora, Tiga Pilar');
        $this->command->line('- Personal Care: Unilever, P&G, Johnson & Johnson');
        $this->command->line('- Electronics: Samsung, Panasonic');
        $this->command->line('- Stationery: Faber-Castell, Pilot, Local suppliers');
        $this->command->line('- Health & Pharmacy: Kalbe Farma, Kimia Farma');
        $this->command->line('- Baby Products: Sarihusada, Merries');
        $this->command->line('- Local Distributors: Various wholesalers');
        $this->command->line('- Frozen Food: Charoen Pokphand, Belfoods');
        $this->command->line('');
        $this->command->line('Payment Terms Range: 7-45 days');
        $this->command->line('All suppliers are set to active status');
    }
}
