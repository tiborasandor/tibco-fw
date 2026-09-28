<?php
declare(strict_types=1);

namespace app;

/**
 * A projekt saját Twig függvényei és szűrői. A keretrendszer automatikusan
 * regisztrálja ezt az osztályt, ha létezik (system/container/sets/view.php),
 * így a projekt kódja nem a system/TwigHelperFunctions.php-ba kerül.
 * A konstruktor megkapja a konténert.
 */
class TwigExtension extends \Twig\Extension\AbstractExtension {

    public function __construct(private \Psr\Container\ContainerInterface $container) {}

    public function getFunctions(): array {
        return [
            // is_safe: a függvény HTML-t ad vissza, a Twig ne escape-elje
            new \Twig\TwigFunction('badge', [$this, 'badge'], ['is_safe' => ['html']]),
        ];
    }

    public function getFilters(): array {
        return [
            new \Twig\TwigFilter('huf', [$this, 'huf']),
        ];
    }

    /**
     * {{ badge('új', 'success') }} -> Bootstrap badge (címke)
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
