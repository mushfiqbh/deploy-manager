@extends('layouts.app')
@section('title', 'Dashboard · Deploy Manager')

@section('content')
    <div class="stats">
        <div class="stat"><div class="v">{{ $sites->count() }}</div><div class="l">Total Sites</div></div>
        <div class="stat"><div class="v" style="color:var(--ok)">{{ $ok }}</div><div class="l">Healthy</div></div>
        <div class="stat"><div class="v" style="color:var(--warn)">{{ $running }}</div><div class="l">Running</div></div>
        <div class="stat"><div class="v" style="color:var(--err)">{{ $error }}</div><div class="l">Errors</div></div>
    </div>

    <section class="panel">
        <h2>Add Site</h2>
        <form method="POST" action="{{ route('sites.store') }}" id="add-site-form">
            @csrf
            <div class="grid-3">
                <div>
                    <label>Domain</label>
                    <input name="domain" id="add-site-domain" placeholder="example.com" value="{{ old('domain') }}" required>
                    <div class="muted">Scheme (https://) is stripped automatically.</div>
                </div>
                <div>
                    <label>Site Type</label>
                    <select name="mode" id="add-site-mode">
                        <option value="new" {{ old('mode', 'new') === 'new' ? 'selected' : '' }}>
                            New Site (first deploy runs deploy.sh)
                        </option>
                        <option value="update" {{ old('mode') === 'update' ? 'selected' : '' }}>
                            Existing Site (first deploy runs up.sh)
                        </option>
                    </select>
                    <div class="muted">
                        Path auto-set to <code>{{ rtrim((string) config('deploy.sites_dir'), '/') }}/&lt;domain&gt;</code>
                        &nbsp;·&nbsp; Branch: <code>{{ config('deploy.branch', 'main') }}</code>
                    </div>
                </div>
                <div>
                    <label>&nbsp;</label>
                    <button type="submit">+ Add Site</button>
                </div>
            </div>
        </form>
        @error('domain') <div class="flash error">{{ $message }}</div> @enderror
        @error('mode')   <div class="flash error">{{ $message }}</div> @enderror
    </section>

    <section class="panel">
        <h2>Sites</h2>
        @if ($sites->isEmpty())
            <div class="empty">No sites yet — add one above.</div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Domain</th>
                        <th>Type</th>
                        <th>Version</th>
                        <th>State</th>
                        <th>Last Deploy</th>
                        <th style="text-align:right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($sites as $site)
                    <tr>
                        <td>
                            <a href="{{ route('sites.show', $site) }}" style="color:var(--accent); text-decoration:none;">
                                {{ $site->domain }}
                            </a>
                            <div class="muted" style="font-size:11px;">{{ $site->path }}</div>
                        </td>
                        <td>
                            @if (! $site->first_deployed)
                                <span class="pill">first (deploy.sh)</span>
                            @else
                                <span class="pill">update (up.sh)</span>
                            @endif
                        </td>
                        <td><span class="pill">{{ $site->current_version ?? '—' }}</span></td>
                        <td><span class="state {{ $site->state }}">{{ ucfirst($site->state) }}</span></td>
                        <td class="muted">{{ optional($site->last_deployed_at)->diffForHumans() ?? 'never' }}</td>
                        <td class="row-actions">
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
                            <form method="POST" action="{{ route('sites.destroy', $site) }}" onsubmit="return confirm('Delete site {{ $site->domain }}?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif

        @if ($sites->isNotEmpty())
            <div style="margin-top:14px; display:flex; gap:10px;">
                <form method="POST" action="{{ route('deploy.all') }}"
                      onsubmit="return confirm('Deploy ALL sites?\n\nFirst, {{ config('deploy.demo_domain') }} will run synchronously.\nThen you will be asked to confirm the rest.');">
                    @csrf
                    <button type="submit">🚀 Deploy All Sites</button>
                </form>
            </div>
        @endif
    </section>

    <section class="panel">
        <h2>Recent Deployments</h2>
        @if ($recent->isEmpty())
            <div class="empty">No deployments yet.</div>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Site</th>
                        <th>Kind</th>
                        <th>Status</th>
                        <th>Version Before → After</th>
                        <th>When</th>
                        <th>By</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($recent as $log)
                    <tr>
                        <td><a href="{{ route('sites.show', $log->site) }}" style="color:var(--accent);">{{ $log->site?->domain ?? '—' }}</a></td>
                        <td>
                            <span class="pill">
                                {{ $log->kind === 'first' ? 'deploy.sh' : 'up.sh' }}
                            </span>
                        </td>
                        <td><span class="state {{ $log->status === 'success' ? 'ok' : ($log->status === 'error' ? 'error' : 'running') }}">{{ ucfirst($log->status) }}</span></td>
                        <td><span class="pill">{{ $log->version_before ?? '—' }}</span> → <span class="pill">{{ $log->version_after ?? '—' }}</span></td>
                        <td class="muted">{{ $log->created_at->diffForHumans() }}</td>
                        <td class="muted">{{ $log->user?->name ?? 'system' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </section>
@endsection
