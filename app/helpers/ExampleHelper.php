<?php
declare(strict_types=1);

namespace app\helpers;

/**
 * Example app-level helper: every *Helper.php in app/helpers/ is registered
 * automatically as $this->helper-><name> (ExampleHelper -> $this->helper->example).
 * The constructor receives the container - use it for settings, other
 * services, etc. (helpers that don't need it can omit the constructor).
 */
class ExampleHelper {

    private string $timezone;

    public function __construct(\Psr\Container\ContainerInterface $container) {
        $this->timezone = $container->get('settings')['system']['timezone'] ?? 'UTC';
    }

    /**
     * Greeting by the time of day ("Jó reggelt, Tibi!").
     */
    public function greeting(string $name): string {
        $hour = (int) (new \DateTime('now', new \DateTimeZone($this->timezone)))->format('G');

        $greeting = match (true) {
            $hour < 9  => 'Jó reggelt',
            $hour < 18 => 'Jó napot',
            default    => 'Jó estét',
        };

        return $greeting.', '.$name.'!';
    }

}
?>
