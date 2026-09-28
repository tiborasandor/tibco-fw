<?php
declare(strict_types=1);

namespace app;

/**
 * Project-specific Twig functions and filters. The framework registers this
 * class automatically if it exists (system/container/sets/view.php), so
 * project code doesn't have to go into system/TwigHelperFunctions.php.
 * The constructor receives the container.
 */
class TwigExtension extends \Twig\Extension\AbstractExtension {

    public function __construct(private \Psr\Container\ContainerInterface $container) {}

    public function getFunctions(): array {
        return [
            // is_safe: the function returns HTML, Twig must not escape it
            new \Twig\TwigFunction('badge', [$this, 'badge'], ['is_safe' => ['html']]),
        ];
    }

    public function getFilters(): array {
        return [
            new \Twig\TwigFilter('huf', [$this, 'huf']),
        ];
    }

    /**
     * {{ badge('új', 'success') }} -> Bootstrap badge
     */
    public function badge(string $text, string $color = 'primary'): string {
        return '<span class="badge text-bg-'.htmlspecialchars($color, ENT_QUOTES).'">'.htmlspecialchars($text, ENT_QUOTES).'</span>';
    }

    /**
     * {{ 1234567|huf }} -> "1 234 567 Ft"
     */
    public function huf($amount): string {
        return number_format((float) $amount, 0, ',', "\u{00A0}")."\u{00A0}Ft";
    }

}
?>
