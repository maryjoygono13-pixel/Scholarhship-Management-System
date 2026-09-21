<?php
/*
 * Where a student lives, as a pick-from-a-list Municipality plus a Barangay, instead of free text
 * that had to be guessed at ("maasin" inside a typed sentence). The municipality decides the map
 * position, so it can't be mistyped; the barangay only nudges the pin a little so students from
 * different barangays of one town don't sit exactly on top of each other.
 *
 * Coordinates are each town's centre from OpenStreetMap (looked up once); edit them here if a pin
 * should sit somewhere else. Add a town by adding a line.
 */

const MUNICIPALITIES = [
    // name => [latitude, longitude, province]
    // Southern Leyte
    'Anahawan'      => [10.2751362, 125.2551968, 'Southern Leyte'],
    'Bontoc'        => [10.3543900, 124.9706062, 'Southern Leyte'],
    'Hinundayan'    => [10.3513466, 125.2529731, 'Southern Leyte'],
    'Hinunangan'    => [10.3940404, 125.2001942, 'Southern Leyte'],
    'Libagon'       => [10.2962647, 125.0509042, 'Southern Leyte'],
    'Liloan'        => [10.1563177, 125.1177940, 'Southern Leyte'],
    'Limasawa'      => [9.9251181,  125.0740924, 'Southern Leyte'],
    'Maasin City'   => [10.1325061, 124.8385147, 'Southern Leyte'],
    'Macrohon'      => [10.0766035, 124.9400612, 'Southern Leyte'],
    'Malitbog'      => [10.1583357, 125.0013069, 'Southern Leyte'],
    'Padre Burgos'  => [10.0307729, 125.0167431, 'Southern Leyte'],
    'Pintuyan'      => [9.9445582,  125.2483289, 'Southern Leyte'],
    'Saint Bernard' => [10.2794927, 125.1394863, 'Southern Leyte'],
    'San Francisco' => [10.0563138, 125.1580978, 'Southern Leyte'],
    'San Juan'      => [10.2629046, 125.1729016, 'Southern Leyte'],
    'San Ricardo'   => [9.9139621,  125.2764986, 'Southern Leyte'],
    'Silago'        => [10.5285405, 125.1623205, 'Southern Leyte'],
    'Sogod'         => [10.3845880, 124.9808027, 'Southern Leyte'],
    'Tomas Oppus'   => [10.3050067, 124.9795399, 'Southern Leyte'],
    // Nearby towns in Leyte
    'Abuyog'        => [10.7467715, 125.0120993, 'Leyte'],
    'Bato'          => [10.3279300, 124.7893242, 'Leyte'],
    'Baybay City'   => [10.6778023, 124.7977531, 'Leyte'],
    'Hilongos'      => [10.3733001, 124.7488169, 'Leyte'],
    'Hindang'       => [10.4344450, 124.7267437, 'Leyte'],
    'Inopacan'      => [10.5003419, 124.7398262, 'Leyte'],
    'Matalom'       => [10.2821237, 124.7858627, 'Leyte'],
    'Ormoc City'    => [11.0090349, 124.6093940, 'Leyte'],
];

// Other spellings people use -> the name in the list above.
const MUNICIPALITY_ALIASES = [
    'maasin' => 'Maasin City', 'maasin city' => 'Maasin City',
    'lilo-an' => 'Liloan', 'lilo an' => 'Liloan',
    'st. bernard' => 'Saint Bernard', 'st bernard' => 'Saint Bernard',
    'batu' => 'Bato',
    'baybay' => 'Baybay City',
    'ormoc' => 'Ormoc City',
    'tomas oppus' => 'Tomas Oppus', 'tomasoppus' => 'Tomas Oppus',
];

// Exactly the name from the list (any capitalisation / common spelling), or null.
function resolveMunicipality(string $raw): ?string {
    $key = strtolower(trim(preg_replace('/\s+/', ' ', $raw)));
    if ($key === '') return null;
    foreach (array_keys(MUNICIPALITIES) as $name) {
        if (strtolower($name) === $key) return $name;
    }
    return MUNICIPALITY_ALIASES[$key] ?? null;
}

