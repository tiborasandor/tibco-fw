<?php
declare(strict_types=1);

namespace app\helpers;

/**
 * Példa app-szintű helper: az app/helpers/ mappa minden *Helper.php fájlja
 * automatikusan regisztrálódik $this->helper-><név> néven
 * (ExampleHelper -> $this->helper->example). A konstruktor megkapja a
 * konténert - ezen keresztül érhetők el a beállítások, más szolgáltatások
 * stb. (amelyik helpernek nem kell, annak konstruktor sem kell).
 */
class ExampleHelper {

    private string $timezone;

    public function __construct(\Psr\Container\ContainerInterface $container) {
        $this->timezone = $container->get('settings')['system']['timezone'] ?? 'UTC';
    }

    /**
     * Napszaknak megfelelő köszöntés ("Jó reggelt, Tibi!").
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
