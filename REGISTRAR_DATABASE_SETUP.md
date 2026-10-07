# Registrar's Database Connection

The system fetches student records and grades from the Registrar's database
(**Data Management → Fetch from Registrar Database**). The connection is set in **one file**.

## Where to put the database port (and the other connection details)

**File:** `config/registrar_database.php`

| Line | Setting | Current (test) value | Put here when deploying |
|-----:|---------|----------------------|-------------------------|
| 20 | `REGISTRAR_DB_HOST` | `127.0.0.1` | The Registrar's database server address (IP or hostname) |
| **21** | **`REGISTRAR_DB_PORT`** | **`3306`** | **The Registrar's database port** |
| 22 | `REGISTRAR_DB_NAME` | `registrar_db` | The Registrar's database name |
| 23 | `REGISTRAR_DB_USER` | `root` | A username for that database (read-only is enough) |
| 24 | `REGISTRAR_DB_PASS` | *(empty)* | That user's password |

Line 21 is marked in the file with `// <<< REGISTRAR DATABASE PORT — change this when deploying`.

If the Registrar's tables have different names, change `REGISTRAR_STUDENTS_TABLE` and
`REGISTRAR_GRADES_TABLE` in the same file. The columns the system reads are listed there too.

> This is separate from the system's own database (`scholarship_db`), which is set in
> `config/database.php`.

## Test setup (this computer)

- **Database:** `registrar_db` on the XAMPP MySQL server, **port 3306** (XAMPP's default).
- **Created by:** `database/registrar_test_seed.php`. Run it again any time to reset the test data:
  ```
  C:\xampp\php\php.exe database\registrar_test_seed.php
  ```
- **Contents:** 25 test students (random program and year level) and a grade for every subject
  of their year level, both semesters, school year 2026-2027 — 330 grades in total.
- **Test file:** `test-data/registrar_test_students.xlsx` — Student ID, Last Name, First Name and
  Middle Name only (no grades). It can also be downloaded from the Data Management page.

## How to test

1. Open **Data Management**. The card at the top should say
   *Connected · 127.0.0.1:3306 / registrar_db · 25 students*.
2. Click **Download the test student list**, then **Choose File** and pick that file.
3. Click **Fetch**. Every student should show *Fetched*, with their program, year level,
   number of grades and GWA per semester.
4. With **Save fetched grades into the system** ticked, the grades are stored in the system
   (the same place grade imports go), so Evaluation, Scholars and Merit use them.
   Untick it to only preview.

A student whose name in the file doesn't match the Registrar's record is flagged and their grades
are **not** saved; a Student ID that isn't in the Registrar's database is listed as *Not found*.

## Verify CHED Enrollment List

**Data Management → Verify CHED Enrollment List.** Upload the list CHED sent (Excel `.xlsx` or CSV).
Every student is checked against the Registrar's database:

| Result | Meaning |
|--------|---------|
| **Enrolled** | Found, and officially enrolled this Academic Year → marked **Enrolled** in the system (no Certificate of Enrollment needed) |
| **Not enrolled** | Found, but not enrolled this Academic Year (e.g. dropped, or last enrolled in an earlier year) |
| **Not found** | Not in the Registrar's records |
| **Check manually** | Can't be decided automatically — several students with the same name, the Student ID belongs to someone else, or the birthdate differs |

- The list doesn't need Student IDs. Without them, students are matched by **last + first name**,
  using middle name/initial, birthdate and program to tell apart students with the same name.
- The header row can be anywhere in the first 20 rows (CHED files usually start with title rows).
- **Download results (Excel)** gives back the CHED list with the Registrar's answer on every row.
  Previous checks stay listed under *Previous checks* for re-download.

**What counts as officially enrolled** is set in `config/registrar_database.php`:
`REGISTRAR_ENROLLED_STATUSES` (the values of `enrollment_status` that mean enrolled) and
`REGISTRAR_CHECK_SCHOOL_YEAR` (also require `school_year` to be the Academic Year set in
Settings > Portal Configuration). Adjust these to match the Registrar's real database.

**Test file:** `test-data/ched_enrollment_list_sample.xlsx` — 23 students in CHED's style
(title rows, no Student IDs). Expected result: 18 Enrolled, 2 Not enrolled (Princess Rosales —
dropped; Princess Bautista — last enrolled 2025-2026), 3 Not found.

### First-year test students

The test database also has **30 first-year students** (Student IDs `20268001`–`20268030`):
officially enrolled in 2026-2027, **no grades yet**, and enrolled in the **1st Semester subjects**
of their program's 1st Year curriculum (table `enrolled_subjects`, set in
`config/registrar_database.php` as `REGISTRAR_SUBJECTS_TABLE`).

**Test file:** `test-data/first_year_enrollment_check.xlsx` — a CHED-style list of these 30
(title rows, names in capitals, no Student IDs). Expected result: **30 Enrolled**.

