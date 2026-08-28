<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Keep Your Streak Alive</title>
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
            background: linear-gradient(135deg, #f59e0b, #d97706);
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
            text-align: center;
        }
        .streak-badge {
            display: inline-block;
            background: #fef3c7;
            color: #b45309;
            font-size: 28px;
            font-weight: 800;
            padding: 12px 24px;
            border-radius: 50px;
            margin: 16px 0;
            border: 2px solid #fde68a;
        }
        .btn {
            display: inline-block;
            background: #f59e0b;
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
            <h1>Don't Break Your Streak! 🔥</h1>
        </div>
        <div class="content">
            <p>Hi <strong>{{ $user->first_name ?: $user->name }}</strong>,</p>
            <p>You've built up great momentum with your learning streak on Lumani:</p>
            <div class="streak-badge">
                🔥 {{ $user->day_streak }} {{ \Illuminate\Support\Str::plural('Day', $user->day_streak) }}
            </div>
            <p>Don't let your hard work reset to zero! Complete today's daily check-in or take a quick chapter quiz before midnight to keep your streak alive.</p>
            <p>
                <a href="{{ config('app.url') }}" class="btn">Practice Now</a>
            </p>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} Lumani Education. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
