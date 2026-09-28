<?php
declare(strict_types=1);

namespace system\validatorcustomrules;

use \Respect\Validation\Message\Template;
use \Respect\Validation\Validators\Core\Simple;

#[Template(
    '{{subject}} must be a valid Hungarian tax number',
    '{{subject}} must not be a valid Hungarian tax number',
)]
final class HungarianTaxNumber extends Simple {
    public function isValid(mixed $input): bool {
        // format: 12345678-1-23
        if (!is_string($input) || !preg_match('/^\d{8}-\d{1}-\d{2}$/i', $input)) {
            return false;
        }

        // the 8th digit is a check digit over the first seven
        $taxNumber = current(explode('-', $input));
        $checkDigit = $taxNumber % 10;

        $weight = [9, 7, 3, 1, 9, 7, 3];
        $checksum = 0;

        for ($i = 0; $i < 7; $i++) {
            $checksum += (int)$taxNumber[$i] * $weight[$i];
        }

        $remainder = $checksum % 10;
        $expectedCheckDigit = (10 - $remainder) % 10;

        return (int)$checkDigit === $expectedCheckDigit;
    }
}
?>