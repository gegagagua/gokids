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
        .attempt-info { color: #9ca3af; font-size: 12px; margin-top: 10px; }
    </style>
</head>
<body>
    <div class="payment-container">
        <div id="status-icon" class="success-icon">⏳</div>
        <div class="header">
            <div class="logo">ProCredit E-commerce</div>
            <div id="status-message" class="success-message">Checking payment status…</div>
            <div id="status-description" class="description"></div>
            <div id="attempt-info" class="attempt-info"></div>
        </div>
        <a href="https://my.gokids.kids/admin/cards" class="btn" id="home-btn" style="display:none;">Return to Home</a>
    </div>
    <script>
        (function() {
            var params = new URLSearchParams(window.location.search);
            var orderId = params.get('order_id');
            var hppStatus = params.get('STATUS');  // Bank sends STATUS=FullyPaid in redirect URL
            var hppId = params.get('ID');           // Bank sends ID (bank order id) in redirect URL
            var statusEl = document.getElementById('status-message');
            var descEl = document.getElementById('status-description');
            var iconEl = document.getElementById('status-icon');
            var homeBtn = document.getElementById('home-btn');
            var attemptEl = document.getElementById('attempt-info');

            var MAX_ATTEMPTS = 10;
            var INTERVAL_MS = 3000; // 3 seconds between checks
            var attempt = 0;

            if (!orderId) {
                statusEl.textContent = 'Order not found';
                statusEl.style.color = '#6b7280';
                descEl.textContent = 'Missing order_id in URL.';
                homeBtn.style.display = 'inline-block';
                return;
            }

            function showFinal(icon, message, color, description) {
                iconEl.textContent = icon;
                statusEl.textContent = message;
                statusEl.style.color = color;
                descEl.textContent = description;
                homeBtn.style.display = 'inline-block';
                attemptEl.textContent = '';
            }

            // Build status URL with hpp_status query param so backend can use it as fallback
            function buildStatusUrl() {
                var url = '/api/procredit-payment/status/' + encodeURIComponent(orderId);
                if (hppStatus) {
                    url += '?hpp_status=' + encodeURIComponent(hppStatus);
                }
                return url;
            }

            function checkStatus() {
                attempt++;
                attemptEl.textContent = 'Checking… (' + attempt + '/' + MAX_ATTEMPTS + ')';

                fetch(buildStatusUrl(), { headers: { 'Accept': 'application/json' } })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.success && data.status) {
                            if (data.status === 'completed') {
                                showFinal('✅', 'Payment Successful!', '#10b981', 'Your payment has been processed successfully.');
                                return;
                            }
                            if (data.status === 'failed' || data.status === 'cancelled') {
                                showFinal('❌', data.status === 'cancelled' ? 'Payment cancelled' : 'Payment failed', '#ef4444', 'The payment was not completed.');
                                return;
                            }
                            // Still pending
                            if (attempt < MAX_ATTEMPTS) {
                                setTimeout(checkStatus, INTERVAL_MS);
                            } else {
                                showFinal('⏳', 'Payment pending', '#6b7280', 'Your payment is still being processed. The license will activate automatically once completed. You can close this page.');
                            }
                        } else {
                            if (attempt < MAX_ATTEMPTS) {
                                setTimeout(checkStatus, INTERVAL_MS);
                            } else {
                                showFinal('❓', 'Unable to verify', '#6b7280', data.message || 'Could not load payment status.');
                            }
                        }
                    })
                    .catch(function() {
                        if (attempt < MAX_ATTEMPTS) {
                            setTimeout(checkStatus, INTERVAL_MS);
                        } else {
                            showFinal('⚠️', 'Error', '#ef4444', 'Could not load payment status. You may check your order later.');
                        }
                    });
            }

            checkStatus();
        })();
    </script>
</body>
</html>
