<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Customer;
use Carbon\Carbon;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $customers = [
            // VIP Customers
            [
                'name' => 'Budi Santoso',
                'email' => 'budi.santoso@email.com',
                'phone' => '081234567890',
                'birth_date' => '1980-05-15',
                'gender' => 'male',
                'address' => 'Jl. Sudirman No. 123',
                'city' => 'Jakarta',
                'state' => 'DKI Jakarta',
                'postal_code' => '10220',
                'country' => 'Indonesia',
                'type' => 'vip',
                'status' => 'active',
                'credit_limit' => 50000000,
                'total_spent' => 15000000,
                'total_orders' => 45,
                'loyalty_points' => 15000,
                'last_purchase_date' => Carbon::now()->subDays(3),
                'company_name' => 'PT. Santoso Jaya',
                'tax_number' => '123456789012345',
            ],

            [
                'name' => 'Siti Nurhaliza',
                'email' => 'siti.nurhaliza@email.com',
                'phone' => '081234567891',
                'birth_date' => '1985-08-22',
                'gender' => 'female',
                'address' => 'Jl. Thamrin No. 456',
                'city' => 'Jakarta',
                'state' => 'DKI Jakarta',
                'postal_code' => '10230',
                'country' => 'Indonesia',
                'type' => 'vip',
                'status' => 'active',
                'credit_limit' => 30000000,
                'total_spent' => 12000000,
                'total_orders' => 38,
                'loyalty_points' => 12000,
                'last_purchase_date' => Carbon::now()->subDays(7),
            ],

            // Wholesale Customers
            [
                'name' => 'Ahmad Wijaya',
                'email' => 'ahmad.wijaya@tokowijaya.com',
                'phone' => '081234567892',
                'birth_date' => '1975-12-10',
                'gender' => 'male',
                'address' => 'Jl. Mangga Dua No. 789',
                'city' => 'Jakarta',
                'state' => 'DKI Jakarta',
                'postal_code' => '10730',
                'country' => 'Indonesia',
                'type' => 'wholesale',
                'status' => 'active',
                'credit_limit' => 100000000,
                'total_spent' => 25000000,
                'total_orders' => 67,
                'loyalty_points' => 25000,
                'last_purchase_date' => Carbon::now()->subDays(1),
                'company_name' => 'Toko Wijaya Elektronik',
                'tax_number' => '987654321098765',
                'notes' => 'Reseller elektronik di Mangga Dua',
            ],

            [
                'name' => 'Rina Susanti',
                'email' => 'rina@supermarket-bahagia.com',
                'phone' => '081234567893',
                'birth_date' => '1982-03-18',
                'gender' => 'female',
                'address' => 'Jl. Pasar Baru No. 321',
                'city' => 'Jakarta',
                'state' => 'DKI Jakarta',
                'postal_code' => '10110',
                'country' => 'Indonesia',
                'type' => 'wholesale',
                'status' => 'active',
                'credit_limit' => 75000000,
                'total_spent' => 18000000,
                'total_orders' => 52,
                'loyalty_points' => 18000,
                'last_purchase_date' => Carbon::now()->subDays(5),
                'company_name' => 'Supermarket Bahagia',
                'tax_number' => '456789123456789',
            ],

            // Member Customers
            [
                'name' => 'Bambang Pamungkas',
                'email' => 'bambang.pamungkas@gmail.com',
                'phone' => '081234567894',
                'birth_date' => '1990-07-25',
                'gender' => 'male',
                'address' => 'Jl. Kemang Raya No. 654',
                'city' => 'Jakarta',
                'state' => 'DKI Jakarta',
                'postal_code' => '12560',
                'country' => 'Indonesia',
                'type' => 'member',
                'status' => 'active',
                'credit_limit' => 5000000,
                'total_spent' => 3500000,
                'total_orders' => 28,
                'loyalty_points' => 3500,
                'last_purchase_date' => Carbon::now()->subDays(12),
            ],

            [
                'name' => 'Diana Sari',
                'email' => 'diana.sari@yahoo.com',
                'phone' => '081234567895',
                'birth_date' => '1988-11-03',
                'gender' => 'female',
                'address' => 'Jl. Pondok Indah No. 987',
                'city' => 'Jakarta',
                'state' => 'DKI Jakarta',
                'postal_code' => '12310',
                'country' => 'Indonesia',
                'type' => 'member',
                'status' => 'active',
                'credit_limit' => 3000000,
                'total_spent' => 2800000,
                'total_orders' => 22,
                'loyalty_points' => 2800,
                'last_purchase_date' => Carbon::now()->subDays(8),
            ],

            // Regular Customers
            [
                'name' => 'Joko Susilo',
                'email' => 'joko.susilo@email.com',
                'phone' => '081234567896',
                'birth_date' => '1992-01-14',
                'gender' => 'male',
                'address' => 'Jl. Tebet Raya No. 111',
                'city' => 'Jakarta',
                'state' => 'DKI Jakarta',
                'postal_code' => '12810',
                'country' => 'Indonesia',
                'type' => 'regular',
                'status' => 'active',
                'credit_limit' => 0,
                'total_spent' => 850000,
                'total_orders' => 12,
                'loyalty_points' => 850,
                'last_purchase_date' => Carbon::now()->subDays(15),
            ],

            [
                'name' => 'Lestari Indah',
                'email' => 'lestari.indah@gmail.com',
                'phone' => '081234567897',
                'birth_date' => '1995-09-30',
                'gender' => 'female',
                'address' => 'Jl. Cipinang No. 222',
                'city' => 'Jakarta',
                'state' => 'DKI Jakarta',
                'postal_code' => '13240',
                'country' => 'Indonesia',
                'type' => 'regular',
                'status' => 'active',
                'credit_limit' => 0,
                'total_spent' => 650000,
                'total_orders' => 8,
                'loyalty_points' => 650,
                'last_purchase_date' => Carbon::now()->subDays(22),
            ],

            [
                'name' => 'Agus Setiawan',
                'email' => 'agus.setiawan@email.com',
                'phone' => '081234567898',
                'birth_date' => '1987-04-12',
                'gender' => 'male',
                'address' => 'Jl. Cempaka Putih No. 333',
                'city' => 'Jakarta',
                'state' => 'DKI Jakarta',
                'postal_code' => '10510',
                'country' => 'Indonesia',
                'type' => 'regular',
                'status' => 'active',
                'credit_limit' => 0,
                'total_spent' => 1250000,
                'total_orders' => 15,
                'loyalty_points' => 1250,
                'last_purchase_date' => Carbon::now()->subDays(18),
            ],

            [
                'name' => 'Maya Puspita',
                'email' => 'maya.puspita@outlook.com',
                'phone' => '081234567899',
                'birth_date' => '1993-06-08',
                'gender' => 'female',
                'address' => 'Jl. Kelapa Gading No. 444',
                'city' => 'Jakarta',
                'state' => 'DKI Jakarta',
                'postal_code' => '14240',
                'country' => 'Indonesia',
                'type' => 'regular',
                'status' => 'active',
                'credit_limit' => 0,
                'total_spent' => 480000,
                'total_orders' => 6,
                'loyalty_points' => 480,
                'last_purchase_date' => Carbon::now()->subDays(25),
            ],

            // Inactive Customer
            [
                'name' => 'Robert Tan',
                'email' => 'robert.tan@email.com',
                'phone' => '081234567800',
                'birth_date' => '1978-02-28',
                'gender' => 'male',
                'address' => 'Jl. PIK No. 555',
                'city' => 'Jakarta',
                'state' => 'DKI Jakarta',
                'postal_code' => '14470',
                'country' => 'Indonesia',
                'type' => 'regular',
                'status' => 'inactive',
                'credit_limit' => 0,
                'total_spent' => 320000,
                'total_orders' => 4,
                'loyalty_points' => 320,
                'last_purchase_date' => Carbon::now()->subDays(120),
                'notes' => 'Inactive customer - no purchases in 120 days',
            ],

            // Customer with referral
            [
                'name' => 'Dewi Lestari',
                'email' => 'dewi.lestari@email.com',
                'phone' => '081234567801',
                'birth_date' => '1991-10-15',
                'gender' => 'female',
                'address' => 'Jl. Menteng No. 666',
                'city' => 'Jakarta',
                'state' => 'DKI Jakarta',
                'postal_code' => '10310',
                'country' => 'Indonesia',
                'type' => 'member',
                'status' => 'active',
                'credit_limit' => 2000000,
                'total_spent' => 1800000,
                'total_orders' => 18,
                'loyalty_points' => 1800,
                'last_purchase_date' => Carbon::now()->subDays(6),
                'referred_by' => 1, // Referred by Budi Santoso
            ],
        ];

        foreach ($customers as $customerData) {
            $customer = Customer::create($customerData);

            // Update referrer's referral count if applicable
            if (isset($customerData['referred_by'])) {
                $referrer = Customer::find($customerData['referred_by']);
                if ($referrer) {
                    $referrer->increment('referral_count');
                }
            }
        }

        $this->command->info('Customers seeded successfully!');
    }
}
