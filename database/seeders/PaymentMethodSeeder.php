<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\PaymentMethod;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $paymentMethods = [
            // Cash Payment
            [
                'code' => 'CASH',
                'name' => 'Tunai',
                'description' => 'Pembayaran tunai',
                'type' => 'cash',
                'is_active' => true,
                'requires_reference' => false,
                'has_processing_fee' => false,
                'processing_fee_percentage' => 0,
                'processing_fee_fixed' => 0,
                'minimum_amount' => 0,
                'maximum_amount' => null,
                'affects_cash_drawer' => true,
                'requires_change' => true,
                'icon' => 'banknotes',
                'color' => '#10b981',
                'sort_order' => 1,
            ],

            // Debit Cards
            [
                'code' => 'DEBIT',
                'name' => 'Kartu Debit',
                'description' => 'Pembayaran menggunakan kartu debit',
                'type' => 'card',
                'is_active' => true,
                'requires_reference' => true,
                'has_processing_fee' => true,
                'processing_fee_percentage' => 0.5,
                'processing_fee_fixed' => 0,
                'minimum_amount' => 10000,
                'maximum_amount' => 25000000,
                'affects_cash_drawer' => false,
                'requires_change' => false,
                'icon' => 'credit-card',
                'color' => '#3b82f6',
                'sort_order' => 2,
            ],

            // Credit Cards
            [
                'code' => 'CREDIT',
                'name' => 'Kartu Kredit',
                'description' => 'Pembayaran menggunakan kartu kredit',
                'type' => 'card',
                'is_active' => true,
                'requires_reference' => true,
                'has_processing_fee' => true,
                'processing_fee_percentage' => 2.5,
                'processing_fee_fixed' => 0,
                'minimum_amount' => 10000,
                'maximum_amount' => 50000000,
                'affects_cash_drawer' => false,
                'requires_change' => false,
                'icon' => 'credit-card',
                'color' => '#f59e0b',
                'sort_order' => 3,
            ],

            // Digital Wallets
            [
                'code' => 'GOPAY',
                'name' => 'GoPay',
                'description' => 'Pembayaran menggunakan GoPay',
                'type' => 'digital_wallet',
                'is_active' => true,
                'requires_reference' => true,
                'has_processing_fee' => true,
                'processing_fee_percentage' => 0.7,
                'processing_fee_fixed' => 0,
                'minimum_amount' => 1000,
                'maximum_amount' => 20000000,
                'affects_cash_drawer' => false,
                'requires_change' => false,
                'gateway_provider' => 'gopay',
                'icon' => 'device-phone-mobile',
                'color' => '#00aa5b',
                'sort_order' => 4,
            ],

            [
                'code' => 'OVO',
                'name' => 'OVO',
                'description' => 'Pembayaran menggunakan OVO',
                'type' => 'digital_wallet',
                'is_active' => true,
                'requires_reference' => true,
                'has_processing_fee' => true,
                'processing_fee_percentage' => 0.7,
                'processing_fee_fixed' => 0,
                'minimum_amount' => 1000,
                'maximum_amount' => 20000000,
                'affects_cash_drawer' => false,
                'requires_change' => false,
                'gateway_provider' => 'ovo',
                'icon' => 'device-phone-mobile',
                'color' => '#4c1d95',
                'sort_order' => 5,
            ],

            [
                'code' => 'DANA',
                'name' => 'DANA',
                'description' => 'Pembayaran menggunakan DANA',
                'type' => 'digital_wallet',
                'is_active' => true,
                'requires_reference' => true,
                'has_processing_fee' => true,
                'processing_fee_percentage' => 0.7,
                'processing_fee_fixed' => 0,
                'minimum_amount' => 1000,
                'maximum_amount' => 20000000,
                'affects_cash_drawer' => false,
                'requires_change' => false,
                'gateway_provider' => 'dana',
                'icon' => 'device-phone-mobile',
                'color' => '#118ec7',
                'sort_order' => 6,
            ],

            [
                'code' => 'SHOPEEPAY',
                'name' => 'ShopeePay',
                'description' => 'Pembayaran menggunakan ShopeePay',
                'type' => 'digital_wallet',
                'is_active' => true,
                'requires_reference' => true,
                'has_processing_fee' => true,
                'processing_fee_percentage' => 0.7,
                'processing_fee_fixed' => 0,
                'minimum_amount' => 1000,
                'maximum_amount' => 20000000,
                'affects_cash_drawer' => false,
                'requires_change' => false,
                'gateway_provider' => 'shopeepay',
                'icon' => 'device-phone-mobile',
                'color' => '#f97316',
                'sort_order' => 7,
            ],

            // Bank Transfers
            [
                'code' => 'BANK_BCA',
                'name' => 'Transfer BCA',
                'description' => 'Transfer bank BCA',
                'type' => 'bank_transfer',
                'is_active' => true,
                'requires_reference' => true,
                'has_processing_fee' => true,
                'processing_fee_percentage' => 0,
                'processing_fee_fixed' => 6500,
                'minimum_amount' => 10000,
                'maximum_amount' => 500000000,
                'affects_cash_drawer' => false,
                'requires_change' => false,
                'account_number' => '1234567890',
                'icon' => 'building-library',
                'color' => '#1e40af',
                'sort_order' => 8,
            ],

            [
                'code' => 'BANK_MANDIRI',
                'name' => 'Transfer Mandiri',
                'description' => 'Transfer bank Mandiri',
                'type' => 'bank_transfer',
                'is_active' => true,
                'requires_reference' => true,
                'has_processing_fee' => true,
                'processing_fee_percentage' => 0,
                'processing_fee_fixed' => 6500,
                'minimum_amount' => 10000,
                'maximum_amount' => 500000000,
                'affects_cash_drawer' => false,
                'requires_change' => false,
                'account_number' => '0987654321',
                'icon' => 'building-library',
                'color' => '#eab308',
                'sort_order' => 9,
            ],

            [
                'code' => 'BANK_BRI',
                'name' => 'Transfer BRI',
                'description' => 'Transfer bank BRI',
                'type' => 'bank_transfer',
                'is_active' => true,
                'requires_reference' => true,
                'has_processing_fee' => true,
                'processing_fee_percentage' => 0,
                'processing_fee_fixed' => 6500,
                'minimum_amount' => 10000,
                'maximum_amount' => 500000000,
                'affects_cash_drawer' => false,
                'requires_change' => false,
                'account_number' => '1122334455',
                'icon' => 'building-library',
                'color' => '#dc2626',
                'sort_order' => 10,
            ],

            // QRIS (QR Code Indonesian Standard)
            [
                'code' => 'QRIS',
                'name' => 'QRIS',
                'description' => 'Pembayaran menggunakan QRIS',
                'type' => 'digital_wallet',
                'is_active' => true,
                'requires_reference' => true,
                'has_processing_fee' => true,
                'processing_fee_percentage' => 0.7,
                'processing_fee_fixed' => 0,
                'minimum_amount' => 1000,
                'maximum_amount' => 20000000,
                'affects_cash_drawer' => false,
                'requires_change' => false,
                'gateway_provider' => 'qris',
                'icon' => 'qr-code',
                'color' => '#7c3aed',
                'sort_order' => 11,
            ],

            // Credit/Store Credit
            [
                'code' => 'STORE_CREDIT',
                'name' => 'Kredit Toko',
                'description' => 'Pembayaran menggunakan kredit toko',
                'type' => 'credit',
                'is_active' => true,
                'requires_reference' => false,
                'has_processing_fee' => false,
                'processing_fee_percentage' => 0,
                'processing_fee_fixed' => 0,
                'minimum_amount' => 0,
                'maximum_amount' => null,
                'affects_cash_drawer' => false,
                'requires_change' => false,
                'icon' => 'receipt-percent',
                'color' => '#64748b',
                'sort_order' => 12,
            ],

            // Voucher
            [
                'code' => 'VOUCHER',
                'name' => 'Voucher',
                'description' => 'Pembayaran menggunakan voucher',
                'type' => 'voucher',
                'is_active' => true,
                'requires_reference' => true,
                'has_processing_fee' => false,
                'processing_fee_percentage' => 0,
                'processing_fee_fixed' => 0,
                'minimum_amount' => 0,
                'maximum_amount' => null,
                'affects_cash_drawer' => false,
                'requires_change' => false,
                'icon' => 'ticket',
                'color' => '#ec4899',
                'sort_order' => 13,
            ],
        ];

        foreach ($paymentMethods as $method) {
            PaymentMethod::updateOrCreate(
                ['code' => $method['code']],
                $method
            );
        }

        $this->command->info('Payment methods seeded successfully!');
    }
}
