<?php
/*
 * =====================================================================================
 *  REGISTRAR'S DATABASE CONNECTION  (student records + grades the system fetches from)
 * =====================================================================================
 *  This is the ONLY place to change when connecting to the actual Registrar's database.
 *  For local testing it points to the test database `registrar_db` on this computer's
 *  XAMPP MySQL (created by database/registrar_test_seed.php).
 *
 *  When deploying, replace the five values below with the Registrar's real server details:
 *    line 20  REGISTRAR_DB_HOST  - server address (IP or hostname)
 *    line 21  REGISTRAR_DB_PORT  - <<< THE DATABASE PORT GOES HERE (MySQL default: 3306)
 *    line 22  REGISTRAR_DB_NAME  - database name
 *    line 23  REGISTRAR_DB_USER  - username (a read-only account is enough)
 *    line 24  REGISTRAR_DB_PASS  - password
 *  See REGISTRAR_DATABASE_SETUP.md in the project root for the full note.
 * =====================================================================================
 */

define('REGISTRAR_DB_HOST', '127.0.0.1');
define('REGISTRAR_DB_PORT', 3306);          // <<< REGISTRAR DATABASE PORT — change this when deploying
define('REGISTRAR_DB_NAME', 'registrar_db');
define('REGISTRAR_DB_USER', 'root');
define('REGISTRAR_DB_PASS', '');

/*
 * The tables this system reads from the Registrar's database (rename here if theirs differ):
 *   students: student_id, last_name, first_name, middle_name, gender, birthdate, email,
 *             program, major, year_level, enrollment_status, school_year
 *   grades:   student_id, subject_code, subject_name, semester, school_year, grade
 */
define('REGISTRAR_STUDENTS_TABLE', 'students');
define('REGISTRAR_GRADES_TABLE', 'grades');
// Subjects each student is enrolled in this term (student_id, subject_code, subject_name, semester, school_year).
define('REGISTRAR_SUBJECTS_TABLE', 'enrolled_subjects');
// Enrollment per semester (student_id, school_year, semester, enrollment_status, enrolled_on): one row per
// student per term they enrolled in. Renewal & Retention checks it whenever the Active Semester or
// Academic Year changes. A student with no row for the active term isn't enrolled in it. If the
// Registrar's database has no such table, the students table's enrollment_status / school_year is used.
define('REGISTRAR_ENROLLMENTS_TABLE', 'enrollments');
// Students who already HOLD a scholarship (student_id, scholarship_program, school_year, semester granted,
// status 'Active' / 'Terminated', granted_on). "Scan Registrar Now" adds the active ones who are officially
// enrolled to Scholars and Records. (students.scholarship_program is only the program a student applies for.)
define('REGISTRAR_SCHOLARSHIPS_TABLE', 'student_scholarships');

/*
 * Officially enrolled = the student's `enrollment_status` is one of these values (compared
 * without regard to case) AND, when REGISTRAR_CHECK_SCHOOL_YEAR is true, their `school_year`
 * is the Academic Year set in Settings > Portal Configuration. Change the list to match how
 * the Registrar's database writes it (e.g. 'Officially Enrolled', 'Active', 'E').
 */
const REGISTRAR_ENROLLED_STATUSES = ['Enrolled', 'Officially Enrolled'];
const REGISTRAR_CHECK_SCHOOL_YEAR = true;

// A PDO connection to the Registrar's database (separate from the system's own scholarship_db).
function getRegistrarDB(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;
    $pdo = new PDO(
        'mysql:host=' . REGISTRAR_DB_HOST . ';port=' . (int)REGISTRAR_DB_PORT . ';dbname=' . REGISTRAR_DB_NAME . ';charset=utf8mb4',
        REGISTRAR_DB_USER,
        REGISTRAR_DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_TIMEOUT => 5]
    );
    return $pdo;
}
