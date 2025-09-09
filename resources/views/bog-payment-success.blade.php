<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful - BOG</title>
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
            text-align: center;
        }
        .success-icon {
            font-size: 64px;
            color: #10b981;
            margin-bottom: 20px;
        }
        .header {
            margin-bottom: 30px;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #1e40af;
            margin-bottom: 10px;
        }
        .success-message {
            color: #10b981;
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 20px;
        }
        .description {
            color: #6b7280;
            margin-bottom: 30px;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background-color: #1e40af;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: bold;
        }
        .btn:hover {
            background-color: #1d4ed8;
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <div class="success-icon">✅</div>
        
        <div class="header">
            <div class="logo">🏦 BOG Bank</div>
            <div class="success-message">Payment Successful!</div>
            <div class="description">
                Your payment has been processed successfully. You will receive a confirmation email shortly.
            </div>
        </div>

        <a href="/" class="btn">Return to Home</a>
    </div>
</body>
</html>
