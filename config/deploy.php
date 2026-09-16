<?php

return [
    // First deploy for new sites — runs the provisioning script.
    'first_script' => env('DEPLOY_FIRST_SCRIPT', '/www/usr/local/bin/deploy.sh'),

    // Subsequent deploys for existing sites — runs the update script.
    'update_script' => env('DEPLOY_UPDATE_SCRIPT', '/www/usr/local/bin/up.sh'),

    // Legacy single-script fallback (kept for backwards compatibility).
    'script' => env('DEPLOY_SCRIPT', '/www/usr/local/bin/up.sh'),

    'branch' => env('DEPLOY_BRANCH', 'main'),
    'base'   => env('DEPLOY_BASE_DIR', base_path('deploy')),

    // Required argument: absolute path to the project on disk.
    'sites_dir' => env('DEPLOY_SITES_DIR', '/www/wwwroot'),

    // Site that will be deployed synchronously *first* when "Deploy All"
    // is run. The remaining sites are dispatched as queued jobs at
    // `queue_interval` seconds apart.
    'demo_domain' => env('DEPLOY_DEMO_DOMAIN', 'demo.barnomala.com'),

    // Seconds between each per-site job when running "Deploy All" in
    // queued (staged) mode.
    'queue_interval' => (int) env('DEPLOY_QUEUE_INTERVAL', 30),
];
