<?php
/*
 * Minimal .xlsx reader/writer that needs only zlib (PHP's zip extension is not enabled on
 * this server). Enough for simple one-sheet lists like student imports:
 *   readXlsxRows($path)          -> rows of the first worksheet, each an array of cell strings
 *   writeXlsx($path, $rows, ...) -> a one-sheet workbook Excel opens normally
 * An .xlsx file is a ZIP archive of XML parts; the ZIP container is handled here directly.
 */

/* ---------------------------- ZIP container ---------------------------- */

// [entry name => raw bytes] for every file inside a ZIP archive (stored or deflated entries).
function xlsxUnzip(string $path): array {
    $data = @file_get_contents($path);
    if ($data === false || strlen($data) < 22) throw new RuntimeException('The file could not be read.');

    // End of central directory record (search backwards; it may be followed by a comment).
    $eocd = strrpos($data, "PK\x05\x06");
    if ($eocd === false) throw new RuntimeException('This is not a valid .xlsx file.');
    $e = unpack('vdisk/vcdDisk/ventriesHere/ventries/VcdSize/VcdOffset', substr($data, $eocd + 4, 16));

    $files = [];
    $p = $e['cdOffset'];
    for ($i = 0; $i < $e['entries']; $i++) {
        if (substr($data, $p, 4) !== "PK\x01\x02") break;
        $c = unpack('vver/vneed/vflags/vmethod/vtime/vdate/Vcrc/VcompSize/Vsize/vnameLen/vextraLen/vcommentLen/vdisk/vintAttr/VextAttr/VlocalOffset', substr($data, $p + 4, 42));
        $name = substr($data, $p + 46, $c['nameLen']);
        $p += 46 + $c['nameLen'] + $c['extraLen'] + $c['commentLen'];

        $lo = $c['localOffset'];
        $l = unpack('vnameLen/vextraLen', substr($data, $lo + 26, 4));
        $raw = substr($data, $lo + 30 + $l['nameLen'] + $l['extraLen'], $c['compSize']);
        if ($c['method'] === 0) {
            $files[$name] = $raw;
        } elseif ($c['method'] === 8) {
            $inflated = @gzinflate($raw);
            if ($inflated === false) throw new RuntimeException('Part of the .xlsx file could not be decompressed.');
            $files[$name] = $inflated;
        }
    }
    return $files;
}

// Writes [entry name => bytes] as a ZIP archive (deflate-compressed).
function xlsxZip(string $path, array $files): void {
    $out = '';
    $central = '';
    $offset = 0;
    [$dosTime, $dosDate] = xlsxDosTime(time());
    foreach ($files as $name => $content) {
        $crc = crc32($content);
        $comp = gzdeflate($content, 6);
        $local = "PK\x03\x04" . pack('vvvvvVVVvv', 20, 0, 8, $dosTime, $dosDate, $crc, strlen($comp), strlen($content), strlen($name), 0) . $name;
        $out .= $local . $comp;
        $central .= "PK\x01\x02" . pack('vvvvvvVVVvvvvvVV', 20, 20, 0, 8, $dosTime, $dosDate, $crc, strlen($comp), strlen($content), strlen($name), 0, 0, 0, 0, 32, $offset) . $name;
        $offset += strlen($local) + strlen($comp);
    }
    $end = "PK\x05\x06" . pack('vvvvVVv', 0, 0, count($files), count($files), strlen($central), $offset, 0);
    if (file_put_contents($path, $out . $central . $end) === false) {
        throw new RuntimeException("Could not write $path.");
    }
}

function xlsxDosTime(int $ts): array {
    $d = getdate($ts);
    return [
        ($d['hours'] << 11) | ($d['minutes'] << 5) | intdiv($d['seconds'], 2),
        (($d['year'] - 1980) << 9) | ($d['mon'] << 5) | $d['mday'],
    ];
}

/* ------------------------------- Reading ------------------------------- */

// "C" -> 2 (0-based column index from a cell reference such as "C7").
function xlsxColumnIndex(string $ref): int {
    $letters = preg_replace('/[^A-Z]/', '', strtoupper($ref));
    $n = 0;
    foreach (str_split($letters) as $ch) $n = $n * 26 + (ord($ch) - 64);
    return max(0, $n - 1);
}