### Batch of 400 test students

Added on top of the students above (455 students in total). Student IDs are
`<entry year><group><number>`, e.g. `20251007` (group A), `20242015` (group B), `20263042` (group C).
Each student has a random year level and is enrolled in that year level's subjects.

| Group | Students | Grades | Scholarship program | Excel file |
|---|---|---|---|---|
| A | 135 | 1st Semester only | non-CHED (from the Scholarships page) | `test-data/batch_A_135_first_sem_grades.xlsx` |
| B | 115 | 1st and 2nd Semester | non-CHED | `test-data/batch_B_115_full_year_grades.xlsx` |
| C | 150 | none (1st Semester subjects only) | CMSP or COSCHO | `test-data/batch_C_ched_verification.xlsx` |

The files for groups A and B start with the Student ID column, so they can go straight into
**Fetch from Registrar Database**.

**Group C:** all 150 are in the registrar database, but 20 are **not officially enrolled**:
10 dropped, and 10 last enrolled in 2025-2026. The CHED-style list (title rows, capitals, no
Student IDs, shuffled) also has **15 students who are not in the registrar database**.
Expected result of **Verify CHED Enrollment List**: **130 Enrolled, 20 Not enrolled, 15 Not found**
(165 rows).

To rebuild everything: `C:\xampp\php\php.exe database\registrar_test_seed.php` (the same students
are recreated every time).

### Dean's List test students (184)

184 more students (Student IDs `<entry year>4<number>`, e.g. `20254017`) who have **no scholarship
program** (they never applied for anything) but have their **1st Semester 2026-2027 grades**, with a
random department / program and year level. Added by `database/registrar_dean_candidates.php`
(run it on its own to add or reset just this group; the main seed runs it too).

The Dean's List is the school's recognition for grades alone — no application — and is separate from
the MERIT-BASED Academic Scholarship (which students apply for). Rule: newest graded semester with a
GWA of **1.50 or better** and **no subject grade of 2.00 or worse**.

| Group | Students |
|---|---|
| Dean's Listers | 70 |
| Almost: GWA 1.51–1.60 (all subjects better than 2.00) | 22 |
| Almost: GWA 1.50 or better but one subject at 2.00 | 22 |
| Not eligible: GWA 1.75 or worse (15 with a failing 5.00) | 70 |

**Test file:** `test-data/dean_list_candidates_184.xlsx` — each student's GWA, lowest grade and
expected result. Expected **Scan Registrar Now** result from this group: **70 new Dean's Listers**,
each added to Scholars and Records under "Dean's List".

### Enrollment per semester (Renewal & Retention)

Table `enrollments` (set in `config/registrar_database.php` as `REGISTRAR_ENROLLMENTS_TABLE`):
one row per student per semester they enrolled in — `student_id, school_year, semester,
enrollment_status, enrolled_on`. Built by `database/registrar_semester_enrollment.php` (the main
seed runs it last).

- 1st Semester 2026-2027: 617 enrolled, 11 dropped.
- **2nd Semester 2026-2027: 512 enrolled, 59 dropped, 46 didn't enroll (no row).**
  Fixed cases: 20269025 enrolled, 20239007 dropped, 20231005 enrolled, 20261004 didn't enroll,
  20241003 enrolled, 20251002 dropped, 20261001 enrolled.

**List of 2nd Semester enrollees:** `test-data/second_semester_enrollees_2026-2027.xlsx`.

Whenever the Active Semester or Academic Year is changed in Settings › Portal Configuration, every
Renewal & Retention entry is checked against this table for the new term (entries added later are
checked when the Renewal & Retention page loads). A scholar who dropped or didn't enroll shows as
such and can only be terminated, not renewed.

### Students who already hold a scholarship (89)

Table `student_scholarships` (`REGISTRAR_SCHOLARSHIPS_TABLE` in `config/registrar_database.php`):
`student_id, scholarship_program, school_year, semester (granted), status ('Active' / 'Terminated'),
granted_on` — the Registrar's list of current scholars. (`students.scholarship_program` is only the
program a student applies for; the scan doesn't read it.)

89 students (IDs `<entry year>5<number>`, e.g. `20255031`), added by `database/registrar_scholars.php`
(the main seed runs it last): one of the 12 active programs each (7–8 per program), granted 1st Semester
2026-2027, with 1st and 2nd Semester grades. 83 enrolled in the 2nd Semester, 6 dropped.

**Scan Registrar Now** now also adds every ACTIVE scholarship holder who is officially enrolled for the
active semester: to Scholars under their program, an approved record for the granted term, and a Renewal &
Retention entry. Expected: portal at 1st Semester → all 89 added (24 with the Dean's Lister badge);
at 2nd Semester → 83 added (22 with the badge). Test file: `test-data/registrar_scholars_89.xlsx`.
