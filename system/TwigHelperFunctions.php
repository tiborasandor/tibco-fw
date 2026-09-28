<?php
declare(strict_types=1);

namespace system;

final class TwigHelperFunctions {

    private $container;

    public function __construct($container) {
        $this->container = $container;
    }

    public function isActivePath(string $string, string $class = 'active'): string {
        // no matched route (e.g. rendering the 404 error page)
        if (!$this->container->has('route') || $this->container->get('route') === null) {
            return '';
        }

        $actualRouteName = $this->container->get('route')->getName();
        $actualRouteArguments = $this->container->get('route')->getArguments();
        $actualRouteUrl = $this->container->get('routeparser')->urlFor($actualRouteName, $actualRouteArguments);
        $active = $actualRouteName == $string;

        if (!$active) {
            $pattern = '/^'.preg_quote($string, '/').'/';
            $active = (bool) preg_match($pattern, $actualRouteUrl);
        }

        return $active ? $class : '';
    }

    private const HUNGARIAN_MONTHS = [
        1 => 'január', 2 => 'február', 3 => 'március', 4 => 'április',
        5 => 'május', 6 => 'június', 7 => 'július', 8 => 'augusztus',
        9 => 'szeptember', 10 => 'október', 11 => 'november', 12 => 'december',
    ];

    /**
     * Hungarian long date (e.g. "2026. május 1.") - the built-in Twig `date`
     * filter is based on PHP date(), which has no localized month names
     * without the intl extension.
     */
    public function hungarianDate($date): string {
        if (empty($date)) {
            return '';
        }

        $dateTime = $date instanceof \DateTimeInterface ? $date : new \DateTime((string) $date);
        $month = self::HUNGARIAN_MONTHS[(int) $dateTime->format('n')];

        return $dateTime->format('Y').'. '.$month.' '.$dateTime->format('j').'.';
    }

    /**
     * Hungarian relative time (e.g. "3 órája", "tegnap") for dates within a
     * week; older dates fall back to hungarianDate().
     */
    public function relativeDate($date): string {
        if (empty($date)) {
            return '';
        }

        $dateTime = $date instanceof \DateTimeInterface ? $date : new \DateTime((string) $date);
        $diff = max(0, (new \DateTime())->getTimestamp() - $dateTime->getTimestamp());

        if ($diff < 60) {
            return 'most';
        }
        if ($diff < 3600) {
            return ((int) floor($diff / 60)).' perce';
        }
        if ($diff < 86400) {
            return ((int) floor($diff / 3600)).' órája';
        }
        if ($diff < 172800) {
            return 'tegnap';
        }
        if ($diff < 604800) {
            return ((int) floor($diff / 86400)).' napja';
        }

        return $this->hungarianDate($dateTime);
    }

}
?>
