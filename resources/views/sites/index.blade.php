@extends('layouts.app')
@section('title', 'Sites · Deploy Manager')

@section('content')
    <section class="panel">
        <h2>Sites</h2>
        @php $sites = \App\Models\Site::orderBy('domain')->get(); @endphp
        @if ($sites->isEmpty())
            <div class="empty">No sites yet — go to <a href="{{ route('dashboard') }}" style="color:var(--accent)">Dashboard</a> to add one.</div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Domain</th>
                        <th>Type</th>
                        <th>State</th>
                        <th>Last Deploy</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($sites as $site)
                    <tr>
                        <td>
                            <a href="{{ route('sites.show', $site) }}" style="color:var(--accent);">{{ $site->domain }}</a>
                            <div class="muted" style="font-size:11px;">{{ $site->path }}</div>
                        </td>
                        <td>
                            @if (! $site->first_deployed)
                                <span class="pill">first (deploy.sh)</span>
                            @else
                                <span class="pill">update (up.sh)</span>
                            @endif
                        </td>
                        <td><span class="state {{ $site->state }}">{{ ucfirst($site->state) }}</span></td>
                        <td class="muted">{{ optional($site->last_deployed_at)->diffForHumans() ?? 'never' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </section>
@endsection
