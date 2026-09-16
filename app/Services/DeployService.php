<?php

namespace App\Services;

use App\Models\DeploymentLog;
use App\Models\Site;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\Process\Process;

class DeployService
{
    /**
     * Run a deployment for a single site (synchronously).
     *
     * - First deploy (when `site->first_deployed` is false) -> `deploy.sh`
     * - Any later deploy                                   -> `up.sh`
     *
     * Returns the DeploymentLog row.
     */
    public function deploySite(Site $site, ?string $branch = null): DeploymentLog
    {
        $isFirst = ! $site->first_deployed;
        $script  = $isFirst
            ? (string) config('deploy.first_script')
            : (string) config('deploy.update_script');
        $branch  = $branch ?: $site->branch ?: (string) config('deploy.branch', 'main');

        $versionBefore = $this->gitHead($site->path);

        $site->update([
            'state'  => Site::STATE_RUNNING,
            'branch' => $branch,
        ]);

        $kind = $isFirst ? DeploymentLog::KIND_FIRST : DeploymentLog::KIND_UPDATE;

        $log = DeploymentLog::create([
            'site_id'        => $site->id,
            'user_id'        => Auth::id(),
            'kind'           => $kind,
            'status'         => DeploymentLog::STATUS_RUNNING,
            'version_before' => $versionBefore,
            'command'        => $script . ' ' . $site->path . ' ' . $branch,
            'started_at'     => now(),
        ]);

        $cmd     = [$script, $site->path, $branch];
        $process = new Process($cmd);
        $process->setTimeout(null);

        try {
            $process->run();
            $exit   = $process->getExitCode() ?? 0;
            $output = $process->getOutput() . $process->getErrorOutput();

            $versionAfter = $this->gitHead($site->path) ?: $versionBefore;
            $status       = $exit === 0 ? DeploymentLog::STATUS_SUCCESS : DeploymentLog::STATUS_ERROR;

            $log->update([
                'status'        => $status,
                'exit_code'     => $exit,
                'output'        => $output,
                'version_after' => $versionAfter,
                'finished_at'   => now(),
            ]);

            $site->update([
                'state'            => $status === DeploymentLog::STATUS_SUCCESS ? Site::STATE_OK : Site::STATE_ERROR,
                'current_version'  => $versionAfter,
                'last_deployed_at' => now(),
            ]);

            // A successful first deploy means the site is no longer "new".
            if ($isFirst && $status === DeploymentLog::STATUS_SUCCESS) {
                $site->update(['first_deployed' => true]);
            }
        } catch (\Throwable $e) {
            $log->update([
                'status'      => DeploymentLog::STATUS_ERROR,
                'exit_code'   => -1,
                'output'      => ($process->getOutput() ?? '') . "\n" . $e->getMessage(),
                'finished_at' => now(),
            ]);
            $site->update(['state' => Site::STATE_ERROR]);
        }

        return $log->fresh();
    }

    /**
     * Deploy every site (sequentially, synchronously).
     */
    public function deployAll(): array
    {
        $logs = [];
        foreach (Site::orderBy('id')->get() as $site) {
            $logs[] = $this->deploySite($site);
        }
        return $logs;
    }

    /**
     * Read current git HEAD sha for a path. Returns null on failure.
     */
    public function gitHead(string $path): ?string
    {
        if (! is_dir($path . '/.git') && ! is_file($path . '/.git')) {
            return null;
        }
        $process = new Process(['git', 'rev-parse', '--short', 'HEAD']);
        $process->setWorkingDirectory($path);
        $process->setTimeout(10);
        try {
            $process->run();
            if ($process->isSuccessful()) {
                return trim($process->getOutput());
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return null;
    }
}
