<?php
declare(strict_types=1);

namespace system;

/**
 * Base class of the scheduled jobs (app/modules/<module>/jobs/). A job gets the
 * same container access as an action or a repository ($this->log, $this->db,
 * $this->repository(), $this->factory(), ...). When it runs is set in the
 * module's schedule.php, not in the job itself.
 *
 * An exception thrown from run() marks the run as failed (logged and shown by
 * `php bin/cron list`); it does not stop the other jobs.
 */
abstract class Job extends Core {

    abstract public function run(): void;

}
?>
