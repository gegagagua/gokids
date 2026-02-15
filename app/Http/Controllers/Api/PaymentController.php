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
     *     description="Retrieve a paginated list of payments with filters: date range, country, city, dister, garden, payment gateway, type, status, and search by card phone.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="page", in="query", required=false, @OA\Schema(type="integer", default=1)),
     *     @OA\Parameter(name="per_page", in="query", required=false, @OA\Schema(type="integer", default=15)),
     *     @OA\Parameter(name="type", in="query", description="Filter by payment type", required=false, @OA\Schema(type="string", enum={"bank","garden_balance","agent_balance","garden_card_change"})),
     *     @OA\Parameter(name="status", in="query", description="Filter by status", required=false, @OA\Schema(type="string", example="completed")),
     *     @OA\Parameter(name="date_from", in="query", description="Filter from date (YYYY-MM-DD)", required=false, @OA\Schema(type="string", format="date", example="2026-01-01")),
     *     @OA\Parameter(name="date_to", in="query", description="Filter to date (YYYY-MM-DD)", required=false, @OA\Schema(type="string", format="date", example="2026-12-31")),
     *     @OA\Parameter(name="country_id", in="query", description="Filter by card's country", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="city_id", in="query", description="Filter by card's city", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="dister_id", in="query", description="Filter by dister (cards in dister's gardens)", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="garden_id", in="query", description="Filter by garden", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="payment_gateway_id", in="query", description="Filter by payment gateway", required=false, @OA\Schema(type="integer")),
     *     @OA\Parameter(name="search", in="query", description="Search by card phone number", required=false, @OA\Schema(type="string", example="995555")),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="current_page", type="integer", example=1),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="transaction_number", type="string"),
     *                     @OA\Property(property="card_id", type="integer"),
     *                     @OA\Property(property="garden_id", type="integer", nullable=true),
     *                     @OA\Property(property="amount", type="number"),
     *                     @OA\Property(property="currency", type="string"),
     *                     @OA\Property(property="type", type="string"),
     *                     @OA\Property(property="status", type="string"),
     *                     @OA\Property(property="card", type="object", nullable=true),
     *                     @OA\Property(property="payment_gateway", type="object", nullable=true),
     *                     @OA\Property(property="garden", type="object", nullable=true)
     *                 )
     *             ),
     *             @OA\Property(property="last_page", type="integer"),
     *             @OA\Property(property="per_page", type="integer"),
     *             @OA\Property(property="total", type="integer")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = Payment::with([
            'card:id,phone,status,child_first_name,child_last_name,parent_name,parent_code,group_id',
            'card.group:id,garden_id',
            'card.group.garden:id,name,country_id,city_id',
            'paymentGateway:id,name,currency,is_active',
            'garden:id,name,balance,country_id',
        ]);

        // ── Role-based access filtering ──
        $user = $request->user();
        $disterRecord = null;

        // Resolve Dister record (user can be Dister model directly or User with type=dister)
        if ($user instanceof \App\Models\Dister) {
            $disterRecord = $user;
        } elseif ($user instanceof \App\Models\User && $user->type === 'dister') {
            $disterRecord = \App\Models\Dister::where('email', $user->email)->first();
        }

        if ($disterRecord) {
            $allowedGardenIds = is_array($disterRecord->gardens) ? $disterRecord->gardens : [];
            $isChildDister = !empty($disterRecord->main_dister);

            if ($isChildDister) {
                // Child (second-level) dister — sees only their own gardens' payments
                if (!empty($allowedGardenIds)) {
                    $query->where(function ($q) use ($allowedGardenIds) {
                        $q->whereIn('garden_id', $allowedGardenIds)
                          ->orWhereHas('card.group', function ($gq) use ($allowedGardenIds) {
                              $gq->whereIn('garden_id', $allowedGardenIds);
                          });
                    });
                } else {
                    $query->whereRaw('1 = 0'); // no gardens assigned — empty result
                }
            } else {
                // Parent (first-level) dister — sees their gardens + all gardens in their country
                $countryId = $disterRecord->country_id;
                $countryGardenIds = [];
                if ($countryId) {
                    $countryGardenIds = \App\Models\Garden::where('country_id', $countryId)
                        ->pluck('id')->toArray();
                }
                $mergedGardenIds = array_values(array_unique(array_merge($allowedGardenIds, $countryGardenIds)));

                if (!empty($mergedGardenIds)) {
                    $query->where(function ($q) use ($mergedGardenIds) {
                        $q->whereIn('garden_id', $mergedGardenIds)
                          ->orWhereHas('card.group', function ($gq) use ($mergedGardenIds) {
                              $gq->whereIn('garden_id', $mergedGardenIds);
                          });
                    });
                } else {
                    $query->whereRaw('1 = 0');
                }
            }
        } elseif ($user instanceof \App\Models\User && $user->type === 'garden') {
            // Garden user — sees only their garden's payments
            $garden = \App\Models\Garden::where('email', $user->email)->first();
            if ($garden) {
                $query->where(function ($q) use ($garden) {
                    $q->where('garden_id', $garden->id)
                      ->orWhereHas('card.group', function ($gq) use ($garden) {
                          $gq->where('garden_id', $garden->id);
                      });
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        }
        // Admin, accountant, technical — no filter, sees all payments

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        // Filter by payment gateway
        if ($request->filled('payment_gateway_id')) {
            $query->where('payment_gateway_id', $request->query('payment_gateway_id'));
        }

        // Filter by date range (created_at)
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->query('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->query('date_to'));
        }

        // Filter by garden (direct garden_id on payment OR card's garden)
        if ($request->filled('garden_id')) {
            $gardenId = $request->query('garden_id');
            $query->where(function ($q) use ($gardenId) {
                $q->where('garden_id', $gardenId)
                  ->orWhereHas('card.group.garden', function ($gq) use ($gardenId) {
                      $gq->where('id', $gardenId);
                  });
            });
        }

        // Filter by country (card → group → garden → country_id)
        if ($request->filled('country_id')) {
            $countryId = $request->query('country_id');
            $query->whereHas('card.group.garden', function ($q) use ($countryId) {
                $q->where('country_id', $countryId);
            });
        }

        // Filter by city (card → group → garden → city_id)
        if ($request->filled('city_id')) {
            $cityId = $request->query('city_id');
            $query->whereHas('card.group.garden', function ($q) use ($cityId) {
                $q->where('city_id', $cityId);
            });
        }

        // Filter by dister (dister has gardens JSON array containing garden IDs)
        if ($request->filled('dister_id')) {
            $dister = \App\Models\Dister::find($request->query('dister_id'));
            if ($dister && is_array($dister->gardens) && count($dister->gardens) > 0) {
                $disterGardenIds = $dister->gardens;
                $query->where(function ($q) use ($disterGardenIds) {
                    $q->whereIn('garden_id', $disterGardenIds)
                      ->orWhereHas('card.group', function ($gq) use ($disterGardenIds) {
                          $gq->whereIn('garden_id', $disterGardenIds);
                      });
                });
            } else {
                // Dister not found or has no gardens — return empty
                $query->whereRaw('1 = 0');
            }
        }

        // Search by card phone
        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->whereHas('card', function ($q) use ($search) {
                $q->where('phone', 'like', '%' . $search . '%');
            });
        }

        $perPage = $request->query('per_page', 15);
        $page = $request->query('page', 1);

        $paginated = $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);

        // ── Add distribution (percentages & amounts) to each payment ──
        // Collect all garden IDs from payments to batch-load related data
        $gardenIds = collect();
        foreach ($paginated->items() as $payment) {
            if ($payment->garden_id) {
                $gardenIds->push($payment->garden_id);
            }
            if ($payment->card && $payment->card->group && $payment->card->group->garden) {
                $gardenIds->push($payment->card->group->garden->id);
            }
        }
        $gardenIds = $gardenIds->unique()->values()->all();

        // Load countries for those gardens
        $gardens = \App\Models\Garden::whereIn('id', $gardenIds)->get()->keyBy('id');
        $countryIds = $gardens->pluck('country_id')->unique()->filter()->values()->all();
        $countries = \App\Models\Country::whereIn('id', $countryIds)->get()->keyBy('id');

        // Load disters that own those gardens
        $disters = \App\Models\Dister::all();
        $gardenDisterMap = [];
        foreach ($disters as $dister) {
            if (is_array($dister->gardens)) {
                foreach ($dister->gardens as $gId) {
                    $gardenDisterMap[$gId] = $dister;
                }
            }
        }

        // Attach distribution to each payment
        $paginated->getCollection()->transform(function ($payment) use ($gardens, $countries, $gardenDisterMap) {
            $amount = abs((float) $payment->amount);

            // Resolve garden
            $gardenId = $payment->garden_id;
            if (!$gardenId && $payment->card && $payment->card->group && $payment->card->group->garden) {
                $gardenId = $payment->card->group->garden->id;
            }

            $garden = $gardenId ? ($gardens[$gardenId] ?? null) : null;
            $country = ($garden && $garden->country_id) ? ($countries[$garden->country_id] ?? null) : null;
            $dister = $gardenId ? ($gardenDisterMap[$gardenId] ?? null) : null;

            // Percentages (must always sum to 100%)
            $disterPercent = $dister ? (float) ($dister->percent ?? 0) : 0;
            $secondDisterPercent = $dister ? (float) ($dister->second_percent ?? 0) : 0;
            $adminPercent = round(100 - $disterPercent - $secondDisterPercent, 2);
            if ($adminPercent < 0) $adminPercent = 0;

            // Amounts
            $disterAmount = round($amount * $disterPercent / 100, 2);
            $secondDisterAmount = round($amount * $secondDisterPercent / 100, 2);
            $adminAmount = round($amount - $disterAmount - $secondDisterAmount, 2);
            if ($adminAmount < 0) $adminAmount = 0;

            $distribution = [
                'admin' => [
                    'percent' => $adminPercent,
                    'amount' => $adminAmount,
                ],
                'dister' => [
                    'name' => $dister ? ($dister->first_name . ' ' . $dister->last_name) : null,
                    'percent' => $disterPercent,
                    'amount' => $disterAmount,
                ],
            ];

            // Only include second_dister if it has a percent > 0 AND name is resolvable
            if ($secondDisterPercent > 0) {
                $secondDisterName = null;
                if ($dister && is_array($dister->main_dister)) {
                    $mainDisterId = $dister->main_dister['id'] ?? null;
                    if ($mainDisterId) {
                        $mainDister = \App\Models\Dister::find($mainDisterId);
                        $secondDisterName = $mainDister ? ($mainDister->first_name . ' ' . $mainDister->last_name) : null;
                    }
                }
                if ($secondDisterName !== null) {
                    $distribution['second_dister'] = [
                        'name' => $secondDisterName,
                        'percent' => $secondDisterPercent,
                        'amount' => $secondDisterAmount,
                    ];
                }
            }

            $payment->distribution = $distribution;
            return $payment;
        });

        return $paginated;
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
     *     description="Create a payment record to update garden balance. Positive amount (e.g. 50) adds to balance, negative amount (e.g. -100) subtracts from balance.",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"garden_id", "amount"},
     *             @OA\Property(property="garden_id", type="integer", example=1, description="Garden ID"),
     *             @OA\Property(property="amount", type="number", example=50, description="Positive to add, negative to subtract (e.g. 50 adds, -100 subtracts)"),
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

        // Amount sign determines operation: positive = add, negative = subtract
        $amount = (float) $validated['amount'];

        // Generate unique transaction_number (must be unique)
        $transactionNumber = '0_' . $validated['garden_id'] . '_' . time() . '_' . uniqid();

        // Create payment with garden_balance type
        $paymentData = [
            'transaction_number' => $transactionNumber,
            'transaction_number_bank' => '0',
            'card_number' => '0',
            'card_id' => null,
            'garden_id' => $validated['garden_id'],
            'amount' => $amount,
            'currency' => $validated['currency'] ?? 'GEL',
            'comment' => $validated['comment'] ?? ('Garden balance ' . ($amount >= 0 ? 'add' : 'subtract')),
            'type' => 'garden_balance',
            'status' => $validated['status'] ?? 'completed',
            'payment_gateway_id' => $validated['payment_gateway_id'] ?? null,
        ];

        $payment = Payment::create($paymentData);

        // Update garden balance only if status is completed
        if ($payment->status === 'completed') {
            $oldBalance = $garden->balance ?? 0;
            $newBalance = $oldBalance + $payment->amount; // amount is negative for subtract

            $garden->update([
                'balance' => max(0, $newBalance), // Ensure balance doesn't go below 0
            ]);

            Log::info('Garden balance updated from Payment', [
                'payment_id' => $payment->id,
                'garden_id' => $garden->id,
                'operation' => $amount >= 0 ? 'add' : 'subtract',
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
            'operation' => $amount >= 0 ? 'add' : 'subtract',
            'garden' => [
                'id' => $garden->id,
                'balance' => $garden->balance,
            ],
        ], 201);
    }

    /**
     * @OA\Post(
     *     path="/api/payments/pay-for-cards",
     *     operationId="payForCards",
     *     tags={"Payments"},
     *     summary="Pay for cards from garden balance",
     *     description="Garden pays for cards using its balance. Deducts tariff × number of cards from garden balance and activates 1-year license for each card.",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"garden_id", "card_ids"},
     *             @OA\Property(property="garden_id", type="integer", example=1, description="Garden ID"),
     *             @OA\Property(property="card_ids", type="array", @OA\Items(type="integer"), description="Array of card IDs to pay for"),
     *             @OA\Property(property="comment", type="string", example="Monthly subscription", description="Payment comment", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cards paid successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="total_amount", type="number", example=200),
     *             @OA\Property(property="tariff_per_card", type="number", example=100),
     *             @OA\Property(property="cards_count", type="integer", example=2),
     *             @OA\Property(property="garden", type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="old_balance", type="number"),
     *                 @OA\Property(property="new_balance", type="number")
     *             ),
     *             @OA\Property(property="activated_cards", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error or insufficient balance"),
     *     @OA\Response(response=404, description="Garden not found")
     * )
     */
    public function payForCards(Request $request)
    {
        $validated = $request->validate([
            'garden_id' => 'required|integer|exists:gardens,id',
            'card_ids' => 'required|array|min:1',
            'card_ids.*' => 'required|integer|exists:cards,id',
            'comment' => 'nullable|string|max:1000',
        ]);

        $garden = Garden::with('countryData')->find($validated['garden_id']);
        if (!$garden) {
            return response()->json(['success' => false, 'message' => 'Garden not found.'], 404);
        }

        // Get tariff from country
        $country = $garden->countryData;
        if (!$country || !$country->tariff || $country->tariff <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Country tariff not configured or is zero.',
            ], 422);
        }

        $tariff = (float) $country->tariff;
        $currency = 'GEL';

        // Use payment gateway currency if available
        $paymentGateway = $country->paymentGateway;
        if ($paymentGateway && !empty($paymentGateway->currency)) {
            $currency = $paymentGateway->currency;
        }

        // Validate cards belong to this garden
        $cards = \App\Models\Card::with(['group.garden'])->whereIn('id', $validated['card_ids'])->get();
        $invalidCards = [];
        foreach ($cards as $card) {
            if (!$card->group || !$card->group->garden || $card->group->garden->id !== $garden->id) {
                $invalidCards[] = $card->id;
            }
        }
        if (!empty($invalidCards)) {
            return response()->json([
                'success' => false,
                'message' => 'Some cards do not belong to this garden.',
                'invalid_card_ids' => $invalidCards,
            ], 422);
        }

        $totalAmount = round($tariff * count($cards), 2);
        $gardenBalance = (float) ($garden->balance ?? 0);

        // Check sufficient balance
        if ($gardenBalance < $totalAmount) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient garden balance.',
                'garden_balance' => $gardenBalance,
                'required_amount' => $totalAmount,
                'shortage' => round($totalAmount - $gardenBalance, 2),
            ], 422);
        }

        $oldBalance = $gardenBalance;
        $comment = $validated['comment'] ?? ('Pay for ' . count($cards) . ' cards from garden balance');
        $activatedCards = [];

        // Process each card: create payment record + activate license
        foreach ($cards as $card) {
            $txn = '0_' . $garden->id . '_card_' . $card->id . '_' . time() . '_' . uniqid();

            $pgId = $paymentGateway ? $paymentGateway->id : null;

            Payment::create([
                'transaction_number' => $txn,
                'transaction_number_bank' => '0',
                'card_number' => $card->phone ? ('****' . substr($card->phone, -4)) : 'N/A',
                'card_id' => $card->id,
                'garden_id' => $garden->id,
                'amount' => -$tariff, // negative — deducted from garden balance
                'currency' => $currency,
                'comment' => $comment . ' - Card ID: ' . $card->id,
                'type' => 'garden_card_change',
                'status' => 'completed',
                'payment_gateway_id' => $pgId,
            ]);

            // Activate card license (1 year)
            $oldLicense = $card->license;
            $expiryDate = \Carbon\Carbon::now()->addYear()->toDateString();
            $card->setLicenseDate($expiryDate);
            $card->save();

            $activatedCards[] = [
                'card_id' => $card->id,
                'amount' => $tariff,
                'old_license' => $oldLicense,
                'new_license' => $card->license,
                'expiry_date' => $expiryDate,
            ];
        }

        // Deduct total from garden balance
        $garden->update([
            'balance' => max(0, $oldBalance - $totalAmount),
        ]);

        Log::info('Garden payForCards: balance deducted and cards activated', [
            'garden_id' => $garden->id,
            'cards_count' => count($cards),
            'tariff_per_card' => $tariff,
            'total_amount' => $totalAmount,
            'old_balance' => $oldBalance,
            'new_balance' => $garden->balance,
        ]);

        return response()->json([
            'success' => true,
            'message' => count($cards) . ' card(s) paid and activated successfully.',
            'total_amount' => $totalAmount,
            'tariff_per_card' => $tariff,
            'currency' => $currency,
            'cards_count' => count($cards),
            'garden' => [
                'id' => $garden->id,
                'old_balance' => $oldBalance,
                'new_balance' => $garden->balance,
            ],
            'activated_cards' => $activatedCards,
        ]);
    }
}
