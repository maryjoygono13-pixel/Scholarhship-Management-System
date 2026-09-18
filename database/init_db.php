<?php
require_once __DIR__ . '/../config/database.php';

function initDatabase(): PDO {
    $pdo = getSQLiteConnection();

    // 1. Users Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT UNIQUE NOT NULL,
        password_hash TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'registrar',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 2. Scholarships Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS scholarships (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        code TEXT NOT NULL,
        description TEXT,
        type TEXT NOT NULL,
        gwa_requirement REAL NOT NULL,
        slots INTEGER NOT NULL DEFAULT 0,
        slots_available INTEGER NOT NULL DEFAULT 0,
        coverage TEXT,
        status TEXT NOT NULL DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $scholarshipsCols = $pdo->query("PRAGMA table_info(scholarships)")->fetchAll(PDO::FETCH_ASSOC);
    $scholarshipsColNames = array_column($scholarshipsCols, 'name');
    if (!in_array('subtype', $scholarshipsColNames)) {
        $pdo->exec("ALTER TABLE scholarships ADD COLUMN subtype TEXT DEFAULT ''");
    }

    // 2a. Scholarship Types / Sub-types — the selectable, growable taxonomy
    // used by the Add/Edit Scholarship form's Type and Sub-type pickers.
    $pdo->exec("CREATE TABLE IF NOT EXISTS scholarship_types (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE,
        sort_order INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS scholarship_subtypes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        type_id INTEGER NOT NULL,
        name TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(type_id, name),
        FOREIGN KEY (type_id) REFERENCES scholarship_types(id)
    )");

    $defaultTypes = [
        'MERIT-BASED Academic Scholarship',
        'NEED-BASED Scholarship',
        'TALENT-BASED Scholarship',
        'Community Service or Leadership Scholarship',
        'Other types of Scholarship and Discount',
        'CHED Scholarship',
    ];
    $insertType = $pdo->prepare("INSERT OR IGNORE INTO scholarship_types (name, sort_order) VALUES (?, ?)");
    foreach ($defaultTypes as $i => $typeName) {
        $insertType->execute([$typeName, $i + 1]);
    }

    // CHED Scholarship is the one category where the actual named programs
    // already used elsewhere in this app (Records/Applicants) are known —
    // seed those as its sub-types.
    $chedTypeId = $pdo->query("SELECT id FROM scholarship_types WHERE name = 'CHED Scholarship'")->fetchColumn();
    if ($chedTypeId) {
        $chedSubtypes = [
            'CMSP (CHED Merit Scholarship Program)',
            'TDP (Tulong Dunong Program)',
            'TES (Tertiary Education Subsidy)',
            'COSCHO (Scholarship for Coconut Farmers and Their Families)',
        ];
        $insertSubtype = $pdo->prepare("INSERT OR IGNORE INTO scholarship_subtypes (type_id, name) VALUES (?, ?)");
        foreach ($chedSubtypes as $subtypeName) {
            $insertSubtype->execute([$chedTypeId, $subtypeName]);
        }

        // One-time backfill: the 4 scholarships already seeded under the old
        // ad-hoc type values (Academic Merit / Financial Need-Based / etc.)
        // are all genuinely CHED programs — reclassify them under the new
        // CHED Scholarship type with their matching sub-type. Idempotent:
        // once migrated, the WHERE code=... no longer matches a non-CHED type.
        $reclassify = $pdo->prepare("UPDATE scholarships SET type = ?, subtype = ? WHERE code = ? AND type != ?");
        foreach ([
            'CMSP' => 'CMSP (CHED Merit Scholarship Program)',
            'TDP' => 'TDP (Tulong Dunong Program)',
            'TES' => 'TES (Tertiary Education Subsidy)',
            'COSCHO' => 'COSCHO (Scholarship for Coconut Farmers and Their Families)',
        ] as $code => $subtypeName) {
            $reclassify->execute(['CHED Scholarship', $subtypeName, $code, 'CHED Scholarship']);
        }
    }

    // 3. Applicants Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS applicants (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        student_id TEXT NOT NULL,
        first_name TEXT NOT NULL,
        middle_name TEXT DEFAULT '',
        last_name TEXT NOT NULL,
        gender TEXT DEFAULT '',
        email TEXT NOT NULL,
        phone TEXT,
        birthdate TEXT,
        age INTEGER DEFAULT NULL,
        address TEXT,
        latitude REAL,
        longitude REAL,
        school TEXT,
        school_year TEXT DEFAULT '',
        program TEXT,
        major TEXT DEFAULT '',
        year_level TEXT,
        gpa REAL DEFAULT 0,
        scholarship_type TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'pending',
        gwa REAL DEFAULT 0,
        gwa_req REAL DEFAULT 1.75,
        failing_grades INTEGER DEFAULT 0,
        units INTEGER DEFAULT 21,
        enrolled INTEGER DEFAULT 1,
        docs_complete INTEGER DEFAULT 1,
        remarks TEXT DEFAULT '',
        essay TEXT DEFAULT '',
        transcript_file TEXT DEFAULT '',
        recommendation_file TEXT DEFAULT '',
        valid_id_file TEXT DEFAULT '',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // ------------------------------------------------------------
    // UPDATE EXISTING APPLICANTS TABLE
    // Adds new columns without deleting existing applicant data.
    // ------------------------------------------------------------

    $columns = $pdo->query("
        PRAGMA table_info(applicants)
    ")->fetchAll(PDO::FETCH_ASSOC);

    $columnNames = array_column($columns, 'name');

    // Middle Name
    if (!in_array('middle_name', $columnNames, true)) {
        $pdo->exec("
            ALTER TABLE applicants
            ADD COLUMN middle_name TEXT DEFAULT ''
        ");
    }

    // Gender
    if (!in_array('gender', $columnNames, true)) {
        $pdo->exec("
            ALTER TABLE applicants
            ADD COLUMN gender TEXT DEFAULT ''
        ");
    }

    // Age
    if (!in_array('age', $columnNames, true)) {
        $pdo->exec("
            ALTER TABLE applicants
            ADD COLUMN age INTEGER DEFAULT NULL
        ");
    }

    // School Year
    if (!in_array('school_year', $columnNames, true)) {
        $pdo->exec("
            ALTER TABLE applicants
            ADD COLUMN school_year TEXT DEFAULT ''
        ");
    }

    // Major
    if (!in_array('major', $columnNames, true)) {
        $pdo->exec("
            ALTER TABLE applicants
            ADD COLUMN major TEXT DEFAULT ''
        ");
    }

    // Location columns
    if (!in_array('latitude', $columnNames, true)) {
        $pdo->exec("
            ALTER TABLE applicants
            ADD COLUMN latitude REAL
        ");
    }

    if (!in_array('longitude', $columnNames, true)) {
        $pdo->exec("
            ALTER TABLE applicants
            ADD COLUMN longitude REAL
        ");
    }

    // 4. Notifications Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        type TEXT NOT NULL,
        recipient_type TEXT NOT NULL DEFAULT 'segment',
        recipient_id TEXT DEFAULT NULL,
        recipient_name TEXT DEFAULT '',
        recipient_email TEXT DEFAULT '',
        subject TEXT NOT NULL,
        message TEXT NOT NULL,
        deadline TEXT DEFAULT '',
        status TEXT NOT NULL DEFAULT 'sent',
        sent_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 5. Records Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS records (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        applicant_id INTEGER,
        student_id TEXT NOT NULL,
        name TEXT NOT NULL,
        scholarship_type TEXT NOT NULL,
        status TEXT NOT NULL,
        semester TEXT NOT NULL,
        sy TEXT NOT NULL,
        date_evaluated TEXT NOT NULL,
        remarks TEXT DEFAULT '',
        FOREIGN KEY(applicant_id) REFERENCES applicants(id) ON DELETE SET NULL
    )");

    // 6. Renewal & Retention Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS renewal_retention (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        student_id TEXT NOT NULL,
        name TEXT NOT NULL,
        gwa REAL NOT NULL,
        failing_grades INTEGER DEFAULT 0,
        enrolled INTEGER DEFAULT 1,
        status TEXT NOT NULL DEFAULT 'eligible',
        school_year TEXT NOT NULL,
        semester TEXT NOT NULL,
        scholarship_type TEXT NOT NULL,
        remarks TEXT DEFAULT '',
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 8. Scholars Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS scholars (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        student_id TEXT NOT NULL,
        name TEXT NOT NULL,
        department TEXT NOT NULL,
        year_level INTEGER NOT NULL DEFAULT 1,
        gwa REAL NOT NULL DEFAULT 1.50,
        status TEXT NOT NULL DEFAULT 'Active',
        school_year TEXT NOT NULL DEFAULT '2025-2026',
        remarks TEXT DEFAULT '',
        address TEXT DEFAULT '',
        latitude REAL DEFAULT NULL,
        longitude REAL DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $scholarsCols = $pdo->query("PRAGMA table_info(scholars)")->fetchAll(PDO::FETCH_ASSOC);
    $scholarsColNames = array_column($scholarsCols, 'name');
    if (!in_array('address', $scholarsColNames)) {
        $pdo->exec("ALTER TABLE scholars ADD COLUMN address TEXT DEFAULT ''");
    }
    if (!in_array('latitude', $scholarsColNames)) {
        $pdo->exec("ALTER TABLE scholars ADD COLUMN latitude REAL DEFAULT NULL");
    }
    if (!in_array('longitude', $scholarsColNames)) {
        $pdo->exec("ALTER TABLE scholars ADD COLUMN longitude REAL DEFAULT NULL");
    }

    // 9. Deleted Items / Trash Bin Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS deleted_items (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        item_type TEXT NOT NULL,
        item_id INTEGER NOT NULL,
        title TEXT NOT NULL,
        item_data TEXT NOT NULL,
        deleted_by TEXT DEFAULT 'Registrar Staff',
        deleted_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // 10. Activity Logs / History Audit Trail Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS activity_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER DEFAULT 0,
        user_name TEXT NOT NULL DEFAULT 'Registrar Staff',
        action TEXT NOT NULL,
        module TEXT NOT NULL,
        description TEXT DEFAULT '',
        record_id INTEGER DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_activity_logs_created_at ON activity_logs(created_at)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_activity_logs_module ON activity_logs(module)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_activity_logs_action ON activity_logs(action)");

    // 11. Settings — simple key/value feature toggles & system config
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        setting_key TEXT PRIMARY KEY,
        setting_value TEXT
    )");
    // History menu is accessible to everyone by default; can be turned off from Settings.
    $pdo->exec("INSERT OR IGNORE INTO settings (setting_key, setting_value) VALUES ('history_enabled', '1')");

    // Seed Data if empty
    seedDataIfEmpty($pdo);

    return $pdo;
}

function seedDataIfEmpty(PDO $pdo): void {
    // Check if scholars is empty
    $stmtScholars = $pdo->query("SELECT COUNT(*) FROM scholars");
    if ($stmtScholars->fetchColumn() == 0) {
        $sampleScholars = [
            ['20230001', 'Juan Dela Cruz', 'Information Technology', 2, 1.25, 'Active', '2025-2026', 'Maintaining high academic standing (1.25 GWA <= 1.50)', 'Maasin City, Southern Leyte', 10.1333, 124.8333],
            ['20230004', 'Angelica Reyes', 'Nursing', 2, 1.15, 'Active', '2025-2026', 'Dean\'s Lister, excellent performance', 'Hilongos, Leyte', 10.3739, 124.7497],
            ['20230101', 'Patricia Lim', 'Accountancy', 3, 1.40, 'Active', '2025-2026', 'Maintained 1.40 GWA requirement', 'Sogod, Southern Leyte', 10.3833, 124.9833],
            ['20230102', 'Mark Anthony Torres', 'Business Administration', 4, 1.50, 'Active', '2025-2026', 'On the 1.50 maintenance threshold', 'Malitbog, Southern Leyte', 10.1500, 125.0000],
            ['20230103', 'Clarissa Santos', 'Liberal Arts and Education', 1, 1.35, 'Active', '2025-2026', 'Freshman scholar in good standing', 'Macrohon, Southern Leyte', 10.0833, 124.9333],
            ['20230104', 'Gabriel Fernandez', 'Food Preparation & Service Technology', 2, 1.48, 'Active', '2025-2026', 'Culinary arts honors student', 'Bontoc, Southern Leyte', 10.3500, 124.9667],
            ['20230005', 'Kevin Bautista', 'Information Technology', 4, 1.75, 'Removed', '2025-2026', 'Removed: GWA 1.75 is below 1.50 maintenance requirement', 'Maasin City, Southern Leyte', 10.1500, 124.8500],
            ['20230105', 'Rhea Villareal', 'Nursing', 3, 1.65, 'Removed', '2025-2026', 'Removed: GWA 1.65 dropped below 1.50 standard', 'Saint Bernard, Southern Leyte', 10.3333, 125.1333],
            ['20230106', 'Dave Tan', 'Accountancy', 1, 1.42, 'Active', '2025-2026', 'Maintaining 1.50 threshold', 'Liloan, Southern Leyte', 10.1667, 125.1333],
            ['20230107', 'Hannah Morales', 'Liberal Arts and Education', 3, 1.30, 'Active', '2025-2026', 'Consistently top of education department', 'Padre Burgos, Southern Leyte', 10.0389, 124.9750]
        ];
        $insertScholar = $pdo->prepare("INSERT INTO scholars (student_id, name, department, year_level, gwa, status, school_year, remarks, address, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($sampleScholars as $s) {
            $insertScholar->execute($s);
        }
    }
    // Check if scholarships is empty
    $stmtSch = $pdo->query("SELECT COUNT(*) FROM scholarships");
    if ($stmtSch->fetchColumn() == 0) {
        $scholarships = [
            ['CMSP (CHED Merit Scholarship Program)', 'CMSP', 'A competitive academic scholarship for students with high grades (such as a 93% GWA or higher in Grade 12).', 'Academic Merit', 1.50, 50, 25, 'Full Tuition & Academic Allowance', 'active'],
            ['TDP (Tulong Dunong Program)', 'TDP', 'A grant-in-aid financial assistance program meant to help partial college costs and expenses.', 'Financial Need-Based', 2.25, 100, 45, 'Partial College Costs & Expenses', 'active'],
            ['TES (Tertiary Education Subsidy)', 'TES', 'A major financial program under Republic Act No. 10931 helping priority students in SUCs, LUCs, and private higher education institutions.', 'Government Subsidy', 2.00, 150, 60, 'Full Tuition & Tertiary Subsidy', 'active'],
            ['COSCHO (Scholarship for Coconut Farmers and Their Families)', 'COSCHO', 'A specialized educational program intended for registered coconut farmers and their direct relatives.', 'Special Program', 2.50, 30, 12, 'Educational Grant for Coconut Farmers & Relatives', 'active']
        ];
        $insertSch = $pdo->prepare("INSERT INTO scholarships (name, code, description, type, gwa_requirement, slots, slots_available, coverage, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($scholarships as $sch) {
            $insertSch->execute($sch);
        }
    }

    // Check if applicants is empty
    $stmtApp = $pdo->query("SELECT COUNT(*) FROM applicants");
    if ($stmtApp->fetchColumn() == 0) {
        $applicants = [
            ['20230001', 'Juan', 'Dela Cruz', 'juan.delacruz@email.com', '09171234567', '2003-05-14', 'Maasin City, Southern Leyte', 10.1333, 124.8333, 'College of Maasin', 'BS Information Technology · 2nd Year', '2nd Year', 1.43, 'Academic Merit', 'pending', 1.43, 1.75, 0, 21, 1, 1, 'Top rank in class'],
            ['20230002', 'Maria', 'Santos', 'maria.santos@email.com', '09189876543', '2002-11-20', 'Macrohon, Southern Leyte', 10.0833, 124.9333, 'College of Maasin', 'BS Computer Science · 3rd Year', '3rd Year', 1.65, 'Academic Merit', 'review', 1.65, 1.75, 0, 21, 1, 1, 'Requires Dean recommendation verification'],
            ['20230003', 'Carlo', 'Mendoza', 'carlo.mendoza@email.com', '09195551234', '2004-01-10', 'Batu, Leyte', 10.3333, 124.7833, 'College of Maasin', 'BS Business Administration · 1st Year', '1st Year', 2.10, 'Financial Need-Based', 'interview', 2.10, 2.25, 0, 18, 1, 1, 'Scheduled for panel interview'],
            ['20230004', 'Angelica', 'Reyes', 'angelica.reyes@email.com', '09204443322', '2003-08-05', 'Hilongos, Leyte', 10.3739, 124.7497, 'College of Maasin', 'BS Nursing · 2nd Year', '2nd Year', 1.25, 'Academic Merit', 'approved', 1.25, 1.75, 0, 24, 1, 1, 'Endorsed for 100% grant'],
            ['20230005', 'Kevin', 'Bautista', 'kevin.bautista@email.com', '09213332211', '2002-03-30', 'Maasin City, Southern Leyte', 10.1500, 124.8500, 'College of Maasin', 'BS Criminology · 4th Year', '4th Year', 2.80, 'Athletic', 'rejected', 2.80, 2.50, 2, 15, 1, 0, 'Did not meet minimum units & GWA'],
            ['20230006', 'Samantha', 'Gomez', 'samantha.gomez@email.com', '09228889900', '2004-09-12', 'Padre Burgos, Southern Leyte', 10.0389, 124.9750, 'College of Maasin', 'BS Education · 1st Year', '1st Year', 1.80, 'Community Service', 'pending', 1.80, 2.00, 0, 21, 1, 1, 'Submitted complete documents']
        ];
        $insertApp = $pdo->prepare("INSERT INTO applicants (student_id, first_name, last_name, email, phone, birthdate, address, latitude, longitude, school, program, year_level, gpa, scholarship_type, status, gwa, gwa_req, failing_grades, units, enrolled, docs_complete, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($applicants as $app) {
            $insertApp->execute($app);
        }
    }

    // Check if notifications is empty
    $stmtNotif = $pdo->query("SELECT COUNT(*) FROM notifications");
    if ($stmtNotif->fetchColumn() == 0) {
        $notifications = [
            ['missing_requirements', 'segment', null, 'Missing Documents Group', 'juan.delacruz@email.com', 'Action needed: missing scholarship requirements', 'Hi Juan, your application is missing the Certificate of Indigency. Please submit before deadline.', '2026-08-20', 'sent', '2026-08-08 10:30:00'],
            ['renewal_deadline', 'segment', null, 'Active Scholars', 'maria.santos@email.com', 'Reminder: scholarship renewal deadline approaching', 'Hi Maria, submit your 1st semester clearance before August 25.', '2026-08-25', 'sent', '2026-08-09 14:15:00'],
            ['failed_retention', 'individual', '5', 'Kevin Bautista', 'kevin.bautista@email.com', 'Important: scholarship retention requirements not met', 'Hi Kevin, please visit the Registrar office regarding your scholarship status.', '', 'sent', '2026-08-10 09:00:00']
        ];
        $insertNotif = $pdo->prepare("INSERT INTO notifications (type, recipient_type, recipient_id, recipient_name, recipient_email, subject, message, deadline, status, sent_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($notifications as $notif) {
            $insertNotif->execute($notif);
        }
    }

    // Check if records is empty
    $stmtRec = $pdo->query("SELECT COUNT(*) FROM records");
    if ($stmtRec->fetchColumn() == 0) {
        $records = [
            [4, '20230004', 'Angelica Reyes', 'Academic Merit', 'approved', '1st Semester', '2025-2026', '2026-08-01', 'Approved with High Distinction'],
            [5, '20230005', 'Kevin Bautista', 'Athletic', 'rejected', '1st Semester', '2025-2026', '2026-08-02', 'Disqualified due to low GWA']
        ];
        $insertRec = $pdo->prepare("INSERT INTO records (applicant_id, student_id, name, scholarship_type, status, semester, sy, date_evaluated, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($records as $rec) {
            $insertRec->execute($rec);
        }
    }

    // Check if renewal_retention is empty
    $stmtRen = $pdo->query("SELECT COUNT(*) FROM renewal_retention");
    if ($stmtRen->fetchColumn() == 0) {
        $renewalData = [
            ['20230001', 'Juan Dela Cruz', 1.43, 0, 1, 'eligible', '2025-2026', 'First Semester', 'Academic Merit', 'Meets all retention requirements'],
            ['20230002', 'Maria Santos', 1.65, 0, 1, 'eligible', '2025-2026', 'First Semester', 'Academic Merit', 'Good standing'],
            ['20230003', 'Carlo Mendoza', 2.45, 1, 1, 'at-risk', '2025-2026', 'First Semester', 'Financial Need-Based', 'Near GWA threshold limit'],
            ['20230005', 'Kevin Bautista', 2.80, 2, 0, 'terminated', '2025-2026', 'First Semester', 'Athletic', 'Unenrolled / failed retention standard']
        ];
        $insertRen = $pdo->prepare("INSERT INTO renewal_retention (student_id, name, gwa, failing_grades, enrolled, status, school_year, semester, scholarship_type, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($renewalData as $ren) {
            $insertRen->execute($ren);
        }
    }

    // Check if imported_files is empty
    $stmtImp = $pdo->query("SELECT COUNT(*) FROM imported_files");
    if ($stmtImp->fetchColumn() == 0) {
        $sampleFiles = [
            ['grades', '2025-SY1_Academic_Grades.csv', 45056, 42, 'Registrar Staff', 'Active', '2026-08-12 11:20:00'],
            ['grades', 'BSIT_2ndYear_Midterm_Grades.xlsx', 62400, 38, 'Registrar Staff', 'Active', '2026-08-14 09:15:00'],
            ['enrollment', '2025-2026_Enrolled_Students.xlsx', 1048576, 120, 'Registrar Staff', 'Active', '2026-08-10 14:45:00'],
            ['enrollment', '1stSem_Official_Enrollment.csv', 81920, 85, 'Registrar Staff', 'Active', '2026-08-13 16:30:00']
        ];
        $insertImp = $pdo->prepare("INSERT INTO imported_files (file_type, file_name, file_size, records_count, imported_by, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($sampleFiles as $imp) {
            $insertImp->execute($imp);
        }
    }
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    initDatabase();
    echo "SQLite Database initialized & seeded successfully!\n";
}
