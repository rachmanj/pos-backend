<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\CustomerAddress;
use App\Models\CustomerNote;
use App\Models\CustomerLoyaltyPoint;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CustomerCrmController extends Controller
{
    // ==================== CUSTOMER MANAGEMENT ====================

    public function index(Request $request): JsonResponse
    {
        $query = Customer::with([
            'assignedSalesRep:id,name',
            'accountManager:id,name',
            'primaryContact',
            'activeAddresses'
        ]);

        // Advanced filtering
        if ($request->filled('stage')) {
            $query->byStage($request->stage);
        }

        if ($request->filled('priority')) {
            $query->byPriority($request->priority);
        }

        if ($request->filled('loyalty_tier')) {
            $query->byLoyaltyTier($request->loyalty_tier);
        }

        if ($request->filled('assigned_to')) {
            $query->assignedTo($request->assigned_to);
        }

        if ($request->filled('lead_source')) {
            $query->byLeadSource($request->lead_source);
        }

        if ($request->filled('industry')) {
            $query->byIndustry($request->industry);
        }

        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->active();
            } elseif ($request->status === 'blacklisted') {
                $query->blacklisted();
            } else {
                $query->where('status', $request->status);
            }
        }

        if ($request->filled('follow_up')) {
            if ($request->follow_up === 'overdue') {
                $query->requireingFollowUp();
            }
        }

        if ($request->filled('search')) {
            $query->search($request->search);
        }

        // Sorting
        $sortField = $request->get('sort_by', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');

        $allowedSorts = [
            'name',
            'customer_code',
            'customer_stage',
            'priority',
            'loyalty_tier',
            'total_spent',
            'total_orders',
            'last_purchase_date',
            'next_follow_up_date',
            'created_at',
            'updated_at'
        ];

        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDirection);
        }

        $customers = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $customers
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:customers,email',
            'phone' => 'nullable|string|max:20',
            'company_name' => 'nullable|string|max:255',
            'business_type' => 'required|in:individual,company,government,ngo,other',
            'industry' => 'nullable|string|max:255',
            'employee_count' => 'nullable|integer|min:0',
            'annual_revenue' => 'nullable|numeric|min:0',
            'website' => 'nullable|url',
            'lead_source' => 'nullable|in:website,referral,advertisement,cold_call,trade_show,social_media,other',
            'customer_stage' => 'required|in:lead,prospect,customer,vip,inactive',
            'priority' => 'required|in:low,normal,high,vip',
            'assigned_sales_rep' => 'nullable|exists:users,id',
            'account_manager' => 'nullable|exists:users,id',
            'credit_limit' => 'nullable|numeric|min:0',
            'payment_terms_days' => 'nullable|integer|min:0|max:365',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'internal_notes' => 'nullable|string',

            // Contact information
            'contact.name' => 'nullable|string|max:255',
            'contact.position' => 'nullable|string|max:255',
            'contact.phone' => 'nullable|string|max:20',
            'contact.email' => 'nullable|email',

            // Address information
            'address.type' => 'nullable|in:billing,shipping,office,warehouse,other',
            'address.address_line_1' => 'nullable|string',
            'address.city' => 'nullable|string|max:255',
            'address.state_province' => 'nullable|string|max:255',
            'address.postal_code' => 'nullable|string|max:20',
            'address.country' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $customer = Customer::create($request->only([
                'name',
                'email',
                'phone',
                'company_name',
                'business_type',
                'industry',
                'employee_count',
                'annual_revenue',
                'website',
                'lead_source',
                'customer_stage',
                'priority',
                'assigned_sales_rep',
                'account_manager',
                'credit_limit',
                'payment_terms_days',
                'discount_percentage',
                'internal_notes'
            ]));

            // Create primary contact if provided
            if ($request->filled('contact.name')) {
                $customer->contacts()->create([
                    'name' => $request->input('contact.name'),
                    'position' => $request->input('contact.position'),
                    'phone' => $request->input('contact.phone'),
                    'email' => $request->input('contact.email'),
                    'is_primary' => true,
                    'is_decision_maker' => true,
                    'receives_invoices' => true,
                ]);
            }

            // Create primary address if provided
            if ($request->filled('address.address_line_1')) {
                $customer->addresses()->create([
                    'type' => $request->input('address.type', 'billing'),
                    'address_line_1' => $request->input('address.address_line_1'),
                    'city' => $request->input('address.city'),
                    'state_province' => $request->input('address.state_province'),
                    'postal_code' => $request->input('address.postal_code'),
                    'country' => $request->input('address.country', 'Indonesia'),
                    'is_primary' => true,
                ]);
            }

            // Create initial note
            $customer->notes()->create([
                'user_id' => Auth::user()->id,
                'type' => 'general',
                'subject' => 'Customer Created',
                'content' => 'Customer profile created in CRM system.',
                'priority' => 'normal',
                'status' => 'completed'
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Customer created successfully',
                'data' => $customer->load(['assignedSalesRep', 'accountManager', 'contacts', 'addresses'])
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create customer: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(Customer $customer): JsonResponse
    {
        $customer->load([
            'assignedSalesRep:id,name,email',
            'accountManager:id,name,email',
            'contacts' => function ($query) {
                $query->where('is_active', true)->orderBy('is_primary', 'desc');
            },
            'addresses' => function ($query) {
                $query->where('is_active', true)->orderBy('is_primary', 'desc');
            },
            'notes' => function ($query) {
                $query->where('is_private', false)->latest()->limit(10);
            },
            'loyaltyPointTransactions' => function ($query) {
                $query->latest()->limit(10);
            },
            'sales' => function ($query) {
                $query->latest()->limit(5);
            }
        ]);

        // Add computed attributes
        $customer->loyalty_points_balance = $customer->getLoyaltyPointsBalance();
        $customer->expiring_points = $customer->getExpiringPoints();
        $customer->primary_contact_info = $customer->getPrimaryContactInfo();

        return response()->json([
            'success' => true,
            'data' => $customer
        ]);
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => [
                'sometimes',
                'email',
                Rule::unique('customers')->ignore($customer->id)
            ],
            'phone' => 'sometimes|nullable|string|max:20',
            'company_name' => 'sometimes|nullable|string|max:255',
            'business_type' => 'sometimes|required|in:individual,company,government,ngo,other',
            'industry' => 'sometimes|nullable|string|max:255',
            'employee_count' => 'sometimes|nullable|integer|min:0',
            'annual_revenue' => 'sometimes|nullable|numeric|min:0',
            'website' => 'sometimes|nullable|url',
            'lead_source' => 'sometimes|nullable|in:website,referral,advertisement,cold_call,trade_show,social_media,other',
            'customer_stage' => 'sometimes|required|in:lead,prospect,customer,vip,inactive',
            'priority' => 'sometimes|required|in:low,normal,high,vip',
            'assigned_sales_rep' => 'sometimes|nullable|exists:users,id',
            'account_manager' => 'sometimes|nullable|exists:users,id',
            'credit_limit' => 'sometimes|nullable|numeric|min:0',
            'payment_terms_days' => 'sometimes|nullable|integer|min:0|max:365',
            'discount_percentage' => 'sometimes|nullable|numeric|min:0|max:100',
            'internal_notes' => 'sometimes|nullable|string',
            'next_follow_up_date' => 'sometimes|nullable|date|after:today',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $customer->update($request->only([
                'name',
                'email',
                'phone',
                'company_name',
                'business_type',
                'industry',
                'employee_count',
                'annual_revenue',
                'website',
                'lead_source',
                'customer_stage',
                'priority',
                'assigned_sales_rep',
                'account_manager',
                'credit_limit',
                'payment_terms_days',
                'discount_percentage',
                'internal_notes',
                'next_follow_up_date'
            ]));

            // Log the update
            $customer->notes()->create([
                'user_id' => Auth::user()->id,
                'type' => 'general',
                'subject' => 'Customer Updated',
                'content' => 'Customer profile updated by ' . Auth::user()->name,
                'priority' => 'normal',
                'status' => 'completed'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Customer updated successfully',
                'data' => $customer->fresh(['assignedSalesRep', 'accountManager'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update customer: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy(Customer $customer): JsonResponse
    {
        try {
            // Check if customer has any sales
            if ($customer->sales()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete customer with existing sales records'
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
                'message' => 'Failed to delete customer: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==================== CONTACT MANAGEMENT ====================

    public function getContacts(Customer $customer): JsonResponse
    {
        $contacts = $customer->contacts()
            ->orderBy('is_primary', 'desc')
            ->orderBy('is_active', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $contacts
        ]);
    }

    public function storeContact(Request $request, Customer $customer): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'position' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'mobile' => 'nullable|string|max:20',
            'email' => 'nullable|email',
            'whatsapp' => 'nullable|string|max:20',
            'is_primary' => 'boolean',
            'is_decision_maker' => 'boolean',
            'receives_invoices' => 'boolean',
            'receives_marketing' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $contact = $customer->contacts()->create($request->all());

            if ($request->get('is_primary', false)) {
                $contact->makePrimary();
            }

            return response()->json([
                'success' => true,
                'message' => 'Contact created successfully',
                'data' => $contact
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create contact: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateContact(Request $request, Customer $customer, CustomerContact $contact): JsonResponse
    {
        if ($contact->customer_id !== $customer->id) {
            return response()->json([
                'success' => false,
                'message' => 'Contact does not belong to this customer'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'position' => 'sometimes|nullable|string|max:255',
            'department' => 'sometimes|nullable|string|max:255',
            'phone' => 'sometimes|nullable|string|max:20',
            'mobile' => 'sometimes|nullable|string|max:20',
            'email' => 'sometimes|nullable|email',
            'whatsapp' => 'sometimes|nullable|string|max:20',
            'is_primary' => 'sometimes|boolean',
            'is_decision_maker' => 'sometimes|boolean',
            'receives_invoices' => 'sometimes|boolean',
            'receives_marketing' => 'sometimes|boolean',
            'notes' => 'sometimes|nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $contact->update($request->all());

            if ($request->get('is_primary', false)) {
                $contact->makePrimary();
            }

            return response()->json([
                'success' => true,
                'message' => 'Contact updated successfully',
                'data' => $contact->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update contact: ' . $e->getMessage()
            ], 500);
        }
    }

    public function deleteContact(Customer $customer, CustomerContact $contact): JsonResponse
    {
        if ($contact->customer_id !== $customer->id) {
            return response()->json([
                'success' => false,
                'message' => 'Contact does not belong to this customer'
            ], 422);
        }

        try {
            $contact->delete();

            return response()->json([
                'success' => true,
                'message' => 'Contact deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete contact: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==================== ADDRESS MANAGEMENT ====================

    public function getAddresses(Customer $customer): JsonResponse
    {
        $addresses = $customer->addresses()
            ->orderBy('is_primary', 'desc')
            ->orderBy('is_active', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $addresses
        ]);
    }

    public function storeAddress(Request $request, Customer $customer): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:billing,shipping,office,warehouse,other',
            'label' => 'nullable|string|max:255',
            'address_line_1' => 'required|string',
            'address_line_2' => 'nullable|string',
            'city' => 'required|string|max:255',
            'state_province' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'country' => 'required|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'is_primary' => 'boolean',
            'delivery_notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $address = $customer->addresses()->create($request->all());

            if ($request->get('is_primary', false)) {
                $address->makePrimary();
            }

            return response()->json([
                'success' => true,
                'message' => 'Address created successfully',
                'data' => $address
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create address: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateAddress(Request $request, Customer $customer, CustomerAddress $address): JsonResponse
    {
        if ($address->customer_id !== $customer->id) {
            return response()->json([
                'success' => false,
                'message' => 'Address does not belong to this customer'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'type' => 'sometimes|required|in:billing,shipping,office,warehouse,other',
            'label' => 'sometimes|nullable|string|max:255',
            'address_line_1' => 'sometimes|required|string',
            'address_line_2' => 'sometimes|nullable|string',
            'city' => 'sometimes|required|string|max:255',
            'state_province' => 'sometimes|nullable|string|max:255',
            'postal_code' => 'sometimes|nullable|string|max:20',
            'country' => 'sometimes|required|string|max:255',
            'latitude' => 'sometimes|nullable|numeric|between:-90,90',
            'longitude' => 'sometimes|nullable|numeric|between:-180,180',
            'is_primary' => 'sometimes|boolean',
            'is_active' => 'sometimes|boolean',
            'delivery_notes' => 'sometimes|nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $address->update($request->all());

            if ($request->get('is_primary', false)) {
                $address->makePrimary();
            }

            return response()->json([
                'success' => true,
                'message' => 'Address updated successfully',
                'data' => $address->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update address: ' . $e->getMessage()
            ], 500);
        }
    }

    public function deleteAddress(Customer $customer, CustomerAddress $address): JsonResponse
    {
        if ($address->customer_id !== $customer->id) {
            return response()->json([
                'success' => false,
                'message' => 'Address does not belong to this customer'
            ], 422);
        }

        try {
            $address->delete();

            return response()->json([
                'success' => true,
                'message' => 'Address deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete address: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==================== NOTES MANAGEMENT ====================

    public function getNotes(Request $request, Customer $customer): JsonResponse
    {
        $query = $customer->notes()->with(['user:id,name', 'followUpAssignedTo:id,name']);

        // Filter by type
        if ($request->filled('type')) {
            $query->byType($request->type);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->byStatus($request->status);
        }

        // Filter by priority
        if ($request->filled('priority')) {
            $query->byPriority($request->priority);
        }

        // Only show public notes unless user has permission to see private notes
        if (!auth()->user()->can('view private customer notes')) {
            $query->public();
        }

        $notes = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 10));

        return response()->json([
            'success' => true,
            'data' => $notes
        ]);
    }

    public function storeNote(Request $request, Customer $customer): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'type' => 'required|in:general,call,meeting,email,complaint,follow_up,payment,delivery,other',
            'subject' => 'nullable|string|max:255',
            'content' => 'required|string',
            'priority' => 'required|in:low,normal,high,urgent',
            'is_private' => 'boolean',
            'requires_follow_up' => 'boolean',
            'follow_up_date' => 'nullable|required_if:requires_follow_up,true|date|after:today',
            'follow_up_assigned_to' => 'nullable|exists:users,id',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $note = $customer->notes()->create([
                'user_id' => auth()->id(),
                'type' => $request->type,
                'subject' => $request->subject,
                'content' => $request->content,
                'priority' => $request->priority,
                'is_private' => $request->get('is_private', false),
                'requires_follow_up' => $request->get('requires_follow_up', false),
                'follow_up_date' => $request->follow_up_date,
                'follow_up_assigned_to' => $request->follow_up_assigned_to,
                'tags' => $request->tags,
                'status' => 'open'
            ]);

            // Update customer's last contact date
            $customer->update(['last_contact_date' => now()]);

            return response()->json([
                'success' => true,
                'message' => 'Note created successfully',
                'data' => $note->load(['user:id,name', 'followUpAssignedTo:id,name'])
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create note: ' . $e->getMessage()
            ], 500);
        }
    }

    public function updateNote(Request $request, Customer $customer, CustomerNote $note): JsonResponse
    {
        if ($note->customer_id !== $customer->id) {
            return response()->json([
                'success' => false,
                'message' => 'Note does not belong to this customer'
            ], 422);
        }

        // Check if user can edit this note
        if (!$note->canBeEditedBy(auth()->user())) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to edit this note'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'type' => 'sometimes|required|in:general,call,meeting,email,complaint,follow_up,payment,delivery,other',
            'subject' => 'sometimes|nullable|string|max:255',
            'content' => 'sometimes|required|string',
            'priority' => 'sometimes|required|in:low,normal,high,urgent',
            'status' => 'sometimes|required|in:open,in_progress,completed,cancelled',
            'requires_follow_up' => 'sometimes|boolean',
            'follow_up_date' => 'sometimes|nullable|date|after:today',
            'follow_up_assigned_to' => 'sometimes|nullable|exists:users,id',
            'tags' => 'sometimes|nullable|array',
            'tags.*' => 'string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $note->update($request->only([
                'type',
                'subject',
                'content',
                'priority',
                'status',
                'requires_follow_up',
                'follow_up_date',
                'follow_up_assigned_to',
                'tags'
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Note updated successfully',
                'data' => $note->fresh(['user:id,name', 'followUpAssignedTo:id,name'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update note: ' . $e->getMessage()
            ], 500);
        }
    }

    public function deleteNote(Customer $customer, CustomerNote $note): JsonResponse
    {
        if ($note->customer_id !== $customer->id) {
            return response()->json([
                'success' => false,
                'message' => 'Note does not belong to this customer'
            ], 422);
        }

        // Check if user can edit this note
        if (!$note->canBeEditedBy(auth()->user())) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to delete this note'
            ], 403);
        }

        try {
            $note->delete();

            return response()->json([
                'success' => true,
                'message' => 'Note deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete note: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==================== LOYALTY POINTS MANAGEMENT ====================

    public function getLoyaltyPoints(Request $request, Customer $customer): JsonResponse
    {
        $query = $customer->loyaltyPointTransactions()->with(['user:id,name', 'sale:id,sale_number']);

        if ($request->filled('type')) {
            $query->byType($request->type);
        }

        $transactions = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        $balance = CustomerLoyaltyPoint::getCustomerBalance($customer->id);
        $expiringPoints = CustomerLoyaltyPoint::getCustomerExpiringPoints($customer->id);

        return response()->json([
            'success' => true,
            'data' => [
                'transactions' => $transactions,
                'balance' => $balance,
                'expiring_points' => $expiringPoints,
                'total_earned' => CustomerLoyaltyPoint::getCustomerEarnedTotal($customer->id),
                'total_redeemed' => CustomerLoyaltyPoint::getCustomerRedeemedTotal($customer->id),
            ]
        ]);
    }

    public function adjustLoyaltyPoints(Request $request, Customer $customer): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'points' => 'required|integer',
            'type' => 'required|in:adjusted,bonus,penalty',
            'description' => 'required|string|max:255',
            'reason' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $transaction = CustomerLoyaltyPoint::createAdjustment(
                $customer->id,
                auth()->id(),
                $request->points,
                $request->type,
                $request->description,
                ['reason' => $request->reason, 'adjusted_by' => auth()->user()->name]
            );

            // Update customer's loyalty points balance
            $customer->update([
                'loyalty_points_balance' => CustomerLoyaltyPoint::getCustomerBalance($customer->id)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Loyalty points adjusted successfully',
                'data' => $transaction->load(['user:id,name'])
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to adjust loyalty points: ' . $e->getMessage()
            ], 500);
        }
    }

    // ==================== UTILITY METHODS ====================

    public function getDropdownData(): JsonResponse
    {
        $salesReps = User::whereHas('roles', function ($query) {
            $query->whereIn('name', ['sales-manager', 'manager', 'super-admin']);
        })->select('id', 'name')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'business_types' => [
                    ['value' => 'individual', 'label' => 'Individual'],
                    ['value' => 'company', 'label' => 'Company'],
                    ['value' => 'government', 'label' => 'Government'],
                    ['value' => 'ngo', 'label' => 'NGO'],
                    ['value' => 'other', 'label' => 'Other'],
                ],
                'customer_stages' => [
                    ['value' => 'lead', 'label' => 'Lead'],
                    ['value' => 'prospect', 'label' => 'Prospect'],
                    ['value' => 'customer', 'label' => 'Customer'],
                    ['value' => 'vip', 'label' => 'VIP'],
                    ['value' => 'inactive', 'label' => 'Inactive'],
                ],
                'priorities' => [
                    ['value' => 'low', 'label' => 'Low'],
                    ['value' => 'normal', 'label' => 'Normal'],
                    ['value' => 'high', 'label' => 'High'],
                    ['value' => 'vip', 'label' => 'VIP'],
                ],
                'loyalty_tiers' => [
                    ['value' => 'bronze', 'label' => 'Bronze'],
                    ['value' => 'silver', 'label' => 'Silver'],
                    ['value' => 'gold', 'label' => 'Gold'],
                    ['value' => 'platinum', 'label' => 'Platinum'],
                    ['value' => 'diamond', 'label' => 'Diamond'],
                ],
                'lead_sources' => [
                    ['value' => 'website', 'label' => 'Website'],
                    ['value' => 'referral', 'label' => 'Referral'],
                    ['value' => 'advertisement', 'label' => 'Advertisement'],
                    ['value' => 'cold_call', 'label' => 'Cold Call'],
                    ['value' => 'trade_show', 'label' => 'Trade Show'],
                    ['value' => 'social_media', 'label' => 'Social Media'],
                    ['value' => 'other', 'label' => 'Other'],
                ],
                'sales_reps' => $salesReps,
                'note_types' => [
                    ['value' => 'general', 'label' => 'General'],
                    ['value' => 'call', 'label' => 'Phone Call'],
                    ['value' => 'meeting', 'label' => 'Meeting'],
                    ['value' => 'email', 'label' => 'Email'],
                    ['value' => 'complaint', 'label' => 'Complaint'],
                    ['value' => 'follow_up', 'label' => 'Follow Up'],
                    ['value' => 'payment', 'label' => 'Payment'],
                    ['value' => 'delivery', 'label' => 'Delivery'],
                    ['value' => 'other', 'label' => 'Other'],
                ],
                'address_types' => [
                    ['value' => 'billing', 'label' => 'Billing'],
                    ['value' => 'shipping', 'label' => 'Shipping'],
                    ['value' => 'office', 'label' => 'Office'],
                    ['value' => 'warehouse', 'label' => 'Warehouse'],
                    ['value' => 'other', 'label' => 'Other'],
                ],
            ]
        ]);
    }

    public function blacklistCustomer(Request $request, Customer $customer): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $customer->markAsBlacklisted($request->reason);

            // Create a note about the blacklisting
            $customer->notes()->create([
                'user_id' => auth()->id(),
                'type' => 'other',
                'subject' => 'Customer Blacklisted',
                'content' => "Customer blacklisted. Reason: {$request->reason}",
                'priority' => 'high',
                'status' => 'completed'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Customer blacklisted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to blacklist customer: ' . $e->getMessage()
            ], 500);
        }
    }

    public function removeFromBlacklist(Customer $customer): JsonResponse
    {
        try {
            $customer->removeFromBlacklist();

            // Create a note about removing from blacklist
            $customer->notes()->create([
                'user_id' => auth()->id(),
                'type' => 'other',
                'subject' => 'Removed from Blacklist',
                'content' => 'Customer removed from blacklist by ' . auth()->user()->name,
                'priority' => 'normal',
                'status' => 'completed'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Customer removed from blacklist successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove customer from blacklist: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getAnalytics(Customer $customer): JsonResponse
    {
        $analytics = [
            'total_sales' => $customer->sales()->count(),
            'total_revenue' => $customer->total_spent,
            'average_order_value' => $customer->getAverageOrderValue(),
            'last_purchase_days_ago' => $customer->last_purchase_days_ago,
            'loyalty_points_balance' => $customer->getLoyaltyPointsBalance(),
            'expiring_points' => $customer->getExpiringPoints(),
            'credit_used' => $customer->getCurrentCreditUsed(),
            'available_credit' => $customer->available_credit,
            'total_contacts' => $customer->contacts()->count(),
            'total_addresses' => $customer->addresses()->count(),
            'total_notes' => $customer->notes()->count(),
            'open_follow_ups' => $customer->notes()->requireingFollowUp()->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $analytics
        ]);
    }
}
