<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;
use App\Models\Unit;
use App\Models\Supplier;
use App\Models\Product;

class InventorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create base units
        $unitPiece = Unit::create([
            'name' => 'Piece',
            'symbol' => 'pcs',
            'conversion_factor' => 1.000000,
        ]);

        $unitKilogram = Unit::create([
            'name' => 'Kilogram',
            'symbol' => 'kg',
            'conversion_factor' => 1.000000,
        ]);

        $unitLiter = Unit::create([
            'name' => 'Liter',
            'symbol' => 'L',
            'conversion_factor' => 1.000000,
        ]);

        $unitMeter = Unit::create([
            'name' => 'Meter',
            'symbol' => 'm',
            'conversion_factor' => 1.000000,
        ]);

        // Create derived units
        Unit::create([
            'name' => 'Gram',
            'symbol' => 'g',
            'base_unit_id' => $unitKilogram->id,
            'conversion_factor' => 0.001000,
        ]);

        Unit::create([
            'name' => 'Milliliter',
            'symbol' => 'ml',
            'base_unit_id' => $unitLiter->id,
            'conversion_factor' => 0.001000,
        ]);

        Unit::create([
            'name' => 'Centimeter',
            'symbol' => 'cm',
            'base_unit_id' => $unitMeter->id,
            'conversion_factor' => 0.010000,
        ]);

        Unit::create([
            'name' => 'Box',
            'symbol' => 'box',
            'conversion_factor' => 1.000000,
        ]);

        Unit::create([
            'name' => 'Package',
            'symbol' => 'pkg',
            'conversion_factor' => 1.000000,
        ]);

        // Create categories
        $electronics = Category::create([
            'name' => 'Electronics',
            'description' => 'Electronic devices and accessories',
            'status' => 'active',
        ]);

        $smartphones = Category::create([
            'name' => 'Smartphones',
            'description' => 'Mobile phones and smartphones',
            'parent_id' => $electronics->id,
            'status' => 'active',
        ]);

        $laptops = Category::create([
            'name' => 'Laptops',
            'description' => 'Laptop computers and accessories',
            'parent_id' => $electronics->id,
            'status' => 'active',
        ]);

        $accessories = Category::create([
            'name' => 'Accessories',
            'description' => 'Electronic accessories',
            'parent_id' => $electronics->id,
            'status' => 'active',
        ]);

        $clothing = Category::create([
            'name' => 'Clothing',
            'description' => 'Apparel and fashion items',
            'status' => 'active',
        ]);

        $menClothing = Category::create([
            'name' => "Men's Clothing",
            'description' => 'Clothing for men',
            'parent_id' => $clothing->id,
            'status' => 'active',
        ]);

        $womenClothing = Category::create([
            'name' => "Women's Clothing",
            'description' => 'Clothing for women',
            'parent_id' => $clothing->id,
            'status' => 'active',
        ]);

        $books = Category::create([
            'name' => 'Books',
            'description' => 'Books and publications',
            'status' => 'active',
        ]);

        $food = Category::create([
            'name' => 'Food & Beverages',
            'description' => 'Food items and beverages',
            'status' => 'active',
        ]);

        // Create suppliers
        $supplier1 = Supplier::create([
            'name' => 'Tech Distributors Inc',
            'contact_person' => 'John Smith',
            'email' => 'john@techdist.com',
            'phone' => '+1-555-0123',
            'address' => "123 Technology Ave\nSilicon Valley, CA 94000",
            'tax_number' => 'TAX123456789',
            'payment_terms' => 30,
            'status' => 'active',
        ]);

        $supplier2 = Supplier::create([
            'name' => 'Fashion Wholesale Co',
            'contact_person' => 'Sarah Johnson',
            'email' => 'sarah@fashionwholesale.com',
            'phone' => '+1-555-0456',
            'address' => "456 Fashion District\nNew York, NY 10001",
            'tax_number' => 'TAX987654321',
            'payment_terms' => 15,
            'status' => 'active',
        ]);

        $supplier3 = Supplier::create([
            'name' => 'Global Electronics Supply',
            'contact_person' => 'Mike Chen',
            'email' => 'mike@globalesupply.com',
            'phone' => '+1-555-0789',
            'address' => "789 Electronics Blvd\nShenzhen, China",
            'tax_number' => 'TAX456789123',
            'payment_terms' => 45,
            'status' => 'active',
        ]);

        // Create sample products
        Product::create([
            'name' => 'iPhone 15 Pro',
            'description' => 'Latest iPhone with advanced features',
            'sku' => 'IPH15PRO-128',
            'barcode' => '1234567890123',
            'category_id' => $smartphones->id,
            'unit_id' => $unitPiece->id,
            'cost_price' => 800.00,
            'selling_price' => 999.00,
            'min_stock_level' => 5,
            'max_stock_level' => 50,
            'tax_rate' => 10.00,
            'status' => 'active',
        ]);

        Product::create([
            'name' => 'MacBook Pro 16"',
            'description' => 'Professional laptop with M3 chip',
            'sku' => 'MBP16-M3-512',
            'barcode' => '1234567890124',
            'category_id' => $laptops->id,
            'unit_id' => $unitPiece->id,
            'cost_price' => 2000.00,
            'selling_price' => 2499.00,
            'min_stock_level' => 3,
            'max_stock_level' => 20,
            'tax_rate' => 10.00,
            'status' => 'active',
        ]);

        Product::create([
            'name' => 'USB-C Charging Cable',
            'description' => 'High-quality USB-C to USB-C cable',
            'sku' => 'USB-C-CABLE-2M',
            'barcode' => '1234567890125',
            'category_id' => $accessories->id,
            'unit_id' => $unitPiece->id,
            'cost_price' => 5.00,
            'selling_price' => 19.99,
            'min_stock_level' => 20,
            'max_stock_level' => 200,
            'tax_rate' => 10.00,
            'status' => 'active',
        ]);

        Product::create([
            'name' => 'Cotton T-Shirt - Blue',
            'description' => 'Comfortable cotton t-shirt in blue',
            'sku' => 'TSHIRT-COT-BLU-M',
            'barcode' => '1234567890126',
            'category_id' => $menClothing->id,
            'unit_id' => $unitPiece->id,
            'cost_price' => 8.00,
            'selling_price' => 24.99,
            'min_stock_level' => 10,
            'max_stock_level' => 100,
            'tax_rate' => 5.00,
            'status' => 'active',
        ]);

        Product::create([
            'name' => 'JavaScript: The Good Parts',
            'description' => 'Programming book by Douglas Crockford',
            'sku' => 'BOOK-JS-GOOD',
            'barcode' => '1234567890127',
            'category_id' => $books->id,
            'unit_id' => $unitPiece->id,
            'cost_price' => 15.00,
            'selling_price' => 29.99,
            'min_stock_level' => 5,
            'max_stock_level' => 30,
            'tax_rate' => 0.00,
            'status' => 'active',
        ]);

        Product::create([
            'name' => 'Organic Coffee Beans',
            'description' => 'Premium organic coffee beans',
            'sku' => 'COFFEE-ORG-1KG',
            'barcode' => '1234567890128',
            'category_id' => $food->id,
            'unit_id' => $unitKilogram->id,
            'cost_price' => 12.00,
            'selling_price' => 24.99,
            'min_stock_level' => 20,
            'max_stock_level' => 100,
            'tax_rate' => 8.00,
            'status' => 'active',
        ]);

        $this->command->info('Inventory seeder completed successfully!');
        $this->command->info('Created:');
        $this->command->info('- 9 Units (including base and derived units)');
        $this->command->info('- 9 Categories (with hierarchical structure)');
        $this->command->info('- 3 Suppliers');
        $this->command->info('- 6 Sample Products');
    }
}
