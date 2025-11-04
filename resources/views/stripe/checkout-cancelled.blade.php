<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Cancelled</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f9ecec;
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
        .cross {
            font-size: 60px;
            color: #dc3545;
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
            background: #dc3545;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
        }
        a:hover {
            background: #c82333;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="cross">✖</div>
        <h1>Payment Cancelled</h1>
        <p>Your payment process was cancelled. Please try again.</p>
        <a href="/">Go to Homepage</a>
    </div>
</body>
</html>
