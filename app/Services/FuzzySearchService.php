<?php

namespace App\Services;

/**
 * Typo-tolerant search helpers.
 *
 * Scoring is case-insensitive and ranked: exact > starts-with > contains > fuzzy.
 * Fuzzy tolerance is "about 1 character off per 4": a candidate matches when its
 * Levenshtein distance to the query is <= max(1, floor(maxLen * 0.25)).
 */
class FuzzySearchService
{
    public static function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    /**
     * Score a single query against a single candidate.
     * Returns a similarity in [0, 1], where 0 means no match.
     * Also scores the query against each word of the candidate, so a typo
     * landing on any single word still matches (e.g. "spidor" -> "Spider Man").
     */
    public static function fuzzyScore(string $query, string $candidate): float
    {
        $q = self::normalize($query);
        $c = self::normalize($candidate);

        if ($q === '' || $c === '') {
            return 0.0;
        }

        $best = self::scorePair($q, $c);
        if ($best >= 0.95) {
            return $best;
        }

        $words = preg_split('/\s+/', $c, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($words as $word) {
            $score = self::scorePair($q, $word);
            if ($score > $best) {
                $best = $score;
            }
        }

        return $best;
    }

    private static function scorePair(string $q, string $c): float
    {
        if ($c === $q) {
            return 1.0;
        }
        if (str_starts_with($c, $q)) {
            return 0.98;
        }
        if (str_contains($c, $q)) {
            return 0.95;
        }

        $qLen = mb_strlen($q);
        $cLen = mb_strlen($c);
        $maxLen = max($qLen, $cLen);
        if ($maxLen === 0) {
            return 0.0;
        }

        $distance = self::levenshtein($q, $c);
        $tolerance = max(1, (int) floor($maxLen * 0.25));
        if ($distance > $tolerance) {
            return 0.0;
        }

        $sim = 1.0 - ($distance / $maxLen);
        return $sim >= 0.6 ? $sim : 0.0;
    }

    /**
     * Best score of a query against any of the candidate fields.
     */
    public static function bestScore(string $query, array $fields): float
    {
        $best = 0.0;
        foreach ($fields as $field) {
            $score = self::fuzzyScore($query, (string) $field);
            if ($score > $best) {
                $best = $score;
            }
        }

        return $best;
    }

    /**
     * True when every whitespace-separated token of the query finds a fuzzy
     * match against at least one of the candidate fields.
     */
    public static function matchesAllTokens(string $query, array $fields): bool
    {
        $tokens = self::tokens($query);

        if ($tokens === []) {
            return false;
        }

        foreach ($tokens as $token) {
            if (self::bestScore($token, $fields) <= 0.0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Average of the per-token best scores; used for ranking multi-word queries.
     */
    public static function queryScore(string $query, array $fields): float
    {
        $tokens = self::tokens($query);

        if ($tokens === []) {
            return 0.0;
        }

        $total = 0.0;
        foreach ($tokens as $token) {
            $total += self::bestScore($token, $fields);
        }

        return $total / count($tokens);
    }

    private static function tokens(string $query): array
    {
        return preg_split('/\s+/', self::normalize($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * Reduced Levenshtein distance between two ASCII-ish strings.
     * Falls back to the native implementation when available.
     */
    private static function levenshtein(string $a, string $b): int
    {
        $a = mb_convert_encoding($a, 'ISO-8859-1', 'UTF-8');
        $b = mb_convert_encoding($b, 'ISO-8859-1', 'UTF-8');

        return function_exists('levenshtein')
            ? levenshtein($a, $b)
            : self::phpLevenshtein($a, $b);
    }

    /**
     * Plain dynamic-programming Levenshtein distance (portable fallback).
     */
    private static function phpLevenshtein(string $a, string $b): int
    {
        $aLen = strlen($a);
        $bLen = strlen($b);
        $prev = range(0, $bLen);

        for ($i = 1; $i <= $aLen; $i++) {
            $current = [$i];
            for ($j = 1; $j <= $bLen; $j++) {
                $cost = $a[$i - 1] === $b[$j - 1] ? 0 : 1;
                $current[$j] = min(
                    $prev[$j] + 1,
                    $current[$j - 1] + 1,
                    $prev[$j - 1] + $cost,
                );
            }
            $prev = $current;
        }

        return $prev[$bLen];
    }
}