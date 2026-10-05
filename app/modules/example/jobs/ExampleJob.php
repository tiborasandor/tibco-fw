<?php
declare(strict_types=1);

namespace app\modules\example\jobs;

/**
 * Példa ütemezett feladat: egy sort ír a naplóba. Az időzítése az example modul
 * schedule.php-jában van. Kézi futtatás: php bin/cron run ExampleJob
 */
final class ExampleJob extends Job {

    public function run(): void {
        $name = $this->repository('MainRepository')->getName();
        $this->log->info("ExampleJob lefutott ($name)");
    }

}
?>
