<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Cancelled - ProCredit</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; background-color: #f5f5f5; }
        .payment-container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; }
        .cancel-icon { font-size: 64px; color: #ef4444; margin-bottom: 20px; }
        .header { margin-bottom: 30px; }
        .logo { font-size: 24px; font-weight: bold; color: #1e40af; margin-bottom: 10px; }
        .cancel-message { color: #ef4444; font-size: 24px; font-weight: bold; margin-bottom: 20px; }
        .description { color: #6b7280; margin-bottom: 30px; }
        .btn { display: inline-block; padding: 12px 24px; background-color: #1e40af; color: white; text-decoration: none; border-radius: 6px; font-weight: bold; margin: 0 10px; }
        .btn:hover { background-color: #1d4ed8; }
        .btn-secondary { background-color: #6b7280; }
        .btn-secondary:hover { background-color: #4b5563; }
    </style>
</head>
<body>
    <div class="payment-container">
        <div class="cancel-icon">❌</div>
        <div class="header">
            <div class="logo">ProCredit E-commerce</div>
            <div class="cancel-message">Payment Cancelled</div>
            <div class="description">Your payment has been cancelled. No charges have been made to your account.</div>
        </div>
        <a href="/" class="btn">Return to Home</a>
        <a href="javascript:history.back()" class="btn btn-secondary">Try Again</a>
    </div>
</body>
</html>
