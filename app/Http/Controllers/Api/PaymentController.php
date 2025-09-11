<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Payment;
use App\Exports\PaymentsExport;
use Maatwebsite\Excel\Facades\Excel;

class PaymentController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/payments",
     *     operationId="getPayments",
     *     tags={"Payments"},
     *     summary="Get all payments",
     *     description="Retrieve a paginated list of all payments",
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
     *                     @OA\Property(property="card", type="object", description="Card information",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="phone", type="string", example="+995555123456"),
     *                         @OA\Property(property="status", type="string", example="active")
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
        $query = Payment::with('card:id,phone,status');
        
        $perPage = $request->query('per_page', 15);
        $page = $request->query('page', 1);
        
        return $query->paginate($perPage, ['*'], 'page', $page);
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
     *             @OA\Property(property="currency", type="string", example="GEL", description="Payment currency (defaults to GEL)"),
     *             @OA\Property(property="comment", type="string", example="Payment for monthly subscription", nullable=true, description="Payment comment (optional, max 1000 characters)"),
     *             @OA\Property(property="type", type="string", example="bank", nullable=true, description="Payment type - choose one of: bank, garden_balance, agent_balance, garden_card_change")
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
     *             @OA\Property(property="currency", type="string", example="GEL"),
     *             @OA\Property(property="comment", type="string", example="Payment for monthly subscription", nullable=true),
     *             @OA\Property(property="type", type="string", example="bank", nullable=true),
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
            'card_number' => 'required|string|max:255',
            'card_id' => 'required|exists:cards,id',
            'currency' => 'nullable|string|max:10',
            'comment' => 'nullable|string|max:1000',
            'type' => 'nullable|string|in:bank,garden_balance,agent_balance,garden_card_change',
        ]);

        $payment = Payment::create($validated);
        $payment->load('card:id,phone,status');

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
     *             @OA\Property(property="currency", type="string", example="GEL", description="Payment currency"),
     *             @OA\Property(property="comment", type="string", example="Payment for monthly subscription", nullable=true, description="Payment comment"),
     *             @OA\Property(property="type", type="string", example="bank", nullable=true, description="Payment type"),
 *             @OA\Property(property="card", type="object", description="Card information",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="phone", type="string", example="+995555123456"),
     *                 @OA\Property(property="status", type="string", example="active"),
     *                 @OA\Property(property="group", type="object", description="Garden group information",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Group A"),
     *                     @OA\Property(property="garden", type="object", description="Garden information",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Garden Name")
     *                     )
     *                 )
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
        return Payment::with(['card.group.garden:id,name'])->findOrFail($id);
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
            'currency' => 'nullable|string|max:10',
            'comment' => 'nullable|string|max:1000',
            'type' => 'nullable|string|in:bank,garden_balance,agent_balance,garden_card_change',
        ]);

        $payment->update($validated);
        $payment->load('card:id,phone,status');

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
}
