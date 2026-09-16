<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign in · Deploy Manager</title>
    <style>
        body { margin:0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
               background: #0f172a; color: #e2e8f0; min-height:100vh;
               display:flex; align-items:center; justify-content:center; }
        .card { background:#1e293b; padding:32px; border-radius:12px; box-shadow:0 8px 24px rgba(0,0,0,0.4);
                max-width:420px; width:90%; }
        h1 { margin:0 0 8px; font-size:22px; }
        p { color:#94a3b8; line-height:1.5; font-size:14px; }
        .err { background: rgba(239,68,68,0.15); border-left: 4px solid #ef4444; padding:10px 14px;
               border-radius:8px; font-size:14px; }
        a.btn { display:inline-block; margin-top:14px; background:#38bdf8; color:#0f172a;
                padding:10px 16px; border-radius:8px; text-decoration:none; font-weight:600; }
    </style>
</head>
<body>
    <div class="card">
        <h1>🚀 Deploy Manager</h1>
        @if (!empty($message))
            <p class="err">{{ $message }}</p>
        @else
            <p>This dashboard is restricted to admins. Please sign in with your tenant SSO portal to continue.</p>
        @endif
        @php
            $portal = env('CLIENT_SSO_PORTAL', 'https://cloud.barnomala.com/sign-in-with-barnomala/')
                . '?redirect_uri=' . urlencode(route('login'));
        @endphp
        <a class="btn" href="{{ $portal }}">Sign in with SSO →</a>
    </div>
</body>
</html>
