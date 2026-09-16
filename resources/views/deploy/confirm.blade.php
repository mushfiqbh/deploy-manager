@extends('layouts.app')
@section('title', 'Confirm Deploy All · Deploy Manager')

@section('content')
    @if (session('demo_done'))
        <div class="flash">{{ session('demo_done') }}</div>
    @endif

    <section class="panel">
        <h2>Confirm Deploy All</h2>

        <p class="muted">
            The demo site <strong>{{ $demoDomain }}</strong> has just been deployed
            synchronously. The remaining {{ $remaining->count() }} site(s) below
            will be queued and run one-by-one at
            <strong>{{ (int) config('deploy.queue_interval', 30) }}s</strong>
            intervals via Laravel jobs.
        </p>

        @if ($remaining->isEmpty())
            <div class="empty">No other sites to deploy.</div>
            <a class="btn ghost" href="{{ route('dashboard') }}">← Back to dashboard</a>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Domain</th>
                        <th>Kind</th>
                        <th>State</th>
                        <th>Path</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($remaining as $site)
                    <tr>
                        <td>{{ $site->domain }}</td>
                        <td>
                            @if (! $site->first_deployed)
                                <span class="pill">first (deploy.sh)</span>
                            @else
                                <span class="pill">update (up.sh)</span>
                            @endif
                        </td>
                        <td><span class="state {{ $site->state }}">{{ ucfirst($site->state) }}</span></td>
                        <td class="path">{{ $site->path }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            <form method="POST" action="{{ route('deploy.all.confirm') }}"
                  onsubmit="return confirm('Queue {{ $remaining->count() }} site(s) at {{ (int) config('deploy.queue_interval', 30) }}s intervals?');"
                  style="margin-top:14px;">
                @csrf
                <button type="submit">✅ Yes, queue all remaining sites</button>
                <a class="btn ghost" href="{{ route('dashboard') }}" style="margin-left:8px;">Cancel</a>
            </form>
        @endif
    </section>
@endsection
