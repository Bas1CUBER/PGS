<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 - Access denied</title>
    <style nonce="{{ request()->attributes->get('csp_nonce') }}">
        :root { --primary: #0b4aa2; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Geist', sans-serif;
            background: #f8fafc;
            color: #0f172a;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 40px;
            max-width: 420px;
            text-align: center;
        }
        h1 { font-size: 2.5rem; color: var(--primary); margin-bottom: 8px; }
        p { color: #64748b; margin-bottom: 20px; }
        a {
            display: inline-block;
            background: var(--primary);
            color: #fff;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
        }
        a:hover { opacity: .9; }
    </style>
</head>
<body>
    <div class="card">
        <h1>403</h1>
        <p>You don't have access to this page. Contact the administrator if you believe this is a mistake.</p>
        <a href="/dashboard">Back to Dashboard</a>
    </div>
</body>
</html>
