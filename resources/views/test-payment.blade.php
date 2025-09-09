<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>BOG Test Payment</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .payment-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #1e40af;
        }
        .payment-details {
            background: #f8fafc;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin: 10px 0;
            padding: 5px 0;
            border-bottom: 1px solid #e5e7eb;
        }
        .detail-label {
            font-weight: bold;
            color: #374151;
        }
        .detail-value {
            color: #6b7280;
        }
        .buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        .btn {
            flex: 1;
            padding: 12px 20px;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-success {
            background-color: #10b981;
            color: white;
        }
        .btn-success:hover {
            background-color: #059669;
        }
        .btn-danger {
            background-color: #ef4444;
            color: white;
        }
        .btn-danger:hover {
            background-color: #dc2626;
        }
        .test-mode {
            background-color: #fef3c7;
            color: #92400e;
            padding: 10px;
            border-radius: 6px;
            text-align: center;
            margin-bottom: 20px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <div class="header">
            <div class="logo">🏦 BOG Bank</div>
            <h2>Test Payment Gateway</h2>
        </div>

        <div class="test-mode">
            ⚠️ TEST MODE - This is a simulated payment
        </div>

        <div class="payment-details">
            <h3>Payment Details</h3>
            <div class="detail-row">
                <span class="detail-label">Transaction ID:</span>
                <span class="detail-value">{{ $transactionId }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Amount:</span>
                <span class="detail-value">{{ $payment->amount }} {{ $payment->currency }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Order ID:</span>
                <span class="detail-value">{{ $payment->order_id }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Status:</span>
                <span class="detail-value">{{ ucfirst($payment->status) }}</span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Created:</span>
                <span class="detail-value">{{ $payment->created_at->format('Y-m-d H:i:s') }}</span>
            </div>
        </div>

        <div class="buttons">
            <button class="btn btn-success" onclick="simulateSuccess()">
                ✅ Simulate Success
            </button>
            <button class="btn btn-danger" onclick="simulateFailure()">
                ❌ Simulate Failure
            </button>
        </div>

        <div style="margin-top: 30px; text-align: center; color: #6b7280; font-size: 14px;">
            <p>This is a test payment simulation. No real money will be charged.</p>
            <p>Use the buttons above to simulate different payment outcomes.</p>
        </div>
    </div>

    <script>
        function simulateSuccess() {
            // Simulate successful payment
            fetch('/api/bog-payments/callback', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({
                    order_id: '{{ $payment->order_id }}',
                    status: 'success',
                    transaction_id: '{{ $transactionId }}'
                })
            })
            .then(response => response.json())
            .then(data => {
                alert('Payment simulated successfully! Check the payment status.');
                window.close();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Payment simulation completed (check logs for details)');
                window.close();
            });
        }

        function simulateFailure() {
            // Simulate failed payment
            fetch('/api/bog-payments/callback', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({
                    order_id: '{{ $payment->order_id }}',
                    status: 'failed',
                    transaction_id: '{{ $transactionId }}'
                })
            })
            .then(response => response.json())
            .then(data => {
                alert('Payment failure simulated! Check the payment status.');
                window.close();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Payment simulation completed (check logs for details)');
                window.close();
            });
        }
    </script>
</body>
</html>
