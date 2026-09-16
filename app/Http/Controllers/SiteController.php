<?php

namespace App\Http\Controllers;

use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SiteController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'domain' => ['required', 'string', 'max:255'],
            'mode'   => ['required', Rule::in([Site::MODE_NEW, Site::MODE_UPDATE])],
        ]);

        $domain = $this->normalizeDomain($data['domain']);

        // Auto-derive path & branch — the user no longer enters these.
        $path   = rtrim((string) config('deploy.sites_dir'), '/') . '/' . $domain;
        $branch = (string) config('deploy.branch', 'main');

        // Apply domain + path uniqueness after normalization.
        $this->validateAfterNormalization([
            'domain' => $domain,
            'path'   => $path,
        ]);

        Site::create([
            'domain'         => $domain,
            'path'           => $path,
            'branch'         => $branch,
            'mode'           => $data['mode'],
            // "update" mode means the site is already on disk, so no
            // first-deploy is ever needed.
            'first_deployed' => $data['mode'] === Site::MODE_UPDATE,
            'state'          => Site::STATE_PENDING,
        ]);

        return redirect()->route('dashboard')->with(
            'status',
            'Site ' . $domain . ' added (' . ($data['mode'] === Site::MODE_NEW ? 'new site' : 'existing site') . ').'
        );
    }

    private function normalizeDomain(string $domain): string
    {
        $domain = trim($domain);
        $domain = preg_replace('#^https?://#i', '', $domain) ?? $domain;
        $domain = rtrim($domain, '/');
        if (str_contains($domain, '/')) {
            $domain = strstr($domain, '/', true) ?: $domain;
        }

        return strtolower($domain);
    }

    private function validateAfterNormalization(array $data): void
    {
        validator($data, [
            'domain' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9.-]+$/', 'unique:sites,domain'],
            'path'   => ['required', 'string', 'max:255', 'unique:sites,path'],
        ])->validate();
    }

    public function destroy(Site $site): RedirectResponse
    {
        $domain = $site->domain;
        $site->delete();
        return redirect()->route('dashboard')->with('status', 'Site ' . $domain . ' removed.');
    }
}
