<?php
declare(strict_types=1);

namespace system\scheduler;

/**
 * Job scheduler. The modules register their jobs in their schedule.php
 * ($schedule->job('ExampleJob')->dailyAt('03:00')), bin/cron is called every
 * minute by the container's cron and runs the jobs that are due.
 *
 * - The jobs of one call run one after the other; a job's exception is logged
 *   and recorded, the remaining jobs still run.
 * - A job runs at most once per scheduled minute, even if bin/cron is called
 *   twice in the same minute.
 * - Missed minutes (e.g. while the container was down) are not caught up.
 * - The last run of every job (start, duration, status, error) is kept in a
 *   JSON state file (system.cron.state_file), `php bin/cron list` shows it.
 */
class Scheduler {

    /** @var array<string,ScheduledJob> */
    private array $jobs = [];
    private string $module = '';
    private string $stateFile;
    private string $lockDir;

    public function __construct(private \Psr\Container\ContainerInterface $container) {
        $settings = $container->get('settings')['system']['cron'] ?? [];
        $this->stateFile = $settings['state_file'] ?? LOG_DIR.DS.'cron'.DS.'state.json';
        $this->lockDir = dirname($this->stateFile).DS.'locks';
    }

    public function isEnabled(): bool {
        return (bool) ($this->container->get('settings')['system']['cron']['enabled'] ?? true);
    }

    /**
     * The module whose schedule.php is being loaded (set by init.php), short
     * job names are resolved in it.
     */
    public function setModule(string $module): void {
        $this->module = $module;
    }

    /**
     * 'ExampleJob' - a job of the module's own jobs/ folder,
     * '@othermodule\ExampleJob' - a job of another, enabled module.
     */
    public function job(string $name): ScheduledJob {
        $parts = explode('\\', ltrim($name, '@'));
        if (count($parts) === 1 && !str_starts_with($name, '@')) {
            [$module, $class] = [$this->module, $parts[0]];
        } elseif (count($parts) === 2 && str_starts_with($name, '@')) {
            [$module, $class] = $parts;
        } else {
            throw new \Error("Invalid job name: $name (expected 'JobName' or '@module\\JobName')");
        }

        $key = "@$module\\jobs\\$class";
        if (!$this->container->has($key)) {
            $expectedFile = MODULES_DIR.DS.$module.DS.'jobs'.DS.$class.'.php';
            throw new \Error("Undefined job: $name (no matching jobs class found for the '$module' module - expected file: $expectedFile)");
        }

        $id = "$module/$class";
        if (isset($this->jobs[$id])) {
            throw new \Error("Job is already scheduled: $id");
        }

        return $this->jobs[$id] = new ScheduledJob($id, $key);
    }

    /** @return array<string,ScheduledJob> */
    public function jobs(): array {
        return $this->jobs;
    }

    /**
     * Finds a job by its id ("module/JobName") or, if it is unambiguous,
     * by its class name alone ("JobName").
     */
    public function find(string $name): ?ScheduledJob {
        if (isset($this->jobs[$name])) {
            return $this->jobs[$name];
        }
        $matches = array_filter($this->jobs, fn ($job) => str_ends_with($job->id, "/$name"));
        return count($matches) === 1 ? reset($matches) : null;
    }

    /**
     * Runs the jobs due in the minute of $now. $onResult (if given) is called
     * right after each job with the job id and the result.
     *
     * @return array<string,array{status:string,duration_ms:?int,error:?string}> keyed by job id
     */
    public function runDue(\DateTimeImmutable $now, ?callable $onResult = null): array {
        $minute = $now->setTime((int) $now->format('H'), (int) $now->format('i'));
        $results = [];

        foreach ($this->jobs as $id => $job) {
            if ($job->isDue($minute)) {
                $results[$id] = $this->run($job, $minute);
                if ($onResult !== null) {
                    $onResult($id, $results[$id]);
                }
            }
        }

        return $results;
    }

