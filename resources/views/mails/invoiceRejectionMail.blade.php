<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Rejection Notice</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f9f9f9;
            color: #333;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            color: #4CAF50;
        }
        .content {
            line-height: 1.6;
        }
        .content p {
            margin: 0 0 10px;
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            font-size: 12px;
            color: #777;
        }
        .button {
            display: inline-block;
            background-color: #4CAF50;
            color: #fff;
            text-decoration: none;
            padding: 10px 15px;
            border-radius: 5px;
            font-size: 14px;
        }
        .button:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Invoice Rejection Notice</h1>
        </div>
        <div class="content">
            <p>Dear {{ $data['username'] }},</p>
            <p>We regret to inform you that your invoice <strong>#{{ $data['invoice_no'] }}</strong> submitted on <strong>{{ $data['submitted_date'] }}</strong> has been rejected. Below are the details:</p>
            <ul>
                <li><strong>Invoice Amount:</strong> {{ $data['invoice_amount'] }}</li>
                <li><strong>Reason for Rejection:</strong> {{ $data['rejection_reason'] }}</li>
            </ul>
            <p>Please review the reasons for rejection and make the necessary adjustments before resubmitting the invoice.</p>
            <p>If you have any questions or require further assistance, feel free to reach out to our support team.</p>
            {{-- <p>
                <a href="{{ $supportLink }}" class="button">Contact Support</a>
            </p> --}}
            <p>Thank you for your understanding.</p>
            <p>Best regards,</p>
            <p>The {{ $data['company_name'] }} Team</p>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} {{ $data['company_name'] }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
