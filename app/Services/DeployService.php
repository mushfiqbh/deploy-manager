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
     * Picks one of the two pipelines from `config/deploy.php`:
     *   - `commands`         — for existing sites (already deployed once)
     *   - `new_site_commands`— for first-time deploys of a new site
     *
     * Both pipelines are run inline via Process (no external script
     * dependency). The literal `{branch}` is substituted with the resolved
     * branch (site branch > `DEPLOY_BRANCH` > "main").
     *
     * Returns the DeploymentLog row.
     */
    public function deploySite(Site $site, ?string $branch = null): DeploymentLog
    {
        $branch = $branch ?: $site->branch ?: (string) config('deploy.branch', 'main');

        // Decide which pipeline runs *before* we flip any state.
        $isFirst = ! $site->first_deployed;

        $versionBefore = $this->gitHead($site->path);

        $site->update([
            'state'  => Site::STATE_RUNNING,
            'branch' => $branch,
        ]);

        $commands   = $this->commands($isFirst);
        $resolved   = array_map(
            fn (string $cmd) => str_replace('{branch}', $branch, $cmd),
            $commands
        );
        $scriptBody = implode(" && \\\n    ", $resolved);
        $prettyCmd  = "bash -c '\n    " . $scriptBody . "\n'";

        $log = DeploymentLog::create([
            'site_id'        => $site->id,
            'user_id'        => Auth::id(),
            'kind'           => $isFirst ? DeploymentLog::KIND_FIRST : DeploymentLog::KIND_UPDATE,
            'status'         => DeploymentLog::STATUS_RUNNING,
            'version_before' => $versionBefore,
            'command'        => $prettyCmd,
            'started_at'     => now(),
        ]);

        $process = new Process(['bash', '-c', $scriptBody]);
        $process->setWorkingDirectory($site->path);
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

            // A successful first inline run means the site is no longer "new".
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

    /**
     * Resolve the deploy pipeline for a site.
     *
     *  - $isFirst = false → `deploy.commands`         (existing-site update)
     *  - $isFirst = true  → `deploy.new_site_commands` (first-time deploy)
     *
     * Falls back to a sensible built-in default if a key is missing.
     */
    protected function commands(bool $isFirst): array
    {
        $key = $isFirst ? 'new_site_commands' : 'commands';

        $configured = config('deploy.' . $key);

        if (is_array($configured) && ! empty($configured)) {
            return array_values(array_map('strval', $configured));
        }

        // Fallback defaults if config was emptied out.
        return $isFirst
            ? self::fallbackNewSiteCommands()
            : self::fallbackCommands();
    }

    /**
     * Hard-coded fallback for the existing-site (update) pipeline.
     */
    protected static function fallbackCommands(): array
    {
        return [
            'git fetch origin',
            'git reset --hard origin/{branch}',
            'composer update',
            'php artisan migrate --force',
            'npm install',
            'npm run build',
            '/etc/init.d/php-fpm-85 restart',
            '/etc/init.d/nginx restart',
            'php artisan optimize:clear',
        ];
    }

    /**
     * Hard-coded fallback for the new-site (first deploy) pipeline.
     */
    protected static function fallbackNewSiteCommands(): array
    {
        return [
            'composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction',
            '[ -f .env ] || cp .env.example .env',
            'php artisan key:generate --force',
            'php artisan storage:link || true',
            'php artisan migrate --force',
            'php artisan db:seed --force',
            'npm install',
            'npm run build',
            'php artisan optimize',
            '/etc/init.d/php-fpm-85 restart',
            '/etc/init.d/nginx restart',
        ];
    }
}