// A town name found inside free text such as "Tawid, Maasin City, Southern Leyte" (older addresses, imports).
function detectMunicipalityInText(string $text): ?string {
    $hay = ' ' . strtolower(preg_replace('/[^a-z0-9\. \-]+/i', ' ', $text)) . ' ';
    // longest names first, so "Baybay City" wins over "Bay", "San Juan" over "San"
    $names = array_merge(array_keys(MUNICIPALITIES), array_keys(MUNICIPALITY_ALIASES));
    usort($names, fn($a, $b) => strlen($b) <=> strlen($a));
    foreach ($names as $n) {
        if (strpos($hay, ' ' . strtolower($n) . ' ') !== false || strpos($hay, ' ' . strtolower($n) . ' ') !== false) {
            return resolveMunicipality($n);
        }
    }
    return null;
}

/*
 * The map position for a municipality (+ barangay), or null when the town isn't in the list.
 * The barangay moves the pin by a fixed 0.4-3 km in a direction taken from its name, so the same
 * barangay always lands on the same spot and different barangays spread out.
 */
/** A barangay name reduced for comparing: "Brgy. Tawid", "TAWID (Pob.)" and "tawid" all match. */
function barangayKey(string $name): string {
    $s = strtolower($name);
    $s = preg_replace('/\(.*?\)/', '', $s);
    $s = str_replace(['barangay', 'brgy', 'poblacion', 'pob.', 'ñ', 'santo', 'sto.', 'santa', 'sta.', 'san ', 'saint', 'st.'],
                     ['', '', 'pob', '', 'n', 'sto', 'sto', 'sta', 'sta', 'san', 'st', 'st'], $s);
    return preg_replace('/[^a-z0-9]/', '', $s);
}

/** The known barangay of a town that a typed name refers to: [official name, [lat, lon] or null], or null. */
function findBarangay(string $town, string $barangay): ?array {
    require_once __DIR__ . '/barangays.php';
    $key = barangayKey($barangay);
    if ($key === '' || empty(BARANGAYS[$town])) return null;
    foreach (BARANGAYS[$town] as $name => $pos) {
        if (barangayKey($name) === $key) return [$name, $pos];
    }
    return null;
}

/**
 * Map position for a town + barangay: the barangay's own position when it is known, otherwise the
 * town centre with a tiny nudge (100-450 m) so students of the same town don't sit exactly on top of each other.
 */
function locationCoordinates(string $municipality, string $barangay = ''): ?array {
    $town = resolveMunicipality($municipality);
    if ($town === null) return null;
    [$lat, $lon] = MUNICIPALITIES[$town];

    $known = findBarangay($town, $barangay);
    if ($known !== null && $known[1] !== null) {
        return [round($known[1][0], 6), round($known[1][1], 6)];
    }

    $b = strtolower(trim(preg_replace('/\s+/', ' ', $barangay)));
    if ($b !== '') {
        $h = crc32($known !== null ? strtolower($known[0]) : $b);
        $angle = (($h & 0xFFFF) / 65535) * 2 * M_PI;
        $radius = 0.001 + ((($h >> 16) & 0xFFFF) / 65535) * 0.003;
        $lat += sin($angle) * $radius;
        $lon += cos($angle) * $radius;
    }
    return [round($lat, 6), round($lon, 6)];
}

/** Barangay names of every town (for the suggestions on the form): town => [names]. */
function barangayNamesByTown(): array {
    require_once __DIR__ . '/barangays.php';
    return array_map('array_keys', BARANGAYS);
}

// "Tawid, Maasin City, Southern Leyte"
function composeAddress(string $barangay, string $municipality): string {
    $town = resolveMunicipality($municipality);
    if ($town === null) return trim($barangay . ($barangay !== '' && $municipality !== '' ? ', ' : '') . $municipality);
    $parts = [];
    if (trim($barangay) !== '') $parts[] = trim($barangay);
    $parts[] = $town;
    $parts[] = MUNICIPALITIES[$town][2];
    return implode(', ', $parts);
}

/*
 * From a free-text address (older data, or an import with only an address column), the
 * municipality and barangay it most likely contains: ['municipality' => ..., 'barangay' => ...].
 */
function splitAddress(string $address): array {
    $town = detectMunicipalityInText($address);
    $barangay = '';
    if ($town !== null) {
        // The barangay is the piece just before the town name, if there is one.
        $parts = array_map('trim', preg_split('/[,;]+/', $address));
        foreach ($parts as $i => $p) {
            if (resolveMunicipality($p) === $town || detectMunicipalityInText($p) === $town) {
                if ($i > 0) $barangay = preg_replace('/^(brgy\.?|barangay)\s+/i', '', $parts[$i - 1]);
                break;
            }
        }
    }
    return ['municipality' => $town ?? '', 'barangay' => $barangay];
}
