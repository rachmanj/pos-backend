<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashSession;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class CashSessionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $query = CashSession::with(['openedBy', 'warehouse']);

        // Filter by user
        if ($request->has('opened_by') && $request->opened_by) {
            $query->where('opened_by', $request->opened_by);
        }

        // Filter by warehouse
        if ($request->has('warehouse_id') && $request->warehouse_id) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        // Filter by status
        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('opened_at', '>=', $request->date_from);
        }
        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('opened_at', '<=', $request->date_to);
        }

        // Sort by opened_at descending by default
        $query->orderBy('opened_at', 'desc');

        $sessions = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $sessions
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'opening_cash' => 'required|numeric|min:0',
            'opening_notes' => 'nullable|string|max:500'
        ]);

        try {
            DB::beginTransaction();

            // Check if user has an active session in any warehouse
            $activeSession = CashSession::where('opened_by', Auth::id())
                ->where('status', 'open')
                ->first();

            if ($activeSession) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'You already have an active cash session. Please close it first.'
                ], 422);
            }

            $session = CashSession::create([
                'warehouse_id' => $validated['warehouse_id'],
                'opened_by' => Auth::id(),
                'opening_cash' => $validated['opening_cash'],
                'opening_notes' => $validated['opening_notes'],
                'opened_at' => now(),
                'status' => 'open'
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $session->load(['openedBy', 'warehouse']),
                'message' => 'Cash session opened successfully'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to open cash session',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(CashSession $cashSession): JsonResponse
    {
        $cashSession->load(['openedBy', 'closedBy', 'warehouse']);

        // Get sales for this session
        $sales = Sale::where('cash_session_id', $cashSession->id)
            ->with(['customer', 'saleItems.product', 'salePayments.paymentMethod'])
            ->orderBy('sale_date', 'desc')
            ->get();

        // Calculate session summary
        $summary = [
            'total_sales_count' => $sales->count(),
            'total_sales_amount' => $sales->sum('total_amount'),
            'total_tax_amount' => $sales->sum('tax_amount'),
            'total_discount_amount' => $sales->sum('discount_amount'),
            'cash_sales' => $sales->sum(function ($sale) {
                return $sale->salePayments->where('payment_method.type', 'cash')->sum('amount');
            }),
            'card_sales' => $sales->sum(function ($sale) {
                return $sale->salePayments->whereIn('payment_method.type', ['credit_card', 'debit_card'])->sum('amount');
            }),
            'digital_sales' => $sales->sum(function ($sale) {
                return $sale->salePayments->where('payment_method.type', 'digital_wallet')->sum('amount');
            })
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'session' => $cashSession,
                'sales' => $sales,
                'summary' => $summary
            ]
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function close(Request $request, CashSession $cashSession): JsonResponse
    {
        $validated = $request->validate([
            'closing_cash' => 'required|numeric|min:0',
            'closing_notes' => 'nullable|string|max:500'
        ]);

        try {
            DB::beginTransaction();

            // Check if session is already closed
            if ($cashSession->status === 'closed') {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Cash session is already closed'
                ], 422);
            }

            // Check if user can close this session (only own sessions for now)
            if ($cashSession->opened_by !== Auth::id()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'You can only close your own cash sessions'
                ], 403);
            }

            // Calculate session totals
            $sales = Sale::where('cash_session_id', $cashSession->id)->get();
            $totalSalesAmount = $sales->sum('total_amount');
            $totalCashSales = 0;

            // Calculate cash sales specifically
            foreach ($sales as $sale) {
                foreach ($sale->salePayments as $payment) {
                    if ($payment->paymentMethod->type === 'cash' && $payment->status === 'completed') {
                        $totalCashSales += $payment->amount;
                    }
                }
            }

            $expectedCash = $cashSession->opening_cash + $totalCashSales;
            $variance = $validated['closing_cash'] - $expectedCash;

            $cashSession->update([
                'closed_by' => Auth::id(),
                'closing_cash' => $validated['closing_cash'],
                'expected_cash' => $expectedCash,
                'variance' => $variance,
                'total_sales' => $totalSalesAmount,
                'total_cash_sales' => $totalCashSales,
                'transaction_count' => $sales->count(),
                'closed_at' => now(),
                'status' => 'closed',
                'closing_notes' => $validated['closing_notes'],
                'is_balanced' => abs($variance) < 0.01 // Allow 1 cent variance
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $cashSession->fresh()->load(['openedBy', 'closedBy', 'warehouse']),
                'message' => 'Cash session closed successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to close cash session',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getActive(): JsonResponse
    {
        $activeSession = CashSession::where('opened_by', Auth::id())
            ->where('status', 'open')
            ->with(['openedBy', 'warehouse'])
            ->first();

        return response()->json([
            'success' => true,
            'data' => $activeSession
        ]);
    }

    public function getSummary(Request $request): JsonResponse
    {
        $query = CashSession::query();

        // Filter by date range
        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('opened_at', '>=', $request->date_from);
        }
        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('opened_at', '<=', $request->date_to);
        }

        $sessions = $query->where('status', 'closed')->get();

        $summary = [
            'total_sessions' => $sessions->count(),
            'total_opening_amount' => $sessions->sum('opening_cash'),
            'total_closing_amount' => $sessions->sum('closing_cash'),
            'total_sales_amount' => $sessions->sum('total_sales'),
            'total_variance' => $sessions->sum('variance'),
            'average_variance' => $sessions->count() > 0 ? $sessions->avg('variance') : 0,
            'sessions_with_variance' => $sessions->where('variance', '!=', 0)->count()
        ];

        return response()->json([
            'success' => true,
            'data' => $summary
        ]);
    }
}
