<?php

namespace App\Http\Controllers;

use App\Jobs\DeploySiteJob;
use App\Models\Site;
use App\Services\DeployService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;

class DeploymentController extends Controller
{
    public function __construct(private readonly DeployService $deploy) {}

    /**
     * Manual deploy of a single site.
     */
    public function deployOne(Request $request, Site $site): RedirectResponse
    {
        if ($site->state === Site::STATE_RUNNING) {
            return back()->with('error', 'Site ' . $site->domain . ' is already deploying.');
        }

        $this->deploy->deploySite($site);

        return redirect()->route('sites.show', $site)
            ->with('status', 'Deploy finished for ' . $site->domain . '.');
    }

    /**
     * Step 1 of "Deploy All":
     *
     *  - Synchronously run deploy for the configured demo domain.
     *  - Redirect to a confirmation page that lists the remaining sites.
     */
    public function deployAll(Request $request): RedirectResponse
    {
        if (Site::where('state', Site::STATE_RUNNING)->exists()) {
            return back()->with('error', 'A deployment is already running.');
        }

        $demoDomain = (string) config('deploy.demo_domain');
        $demoSite   = Site::where('domain', $demoDomain)->first();

        if ($demoSite) {
            if ($demoSite->state === Site::STATE_RUNNING) {
                return back()->with('error', 'Demo site is already deploying.');
            }
            $this->deploy->deploySite($demoSite);
        }

        return redirect()->route('deploy.confirm')->with(
            'demo_done',
            $demoSite
                ? 'Demo deploy finished for ' . $demoSite->domain . '.'
                : 'No demo site (' . $demoDomain . ') configured \u2014 nothing ran synchronously.'
        );
    }

    /**
     * Confirmation screen between the demo deploy and the rest.
     */
    public function deployConfirm(Request $request)
    {
        $demoDomain = (string) config('deploy.demo_domain');
        $remaining  = Site::orderBy('domain')
            ->where('domain', '!=', $demoDomain)
            ->get();

        return view('deploy.confirm', [
            'demoDomain' => $demoDomain,
            'remaining'  => $remaining,
        ]);
    }

    /**
     * Step 2 of "Deploy All":
     *
     * Dispatch one queued job per remaining site, spaced `queue_interval`
     * seconds apart, using a Bus::chain with incremental delays.
     */
    public function deployAllConfirm(Request $request): RedirectResponse
    {
        $demoDomain = (string) config('deploy.demo_domain');
        $interval   = max(0, (int) config('deploy.queue_interval', 30));

        $pending = Site::orderBy('id')
            ->where('domain', '!=', $demoDomain)
            ->get();

        if ($pending->isEmpty()) {
            return redirect()->route('dashboard')
                ->with('status', 'No remaining sites to queue.');
        }

        $chain = [];
        $delay = $interval;
        foreach ($pending as $site) {
            $chain[] = (new DeploySiteJob($site->id))->delay(now()->addSeconds($delay));
            $delay  += $interval;
        }

        Bus::chain($chain)->dispatch();

        return redirect()->route('dashboard')->with(
            'status',
            'Queued ' . $pending->count() . ' site(s) every ' . $interval . 's.'
        );
    }
}
