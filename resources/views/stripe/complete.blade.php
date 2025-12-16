<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Stripe Connection</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f9fafb;
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
        }

        .card {
            background: #fff;
            padding: 40px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            max-width: 420px;
            width: 100%;
        }

        .success {
            color: #16a34a;
            font-size: 22px;
            margin-bottom: 10px;
        }

        .pending {
            color: #f59e0b;
            font-size: 22px;
            margin-bottom: 10px;
        }

        .icon {
            font-size: 48px;
            margin-bottom: 15px;
        }

        p {
            color: #6b7280;
        }
    </style>
</head>
<body>

<div class="card">
    @if($connected)
        <div class="icon">✅</div>
        <div class="success">Stripe Connected Successfully</div>
        <p>Your shop is now ready to receive payments.</p>
    @else
        <div class="icon">⏳</div>
        <div class="pending">Verification in Progress</div>
        <p>
            Stripe is reviewing your details.<br>
            This may take a few minutes to 24 hours.
        </p>
    @endif
</div>

</body>
</html>
