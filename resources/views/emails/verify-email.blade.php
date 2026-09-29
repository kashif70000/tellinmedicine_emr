<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email Address | {{ $siteName }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f4f6f9;
            color: #334155;
            margin: 0;
            padding: 0;
            -webkit-text-size-adjust: none;
            width: 100% !important;
        }
        .wrapper {
            width: 100%;
            background-color: #f4f6f9;
            padding: 40px 0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        .header {
            padding: 30px 40px 20px 40px;
            text-align: center;
            border-bottom: 1px solid #f1f5f9;
        }
        .header img {
            max-height: 50px;
            width: auto;
        }
        .body-content {
            padding: 40px;
        }
        .title {
            font-size: 22px;
            font-weight: 700;
            color: #0f172a;
            margin-top: 0;
            margin-bottom: 16px;
            text-align: center;
        }
        .text {
            font-size: 15px;
            line-height: 1.6;
            color: #475569;
            margin-bottom: 24px;
        }
        .btn-container {
            text-align: center;
            margin: 32px 0;
        }
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #ffffff !important;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            padding: 14px 32px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
        }
        .subtext {
            font-size: 13px;
            color: #64748b;
            line-height: 1.5;
            margin-top: 24px;
            border-top: 1px dashed #e2e8f0;
            padding-top: 20px;
        }
        .link-break {
            word-break: break-all;
            color: #2563eb;
            font-size: 12px;
        }
        .footer {
            padding: 24px 40px;
            background-color: #f8fafc;
            text-align: center;
            font-size: 13px;
            color: #94a3b8;
            border-top: 1px solid #f1f5f9;
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="container">
            <!-- Header with Logo -->
            <div class="header">
                <img src="{{ $logoUrl }}" alt="{{ $siteName }}">
            </div>

            <!-- Body -->
            <div class="body-content">
                <h1 class="title">Verify Your Email Address</h1>
                <p class="text">
                    Hello {{ $user->name ?? 'there' }},
                </p>
                <p class="text">
                    Welcome to <strong>{{ $siteName }}</strong>! Please click the button below to verify your email address and activate your account.
                </p>

                <div class="btn-container">
                    <a href="{{ $url }}" class="btn" target="_blank">Verify Email Address</a>
                </div>

                <p class="text" style="font-size: 14px; margin-bottom: 0;">
                    If you did not create an account on {{ $siteName }}, no further action is required.
                </p>

                <div class="subtext">
                    If you're having trouble clicking the "Verify Email Address" button, copy and paste the URL below into your web browser:
                    <br><br>
                    <a href="{{ $url }}" class="link-break">{{ $url }}</a>
                </div>
            </div>

            <!-- Footer -->
            <div class="footer">
                &copy; {{ date('Y') }} {{ $siteName }}. All rights reserved.
            </div>
        </div>
    </div>
</body>
</html>
