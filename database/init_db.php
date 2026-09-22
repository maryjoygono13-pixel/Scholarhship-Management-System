<?php
require_once __DIR__ . '/../config/database.php';

function initDatabase(): PDO {
    $pdo = getMySQLConnection();

    // 1. Users Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        role VARCHAR(50) NOT NULL DEFAULT 'registrar',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 2. Scholarships Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS scholarships (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        code VARCHAR(50) NOT NULL,
        description TEXT,
        type VARCHAR(255) NOT NULL,
        gwa_requirement DOUBLE NOT NULL,
        slots INT NOT NULL DEFAULT 0,
        slots_available INT NOT NULL DEFAULT 0,
        coverage TEXT,
        status VARCHAR(50) NOT NULL DEFAULT 'active',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        subtype VARCHAR(255) DEFAULT '',
        unlimited_slots INT NOT NULL DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 2a. Scholarship Types / Sub-types — the selectable, growable taxonomy
    // used by the Add/Edit Scholarship form's Type and Sub-type pickers.
    $pdo->exec("CREATE TABLE IF NOT EXISTS scholarship_types (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL UNIQUE,
        sort_order INT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS scholarship_subtypes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        gwa_requirement DOUBLE NOT NULL DEFAULT 1.75,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_type_name (type_id, name),
        FOREIGN KEY (type_id) REFERENCES scholarship_types(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $defaultTypes = [
        'MERIT-BASED Academic Scholarship',
        'NEED-BASED Scholarship',
        'TALENT-BASED Scholarship',
        'Community Service or Leadership Scholarship',
        'Other types of Scholarship and Discount',
        'CHED Scholarship',
    ];
    $insertType = $pdo->prepare("INSERT IGNORE INTO scholarship_types (name, sort_order) VALUES (?, ?)");
    foreach ($defaultTypes as $i => $typeName) {
        $insertType->execute([$typeName, $i + 1]);
    }

    // CHED Scholarship is the one category where the actual named programs
    // already used elsewhere in this app (Records/Applicants) are known —
    // seed those as its sub-types.
    $chedTypeId = $pdo->query("SELECT id FROM scholarship_types WHERE name = 'CHED Scholarship'")->fetchColumn();
    if ($chedTypeId) {
        $chedSubtypes = [
            'CMSP (CHED Merit Scholarship Program)' => 1.50,
            'TDP (Tulong Dunong Program)' => 2.25,
            'TES (Tertiary Education Subsidy)' => 2.00,
            'COSCHO (Scholarship for Coconut Farmers and Their Families)' => 2.50,
        ];
        $insertSubtype = $pdo->prepare("INSERT IGNORE INTO scholarship_subtypes (type_id, name, gwa_requirement) VALUES (?, ?, ?)");
        foreach ($chedSubtypes as $subtypeName => $subtypeGwa) {
            $insertSubtype->execute([$chedTypeId, $subtypeName, $subtypeGwa]);
        }

        // The 4 scholarships already seeded under the old ad-hoc type values
        // (Academic Merit / Financial Need-Based / etc.) are all genuinely CHED
        // programs — reclassify them under the new CHED Scholarship type with
        // their matching sub-type. Idempotent: once migrated, the WHERE code=...
        // no longer matches a non-CHED type.
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
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(50) NOT NULL,
        first_name VARCHAR(255) NOT NULL,
        middle_name VARCHAR(255) DEFAULT '',
        last_name VARCHAR(255) NOT NULL,
        gender VARCHAR(20) DEFAULT '',
        email VARCHAR(255) NOT NULL,
        phone VARCHAR(50),
        birthdate VARCHAR(20),
        age INT DEFAULT NULL,
        address TEXT,
        latitude DOUBLE,
        longitude DOUBLE,
        school VARCHAR(255),
        school_year VARCHAR(50) DEFAULT '',
        program VARCHAR(255),
        major VARCHAR(255) DEFAULT '',
        year_level VARCHAR(50),
        gpa DOUBLE DEFAULT 0,
        scholarship_type VARCHAR(255) NOT NULL,
        status VARCHAR(50) NOT NULL DEFAULT 'pending',
        gwa DOUBLE DEFAULT 0,
        gwa_req DOUBLE DEFAULT 1.75,
        failing_grades INT DEFAULT 0,
        units INT DEFAULT 21,
        enrolled INT DEFAULT 1,
        docs_complete INT DEFAULT 1,
        remarks TEXT,
        essay TEXT,
        transcript_file VARCHAR(500) DEFAULT '',
        coe_file VARCHAR(500) DEFAULT '',
        good_moral_file VARCHAR(500) DEFAULT '',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        semester VARCHAR(50) DEFAULT '1st Semester',
        municipality VARCHAR(255) DEFAULT '',
        barangay VARCHAR(255) DEFAULT ''
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 4. Notifications Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        type VARCHAR(100) NOT NULL,
        recipient_type VARCHAR(50) NOT NULL DEFAULT 'segment',
        recipient_id VARCHAR(50) DEFAULT NULL,
        recipient_name VARCHAR(255) DEFAULT '',
        recipient_email VARCHAR(255) DEFAULT '',
        subject VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        deadline VARCHAR(50) DEFAULT '',
        status VARCHAR(50) NOT NULL DEFAULT 'sent',
        sent_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Inbox: messages received from scholars / applicants
    $pdo->exec("CREATE TABLE IF NOT EXISTS inbox_messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_name VARCHAR(255) NOT NULL DEFAULT '',
        sender_email VARCHAR(255) NOT NULL DEFAULT '',
        student_id VARCHAR(50) DEFAULT '',
        subject VARCHAR(255) NOT NULL DEFAULT '',
        message TEXT NOT NULL,
        source VARCHAR(50) NOT NULL DEFAULT 'manual',
        is_read INT NOT NULL DEFAULT 0,
        received_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 5. Records Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS records (
        id INT AUTO_INCREMENT PRIMARY KEY,
        applicant_id INT,
        student_id VARCHAR(50) NOT NULL,
        name VARCHAR(255) NOT NULL,
        scholarship_type VARCHAR(255) NOT NULL,
        status VARCHAR(50) NOT NULL,
        semester VARCHAR(50) NOT NULL,
        sy VARCHAR(50) NOT NULL,
        date_evaluated VARCHAR(20) NOT NULL,
        remarks TEXT,
        FOREIGN KEY (applicant_id) REFERENCES applicants(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 6. Renewal & Retention Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS renewal_retention (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(50) NOT NULL,
        name VARCHAR(255) NOT NULL,
        gwa DOUBLE NOT NULL,
        failing_grades INT DEFAULT 0,
        enrolled INT DEFAULT 1,
        status VARCHAR(50) NOT NULL DEFAULT 'eligible',
        school_year VARCHAR(50) NOT NULL,
        semester VARCHAR(50) NOT NULL,
        scholarship_type VARCHAR(255) NOT NULL,
        remarks TEXT,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        origin VARCHAR(50) NOT NULL DEFAULT 'approved'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // A scholar terminated from a scholarship can't apply for that same scholarship again
    // (they may still apply for a different one). See includes/renewal_helper.php.
    $pdo->exec("CREATE TABLE IF NOT EXISTS scholarship_terminations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(50) NOT NULL,
        scholarship_type VARCHAR(255) NOT NULL,
        renewal_id INT,
        reason TEXT,
        terminated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_terminations_student (student_id, scholarship_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 7. Student Grades Table (per-subject grades, keyed by student + subject + semester)
    $pdo->exec("CREATE TABLE IF NOT EXISTS student_grades (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(50) NOT NULL,
        subject_code VARCHAR(100) NOT NULL,
        subject_name VARCHAR(255) DEFAULT '',
        semester VARCHAR(50) NOT NULL DEFAULT '1st Semester',
        school_year VARCHAR(50) DEFAULT '',
        grade DOUBLE NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY idx_student_grades_unique (student_id, subject_code, semester)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 8. Scholars Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS scholars (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id VARCHAR(50) NOT NULL,
        name VARCHAR(255) NOT NULL,
        department VARCHAR(255) NOT NULL,
        year_level INT NOT NULL DEFAULT 1,
        gwa DOUBLE NOT NULL DEFAULT 1.50,
        status VARCHAR(50) NOT NULL DEFAULT 'Active',
        school_year VARCHAR(50) NOT NULL DEFAULT '2025-2026',
        remarks TEXT,
        address VARCHAR(500) DEFAULT '',
        latitude DOUBLE DEFAULT NULL,
        longitude DOUBLE DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        scholarship_type VARCHAR(255) DEFAULT ''
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 9. Deleted Items / Trash Bin Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS deleted_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item_type VARCHAR(50) NOT NULL,
        item_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        item_data LONGTEXT NOT NULL,
        deleted_by VARCHAR(255) DEFAULT 'Registrar Staff',
        deleted_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 10. Activity Logs / History Audit Trail Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS activity_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT DEFAULT 0,
        user_name VARCHAR(255) NOT NULL DEFAULT 'Registrar Staff',
        action VARCHAR(100) NOT NULL,
        module VARCHAR(100) NOT NULL,
        description TEXT,
        record_id INT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_activity_logs_created_at (created_at),
        INDEX idx_activity_logs_module (module),
        INDEX idx_activity_logs_action (action)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 10a. Login Attempts / Brute-Force Protection
    $pdo->exec("CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        identifier VARCHAR(255) NOT NULL,
        ip_address VARCHAR(100) NOT NULL,
        successful INT NOT NULL DEFAULT 0,
        attempted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_login_attempts_lookup (identifier, ip_address, attempted_at),
        INDEX idx_login_attempts_attempted_at (attempted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // 11. Settings — simple key/value feature toggles & system config
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        setting_key VARCHAR(100) PRIMARY KEY,
        setting_value TEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    // History menu is accessible to everyone by default; can be turned off from Settings.
    $pdo->exec("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('history_enabled', '1')");
    // Active term: everything (Applicants, Records, Renewal & Retention) follows these.
    $pdo->exec("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('active_semester', '1st Semester')");
    // New installs start on the academic year of today's date (June to May).
    $startYear = (int)date('n') >= 6 ? (int)date('Y') : (int)date('Y') - 1;
    $pdo->exec("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('active_school_year', '" . $startYear . '-' . ($startYear + 1) . "')");

    // Imported Files Table
    $pdo->exec("CREATE TABLE IF NOT EXISTS imported_files (
        id INT AUTO_INCREMENT PRIMARY KEY,
        file_type VARCHAR(50) NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        file_size INT DEFAULT 0,
        records_count INT DEFAULT 0,
        imported_by VARCHAR(255) DEFAULT 'Registrar Staff',
        status VARCHAR(50) NOT NULL DEFAULT 'Active',
        stored_path VARCHAR(500) DEFAULT NULL,
        created_applicant_ids TEXT DEFAULT NULL,
        created_grade_ids TEXT DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Seed Data if empty
    seedDataIfEmpty($pdo);

    return $pdo;
}

function seedDataIfEmpty(PDO $pdo): void {
    // Demo data is planted ONCE, on a brand-new install. Without this, deleting everyone on a
    // page (e.g. all Scholars) made the sample rows reappear on the next page load.
    $demoSeeded = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'demo_data_seeded'")->fetchColumn() === '1';

    // Check if scholars is empty
    $stmtScholars = $pdo->query("SELECT COUNT(*) FROM scholars");
    if (!$demoSeeded && $stmtScholars->fetchColumn() == 0) {
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
    if (!$demoSeeded && $stmtSch->fetchColumn() == 0) {
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
    if (!$demoSeeded && $stmtApp->fetchColumn() == 0) {
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
    if (!$demoSeeded && $stmtNotif->fetchColumn() == 0) {
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
    if (!$demoSeeded && $stmtRec->fetchColumn() == 0) {
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
    if (!$demoSeeded && $stmtRen->fetchColumn() == 0) {
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
    if (!$demoSeeded && $stmtImp->fetchColumn() == 0) {
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

    if (!$demoSeeded) {
        $pdo->exec("REPLACE INTO settings (setting_key, setting_value) VALUES ('demo_data_seeded', '1')");
    }
}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    initDatabase();
    echo "MySQL Database initialized & seeded successfully!\n";
}
