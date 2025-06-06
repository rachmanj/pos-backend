<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class PaymentMethodController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PaymentMethod::query();

        // Filter by status
        if ($request->has('status') && $request->status !== '') {
            $query->where('is_active', $request->status === 'active');
        }

        // Search by name
        if ($request->has('search') && $request->search) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Sort by specified field
        $sortBy = $request->get('sort_by', 'name');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $paymentMethods = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $paymentMethods
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:payment_methods',
            'type' => ['required', Rule::in(['cash', 'credit_card', 'debit_card', 'digital_wallet', 'bank_transfer', 'other'])],
            'description' => 'nullable|string|max:500',
            'requires_reference' => 'boolean',
            'is_active' => 'boolean'
        ]);

        $paymentMethod = PaymentMethod::create($validated);

        return response()->json([
            'success' => true,
            'data' => $paymentMethod,
            'message' => 'Payment method created successfully'
        ], 201);
    }

    public function show(PaymentMethod $paymentMethod): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $paymentMethod
        ]);
    }

    public function update(Request $request, PaymentMethod $paymentMethod): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:payment_methods,name,' . $paymentMethod->id,
            'type' => ['required', Rule::in(['cash', 'credit_card', 'debit_card', 'digital_wallet', 'bank_transfer', 'other'])],
            'description' => 'nullable|string|max:500',
            'requires_reference' => 'boolean',
            'is_active' => 'boolean'
        ]);

        $paymentMethod->update($validated);

        return response()->json([
            'success' => true,
            'data' => $paymentMethod,
            'message' => 'Payment method updated successfully'
        ]);
    }

    public function destroy(PaymentMethod $paymentMethod): JsonResponse
    {
        // Check if payment method is used in any sales
        if ($paymentMethod->salePayments()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete payment method that has been used in transactions'
            ], 422);
        }

        $paymentMethod->delete();

        return response()->json([
            'success' => true,
            'message' => 'Payment method deleted successfully'
        ]);
    }

    public function toggleStatus(PaymentMethod $paymentMethod): JsonResponse
    {
        $paymentMethod->update([
            'is_active' => !$paymentMethod->is_active
        ]);

        $status = $paymentMethod->is_active ? 'activated' : 'deactivated';

        return response()->json([
            'success' => true,
            'data' => $paymentMethod,
            'message' => "Payment method {$status} successfully"
        ]);
    }

    public function getActive(): JsonResponse
    {
        $paymentMethods = PaymentMethod::where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $paymentMethods
        ]);
    }
}
