<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    /**
     * Display a listing of customers with filtering and search
     */
    public function index(Request $request): JsonResponse
    {
        $query = Customer::query();

        // Search functionality
        if ($request->has('search') && !empty($request->search)) {
            $query->search($request->search);
        }

        // Filter by type
        if ($request->has('type') && !empty($request->type)) {
            $query->byType($request->type);
        }

        // Filter by status
        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }

        // Filter by recent activity
        if ($request->has('recent_days') && !empty($request->recent_days)) {
            $query->recentCustomers($request->recent_days);
        }

        // Filter inactive customers
        if ($request->has('inactive') && $request->inactive === 'true') {
            $query->inactiveCustomers();
        }

        // Filter VIP customers
        if ($request->has('vip') && $request->vip === 'true') {
            $query->vip();
        }

        // Sort options
        $sortField = $request->get('sort_field', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');

        $validSortFields = ['name', 'customer_code', 'total_spent', 'total_orders', 'last_purchase_date', 'created_at'];
        if (in_array($sortField, $validSortFields)) {
            $query->orderBy($sortField, $sortDirection);
        }

        // Load relationships
        $query->withCount('sales');

        // Pagination
        $perPage = min($request->get('per_page', 15), 100);
        $customers = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $customers
        ]);
    }

    /**
     * Store a newly created customer
     */
    public function store(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:customers,email',
            'phone' => 'nullable|string|max:20',
            'birth_date' => 'nullable|date|before:today',
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'type' => ['required', Rule::in(['regular', 'vip', 'wholesale', 'member'])],
            'credit_limit' => 'nullable|numeric|min:0',
            'tax_number' => 'nullable|string|max:50',
            'company_name' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'preferences' => 'nullable|array',
            'referred_by' => 'nullable|exists:customers,id',
        ]);

        try {
            DB::beginTransaction();

            $customer = Customer::create($validatedData);

            // Update referrer's count if applicable
            if (isset($validatedData['referred_by'])) {
                $referrer = Customer::find($validatedData['referred_by']);
                if ($referrer) {
                    $referrer->increment('referral_count');
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Customer created successfully',
                'data' => $customer->load('referrer')
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create customer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified customer
     */
    public function show(Customer $customer): JsonResponse
    {
        $customer->load([
            'sales' => function ($query) {
                $query->orderBy('sale_date', 'desc')->limit(10);
            },
            'referrer',
            'referrals'
        ]);

        return response()->json([
            'success' => true,
            'data' => $customer
        ]);
    }

    /**
     * Update the specified customer
     */
    public function update(Request $request, Customer $customer): JsonResponse
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'nullable',
                'email',
                Rule::unique('customers', 'email')->ignore($customer->id)
            ],
            'phone' => 'nullable|string|max:20',
            'birth_date' => 'nullable|date|before:today',
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'type' => ['required', Rule::in(['regular', 'vip', 'wholesale', 'member'])],
            'status' => ['required', Rule::in(['active', 'inactive', 'suspended'])],
            'credit_limit' => 'nullable|numeric|min:0',
            'tax_number' => 'nullable|string|max:50',
            'company_name' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'preferences' => 'nullable|array',
        ]);

        try {
            $customer->update($validatedData);

            return response()->json([
                'success' => true,
                'message' => 'Customer updated successfully',
                'data' => $customer->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update customer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified customer
     */
    public function destroy(Customer $customer): JsonResponse
    {
        try {
            // Check if customer has active sales
            if ($customer->sales()->where('status', '!=', 'cancelled')->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete customer with active sales'
                ], 422);
            }

            $customer->delete();

            return response()->json([
                'success' => true,
                'message' => 'Customer deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete customer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get customer analytics and statistics
     */
    public function analytics(Request $request): JsonResponse
    {
        $analytics = [
            'total_customers' => Customer::count(),
            'active_customers' => Customer::active()->count(),
            'vip_customers' => Customer::vip()->count(),
            'wholesale_customers' => Customer::byType('wholesale')->count(),
            'new_customers_this_month' => Customer::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count(),
            'recent_customers' => Customer::recentCustomers(30)->count(),
            'inactive_customers' => Customer::inactiveCustomers(90)->count(),
        ];

        // Customer type distribution
        $typeDistribution = Customer::select('type', DB::raw('count(*) as count'))
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();

        // Top customers by spending
        $topCustomers = Customer::select('name', 'customer_code', 'total_spent', 'total_orders')
            ->orderBy('total_spent', 'desc')
            ->limit(10)
            ->get();

        // Monthly new customers (last 12 months)
        $monthlyNewCustomers = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $count = Customer::whereMonth('created_at', $date->month)
                ->whereYear('created_at', $date->year)
                ->count();
            $monthlyNewCustomers[] = [
                'month' => $date->format('M Y'),
                'count' => $count
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'overview' => $analytics,
                'type_distribution' => $typeDistribution,
                'top_customers' => $topCustomers,
                'monthly_new_customers' => $monthlyNewCustomers
            ]
        ]);
    }

    /**
     * Get customer purchase history
     */
    public function purchaseHistory(Customer $customer, Request $request): JsonResponse
    {
        $query = $customer->sales()->with(['items.product', 'payments.paymentMethod']);

        // Date range filter
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->byDateRange($request->start_date, $request->end_date);
        }

        // Status filter
        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }

        $perPage = min($request->get('per_page', 10), 50);
        $sales = $query->orderBy('sale_date', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $sales
        ]);
    }

    /**
     * Update customer loyalty points
     */
    public function updateLoyaltyPoints(Request $request, Customer $customer): JsonResponse
    {
        $validatedData = $request->validate([
            'points' => 'required|numeric',
            'action' => ['required', Rule::in(['add', 'redeem', 'set'])],
            'reason' => 'nullable|string|max:255'
        ]);

        try {
            DB::beginTransaction();

            switch ($validatedData['action']) {
                case 'add':
                    $customer->addLoyaltyPoints($validatedData['points']);
                    $message = "Added {$validatedData['points']} loyalty points";
                    break;

                case 'redeem':
                    if ($customer->redeemLoyaltyPoints($validatedData['points'])) {
                        $message = "Redeemed {$validatedData['points']} loyalty points";
                    } else {
                        throw new \Exception('Insufficient loyalty points');
                    }
                    break;

                case 'set':
                    $customer->update(['loyalty_points' => $validatedData['points']]);
                    $message = "Set loyalty points to {$validatedData['points']}";
                    break;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'current_points' => $customer->fresh()->loyalty_points
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update loyalty points',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search customers for quick selection (e.g., in POS)
     */
    public function search(Request $request): JsonResponse
    {
        $search = $request->get('q', '');

        if (strlen($search) < 2) {
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }

        $customers = Customer::search($search)
            ->active()
            ->select(['id', 'customer_code', 'name', 'phone', 'email', 'type', 'loyalty_points'])
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $customers
        ]);
    }
}
