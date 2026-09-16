<?php

return [
    'first_script' => env('DEPLOY_FIRST_SCRIPT', '/usr/local/bin/deploy.sh'),
    'update_script' => env('DEPLOY_UPDATE_SCRIPT', '/usr/local/bin/up.sh'),
    'script' => env('DEPLOY_SCRIPT', '/usr/local/bin/up.sh'),

    'branch' => env('DEPLOY_BRANCH', 'main'),
    'base'   => env('DEPLOY_BASE_DIR', base_path('deploy')),

    'sites_dir' => env('DEPLOY_SITES_DIR', '/www/wwwroot'),

    'demo_domain' => env('DEPLOY_DEMO_DOMAIN', 'demo.barnomala.com'),

    'queue_interval' => (int) env('DEPLOY_QUEUE_INTERVAL', 30),
];
