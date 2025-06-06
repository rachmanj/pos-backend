<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Unit;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Warehouse;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // First, create categories if they don't exist
        $categories = $this->createCategories();

        // Create units if they don't exist
        $units = $this->createUnits();

        // Get default warehouse
        $warehouse = Warehouse::first();
        if (!$warehouse) {
            $warehouse = Warehouse::create([
                'name' => 'Main Warehouse',
                'code' => 'WH001',
                'description' => 'Main warehouse for POS-ATK system',
                'type' => 'main',
                'address' => 'Jl. Sudirman No. 123, Jakarta Pusat',
                'city' => 'Jakarta',
                'state' => 'DKI Jakarta',
                'postal_code' => '10220',
                'country' => 'Indonesia',
                'phone' => '021-5551234',
                'email' => 'warehouse@pos-atk.com',
                'manager_name' => 'Budi Santoso',
                'manager_phone' => '021-5551235',
                'total_area' => 1000.00,
                'storage_area' => 800.00,
                'max_capacity' => 10000,
                'current_utilization' => 0,
                'opening_time' => '08:00:00',
                'closing_time' => '17:00:00',
                'operating_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'],
                'is_default' => true,
                'status' => 'active'
            ]);
        }

        // Create 50 realistic Indonesian market products
        $products = [
            // Makanan & Minuman (Food & Beverages)
            ['name' => 'Indomie Goreng Original', 'category' => 'Makanan Instan', 'unit' => 'pcs', 'price' => 3500, 'cost' => 2800, 'stock' => 200, 'barcode' => '8993456789001'],
            ['name' => 'Indomie Soto Ayam', 'category' => 'Makanan Instan', 'unit' => 'pcs', 'price' => 3500, 'cost' => 2800, 'stock' => 150, 'barcode' => '8993456789002'],
            ['name' => 'Pop Mie Ayam Bawang', 'category' => 'Makanan Instan', 'unit' => 'pcs', 'price' => 4500, 'cost' => 3600, 'stock' => 100, 'barcode' => '8993456789003'],
            ['name' => 'Aqua Air Mineral 600ml', 'category' => 'Minuman', 'unit' => 'botol', 'price' => 3000, 'cost' => 2200, 'stock' => 300, 'barcode' => '8993456789004'],
            ['name' => 'Teh Botol Sosro 450ml', 'category' => 'Minuman', 'unit' => 'botol', 'price' => 4000, 'cost' => 3000, 'stock' => 180, 'barcode' => '8993456789005'],
            ['name' => 'Coca Cola 330ml', 'category' => 'Minuman', 'unit' => 'kaleng', 'price' => 5000, 'cost' => 3800, 'stock' => 120, 'barcode' => '8993456789006'],
            ['name' => 'Kopi Kapal Api Bubuk 165g', 'category' => 'Minuman', 'unit' => 'pak', 'price' => 15000, 'cost' => 12000, 'stock' => 80, 'barcode' => '8993456789007'],
            ['name' => 'Gula Pasir Gulaku 1kg', 'category' => 'Sembako', 'unit' => 'kg', 'price' => 18000, 'cost' => 15000, 'stock' => 50, 'barcode' => '8993456789008'],
            ['name' => 'Beras Premium 5kg', 'category' => 'Sembako', 'unit' => 'karung', 'price' => 75000, 'cost' => 65000, 'stock' => 30, 'barcode' => '8993456789009'],
            ['name' => 'Minyak Goreng Tropical 2L', 'category' => 'Sembako', 'unit' => 'botol', 'price' => 32000, 'cost' => 28000, 'stock' => 40, 'barcode' => '8993456789010'],

            // Produk Kebersihan & Perawatan (Cleaning & Personal Care)
            ['name' => 'Sabun Mandi Lifebuoy 85g', 'category' => 'Perawatan Pribadi', 'unit' => 'pcs', 'price' => 4500, 'cost' => 3500, 'stock' => 100, 'barcode' => '8993456789011'],
            ['name' => 'Shampo Pantene 170ml', 'category' => 'Perawatan Pribadi', 'unit' => 'botol', 'price' => 18000, 'cost' => 14000, 'stock' => 60, 'barcode' => '8993456789012'],
            ['name' => 'Pasta Gigi Pepsodent 75g', 'category' => 'Perawatan Pribadi', 'unit' => 'tube', 'price' => 8500, 'cost' => 6500, 'stock' => 80, 'barcode' => '8993456789013'],
            ['name' => 'Detergen Rinso 800g', 'category' => 'Kebersihan', 'unit' => 'pak', 'price' => 12000, 'cost' => 9500, 'stock' => 70, 'barcode' => '8993456789014'],
            ['name' => 'Sabun Cuci Piring Sunlight 755ml', 'category' => 'Kebersihan', 'unit' => 'botol', 'price' => 9500, 'cost' => 7500, 'stock' => 90, 'barcode' => '8993456789015'],
            ['name' => 'Tisu Paseo 250 sheets', 'category' => 'Kebersihan', 'unit' => 'pak', 'price' => 15000, 'cost' => 12000, 'stock' => 50, 'barcode' => '8993456789016'],

            // Snack & Permen (Snacks & Candy)
            ['name' => 'Chitato Rasa Sapi Panggang 68g', 'category' => 'Snack', 'unit' => 'pak', 'price' => 8500, 'cost' => 6500, 'stock' => 150, 'barcode' => '8993456789017'],
            ['name' => 'Tango Wafer Cokelat 176g', 'category' => 'Snack', 'unit' => 'pak', 'price' => 6000, 'cost' => 4500, 'stock' => 120, 'barcode' => '8993456789018'],
            ['name' => 'Oreo Original 137g', 'category' => 'Snack', 'unit' => 'pak', 'price' => 12000, 'cost' => 9500, 'stock' => 80, 'barcode' => '8993456789019'],
            ['name' => 'Permen Kopiko 150g', 'category' => 'Permen', 'unit' => 'pak', 'price' => 7500, 'cost' => 6000, 'stock' => 100, 'barcode' => '8993456789020'],
            ['name' => 'Chiki Balls Keju 20g', 'category' => 'Snack', 'unit' => 'pak', 'price' => 2500, 'cost' => 2000, 'stock' => 200, 'barcode' => '8993456789021'],

            // Alat Tulis & Kantor (Stationery & Office)
            ['name' => 'Pulpen Pilot G2 0.7mm', 'category' => 'Alat Tulis', 'unit' => 'pcs', 'price' => 8500, 'cost' => 6500, 'stock' => 80, 'barcode' => '8993456789022'],
            ['name' => 'Pensil 2B Faber Castell', 'category' => 'Alat Tulis', 'unit' => 'pcs', 'price' => 3500, 'cost' => 2500, 'stock' => 100, 'barcode' => '8993456789023'],
            ['name' => 'Buku Tulis 58 Lembar', 'category' => 'Alat Tulis', 'unit' => 'pcs', 'price' => 5000, 'cost' => 3500, 'stock' => 150, 'barcode' => '8993456789024'],
            ['name' => 'Penghapus Steadtler', 'category' => 'Alat Tulis', 'unit' => 'pcs', 'price' => 4000, 'cost' => 3000, 'stock' => 120, 'barcode' => '8993456789025'],
            ['name' => 'Kertas HVS A4 80gr Sidu 500 Lembar', 'category' => 'Alat Tulis', 'unit' => 'rim', 'price' => 45000, 'cost' => 38000, 'stock' => 25, 'barcode' => '8993456789026'],
            ['name' => 'Spidol Snowman Hitam', 'category' => 'Alat Tulis', 'unit' => 'pcs', 'price' => 6500, 'cost' => 5000, 'stock' => 90, 'barcode' => '8993456789027'],
            ['name' => 'Lem UHU Stick 21g', 'category' => 'Alat Tulis', 'unit' => 'pcs', 'price' => 12000, 'cost' => 9500, 'stock' => 60, 'barcode' => '8993456789028'],

            // Elektronik & Aksesoris (Electronics & Accessories)
            ['name' => 'Baterai AA Alkaline Energizer 4pcs', 'category' => 'Elektronik', 'unit' => 'pak', 'price' => 35000, 'cost' => 28000, 'stock' => 40, 'barcode' => '8993456789029'],
            ['name' => 'Kabel USB Type-C 1m', 'category' => 'Elektronik', 'unit' => 'pcs', 'price' => 25000, 'cost' => 18000, 'stock' => 50, 'barcode' => '8993456789030'],
            ['name' => 'Earphone Basic 3.5mm', 'category' => 'Elektronik', 'unit' => 'pcs', 'price' => 45000, 'cost' => 35000, 'stock' => 30, 'barcode' => '8993456789031'],
            ['name' => 'Power Bank 10000mAh', 'category' => 'Elektronik', 'unit' => 'pcs', 'price' => 150000, 'cost' => 120000, 'stock' => 15, 'barcode' => '8993456789032'],

            // Kesehatan & Obat-obatan (Health & Medicine)
            ['name' => 'Paracetamol 500mg Strip', 'category' => 'Obat', 'unit' => 'strip', 'price' => 3500, 'cost' => 2800, 'stock' => 100, 'barcode' => '8993456789033'],
            ['name' => 'Betadine 15ml', 'category' => 'Obat', 'unit' => 'botol', 'price' => 18000, 'cost' => 14000, 'stock' => 40, 'barcode' => '8993456789034'],
            ['name' => 'Masker Medis 3 Ply 50pcs', 'category' => 'Kesehatan', 'unit' => 'box', 'price' => 35000, 'cost' => 28000, 'stock' => 60, 'barcode' => '8993456789035'],
            ['name' => 'Hand Sanitizer 60ml', 'category' => 'Kesehatan', 'unit' => 'botol', 'price' => 12000, 'cost' => 9000, 'stock' => 80, 'barcode' => '8993456789036'],
            ['name' => 'Vitamin C 1000mg 30 Tablet', 'category' => 'Suplemen', 'unit' => 'botol', 'price' => 45000, 'cost' => 35000, 'stock' => 35, 'barcode' => '8993456789037'],

            // Produk Rumah Tangga (Household Items)
            ['name' => 'Lampu LED 12W Phillips', 'category' => 'Rumah Tangga', 'unit' => 'pcs', 'price' => 35000, 'cost' => 28000, 'stock' => 45, 'barcode' => '8993456789038'],
            ['name' => 'Kantong Plastik HD 1kg', 'category' => 'Rumah Tangga', 'unit' => 'pak', 'price' => 25000, 'cost' => 20000, 'stock' => 30, 'barcode' => '8993456789039'],
            ['name' => 'Sendok Stainless Steel Set 6pcs', 'category' => 'Rumah Tangga', 'unit' => 'set', 'price' => 45000, 'cost' => 35000, 'stock' => 20, 'barcode' => '8993456789040'],
            ['name' => 'Gelas Plastik 220ml 12pcs', 'category' => 'Rumah Tangga', 'unit' => 'pak', 'price' => 18000, 'cost' => 14000, 'stock' => 40, 'barcode' => '8993456789041'],

            // Produk Bayi & Anak (Baby & Kids)
            ['name' => 'Popok Bayi Merries M 34pcs', 'category' => 'Bayi', 'unit' => 'pak', 'price' => 85000, 'cost' => 70000, 'stock' => 25, 'barcode' => '8993456789042'],
            ['name' => 'Susu Formula SGM 400g', 'category' => 'Bayi', 'unit' => 'kaleng', 'price' => 65000, 'cost' => 52000, 'stock' => 30, 'barcode' => '8993456789043'],
            ['name' => 'Baby Oil Johnson 200ml', 'category' => 'Bayi', 'unit' => 'botol', 'price' => 28000, 'cost' => 22000, 'stock' => 45, 'barcode' => '8993456789044'],

            // Mainan & Hobi (Toys & Hobbies)
            ['name' => 'Rubik 3x3 Original', 'category' => 'Mainan', 'unit' => 'pcs', 'price' => 55000, 'cost' => 42000, 'stock' => 20, 'barcode' => '8993456789045'],
            ['name' => 'Kartu Uno Original', 'category' => 'Mainan', 'unit' => 'pak', 'price' => 35000, 'cost' => 28000, 'stock' => 30, 'barcode' => '8993456789046'],
            ['name' => 'Puzzle 1000 Pieces', 'category' => 'Mainan', 'unit' => 'box', 'price' => 75000, 'cost' => 60000, 'stock' => 15, 'barcode' => '8993456789047'],

            // Produk Frozen & Dingin (Frozen Products)
            ['name' => 'Nugget Ayam Fiesta 500g', 'category' => 'Frozen', 'unit' => 'pak', 'price' => 28000, 'cost' => 22000, 'stock' => 35, 'barcode' => '8993456789048'],
            ['name' => 'Es Krim Walls Cornetto', 'category' => 'Frozen', 'unit' => 'pcs', 'price' => 8500, 'cost' => 6500, 'stock' => 50, 'barcode' => '8993456789049'],
            ['name' => 'Sosis Ayam Bernardi 500g', 'category' => 'Frozen', 'unit' => 'pak', 'price' => 32000, 'cost' => 25000, 'stock' => 40, 'barcode' => '8993456789050'],
        ];

        foreach ($products as $productData) {
            // Find or create category
            $category = $categories->firstWhere('name', $productData['category']);

            // Find or create unit
            $unit = $units->firstWhere('name', $productData['unit']);

            // Create product
            $product = Product::create([
                'name' => $productData['name'],
                'description' => 'Produk berkualitas tinggi untuk kebutuhan sehari-hari',
                'category_id' => $category->id,
                'unit_id' => $unit->id,
                'sku' => 'SKU' . str_pad(rand(10000, 99999), 5, '0', STR_PAD_LEFT),
                'barcode' => $productData['barcode'],
                'selling_price' => $productData['price'],
                'cost_price' => $productData['cost'],
                'min_stock_level' => 10,
                'max_stock_level' => 1000,
                'status' => 'active',
                'tax_rate' => 11.00, // PPN 11%
            ]);

            // Create stock for the product
            ProductStock::firstOrCreate(
                ['product_id' => $product->id],
                [
                    'current_stock' => $productData['stock'],
                    'reserved_stock' => 0,
                    'available_stock' => $productData['stock'],
                ]
            );
        }

        $this->command->info('Successfully created 50 products with stock levels!');
    }

    private function createCategories()
    {
        $categoryData = [
            ['name' => 'Makanan Instan', 'description' => 'Mie instan dan makanan siap saji'],
            ['name' => 'Minuman', 'description' => 'Minuman ringan, teh, kopi, dan air mineral'],
            ['name' => 'Sembako', 'description' => 'Kebutuhan pokok sehari-hari'],
            ['name' => 'Perawatan Pribadi', 'description' => 'Produk perawatan dan kecantikan'],
            ['name' => 'Kebersihan', 'description' => 'Produk pembersih rumah tangga'],
            ['name' => 'Snack', 'description' => 'Makanan ringan dan cemilan'],
            ['name' => 'Permen', 'description' => 'Permen dan cokelat'],
            ['name' => 'Alat Tulis', 'description' => 'Perlengkapan tulis menulis dan kantor'],
            ['name' => 'Elektronik', 'description' => 'Aksesoris elektronik dan gadget'],
            ['name' => 'Obat', 'description' => 'Obat-obatan bebas dan P3K'],
            ['name' => 'Kesehatan', 'description' => 'Produk kesehatan dan medis'],
            ['name' => 'Suplemen', 'description' => 'Vitamin dan suplemen makanan'],
            ['name' => 'Rumah Tangga', 'description' => 'Peralatan dan perlengkapan rumah'],
            ['name' => 'Bayi', 'description' => 'Perlengkapan bayi dan anak'],
            ['name' => 'Mainan', 'description' => 'Mainan anak dan hobi'],
            ['name' => 'Frozen', 'description' => 'Produk beku dan dingin'],
        ];

        $categories = collect();
        foreach ($categoryData as $data) {
            $category = Category::firstOrCreate(
                ['name' => $data['name']],
                [
                    'description' => $data['description'],
                    'status' => 'active',
                ]
            );
            $categories->push($category);
        }

        return $categories;
    }

    private function createUnits()
    {
        $unitData = [
            'pcs',
            'pak',
            'botol',
            'kaleng',
            'kg',
            'karung',
            'tube',
            'rim',
            'strip',
            'box',
            'set'
        ];

        $units = collect();
        foreach ($unitData as $unitName) {
            $unit = Unit::firstOrCreate(
                ['name' => $unitName],
                [
                    'symbol' => $unitName,
                    'conversion_factor' => 1,
                ]
            );
            $units->push($unit);
        }

        return $units;
    }
}
