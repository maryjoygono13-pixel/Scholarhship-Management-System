<?php
/*
 * A person's age worked out from their birthdate as of today in Philippine time — so it
 * goes up by itself on their birthday (the server's own timezone is not Manila's).
 */

function computeAge(?string $birthdate): ?int {
    $birthdate = trim((string)$birthdate);
    if ($birthdate === '') return null;
    try {
        $tz = new DateTimeZone('Asia/Manila');
        $born = new DateTime($birthdate, $tz);
        $today = new DateTime('today', $tz);
    } catch (Throwable $e) {
        return null;
    }
    if ($born > $today) return null;
    return $born->diff($today)->y;
}
