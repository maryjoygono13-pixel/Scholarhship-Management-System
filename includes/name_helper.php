<?php
/*
 * Shared name formatting: tables show a middle initial ("Juan D. Cruz"),
 * detail views show the complete middle name ("Juan Dela Cruz").
 */

function buildShortName(string $first, string $middle, string $last): string {
    $first = trim($first);
    $middle = trim($middle);
    $last = trim($last);
    $mi = $middle !== '' ? mb_strtoupper(mb_substr($middle, 0, 1)) . '.' : '';
    return trim(implode(' ', array_filter([$first, $mi, $last], fn($p) => $p !== '')));
}

function buildFullName(string $first, string $middle, string $last): string {
    $first = trim($first);
    $middle = trim($middle);
    $last = trim($last);
    return trim(implode(' ', array_filter([$first, $middle, $last], fn($p) => $p !== '')));
}
