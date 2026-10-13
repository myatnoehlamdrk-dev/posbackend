<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Myanmar NRC format check: 12/KHA(N)191445.
 *
 * Structure accepted (after trim + uppercasing):
 *
 *     state code 1-15 + "/"  township letters  (N|E|P|T|Y|S)  6 digits
 *
 * The shape follows the reference dataset shipped with the `mm_nrc_kit`
 * Flutter package -- the same picker the register screen uses -- so the rule
 * and the UI can never disagree about what an NRC is:
 *
 *   - citizenship letters are the six the NRC office issues (type.json):
 *     N citizen, E visitor, P organisation, T religious, Y temporary, S "S"
 *   - township short codes run up to 9 letters (NGATHAKHA), hence 1-10 here
 *   - the serial is exactly 6 digits, per the card
 *   - the state code is always present: the picker composes it, and an NRC
 *     without one is not a card anyone holds
 *
 * The pattern rejects -- among others -- "16/..." (no state 16), "12/KHA(N)123"
 * (serial too short), "12/KHA(F)123456" (F is not an issued letter) and
 * "KHATANA(N) 191445" (no state code, stray space).
 */
class MyanmarNrc implements ValidationRule
{
    private const PATTERN = '/^(?:0?[1-9]|1[0-5])\/[A-Z]{1,10}\((?:N|E|P|T|Y|S)\)\d{6}$/';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::isValid($value)) {
            $fail('The :attribute must be a valid Myanmar NRC, e.g. 12/KHA(N)191445.');
        }
    }

    /**
     * Whether the value matches the format.
     *
     * Case-insensitive: "12/kha(n)191445" is the same card as
     * "12/KHA(N)191445", and rejecting it would only force callers to guess
     * which letters the rule felt like seeing today.
     */
    public static function isValid(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        return preg_match(self::PATTERN, self::normalize($value)) === 1;
    }

    /**
     * The canonical form to persist: trimmed, uppercased.
     *
     * Returns null for null so the nullable column stays nullable rather than
     * being stored as the string "null" or an empty value.
     */
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return strtoupper(trim($value));
    }
}
