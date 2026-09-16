<?php

namespace App\Jobs;

use App\Models\Site;
use App\Services\DeployService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeploySiteJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Run the job even if the request that enqueued it goes away. */
    public bool $deleteWhenMissingModels = true;

    /** Per-job retry policy — failing deploys should not retry forever. */
    public int $tries = 1;

    public int $timeout = 0; // no timeout — long deploys

    public function __construct(public int $siteId)
    {
    }

    public function handle(DeployService $deploy): void
    {
        $site = Site::find($this->siteId);
        if (! $site) {
            return; // site was deleted while the job was queued
        }

        $deploy->deploySite($site);
    }
}
