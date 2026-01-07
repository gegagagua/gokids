<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Payment;
use App\Models\Garden;
use App\Exports\PaymentsExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/payments",
     *     operationId="getPayments",
     *     tags={"Payments"},
     *     summary="Get all payments",
     *     description="Retrieve a paginated list of all payments. Supports filtering by payment type.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Filter by payment type",
     *         required=false,
     *         @OA\Schema(type="string", enum={"bank","garden_balance","agent_balance","garden_card_change"}, example="bank")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="current_page", type="integer", example=1),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="transaction_number", type="string", example="TXN123456789"),
     *                     @OA\Property(property="transaction_number_bank", type="string", nullable=true, example="BANK123456"),
     *                     @OA\Property(property="card_number", type="string", example="1234567890123456"),
     *                     @OA\Property(property="card_id", type="integer", example=1),
     *                     @OA\Property(property="currency", type="string", example="GEL", description="Payment currency"),
     *                     @OA\Property(property="comment", type="string", example="Payment for monthly subscription", nullable=true, description="Payment comment"),
     *                     @OA\Property(property="type", type="string", example="bank", nullable=true, description="Payment type"),
     *                     @OA\Property(property="amount", type="number", example=100.50, nullable=true, description="Payment amount"),
     *                     @OA\Property(property="status", type="string", example="pending", nullable=true, description="Payment status"),
     *                     @OA\Property(property="payment_gateway_id", type="integer", example=1, nullable=true, description="Payment gateway ID"),
     *                     @OA\Property(property="card", type="object", description="Card information",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="phone", type="string", example="+995555123456"),
     *                         @OA\Property(property="status", type="string", example="active"),
     *                         @OA\Property(property="child_first_name", type="string", example="John"),
     *                         @OA\Property(property="child_last_name", type="string", example="Doe"),
     *                         @OA\Property(property="parent_name", type="string", example="Jane Doe"),
     *                         @OA\Property(property="parent_code", type="string", example="ABC123")
     *                     ),
     *                     @OA\Property(property="payment_gateway", type="object", nullable=true, description="Payment gateway information",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Bank of Georgia"),
     *                         @OA\Property(property="currency", type="string", example="GEL"),
     *                         @OA\Property(property="is_active", type="boolean", example=true)
     *                     ),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )
     *             ),
     *             @OA\Property(property="last_page", type="integer", example=5),
     *             @OA\Property(property="per_page", type="integer", example=15),
     *             @OA\Property(property="total", type="integer", example=50)
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = Payment::with([
            'card:id,phone,status,child_first_name,child_last_name,parent_name,parent_code',
            'paymentGateway:id,name,currency,is_active'
        ]);
        
        // Filter by type if provided
        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }
        
        $perPage = $request->query('per_page', 15);
        $page = $request->query('page', 1);
        
        return $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);
    }

    // type: bank, garden_balance, agent_balance, garden_card_change

    /**
     * @OA\Post(
     *     path="/api/payments",
     *     operationId="storePayment",
     *     tags={"Payments"},
     *     summary="Create a new payment",
     *     description="Create a new payment with the provided information",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"transaction_number","card_number","card_id"},
     *             @OA\Property(property="transaction_number", type="string", example="TXN123456789", description="Unique transaction number"),
     *             @OA\Property(property="transaction_number_bank", type="string", example="BANK123456", nullable=true, description="Bank transaction number (optional)"),
     *             @OA\Property(property="card_number", type="string", example="1234567890123456", description="Card number"),
     *             @OA\Property(property="card_id", type="integer", example=1, description="Card ID"),
     *             @OA\Property(property="amount", type="number", example=100.50, nullable=true, description="Payment amount"),
     *             @OA\Property(property="currency", type="string", example="GEL", description="Payment currency (defaults to GEL)"),
     *             @OA\Property(property="comment", type="string", example="Payment for monthly subscription", nullable=true, description="Payment comment (optional, max 1000 characters)"),
     *             @OA\Property(property="type", type="string", example="bank", nullable=true, description="Payment type - choose one of: bank, garden_balance, agent_balance, garden_card_change"),
     *             @OA\Property(property="status", type="string", example="pending", nullable=true, description="Payment status (defaults to pending)"),
     *             @OA\Property(property="payment_gateway_id", type="integer", example=1, nullable=true, description="Payment gateway ID")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Payment created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="transaction_number", type="string", example="TXN123456789"),
     *             @OA\Property(property="transaction_number_bank", type="string", example="BANK123456"),
     *             @OA\Property(property="card_number", type="string", example="1234567890123456"),
     *             @OA\Property(property="card_id", type="integer", example=1),
     *             @OA\Property(property="amount", type="number", example=100.50, nullable=true),
     *             @OA\Property(property="currency", type="string", example="GEL"),
     *             @OA\Property(property="comment", type="string", example="Payment for monthly subscription", nullable=true),
     *             @OA\Property(property="type", type="string", example="bank", nullable=true),
     *             @OA\Property(property="status", type="string", example="pending", nullable=true),
     *             @OA\Property(property="payment_gateway_id", type="integer", example=1, nullable=true),
     *             @OA\Property(property="card", type="object", description="Card information",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="phone", type="string", example="+995555123456"),
     *                 @OA\Property(property="status", type="string", example="active"),
     *                 @OA\Property(property="child_first_name", type="string", example="John"),
     *                 @OA\Property(property="child_last_name", type="string", example="Doe"),
     *                 @OA\Property(property="parent_name", type="string", example="Jane Doe"),
     *                 @OA\Property(property="parent_code", type="string", example="ABC123")
     *             ),
     *             @OA\Property(property="payment_gateway", type="object", nullable=true, description="Payment gateway information",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Bank of Georgia"),
     *                 @OA\Property(property="currency", type="string", example="GEL"),
     *                 @OA\Property(property="is_active", type="boolean", example=true)
     *             ),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="transaction_number",
     *                     type="array",
     *                     @OA\Items(type="string", example="The transaction number field is required.")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'transaction_number' => 'required|string|max:255|unique:payments,transaction_number',
            'transaction_number_bank' => 'nullable|string|max:255',
            'card_number' => 'nullable|string|max:255',
            'card_id' => 'nullable|exists:cards,id',
            'garden_id' => 'nullable|exists:gardens,id',
            'amount' => 'required|numeric|max:9999999.99|min:-9999999.99',
            'currency' => 'nullable|string|max:10',
            'comment' => 'nullable|string|max:1000',
            'type' => 'nullable|string|in:bank,garden_balance,agent_balance,garden_card_change',
            'status' => 'nullable|string|max:255',
            'payment_gateway_id' => 'nullable|exists:payment_gateways,id',
        ]);

        // For garden_balance type, set transaction_number, transaction_number_bank, and card_number to "0"
        if ($validated['type'] === 'garden_balance') {
            // Set transaction_number to "0" with unique suffix (since it must be unique)
            $validated['transaction_number'] = '0_' . ($validated['garden_id'] ?? '0') . '_' . time() . '_' . uniqid();
            $validated['transaction_number_bank'] = '0';
            $validated['card_number'] = '0';
            $validated['card_id'] = null;
            
            // Validate garden_id is required for garden_balance type
            if (empty($validated['garden_id'])) {
                return response()->json([
                    'message' => 'garden_id is required for garden_balance type'
                ], 422);
            }
        } else {
            // For other types, validate card_id is required if not garden_balance
            if (empty($validated['card_id']) && empty($validated['garden_id'])) {
                return response()->json([
                    'message' => 'Either card_id or garden_id is required'
                ], 422);
            }
        }

        $payment = Payment::create($validated);

        // Update garden balance only if type is garden_balance AND status is completed
        if ($payment->type === 'garden_balance' && $payment->garden_id && $payment->status === 'completed') {
            $garden = \App\Models\Garden::find($payment->garden_id);
            if ($garden) {
                $oldBalance = $garden->balance ?? 0;
                $newBalance = $oldBalance + $payment->amount; // amount can be negative
                
                $garden->update([
                    'balance' => max(0, $newBalance), // Ensure balance doesn't go below 0
                ]);

                \Log::info('Garden balance updated from Payment', [
                    'payment_id' => $payment->id,
                    'garden_id' => $garden->id,
                    'amount' => $payment->amount,
                    'old_balance' => $oldBalance,
                    'new_balance' => $garden->balance,
                ]);
            }
        }

        $payment->load(['card:id,phone,status,child_first_name,child_last_name,parent_name,parent_code', 'paymentGateway:id,name,currency,is_active', 'garden:id,name,balance']);

        return response()->json($payment, 201);
    }

    /**
     * @OA\Get(
     *     path="/api/payments/{id}",
     *     operationId="getPayment",
     *     tags={"Payments"},
     *     summary="Get a specific payment",
     *     description="Retrieve detailed information about a specific payment",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Payment ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="transaction_number", type="string", example="TXN123456789"),
     *             @OA\Property(property="transaction_number_bank", type="string", nullable=true, example="BANK123456"),
     *             @OA\Property(property="card_number", type="string", example="1234567890123456"),
     *             @OA\Property(property="card_id", type="integer", example=1),
     *             @OA\Property(property="amount", type="number", example=100.50, nullable=true, description="Payment amount"),
     *             @OA\Property(property="currency", type="string", example="GEL", description="Payment currency"),
     *             @OA\Property(property="comment", type="string", example="Payment for monthly subscription", nullable=true, description="Payment comment"),
     *             @OA\Property(property="type", type="string", example="bank", nullable=true, description="Payment type"),
     *             @OA\Property(property="status", type="string", example="pending", nullable=true, description="Payment status"),
     *             @OA\Property(property="payment_gateway_id", type="integer", example=1, nullable=true, description="Payment gateway ID"),
     *             @OA\Property(property="card", type="object", description="Card information",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="phone", type="string", example="+995555123456"),
     *                 @OA\Property(property="status", type="string", example="active"),
     *                 @OA\Property(property="child_first_name", type="string", example="John"),
     *                 @OA\Property(property="child_last_name", type="string", example="Doe"),
     *                 @OA\Property(property="parent_name", type="string", example="Jane Doe"),
     *                 @OA\Property(property="parent_code", type="string", example="ABC123"),
     *                 @OA\Property(property="group", type="object", description="Garden group information",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Group A"),
     *                     @OA\Property(property="garden", type="object", description="Garden information",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Garden Name")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="payment_gateway", type="object", nullable=true, description="Payment gateway information",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Bank of Georgia"),
     *                 @OA\Property(property="currency", type="string", example="GEL"),
     *                 @OA\Property(property="is_active", type="boolean", example=true)
     *             ),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Payment not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No query results for model [App\\Models\\Payment]")
     *         )
     *     )
     * )
     */
    public function show(string $id)
    {
        return Payment::with([
            'card:id,phone,status,child_first_name,child_last_name,parent_name,parent_code,group_id',
            'card.group:id,name,garden_id',
            'card.group.garden:id,name',
            'paymentGateway:id,name,currency,is_active'
        ])->findOrFail($id);
    }

    /**
     * @OA\Put(
     *     path="/api/payments/{id}",
     *     operationId="updatePayment",
     *     tags={"Payments"},
     *     summary="Update a payment",
     *     description="Update an existing payment by ID",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Payment ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
      *             @OA\Property(property="transaction_number", type="string", example="TXN123456789"),
 *             @OA\Property(property="transaction_number_bank", type="string", example="BANK123456", nullable=true),
 *             @OA\Property(property="card_number", type="string", example="1234567890123456"),
 *             @OA\Property(property="card_id", type="integer", example=1),
 *             @OA\Property(property="currency", type="string", example="GEL", description="Payment currency")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="transaction_number", type="string", example="TXN123456789"),
     *             @OA\Property(property="transaction_number_bank", type="string", example="BANK123456"),
      *             @OA\Property(property="card_number", type="string", example="1234567890123456"),
 *             @OA\Property(property="card_id", type="integer", example=1),
 *             @OA\Property(property="currency", type="string", example="GEL"),
 *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Payment not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No query results for model [App\\Models\\Payment]")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="transaction_number",
     *                     type="array",
     *                     @OA\Items(type="string", example="The transaction number field is required.")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function update(Request $request, string $id)
    {
        $payment = Payment::findOrFail($id);

        $validated = $request->validate([
            'transaction_number' => 'sometimes|required|string|max:255|unique:payments,transaction_number,' . $id,
            'transaction_number_bank' => 'nullable|string|max:255',
            'card_number' => 'sometimes|required|string|max:255',
            'card_id' => 'sometimes|required|exists:cards,id',
            'amount' => 'nullable|numeric|min:0|max:9999999.99',
            'currency' => 'nullable|string|max:10',
            'comment' => 'nullable|string|max:1000',
            'type' => 'nullable|string|in:bank,garden_balance,agent_balance,garden_card_change',
            'status' => 'nullable|string|max:255',
            'payment_gateway_id' => 'nullable|exists:payment_gateways,id',
        ]);

        $oldStatus = $payment->status;
        $payment->update($validated);

        // Update garden balance if type is garden_balance and status changed to/from completed
        if ($payment->type === 'garden_balance' && $payment->garden_id) {
            $garden = \App\Models\Garden::find($payment->garden_id);
            if ($garden) {
                // If status changed to completed, add amount to balance
                if ($oldStatus !== 'completed' && $payment->status === 'completed') {
                    $oldBalance = $garden->balance ?? 0;
                    $newBalance = $oldBalance + $payment->amount;
                    
                    $garden->update([
                        'balance' => max(0, $newBalance),
                    ]);

                    Log::info('Garden balance updated from Payment status change to completed', [
                        'payment_id' => $payment->id,
                        'garden_id' => $garden->id,
                        'amount' => $payment->amount,
                        'old_balance' => $oldBalance,
                        'new_balance' => $garden->balance,
                    ]);
                }
                // If status changed from completed to something else, subtract amount from balance
                elseif ($oldStatus === 'completed' && $payment->status !== 'completed') {
                    $oldBalance = $garden->balance ?? 0;
                    $newBalance = $oldBalance - $payment->amount; // Reverse the previous addition
                    
                    $garden->update([
                        'balance' => max(0, $newBalance),
                    ]);

                    Log::info('Garden balance reverted from Payment status change from completed', [
                        'payment_id' => $payment->id,
                        'garden_id' => $garden->id,
                        'amount' => $payment->amount,
                        'old_balance' => $oldBalance,
                        'new_balance' => $garden->balance,
                    ]);
                }
            }
        }

        $payment->load(['card:id,phone,status,child_first_name,child_last_name,parent_name,parent_code', 'paymentGateway:id,name,currency,is_active', 'garden:id,name,balance']);

        return response()->json($payment);
    }

    /**
     * @OA\Delete(
     *     path="/api/payments/{id}",
     *     operationId="deletePayment",
     *     tags={"Payments"},
     *     summary="Delete a payment",
     *     description="Permanently delete a payment",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Payment ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Payment deleted")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Payment not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No query results for model [App\\Models\\Payment]")
     *         )
     *     )
     * )
     */
    public function destroy(string $id)
    {
        $payment = Payment::findOrFail($id);
        $payment->delete();

        return response()->json(['message' => 'Payment deleted']);
    }

    /**
     * @OA\Get(
     *     path="/api/payments/export",
     *     operationId="exportPayments",
     *     tags={"Payments"},
     *     summary="Export all payments",
     *     description="Export all payments data to Excel file",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Excel file download",
     *         @OA\Header(
     *             header="Content-Disposition",
     *             description="Attachment filename",
     *             @OA\Schema(type="string", example="attachment; filename=payments.xlsx")
     *         ),
     *         @OA\Header(
     *             header="Content-Type",
     *             description="File content type",
     *             @OA\Schema(type="string", example="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet")
     *         )
     *     )
     * )
     */
    public function export()
    {
        return Excel::download(new PaymentsExport, 'payments.xlsx');
    }

    /**
     * @OA\Post(
     *     path="/api/payments/create-garden-payment",
     *     operationId="createGardenPayment",
     *     tags={"Payments"},
     *     summary="Create garden balance payment",
     *     description="Create a payment record to update garden balance. Amount can be positive (add to balance) or negative (subtract from balance).",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"garden_id", "amount"},
             *             @OA\Property(property="garden_id", type="integer", example=1, description="Garden ID"),
             *             @OA\Property(property="amount", type="number", example=100.5, description="Amount to add (positive) or subtract (negative) from garden balance"),
             *             @OA\Property(property="currency", type="string", example="GEL", description="Currency code", nullable=true),
             *             @OA\Property(property="comment", type="string", example="Payment for monthly subscription", description="Payment comment", nullable=true),
             *             @OA\Property(property="payment_gateway_id", type="integer", example=1, description="Payment gateway ID", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Garden payment created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Garden payment created successfully"),
     *             @OA\Property(property="payment", type="object"),
     *             @OA\Property(property="garden", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="balance", type="number", example=250.50)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Garden not found"
     *     )
     * )
     */
    public function createGardenPayment(Request $request)
    {
        $validated = $request->validate([
            'garden_id' => 'required|integer|exists:gardens,id',
            'amount' => 'required|numeric|max:9999999.99|min:-9999999.99',
            'currency' => 'nullable|string|max:10',
            'comment' => 'nullable|string|max:1000',
            'status' => 'nullable|string|max:255',
            'payment_gateway_id' => 'nullable|exists:payment_gateways,id',
        ]);

        // Get garden
        $garden = Garden::find($validated['garden_id']);
        if (!$garden) {
            return response()->json([
                'success' => false,
                'message' => 'Garden not found.'
            ], 404);
        }

        // Generate unique transaction_number (must be unique)
        $transactionNumber = '0_' . $validated['garden_id'] . '_' . time() . '_' . uniqid();

        // Create payment with garden_balance type
        $paymentData = [
            'transaction_number' => $transactionNumber,
            'transaction_number_bank' => '0',
            'card_number' => '0',
            'card_id' => null,
            'garden_id' => $validated['garden_id'],
            'amount' => $validated['amount'],
            'currency' => $validated['currency'] ?? 'GEL',
            'comment' => $validated['comment'] ?? 'Garden balance update',
            'type' => 'garden_balance',
            'status' => $validated['status'] ?? 'completed',
            'payment_gateway_id' => $validated['payment_gateway_id'] ?? null,
        ];

        $payment = Payment::create($paymentData);

        // Update garden balance only if status is completed
        if ($payment->status === 'completed') {
            $oldBalance = $garden->balance ?? 0;
            $newBalance = $oldBalance + $payment->amount; // amount can be negative
            
            $garden->update([
                'balance' => max(0, $newBalance), // Ensure balance doesn't go below 0
            ]);

            Log::info('Garden balance updated from Payment', [
                'payment_id' => $payment->id,
                'garden_id' => $garden->id,
                'amount' => $payment->amount,
                'old_balance' => $oldBalance,
                'new_balance' => $garden->balance,
            ]);
        }

        $payment->load(['garden:id,name,balance']);

        return response()->json([
            'success' => true,
            'message' => 'Garden payment created successfully',
            'payment' => $payment,
            'garden' => [
                'id' => $garden->id,
                'balance' => $garden->balance,
            ],
        ], 201);
    }
}
