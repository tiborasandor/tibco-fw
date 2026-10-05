<?php
declare(strict_types=1);

namespace system\scheduler;

use Cron\CronExpression;

/**
 * One entry of the schedule: which job and when (a cron expression).
 * Created by Scheduler::job(), configured with the fluent methods below
 * in a module's schedule.php.
 */
class ScheduledJob {

    private ?CronExpression $expression = null;
    private bool $withoutOverlapping = false;

    /**
     * @param string $id        Display/state name, e.g. "example/ExampleJob"
     * @param string $container Container key of the job, e.g. "@example\jobs\ExampleJob"
     */
    public function __construct(
        public readonly string $id,
        public readonly string $container
    ) {
    }

    /**
     * Any standard cron expression ("minute hour day-of-month month day-of-week"),
     * in the timezone of settings.php (system.timezone).
     */
    public function cron(string $expression): static {
        if (!CronExpression::isValidExpression($expression)) {
            throw new \InvalidArgumentException("Invalid cron expression for {$this->id}: $expression");
        }
        $this->expression = new CronExpression($expression);
        return $this;
    }

    public function everyMinute(): static {
        return $this->cron('* * * * *');
    }

    /** Every N minutes (N should divide 60, e.g. 5, 10, 15, 30). */
    public function everyMinutes(int $minutes): static {
        return $this->cron("*/$minutes * * * *");
    }

    public function hourly(): static {
        return $this->cron('0 * * * *');
    }

    public function hourlyAt(int $minute): static {
        return $this->cron("$minute * * * *");
    }

    public function daily(): static {
        return $this->cron('0 0 * * *');
    }

    /** @param string $time "HH:MM" */
    public function dailyAt(string $time): static {
        [$hour, $minute] = $this->parseTime($time);
        return $this->cron("$minute $hour * * *");
    }

    /** Mondays at 00:00. */
    public function weekly(): static {
        return $this->cron('0 0 * * 1');
    }

    /**
     * @param int    $dayOfWeek 1 = Monday ... 7 = Sunday (0 is Sunday too)
     * @param string $time      "HH:MM"
     */
    public function weeklyOn(int $dayOfWeek, string $time = '00:00'): static {
        [$hour, $minute] = $this->parseTime($time);
        return $this->cron("$minute $hour * * ".($dayOfWeek % 7));
    }

    /** On the 1st of every month at 00:00. */
    public function monthly(): static {
        return $this->cron('0 0 1 * *');
    }

    /**
     * Skip a run while the previous run of this job is still in progress
     * (otherwise a slow job may run in parallel with itself).
     */
    public function withoutOverlapping(): static {
        $this->withoutOverlapping = true;
        return $this;
    }

    public function preventsOverlapping(): bool {
        return $this->withoutOverlapping;
    }

    public function getExpression(): string {
        return $this->expression()->getExpression();
    }

    public function isDue(\DateTimeImmutable $at): bool {
        return $this->expression()->isDue($at);
    }

    public function nextRun(\DateTimeImmutable $after): \DateTimeImmutable {
        return \DateTimeImmutable::createFromMutable($this->expression()->getNextRunDate($after));
    }

    private function expression(): CronExpression {
        if ($this->expression === null) {
            throw new \LogicException("No schedule set for {$this->id} (e.g. ->dailyAt('03:00') or ->cron('...'))");
        }
        return $this->expression;
    }

    private function parseTime(string $time): array {
        if (!preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $time, $m)) {
            throw new \InvalidArgumentException("Invalid time for {$this->id}: $time (expected HH:MM)");
        }
        return [(int) $m[1], (int) $m[2]];
    }
}
?>
