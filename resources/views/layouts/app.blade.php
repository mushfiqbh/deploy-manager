<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Deploy Manager')</title>
    <style>
        :root {
            --bg: #0f172a; --panel: #1e293b; --panel-2: #334155;
            --text: #e2e8f0; --muted: #94a3b8; --accent: #38bdf8;
            --ok: #22c55e; --warn: #f59e0b; --err: #ef4444; --idle: #64748b;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: var(--bg); color: var(--text); min-height: 100vh;
        }
        header.top {
            padding: 16px 24px;
            background: linear-gradient(90deg, #0ea5e9, #6366f1);
            display: flex; align-items: center; justify-content: space-between;
            flex-wrap: wrap; gap: 12px;
        }
        header.top h1 { margin: 0; font-size: 20px; letter-spacing: 0.5px; }
        header.top nav a {
            color: white; text-decoration: none; margin-right: 16px;
            font-size: 14px; font-weight: 500;
        }
        header.top nav a:hover { text-decoration: underline; }
        .user-chip {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(0,0,0,0.25); padding: 6px 10px; border-radius: 999px; font-size: 13px;
        }
        main { padding: 24px; max-width: 1100px; margin: 0 auto; }
        .panel {
            background: var(--panel); border-radius: 12px; padding: 18px 20px;
            margin-bottom: 18px; box-shadow: 0 4px 16px rgba(0,0,0,0.25);
        }
        .panel h2 {
            margin: 0 0 12px; font-size: 16px; font-weight: 600;
            text-transform: uppercase; letter-spacing: 1px; color: var(--muted);
        }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .grid-3 { display: grid; grid-template-columns: 1.4fr 2fr auto; gap: 10px; }
        @media (max-width: 720px) {
            .grid-2, .grid-3 { grid-template-columns: 1fr; }
        }
        label { display: block; font-size: 12px; color: var(--muted); margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
        input, select, textarea {
            width: 100%; background: #0f172a; color: var(--text);
            border: 1px solid var(--panel-2); border-radius: 8px;
            padding: 10px 12px; font-size: 14px;
        }
        input:focus, select:focus, textarea:focus { outline: none; border-color: var(--accent); }
        button, .btn {
            background: var(--accent); color: #0f172a; border: 0;
            padding: 10px 16px; font-weight: 600; cursor: pointer;
            border-radius: 8px; font-size: 14px; text-decoration: none;
            display: inline-block;
        }
        button:hover, .btn:hover { filter: brightness(1.1); }
        button:disabled { opacity: 0.5; cursor: not-allowed; }
        button.danger, .btn.danger { background: var(--err); color: white; }
        button.ghost, .btn.ghost { background: transparent; color: var(--muted); border: 1px solid var(--panel-2); }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 10px; text-align: left; font-size: 14px; border-bottom: 1px solid var(--panel-2); }
        th { color: var(--muted); font-weight: 500; text-transform: uppercase; font-size: 11px; letter-spacing: 1px; }
        tr:last-child td { border-bottom: 0; }
        td .path { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; color: #cbd5e1; font-size: 13px; }
        .state {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 4px 10px; border-radius: 999px;
            font-size: 12px; font-weight: 600; text-transform: capitalize;
        }
        .state.pending { background: rgba(100,116,139,0.25); color: var(--idle); }
        .state.running { background: rgba(245,158,11,0.2); color: var(--warn); }
        .state.ok      { background: rgba(34,197,94,0.18); color: var(--ok); }
        .state.error   { background: rgba(239,68,68,0.2); color: var(--err); }
        .stat {
            background: var(--panel); border-radius: 12px; padding: 16px 20px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.25);
        }
        .stat .v { font-size: 28px; font-weight: 700; }
        .stat .l { font-size: 12px; color: var(--muted); text-transform: uppercase; letter-spacing: 1px; }
        .stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 18px; }
        @media (max-width: 720px) { .stats { grid-template-columns: repeat(2, 1fr); } }
        .flash {
            padding: 10px 14px; border-radius: 8px; margin-bottom: 14px;
            background: rgba(56,189,248,0.12); border-left: 4px solid var(--accent);
            font-size: 14px;
        }
        .flash.error { background: rgba(239,68,68,0.15); border-left-color: var(--err); }
        .actions form { display: inline-block; margin: 0; }
        .muted { color: var(--muted); font-size: 13px; }
        pre.log {
            background: #0b1220; color: #cbd5e1; padding: 12px 14px;
            border-radius: 8px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 12px; line-height: 1.55; max-height: 480px; overflow: auto;
            white-space: pre-wrap; word-break: break-word;
        }
        .empty { color: var(--muted); padding: 16px 0; font-size: 14px; text-align: center; }
        .pill {
            display: inline-block; padding: 2px 8px; border-radius: 999px;
            background: rgba(255,255,255,0.08); font-size: 11px;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        }
        .row-actions { display: flex; gap: 6px; justify-content: flex-end; flex-wrap: wrap; }
    </style>
</head>
<body>
    <header class="top">
        <h1>🚀 Deploy Manager</h1>
        <nav>
            <a href="{{ route('dashboard') }}">Dashboard</a>
            <a href="{{ route('sites.index') }}">Sites</a>
        </nav>
        @auth
            <form method="POST" action="{{ route('logout') }}" style="display:inline;">
                @csrf
                <span class="user-chip">{{ auth()->user()->name }}</span>
                <button class="ghost" type="submit">Logout</button>
            </form>
        @endauth
    </header>

    <main>
        @if (session('status'))
            <div class="flash">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="flash error">{{ session('error') }}</div>
        @endif
        @yield('content')
    </main>
</body>
</html>