// Rows of the first worksheet as arrays of strings (blank cells filled with '').
function readXlsxRows(string $path): array {
    $files = xlsxUnzip($path);

    // The first sheet listed in the workbook, via its relationship target.
    $sheetPath = 'xl/worksheets/sheet1.xml';
    if (isset($files['xl/workbook.xml'], $files['xl/_rels/workbook.xml.rels'])) {
        $wb = @simplexml_load_string($files['xl/workbook.xml']);
        $rels = @simplexml_load_string($files['xl/_rels/workbook.xml.rels']);
        if ($wb && $rels && isset($wb->sheets->sheet[0])) {
            $rid = (string)$wb->sheets->sheet[0]->attributes('r', true)['id'];
            foreach ($rels->Relationship as $rel) {
                if ((string)$rel['Id'] === $rid) {
                    $target = ltrim((string)$rel['Target'], '/');
                    $sheetPath = strpos($target, 'xl/') === 0 ? $target : 'xl/' . $target;
                }
            }
        }
    }
    if (!isset($files[$sheetPath])) throw new RuntimeException('The .xlsx file has no worksheet.');

    $shared = [];
    if (isset($files['xl/sharedStrings.xml'])) {
        $sst = @simplexml_load_string($files['xl/sharedStrings.xml']);
        if ($sst) {
            foreach ($sst->si as $si) {
                // Plain text, or rich text split into runs.
                $text = isset($si->t) ? (string)$si->t : '';
                if (isset($si->r)) foreach ($si->r as $run) $text .= (string)$run->t;
                $shared[] = $text;
            }
        }
    }

    $sheet = @simplexml_load_string($files[$sheetPath]);
    if (!$sheet) throw new RuntimeException('The worksheet could not be read.');

    $rows = [];
    foreach ($sheet->sheetData->row as $row) {
        $cells = [];
        foreach ($row->c as $c) {
            $idx = xlsxColumnIndex((string)$c['r']);
            $type = (string)$c['t'];
            if ($type === 's') {
                $val = $shared[(int)$c->v] ?? '';
            } elseif ($type === 'inlineStr') {
                $val = (string)$c->is->t;
            } else {
                $val = (string)$c->v;
            }
            $cells[$idx] = trim($val);
        }
        if (empty($cells)) continue;
        $line = array_fill(0, max(array_keys($cells)) + 1, '');
        foreach ($cells as $i => $v) $line[$i] = $v;
        $rows[] = $line;
    }
    return $rows;
}

/* ------------------------------- Writing ------------------------------- */

// A one-sheet workbook. The first row is written as a bold header; $widths are column widths.
// $opts (the page exports, api/export_xlsx.php):
//   'center'     => true: every cell centered (header included)
//   'numberCols' => [column indexes]: numeric values there are real numbers shown with 2 decimals
//                   (e.g. GWAs); everything else stays text, so Student IDs keep their exact digits.
function writeXlsx(string $path, array $rows, string $sheetName = 'Sheet1', array $widths = [], array $opts = []): void {
    $center = !empty($opts['center']);
    $numberCols = array_flip(array_map('intval', $opts['numberCols'] ?? []));
    $esc = fn($s) => htmlspecialchars((string)$s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $colName = function (int $i): string {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) $s = chr(65 + ($i - 1) % 26) . $s;
        return $s;
    };

    $sheetRows = '';
    foreach (array_values($rows) as $r => $row) {
        $cells = '';
        foreach (array_values($row) as $c => $value) {
            $ref = $colName($c) . ($r + 1);
            if ($r > 0 && isset($numberCols[$c]) && is_numeric($value) && trim((string)$value) !== '') {
                $cells .= '<c r="' . $ref . '" s="4"><v>' . (float)$value . '</v></c>';   // number, 0.00
                continue;
            }
            $style = $r === 0 ? ($center ? ' s="3"' : ' s="1"') : ($center ? ' s="2"' : '');
            // Everything else as text, so Student IDs keep their exact digits.
            $cells .= '<c r="' . $ref . '" t="inlineStr"' . $style . '><is><t xml:space="preserve">' . $esc($value) . '</t></is></c>';
        }
        $sheetRows .= '<row r="' . ($r + 1) . '">' . $cells . '</row>';
    }
    $cols = '';
    if ($widths) {
        $cols = '<cols>';
        foreach (array_values($widths) as $i => $w) $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . (float)$w . '" customWidth="1"/>';
        $cols .= '</cols>';
    }

    $files = [
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>',
        'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="' . $esc($sheetName) . '" sheetId="1" r:id="rId1"/></sheets></workbook>',
        'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>',
        'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            // 0 normal, 1 bold header, 2 centered, 3 bold centered header, 4 number 0.00 (centered when $center)
            . '<cellXfs count="5"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>'
            . '<xf numFmtId="2" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"' . ($center ? ' applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' : '/>') . '</cellXfs>'
            . '</styleSheet>',
        'xl/worksheets/sheet1.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . $cols . '<sheetData>' . $sheetRows . '</sheetData></worksheet>',
    ];
    xlsxZip($path, $files);
}
