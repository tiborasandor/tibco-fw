<?php
declare(strict_types=1);

namespace system;

final class TwigHelperFunctions {

    private $container;

    public function __construct($container) {
        $this->container = $container;
    }

    public function isActivePath(string $string, string $class = 'active'): string {
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

}
?>
