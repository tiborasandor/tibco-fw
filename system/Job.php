<?php
declare(strict_types=1);

namespace system;

/** Base class of scheduled jobs; the schedule is set in the module's schedule.php. */
abstract class Job extends Core {

    abstract public function run(): void;

}
?>
