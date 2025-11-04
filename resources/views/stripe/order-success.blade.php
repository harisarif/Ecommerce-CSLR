<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Successful</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f6f8;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .card {
            background: white;
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .checkmark {
            font-size: 60px;
            color: #28a745;
        }
        h1 {
            color: #333;
        }
        p {
            color: #666;
        }
        a {
            display: inline-block;
            margin-top: 20px;
            background: #28a745;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
        }
        a:hover {
            background: #218838;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="checkmark">✔</div>
        <h1>Payment Successful!</h1>
        <p>Thank you for your purchase. Your payment has been received successfully.</p>
        @if($sessionId)
            <p><strong>Session ID:</strong> {{ $sessionId }}</p>
        @endif
        <a href="/">Go to Homepage</a>
    </div>
</body>
</html>
