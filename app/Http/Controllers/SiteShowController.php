<?php

namespace App\Http\Controllers;

use App\Models\Site;
use Illuminate\View\View;

class SiteShowController extends Controller
{
    public function index(): View
    {
        $sites = Site::orderBy('domain')->get();
        return view('sites.index', compact('sites'));
    }

    public function show(Site $site): View
    {
        $logs = $site->logs()->with('user')->paginate(20);
        return view('sites.show', compact('site', 'logs'));
    }
}
