@extends('layouts.app')
@section('title', $site->domain.' · Deploy Manager')

@section('content')
    <section class="panel">
        <h2>{{ $site->domain }}</h2>
        <div class="muted">
            Path: <span class="pill">{{ $site->path }}</span>
            &nbsp;·&nbsp; Branch: <span class="pill">{{ $site->branch }}</span>
            &nbsp;·&nbsp; Version: <span class="pill">{{ $site->current_version ?? '—' }}</span>
            &nbsp;·&nbsp; State: <span class="state {{ $site->state }}">{{ ucfirst($site->state) }}</span>
            &nbsp;·&nbsp; Type:
            @if (! $site->first_deployed)
                <span class="pill">first (deploy.sh)</span>
            @else
                <span class="pill">update (up.sh)</span>
            @endif
        </div>

        <div style="margin-top:14px;" class="row-actions">
            <form method="POST" action="{{ route('deploy.one', $site) }}">
                @csrf
                <button type="submit" {{ $site->state === 'running' ? 'disabled' : '' }}>
                    @if (! $site->first_deployed)
                        🚀 First Deploy
                    @else
                        🔁 Deploy Now
                    @endif
                </button>
            </form>
            <a class="btn ghost" href="{{ route('dashboard') }}">← Back</a>
        </div>
    </section>

    <section class="panel">
        <h2>Deployment History</h2>
        @if ($logs->isEmpty())
            <div class="empty">No deployments yet for this site.</div>
        @else
            @foreach ($logs as $log)
                <div style="border-top:1px solid var(--panel-2); padding:12px 0;">
                    <div>
                        <span class="state {{ $log->status === 'success' ? 'ok' : ($log->status === 'error' ? 'error' : 'running') }}">{{ ucfirst($log->status) }}</span>
                        &nbsp; <span class="muted">{{ $log->created_at->toDayDateTimeString() }}</span>
                        &nbsp; · <span class="muted">by {{ $log->user?->name ?? 'system' }}</span>
                        @if ($log->exit_code !== null)
                            &nbsp; · <span class="muted">exit {{ $log->exit_code }}</span>
                        @endif
                    </div>
                    <div class="muted" style="margin:4px 0;">
                        <span class="pill">{{ $log->version_before ?? '—' }}</span> →
                        <span class="pill">{{ $log->version_after ?? '—' }}</span>
                        @if ($log->command)
                            &nbsp;·&nbsp; <code>{{ $log->command }}</code>
                        @endif
                    </div>
                    @if ($log->output)
                        <pre class="log">{{ $log->output }}</pre>
                    @endif
                </div>
            @endforeach

            <div style="margin-top:12px;">{{ $logs->links() }}</div>
        @endif
    </section>
@endsection
