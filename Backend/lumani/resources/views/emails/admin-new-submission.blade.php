<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Question Submission Awaiting Review</title>
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
            background: linear-gradient(135deg, #0f172a, #334155);
            color: #ffffff;
            padding: 32px 24px;
            text-align: center;
        }
        .header h1 {
            margin: 0 0 8px;
            font-size: 22px;
            font-weight: 700;
        }
        .content {
            padding: 32px 24px;
            line-height: 1.6;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: #f8fafc;
            border-radius: 8px;
            overflow: hidden;
        }
        .details-table td {
            padding: 12px 16px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
        }
        .details-table td.label {
            font-weight: 600;
            color: #64748b;
            width: 35%;
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
            <h1>New Question Submission 📋</h1>
            <p>Admin Content-Review Queue</p>
        </div>
        <div class="content">
            <p>Hello Administrator,</p>
            <p>A new question has been submitted and is currently awaiting review in the content-review workflow.</p>

            <table class="details-table">
                <tr>
                    <td class="label">Submission ID</td>
                    <td>#{{ $submittedQuestion->id }}</td>
                </tr>
                <tr>
                    <td class="label">Chapter</td>
                    <td>{{ $submittedQuestion->chapter->title ?? ('Chapter #' . $submittedQuestion->chapter_id) }}</td>
                </tr>
                <tr>
                    <td class="label">Subject</td>
                    <td>{{ $submittedQuestion->chapter->subject->name ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="label">Question Text</td>
                    <td>{{ \Illuminate\Support\Str::limit($submittedQuestion->question_text, 100) }}</td>
                </tr>
                <tr>
                    <td class="label">Submitted By</td>
                    <td>{{ $submittedQuestion->submitter->email ?? 'System / Anonymous' }}</td>
                </tr>
                <tr>
                    <td class="label">Status</td>
                    <td><strong>{{ ucfirst($submittedQuestion->review_status->value ?? $submittedQuestion->review_status) }}</strong></td>
                </tr>
            </table>

            <p style="text-align: center;">
                <a href="{{ url('/admin/submitted-questions/' . $submittedQuestion->id . '/edit') }}" class="btn">
                    Review Submission in Admin Panel
                </a>
            </p>
        </div>
        <div class="footer">
            <p>&copy; {{ date('Y') }} Lumani Education Admin System</p>
        </div>
    </div>
</body>
</html>
