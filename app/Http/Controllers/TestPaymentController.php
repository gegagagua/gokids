<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BogPayment;

class TestPaymentController extends Controller
{
    /**
     * Display the test payment page
     */
    public function show(Request $request, string $transactionId)
    {
        // Find the payment by BOG transaction ID
        $payment = BogPayment::where('bog_transaction_id', $transactionId)->first();
        
        if (!$payment) {
            abort(404, 'Payment not found');
        }

        return view('test-payment', [
            'payment' => $payment,
            'transactionId' => $transactionId
        ]);
    }
}
