<?php

namespace App\Tests\Support;

/**
 * Collapses SymfonyStyle's terminal-width-wrapped output into a single
 * whitespace-normalized line so substring assertions are not split across
 * a wrap on narrower CI terminals (e.g. "does not" + newline + "exist").
 */
trait NormalizesConsoleOutputTrait
{
    private static function normalizeWhitespace(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }
}
