<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment - ProCredit</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; background-color: #f5f5f5; }
        .payment-container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; }
        .success-icon { font-size: 64px; color: #10b981; margin-bottom: 20px; }
        .header { margin-bottom: 30px; }
        .logo { font-size: 24px; font-weight: bold; color: #1e40af; margin-bottom: 10px; }
        .success-message { color: #10b981; font-size: 24px; font-weight: bold; margin-bottom: 20px; }
        .description { color: #6b7280; margin-bottom: 30px; }
        .btn { display: inline-block; padding: 12px 24px; background-color: #1e40af; color: white; text-decoration: none; border-radius: 6px; font-weight: bold; }
        .btn:hover { background-color: #1d4ed8; }
    </style>
</head>
<body>
    <div class="payment-container">
        <div id="status-icon" class="success-icon">⏳</div>
        <div class="header">
            <div class="logo">ProCredit E-commerce</div>
            <div id="status-message" class="success-message">Checking payment status…</div>
            <div id="status-description" class="description"></div>
        </div>
        <a href="/" class="btn" id="home-btn" style="display:none;">Return to Home</a>
    </div>
    <script>
        (function() {
            var params = new URLSearchParams(window.location.search);
            var orderId = params.get('order_id');
            var statusEl = document.getElementById('status-message');
            var descEl = document.getElementById('status-description');
            var iconEl = document.getElementById('status-icon');
            var homeBtn = document.getElementById('home-btn');
            if (!orderId) {
                statusEl.textContent = 'Order not found';
                statusEl.style.color = '#6b7280';
                descEl.textContent = 'Missing order_id in URL.';
                homeBtn.style.display = 'inline-block';
                return;
            }
            fetch('/api/procredit-payment/status/' + encodeURIComponent(orderId), { headers: { 'Accept': 'application/json' } })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    homeBtn.style.display = 'inline-block';
                    if (data.success && data.status) {
                        if (data.status === 'completed') {
                            iconEl.textContent = '✅';
                            statusEl.textContent = 'Payment Successful!';
                            statusEl.style.color = '#10b981';
                            descEl.textContent = 'Your payment has been processed successfully.';
                        } else if (data.status === 'failed' || data.status === 'cancelled') {
                            iconEl.textContent = '❌';
                            statusEl.textContent = data.status === 'cancelled' ? 'Payment cancelled' : 'Payment failed';
                            statusEl.style.color = '#ef4444';
                            descEl.textContent = 'The payment was not completed.';
                        } else {
                            iconEl.textContent = '⏳';
                            statusEl.textContent = 'Payment pending';
                            statusEl.style.color = '#6b7280';
                            descEl.textContent = 'Your payment is still being processed. You can close this page and check back later.';
                        }
                    } else {
                        iconEl.textContent = '❓';
                        statusEl.textContent = 'Unable to verify';
                        statusEl.style.color = '#6b7280';
                        descEl.textContent = data.message || 'Could not load payment status.';
                    }
                })
                .catch(function() {
                    iconEl.textContent = '⚠️';
                    statusEl.textContent = 'Error';
                    statusEl.style.color = '#ef4444';
                    descEl.textContent = 'Could not load payment status. You may check your order later.';
                    homeBtn.style.display = 'inline-block';
                });
        })();
    </script>
</body>
</html>
