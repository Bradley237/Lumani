<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to Lumani</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        .header {
            background: linear-gradient(135deg, #4f46e5, #6366f1);
            color: #ffffff;
            padding: 32px 24px;
            text-align: center;
        }
        .header h1 {
            margin: 0 0 8px;
            font-size: 24px;
            font-weight: 700;
        }
        .content {
            padding: 32px 24px;
            line-height: 1.6;
        }
        .referral-box {
            background: #f1f5f9;
            border: 2px dashed #cbd5e1;
            border-radius: 8px;
            padding: 16px;
            text-align: center;
            margin: 24px 0;
        }
        .referral-code {
            font-size: 24px;
            font-weight: 800;
            letter-spacing: 2px;
            color: #4f46e5;
        }
        .btn {
            display: inline-block;
            background: #4f46e5;
            color: #ffffff !important;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            margin-top: 16px;
        }
        .footer {
            padding: 24px;
            text-align: center;
            font-size: 12px;
            color: #64748b;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Welcome to Lumani! 🎓</h1>
            <p>Your ultimate Cameroonian exam preparation companion</p>
        </div>
        <div class="content">
            <p>Hi <strong>{{ $user->first_name ?: $user->name }}</strong>,</p>
            <p>Welcome to <strong>Lumani</strong>! We're excited to accompany you on your journey toward academic excellence.</p>
            <p>Here is what you can do right away:</p>
            <ul>
                <li><strong>Chapter Quizzes:</strong> Master topics step-by-step and test your recall.</li>
                <li><strong>Exam Mode:</strong> Practice with real past questions under timed conditions.</li>
                <li><strong>AI Tutor:</strong> Ask questions anytime and get instant, curriculum-tailored explanations.</li>
            </ul>

            <div class="referral-box">
                <p style="margin: 0 0 8px; font-size: 14px; color: #475569;">Share your referral code with friends and earn <strong>50 bonus coins</strong> each:</p>
                <div class="referral-code">{{ $user->referral_code }}</div>
            </div>

            <p style="text-align: center;">
                <a href="{{ config('app.url') }}" class="btn">Start Learning Now</a>
            </p>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} Lumani Education. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