    /**
     * Runs one job. With $minute it is a scheduled run (skipped if this minute
     * was already run), without it a manual run (`php bin/cron run`).
     *
     * @return array{status:string,duration_ms:?int,error:?string} status: ok, error, skipped
     */
    public function run(ScheduledJob $job, ?\DateTimeImmutable $minute = null): array {
        // a second call in the same minute: nothing to do (checked again under lock below)
        if ($minute !== null && ($this->state()[$job->id]['scheduled_minute'] ?? null) === $minute->format('Y-m-d H:i')) {
            return ['status' => 'skipped', 'duration_ms' => null, 'error' => 'already run in this minute'];
        }

        $lock = null;
        if ($job->preventsOverlapping()) {
            $lock = $this->acquireLock($job);
            if ($lock === null) {
                $this->log()->warning("cron: {$job->id} skipped, the previous run is still in progress");
                return ['status' => 'skipped', 'duration_ms' => null, 'error' => 'previous run still in progress'];
            }
        }

        try {
            $start = new \DateTimeImmutable();

            // claim the run under the state file lock, so two calls in the same minute can't both run it
            $claimed = $this->updateState($job->id, function (?array $entry) use ($minute, $start) {
                if ($minute !== null && ($entry['scheduled_minute'] ?? null) === $minute->format('Y-m-d H:i')) {
                    return null;
                }
                return [
                    'scheduled_minute' => $minute !== null ? $minute->format('Y-m-d H:i') : ($entry['scheduled_minute'] ?? null),
                    'last_start'       => $start->format('Y-m-d H:i:s'),
                    'duration_ms'      => null,
                    'status'           => 'running',
                    'error'            => null,
                ];
            });
            if (!$claimed) {
                return ['status' => 'skipped', 'duration_ms' => null, 'error' => 'already run in this minute'];
            }

            $timer = hrtime(true);
            $error = null;
            try {
                $this->container->get($job->container)->run();
            } catch (\Throwable $th) {
                $error = $th;
            }
            $duration = (int) round((hrtime(true) - $timer) / 1e6);

            $result = [
                'status'      => $error === null ? 'ok' : 'error',
                'duration_ms' => $duration,
                'error'       => $error?->getMessage(),
            ];
            $this->updateState($job->id, fn (?array $entry) => array_merge($entry ?? [], $result));

            if ($error === null) {
                $this->log()->debug("cron: {$job->id} ok ($duration ms)");
            } else {
                $this->log()->error("cron: {$job->id} failed: ".$error->getMessage(), [
                    'exception' => get_class($error),
                    'at'        => $error->getFile().':'.$error->getLine(),
                ]);
            }

            return $result;
        } finally {
            if ($lock !== null) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }

    /**
     * The recorded last runs, keyed by job id.
     *
     * @return array<string,array{scheduled_minute:?string,last_start:string,duration_ms:?int,status:string,error:?string}>
     */
    public function state(): array {
        if (!is_file($this->stateFile)) {
            return [];
        }
        $handle = fopen($this->stateFile, 'r');
        flock($handle, LOCK_SH);
        $content = stream_get_contents($handle);
        flock($handle, LOCK_UN);
        fclose($handle);

        return json_decode($content ?: '[]', true) ?: [];
    }

    /**
     * Read-modify-write of one job's state entry under an exclusive lock
     * (parallel bin/cron calls may run jobs at the same time). If $update
     * returns null, nothing is written and false is returned.
     */
    private function updateState(string $id, callable $update): bool {
        $this->ensureDir(dirname($this->stateFile));
        $handle = fopen($this->stateFile, 'c+');
        flock($handle, LOCK_EX);
        try {
            $state = json_decode(stream_get_contents($handle) ?: '[]', true) ?: [];
            $entry = $update($state[$id] ?? null);
            if ($entry === null) {
                return false;
            }
            $state[$id] = $entry;
            ksort($state);

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL);
            fflush($handle);
            return true;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * Non-blocking per-job lock. flock is released by the OS when the process
     * dies, so a crashed run never leaves a stale lock behind.
     *
     * @return resource|null null if another run holds it
     */
    private function acquireLock(ScheduledJob $job) {
        $this->ensureDir($this->lockDir);
        $handle = fopen($this->lockDir.DS.str_replace('/', '.', $job->id).'.lock', 'c');
        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return null;
        }
        return $handle;
    }

    private function ensureDir(string $dir): void {
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
    }

    private function log(): \Psr\Log\LoggerInterface {
        return $this->container->get('log');
    }
}
?>
