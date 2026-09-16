<?php

namespace App\Http\Controllers;

use App\Models\DeploymentLog;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $sites = Site::orderBy('domain')->get();
        $totalDeploys = DeploymentLog::count();
        $running = $sites->where('state', Site::STATE_RUNNING)->count();
        $ok      = $sites->where('state', Site::STATE_OK)->count();
        $error   = $sites->where('state', Site::STATE_ERROR)->count();

        $recent = DeploymentLog::with(['site', 'user'])
            ->latest('id')
            ->limit(8)
            ->get();

        return view('dashboard.index', compact('sites', 'totalDeploys', 'running', 'ok', 'error', 'recent'));
    }
}
