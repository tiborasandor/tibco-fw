<?php
declare(strict_types=1);

namespace system\scheduler;

/** Runs the jobs of the modules' schedule.php files; bin/cron calls it every minute. */
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

    /** Module whose schedule.php is being loaded; short job names resolve in it. */
    public function setModule(string $module): void {
        $this->module = $module;
    }

    /** 'ExampleJob' (own module) or '@othermodule\ExampleJob' */
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

    /** Finds a job by id ("module/JobName") or by an unambiguous class name. */
    public function find(string $name): ?ScheduledJob {
        if (isset($this->jobs[$name])) {
            return $this->jobs[$name];
        }
        $matches = array_filter($this->jobs, fn ($job) => str_ends_with($job->id, "/$name"));
        return count($matches) === 1 ? reset($matches) : null;
    }

    /** @return array<string,array{status:string,duration_ms:?int,error:?string}> Jobs due in the minute of $now, by job id */
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

    /** @return array{status:string,duration_ms:?int,error:?string} Scheduled run with $minute, manual run without */
    public function run(ScheduledJob $job, ?\DateTimeImmutable $minute = null): array {
        // already run in this minute
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

            // claim the run under the state file lock
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

    /** @return array<string,array{scheduled_minute:?string,last_start:string,duration_ms:?int,status:string,error:?string}> */
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

    /** Locked read-modify-write of one job's state; null from $update writes nothing. */
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

    /** @return resource|null Non-blocking per-job lock, null if held by another run */
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
