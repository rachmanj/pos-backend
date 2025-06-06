<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Supplier;
use App\Models\Product;
use App\Models\Unit;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptItem;
use Carbon\Carbon;

class PurchasingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get required data
        $users = User::all();
        $suppliers = Supplier::where('status', 'active')->get();
        $products = Product::with('category', 'unit')->get();
        $units = Unit::all();

        if ($users->isEmpty() || $suppliers->isEmpty() || $products->isEmpty()) {
            $this->command->warn('Skipping PurchasingSeeder - required data (users, suppliers, products) not found.');
            return;
        }

        $manager = $users->whereIn('name', ['Manager', 'Admin'])->first() ?? $users->first();
        $purchasingClerk = $users->where('name', 'like', '%clerk%')->first() ?? $users->skip(1)->first() ?? $users->first();

        // Create Purchase Orders
        $this->createPurchaseOrders($suppliers, $products, $manager, $purchasingClerk);

        $this->command->info('Purchasing system seeded successfully!');
    }

    private function createPurchaseOrders($suppliers, $products, $manager, $purchasingClerk)
    {
        foreach ($suppliers->take(3) as $index => $supplier) {
            // Create a draft purchase order
            $draftPO = PurchaseOrder::create([
                'supplier_id' => $supplier->id,
                'created_by' => $purchasingClerk->id,
                'order_date' => Carbon::now()->subDays(5),
                'expected_delivery_date' => Carbon::now()->addDays(7),
                'status' => 'draft',
                'notes' => "Sample draft purchase order for {$supplier->name}",
                'terms_conditions' => 'Standard payment terms apply. Delivery within 7 days.',
            ]);

            // Add items to draft PO
            $selectedProducts = $products->random(rand(2, 4));
            foreach ($selectedProducts as $product) {
                $quantity = rand(10, 100);
                $unitPrice = rand(10000, 50000); // IDR 10k - 50k

                PurchaseOrderItem::create([
                    'purchase_order_id' => $draftPO->id,
                    'product_id' => $product->id,
                    'unit_id' => $product->unit_id,
                    'quantity_ordered' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $quantity * $unitPrice,
                    'notes' => "Sample item for testing",
                ]);
            }
            $draftPO->calculateTotals();

            // Create an approved purchase order
            $approvedPO = PurchaseOrder::create([
                'supplier_id' => $supplier->id,
                'created_by' => $purchasingClerk->id,
                'approved_by' => $manager->id,
                'order_date' => Carbon::now()->subDays(10),
                'expected_delivery_date' => Carbon::now()->subDays(3),
                'approved_date' => Carbon::now()->subDays(8),
                'status' => 'approved',
                'notes' => "Sample approved purchase order for {$supplier->name}",
                'terms_conditions' => 'Approved for immediate processing. Urgent delivery required.',
            ]);

            // Add items to approved PO
            $selectedProducts = $products->random(rand(3, 5));
            foreach ($selectedProducts as $product) {
                $quantity = rand(20, 150);
                $unitPrice = rand(15000, 75000); // IDR 15k - 75k

                PurchaseOrderItem::create([
                    'purchase_order_id' => $approvedPO->id,
                    'product_id' => $product->id,
                    'unit_id' => $product->unit_id,
                    'quantity_ordered' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => $quantity * $unitPrice,
                    'notes' => "Approved item for immediate delivery",
                ]);
            }
            $approvedPO->calculateTotals();

            // Create a purchase order with partial receipts
            if ($index < 2) {
                $partialPO = PurchaseOrder::create([
                    'supplier_id' => $supplier->id,
                    'created_by' => $purchasingClerk->id,
                    'approved_by' => $manager->id,
                    'order_date' => Carbon::now()->subDays(15),
                    'expected_delivery_date' => Carbon::now()->subDays(8),
                    'approved_date' => Carbon::now()->subDays(13),
                    'status' => 'partially_received',
                    'notes' => "Purchase order with partial delivery for {$supplier->name}",
                    'terms_conditions' => 'Partial deliveries accepted as per agreement.',
                ]);

                // Add items to partial PO
                $poItems = [];
                $selectedProducts = $products->random(3);
                foreach ($selectedProducts as $product) {
                    $quantity = rand(50, 200);
                    $unitPrice = rand(20000, 100000); // IDR 20k - 100k

                    $item = PurchaseOrderItem::create([
                        'purchase_order_id' => $partialPO->id,
                        'product_id' => $product->id,
                        'unit_id' => $product->unit_id,
                        'quantity_ordered' => $quantity,
                        'unit_price' => $unitPrice,
                        'total_price' => $quantity * $unitPrice,
                        'notes' => "Item with partial delivery expected",
                    ]);
                    $poItems[] = $item;
                }
                $partialPO->calculateTotals();

                // Create partial receipt
                $receipt = PurchaseReceipt::create([
                    'purchase_order_id' => $partialPO->id,
                    'received_by' => $manager->id,
                    'receipt_date' => Carbon::now()->subDays(5),
                    'status' => 'approved',
                    'notes' => 'Partial delivery received and approved',
                    'quality_check_notes' => 'Quality check passed for all received items',
                    'stock_updated' => true,
                ]);

                // Add receipt items (partial quantities)
                foreach ($poItems as $poItem) {
                    $receivedQty = rand(20, (int)($poItem->quantity_ordered * 0.7)); // 70% max
                    $acceptedQty = rand((int)($receivedQty * 0.9), $receivedQty); // 90-100% accepted
                    $rejectedQty = $receivedQty - $acceptedQty;

                    PurchaseReceiptItem::create([
                        'purchase_receipt_id' => $receipt->id,
                        'purchase_order_item_id' => $poItem->id,
                        'product_id' => $poItem->product_id,
                        'unit_id' => $poItem->unit_id,
                        'quantity_received' => $receivedQty,
                        'quantity_accepted' => $acceptedQty,
                        'quantity_rejected' => $rejectedQty,
                        'quality_status' => $rejectedQty > 0 ? 'partial' : 'passed',
                        'quality_notes' => $rejectedQty > 0 ? 'Some items damaged during shipping' : 'All items in good condition',
                        'rejection_reason' => $rejectedQty > 0 ? 'Damaged packaging' : null,
                    ]);

                    // Update PO item received quantity
                    $poItem->update(['quantity_received' => $receivedQty]);
                }
            }

            // Create a fully received purchase order
            if ($index < 1) {
                $completePO = PurchaseOrder::create([
                    'supplier_id' => $supplier->id,
                    'created_by' => $purchasingClerk->id,
                    'approved_by' => $manager->id,
                    'order_date' => Carbon::now()->subDays(20),
                    'expected_delivery_date' => Carbon::now()->subDays(13),
                    'approved_date' => Carbon::now()->subDays(18),
                    'status' => 'fully_received',
                    'notes' => "Completed purchase order for {$supplier->name}",
                    'terms_conditions' => 'All items delivered as scheduled.',
                ]);

                // Add items to complete PO
                $poItems = [];
                $selectedProducts = $products->random(4);
                foreach ($selectedProducts as $product) {
                    $quantity = rand(30, 80);
                    $unitPrice = rand(25000, 80000); // IDR 25k - 80k

                    $item = PurchaseOrderItem::create([
                        'purchase_order_id' => $completePO->id,
                        'product_id' => $product->id,
                        'unit_id' => $product->unit_id,
                        'quantity_ordered' => $quantity,
                        'unit_price' => $unitPrice,
                        'total_price' => $quantity * $unitPrice,
                        'notes' => "Fully delivered item",
                    ]);
                    $poItems[] = $item;
                }
                $completePO->calculateTotals();

                // Create complete receipt
                $receipt = PurchaseReceipt::create([
                    'purchase_order_id' => $completePO->id,
                    'received_by' => $manager->id,
                    'receipt_date' => Carbon::now()->subDays(10),
                    'status' => 'approved',
                    'notes' => 'Full delivery received and approved',
                    'quality_check_notes' => 'All items passed quality check',
                    'stock_updated' => true,
                ]);

                // Add receipt items (full quantities)
                foreach ($poItems as $poItem) {
                    $receivedQty = $poItem->quantity_ordered;
                    $acceptedQty = rand((int)($receivedQty * 0.95), $receivedQty); // 95-100% accepted
                    $rejectedQty = $receivedQty - $acceptedQty;

                    PurchaseReceiptItem::create([
                        'purchase_receipt_id' => $receipt->id,
                        'purchase_order_item_id' => $poItem->id,
                        'product_id' => $poItem->product_id,
                        'unit_id' => $poItem->unit_id,
                        'quantity_received' => $receivedQty,
                        'quantity_accepted' => $acceptedQty,
                        'quantity_rejected' => $rejectedQty,
                        'quality_status' => $rejectedQty > 0 ? 'partial' : 'passed',
                        'quality_notes' => 'Excellent quality',
                        'rejection_reason' => $rejectedQty > 0 ? 'Minor defects' : null,
                    ]);

                    // Update PO item received quantity
                    $poItem->update(['quantity_received' => $receivedQty]);
                }
            }
        }
    }
}
