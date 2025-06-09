<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceItem;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\User;
use Carbon\Carbon;

class SalesOrderTestDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creating Sales Order test data...');

        // Get sample data
        $customers = Customer::take(5)->get();
        $products = Product::take(10)->get();
        $warehouses = Warehouse::take(2)->get();
        $salesReps = User::whereHas('roles', function ($query) {
            $query->whereIn('name', ['sales-manager', 'sales-rep', 'manager']);
        })->take(3)->get();

        if ($customers->isEmpty() || $products->isEmpty() || $warehouses->isEmpty()) {
            $this->command->warn('Insufficient test data. Please run CustomerSeeder, ProductSeeder, and WarehouseSeeder first.');
            return;
        }

        // Create 5 sample sales orders
        for ($i = 1; $i <= 5; $i++) {
            $customer = $customers->random();
            $warehouse = $warehouses->random();
            $salesRep = $salesReps->isNotEmpty() ? $salesReps->random() : null;

            $salesOrder = SalesOrder::create([
                'customer_id' => $customer->id,
                'warehouse_id' => $warehouse->id,
                'order_date' => Carbon::now()->subDays(rand(1, 30)),
                'requested_delivery_date' => Carbon::now()->addDays(rand(1, 14)),
                'confirmed_delivery_date' => Carbon::now()->addDays(rand(1, 14)),
                'payment_terms_days' => collect([15, 30, 60])->random(),
                'sales_rep_id' => $salesRep?->id,
                'notes' => "Sample sales order #{$i} for testing",
                'special_instructions' => $i % 2 == 0 ? 'Handle with care' : null,
                'order_status' => collect(['draft', 'confirmed', 'approved', 'in_progress', 'completed'])->random(),
                'created_by' => $salesRep?->id ?? 1,
                'approved_by' => $i % 3 == 0 ? ($salesRep?->id ?? 1) : null,
                'subtotal_amount' => 0,
                'tax_amount' => 0,
                'discount_amount' => 0,
                'total_amount' => 0
            ]);

            // Create 2-4 order items
            $itemCount = rand(2, 4);
            $subtotal = 0;
            $totalTax = 0;
            $totalDiscount = 0;

            for ($j = 1; $j <= $itemCount; $j++) {
                $product = $products->random();
                $quantity = rand(1, 10);
                $unitPrice = $product->selling_price ?? rand(10000, 100000);
                $discountAmount = $unitPrice * $quantity * (rand(0, 15) / 100); // 0-15% discount
                $taxRate = 11; // PPN 11%

                $lineTotal = $quantity * $unitPrice;
                $lineSubtotal = $lineTotal - $discountAmount;
                $lineTax = ($lineSubtotal * $taxRate) / 100;
                $lineTotalWithTax = $lineSubtotal + $lineTax;

                SalesOrderItem::create([
                    'sales_order_id' => $salesOrder->id,
                    'product_id' => $product->id,
                    'quantity_ordered' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount_amount' => $discountAmount,
                    'tax_rate' => $taxRate,
                    'line_total' => $lineTotalWithTax,
                    'quantity_delivered' => in_array($salesOrder->order_status, ['completed']) ? $quantity : rand(0, $quantity),
                    'quantity_remaining' => in_array($salesOrder->order_status, ['completed']) ? 0 : rand(0, $quantity),
                    'delivery_status' => $salesOrder->order_status === 'completed' ? 'completed' : ($salesOrder->order_status === 'in_progress' ? 'partial' : 'pending'),
                    'notes' => $j == 1 ? 'Priority item' : null
                ]);

                $subtotal += $lineTotal;
                $totalDiscount += $discountAmount;
                $totalTax += $lineTax;
            }

            // Update sales order totals
            $salesOrder->update([
                'subtotal_amount' => $subtotal,
                'discount_amount' => $totalDiscount,
                'tax_amount' => $totalTax,
                'total_amount' => $subtotal - $totalDiscount + $totalTax
            ]);

            // Create delivery order for approved/completed orders
            if (in_array($salesOrder->order_status, ['approved', 'in_progress', 'completed'])) {
                $deliveryOrder = DeliveryOrder::create([
                    'sales_order_id' => $salesOrder->id,
                    'warehouse_id' => $salesOrder->warehouse_id,
                    'delivery_date' => $salesOrder->confirmed_delivery_date,
                    'delivery_address' => $customer->address ?? 'Sample delivery address',
                    'delivery_contact' => $customer->phone ?? '08123456789',
                    'driver_id' => $salesReps->isNotEmpty() ? $salesReps->random()->id : null,
                    'vehicle_id' => 'V-' . str_pad($i, 3, '0', STR_PAD_LEFT),
                    'delivery_notes' => "Delivery for sales order #{$salesOrder->sales_order_number}",
                    'delivery_status' => $salesOrder->order_status === 'completed' ? 'delivered' : ($salesOrder->order_status === 'in_progress' ? 'in_transit' : 'pending'),
                    'shipped_at' => in_array($salesOrder->order_status, ['in_progress', 'completed']) ?
                        Carbon::now()->subHours(rand(1, 24)) : null,
                    'delivered_at' => $salesOrder->order_status === 'completed' ?
                        Carbon::now()->subHours(rand(1, 12)) : null,
                    'delivery_confirmed_by' => $salesOrder->order_status === 'completed' ?
                        $customer->name : null
                ]);

                // Create delivery order items
                foreach ($salesOrder->salesOrderItems as $orderItem) {
                    $quantityToDeliver = $orderItem->quantity_delivered > 0 ?
                        $orderItem->quantity_delivered :
                        $orderItem->quantity_ordered;

                    DeliveryOrderItem::create([
                        'delivery_order_id' => $deliveryOrder->id,
                        'sales_order_item_id' => $orderItem->id,
                        'product_id' => $orderItem->product_id,
                        'quantity_to_deliver' => $quantityToDeliver,
                        'quantity_delivered' => $deliveryOrder->delivery_status === 'delivered' ?
                            $quantityToDeliver : 0,
                        'unit_price' => $orderItem->unit_price,
                        'line_total' => $quantityToDeliver * $orderItem->unit_price,
                        'delivery_condition' => $deliveryOrder->delivery_status === 'delivered' ? 'good' : null,
                        'delivery_notes' => null
                    ]);
                }

                // Create sales invoice for delivered orders
                if ($deliveryOrder->delivery_status === 'delivered') {
                    $salesInvoice = SalesInvoice::create([
                        'customer_id' => $customer->id,
                        'sales_order_id' => $salesOrder->id,
                        'delivery_order_id' => $deliveryOrder->id,
                        'invoice_date' => $deliveryOrder->delivered_at,
                        'due_date' => Carbon::parse($deliveryOrder->delivered_at)->addDays($salesOrder->payment_terms_days),
                        'payment_terms_days' => $salesOrder->payment_terms_days,
                        'invoice_status' => collect(['draft', 'sent'])->random(),
                        'payment_status' => collect(['unpaid', 'partial', 'paid'])->random(),
                        'subtotal_amount' => $salesOrder->subtotal_amount,
                        'tax_amount' => $salesOrder->tax_amount,
                        'discount_amount' => $salesOrder->discount_amount,
                        'total_amount' => $salesOrder->total_amount,
                        'sent_at' => rand(0, 1) ? Carbon::now()->subDays(rand(1, 5)) : null,
                        'sent_method' => rand(0, 1) ? 'email' : null,
                        'sent_to' => $customer->email
                    ]);

                    // Create sales invoice items
                    foreach ($deliveryOrder->deliveryOrderItems as $deliveryItem) {
                        if ($deliveryItem->quantity_delivered > 0) {
                            SalesInvoiceItem::create([
                                'sales_invoice_id' => $salesInvoice->id,
                                'product_id' => $deliveryItem->product_id,
                                'quantity' => $deliveryItem->quantity_delivered,
                                'unit_price' => $deliveryItem->unit_price,
                                'discount_amount' => 0,
                                'tax_rate' => 11,
                                'line_total' => $deliveryItem->quantity_delivered * $deliveryItem->unit_price * 1.11,
                                'description' => $deliveryItem->product->name ?? 'Product'
                            ]);
                        }
                    }
                }
            }

            $this->command->info("Created sales order #{$i} with related records");
        }

        $this->command->info('Sales Order test data created successfully!');
        $this->command->line('Created:');
        $this->command->line('- 5 Sales Orders');
        $this->command->line('- ' . SalesOrderItem::count() . ' Sales Order Items');
        $this->command->line('- ' . DeliveryOrder::count() . ' Delivery Orders');
        $this->command->line('- ' . DeliveryOrderItem::count() . ' Delivery Order Items');
        $this->command->line('- ' . SalesInvoice::count() . ' Sales Invoices');
        $this->command->line('- ' . SalesInvoiceItem::count() . ' Sales Invoice Items');
    }
}
