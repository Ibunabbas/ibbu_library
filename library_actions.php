<?php
// ============================================================
//  FILE: library_actions.php
//  FIXED:
//    1. ob_start() captures stray output that would break JSON
//    2. bind_param type string corrected to exactly 14 chars
//    3. $conn not used after ->close() in catch blocks
//    4. get_result() always stored in a variable before looping
//    5. mysqli exceptions enabled for clear error messages
// ============================================================

// Capture ANY stray output (warnings, notices) so they never
// corrupt the JSON response sent back to the browser.
ob_start();
require_once 'db_config.php';
start_session();

ini_set('display_errors', 0);
error_reporting(E_ALL);

// ── Verify CSRF token on every state-changing POST ─────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
}

$action = sanitise($_REQUEST['action'] ?? '');

// Route to the correct handler
switch ($action) {
    // ── FETCH (GET) ────────────────────────────────────────
    case 'get_faculties':        get_faculties();        break;
    case 'get_departments':      get_departments();      break;
    case 'get_books':            get_books();            break;
    case 'get_book':             get_book();             break;
    case 'search_books':         search_books();         break;
    case 'get_announcements':    get_announcements();    break;
    case 'get_user_loans':       get_user_loans();       break;
    case 'get_user_profile':     get_user_profile();     break;
    case 'preview_matric':       preview_matric();       break;

    // ── STUDENT PORTAL ─────────────────────────────────────
    case 'student_dashboard':    student_dashboard();    break;
    case 'student_reservations': student_reservations(); break;
    case 'student_renew_loan':   student_renew_loan();   break;
    case 'student_cancel_reservation': student_cancel_reservation(); break;

    // ── ADMIN FETCH ────────────────────────────────────────
    case 'admin_get_stats':      admin_get_stats();      break;
    case 'admin_get_loans':      admin_get_loans();      break;
    case 'admin_get_students':   admin_get_students();   break;
    case 'admin_get_staff':      admin_get_staff();      break;
    case 'admin_search_student': admin_search_student(); break;
    case 'admin_search_book':    admin_search_book();    break;
    case 'admin_get_loan_detail':admin_get_loan_detail();break;
    case 'admin_get_fines':      admin_get_fines();      break;
    case 'admin_update_overdue': admin_update_overdue(); break;
    case 'admin_get_books_list': admin_get_books_list(); break;
    case 'admin_get_book_detail': admin_get_book_detail(); break;

    // ── SAVE (POST) ────────────────────────────────────────
    case 'register_student':     register_student();     break;
    case 'register_staff':       register_staff();       break;
    case 'login_user':           login_user();           break;
    case 'logout_user':          logout_user();          break;
    case 'reserve_book':         reserve_book();         break;
    case 'borrow_book':          borrow_book();          break;
    case 'return_book':          return_book();          break;
    case 'renew_loan':           renew_loan();           break;
    case 'pay_fine':             pay_fine();             break;

    // ── ADMIN SAVE ─────────────────────────────────────────
    case 'admin_issue_loan':     admin_issue_loan();     break;
    case 'admin_return_book':    admin_return_book();    break;
    case 'admin_waive_fine':     admin_waive_fine();     break;
    case 'admin_add_book':       admin_add_book();       break;
    case 'admin_update_book':    admin_update_book();    break;
    case 'admin_add_announcement': admin_add_announcement(); break;
    case 'admin_mark_fine_paid': admin_mark_fine_paid(); break;
    case 'admin_toggle_student': admin_toggle_student(); break;
    case 'admin_toggle_staff':   admin_toggle_staff();   break;

    // ── STAFF APPROVAL ──────────────────────────────────────
    case 'admin_get_pending_staff':  admin_get_pending_staff();  break;
    case 'admin_approve_staff':      admin_approve_staff();      break;
    case 'admin_reject_staff':       admin_reject_staff();       break;

    // ── AUDIT LOG ───────────────────────────────────────────
    case 'admin_get_audit_log':      admin_get_audit_log();      break;

    default:
        json_response(['status' => 'error', 'message' => 'Unknown action: ' . $action], 400);
}


// ============================================================
//  FETCH FUNCTIONS
// ============================================================

function get_faculties(): void {
    $conn   = db_connect();
    $result = $conn->query('SELECT id, code, name, short_name FROM faculties ORDER BY name ASC');
    $rows   = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) $rows[] = $row;
        $result->free();
    }
    $conn->close();
    json_response(['status' => 'success', 'data' => $rows]);
}

function get_departments(): void {
    $conn         = db_connect();
    $faculty_code = sanitise($_GET['faculty_code'] ?? '');
    $rows         = [];

    if (!$faculty_code) {
        $result = $conn->query(
            'SELECT d.id, d.code, d.name, f.code AS faculty_code, f.name AS faculty_name
             FROM departments d JOIN faculties f ON f.id = d.faculty_id
             ORDER BY f.name ASC, d.name ASC'
        );
        if ($result) {
            while ($row = $result->fetch_assoc()) $rows[] = $row;
            $result->free();
        }
    } else {
        $stmt = $conn->prepare(
            'SELECT d.id, d.code, d.name, f.code AS faculty_code, f.name AS faculty_name
             FROM departments d JOIN faculties f ON f.id = d.faculty_id
             WHERE f.code = ? ORDER BY d.name ASC'
        );
        if ($stmt) {
            $stmt->bind_param('s', $faculty_code);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result) {
                while ($row = $result->fetch_assoc()) $rows[] = $row;
                $result->free();
            }
            $stmt->close();
        }
    }

    $conn->close();
    json_response(['status' => 'success', 'data' => $rows]);
}

function preview_matric(): void {
    $conn         = db_connect();
    $year2        = sanitise($_GET['year']         ?? '');
    $faculty_code = strtoupper(sanitise($_GET['faculty_code'] ?? ''));
    $dept_code    = strtoupper(sanitise($_GET['dept_code']    ?? ''));

    if (!$year2 || !$faculty_code || !$dept_code) {
        $conn->close();
        json_response(['status' => 'error', 'message' => 'year, faculty_code and dept_code required'], 400);
    }

    $stmt = $conn->prepare(
        'SELECT last_serial FROM matric_serials
         WHERE admission_year = ? AND faculty_code = ? AND dept_code = ?'
    );
    $serial = 1;
    if ($stmt) {
        $stmt->bind_param('sss', $year2, $faculty_code, $dept_code);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            $row    = $result->fetch_row();
            $serial = $row ? (int)$row[0] + 1 : 1;
            $result->free();
        }
        $stmt->close();
    }
    $conn->close();

    $preview = sprintf('U%s/%s/%s/%04d', $year2, $faculty_code, $dept_code, $serial);
    json_response(['status' => 'success', 'preview' => $preview, 'next_serial' => $serial]);
}

function get_books(): void {
    $conn     = db_connect();
    $category = sanitise($_GET['category']  ?? '');
    $type     = sanitise($_GET['type']      ?? '');
    $dept     = sanitise($_GET['dept_code'] ?? '');

    $where  = ['1=1'];
    $params = [];
    $types  = '';

    if ($category) { $where[] = 'category = ?';       $params[] = $category; $types .= 's'; }
    if ($type)     { $where[] = 'resource_type = ?';  $params[] = $type;     $types .= 's'; }
    if ($dept)     { $where[] = 'dept_code = ?';      $params[] = $dept;     $types .= 's'; }

    $sql   = 'SELECT * FROM books WHERE ' . implode(' AND ', $where) . ' ORDER BY title ASC LIMIT 60';
    $stmt  = $conn->prepare($sql);
    $books = [];

    if ($stmt) {
        if ($params) $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) $books[] = $row;
            $result->free();
        }
        $stmt->close();
    }

    $conn->close();
    json_response(['status' => 'success', 'data' => $books, 'count' => count($books)]);
}

function get_book(): void {
    $conn = db_connect();
    $id   = (int)($_GET['id'] ?? 0);
    if (!$id) { $conn->close(); json_response(['status' => 'error', 'message' => 'Book ID required'], 400); }

    $stmt = $conn->prepare('SELECT * FROM books WHERE id = ? LIMIT 1');
    $book = null;
    if ($stmt) {
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) { $book = $result->fetch_assoc(); $result->free(); }
        $stmt->close();
    }
    $conn->close();

    if (!$book) json_response(['status' => 'error', 'message' => 'Book not found'], 404);
    json_response(['status' => 'success', 'data' => $book]);
}

function search_books(): void {
    $conn  = db_connect();
    $query = sanitise($_GET['q'] ?? '');
    if (strlen($query) < 2) { $conn->close(); json_response(['status' => 'error', 'message' => 'Query too short'], 400); }

    $like  = "%$query%";
    $stmt  = $conn->prepare(
        'SELECT * FROM books
         WHERE title LIKE ? OR author LIKE ? OR isbn LIKE ?
            OR subject LIKE ? OR department_name LIKE ? OR dept_code LIKE ?
         ORDER BY title ASC LIMIT 40'
    );
    $books = [];
    if ($stmt) {
        $stmt->bind_param('ssssss', $like, $like, $like, $like, $like, $like);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) $books[] = $row;
            $result->free();
        }
        $stmt->close();
    }
    $conn->close();
    json_response(['status' => 'success', 'data' => $books, 'count' => count($books)]);
}

function get_announcements(): void {
    $conn  = db_connect();
    $limit = max(1, (int)($_GET['limit'] ?? 6));
    $items = [];

    $stmt = $conn->prepare(
        'SELECT id, title, body, tag, tag_color, published_at
         FROM announcements WHERE is_active = 1
         ORDER BY published_at DESC LIMIT ?'
    );
    if ($stmt) {
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) $items[] = $row;
            $result->free();
        }
        $stmt->close();
    }
    $conn->close();
    json_response(['status' => 'success', 'data' => $items]);
}

function get_user_loans(): void {
    if (!is_logged_in()) json_response(['status' => 'error', 'message' => 'Not authenticated'], 401);

    $conn    = db_connect();
    $user_id = (int)$_SESSION['user_id'];
    $loans   = [];

    $stmt = $conn->prepare(
        'SELECT l.id, l.borrow_date, l.due_date, l.return_date,
                l.status, l.fine_amount, l.fine_paid,
                b.title, b.author, b.cover_emoji, b.cover_color
         FROM loans l JOIN books b ON b.id = l.book_id
         WHERE l.user_id = ? ORDER BY l.created_at DESC'
    );
    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) $loans[] = $row;
            $result->free();
        }
        $stmt->close();
    }
    $conn->close();
    json_response(['status' => 'success', 'data' => $loans]);
}

function get_user_profile(): void {
    if (!is_logged_in()) json_response(['status' => 'error', 'message' => 'Not authenticated'], 401);

    $conn    = db_connect();
    $user_id = (int)$_SESSION['user_id'];
    $user    = null;

    $stmt = $conn->prepare(
        'SELECT id, matric_number, full_name, email, phone, role,
                admission_year, faculty_code, faculty_name,
                dept_code, department_name, level, status, created_at
         FROM users WHERE id = ? LIMIT 1'
    );
    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) { $user = $result->fetch_assoc(); $result->free(); }
        $stmt->close();
    }
    $conn->close();

    if (!$user) json_response(['status' => 'error', 'message' => 'User not found'], 404);
    json_response(['status' => 'success', 'data' => $user]);
}


// ============================================================
//  SAVE FUNCTIONS
// ============================================================

// ── REGISTER STUDENT ─────────────────────────────────────────
// The student enters their school-assigned matric number.
// The system generates a library registration number (LIB-YYYY-NNNN)
// which they will use to log into the library portal.
function register_student(): void {
    $conn = db_connect();

    $full_name      = sanitise($_POST['full_name']      ?? '');
    $school_matric  = strtoupper(sanitise($_POST['school_matric'] ?? '')); // entered by student
    $admission_year = sanitise($_POST['admission_year'] ?? '');
    $faculty_code   = strtoupper(sanitise($_POST['faculty_code'] ?? ''));
    $dept_code      = strtoupper(sanitise($_POST['dept_code']    ?? ''));
    $email_raw      = trim($_POST['email']   ?? '');
    $email          = filter_var($email_raw, FILTER_VALIDATE_EMAIL);
    $phone          = sanitise($_POST['phone'] ?? '');
    $level          = sanitise($_POST['level'] ?? '100');
    $password       = $_POST['password'] ?? '';

    // ── Validation ─────────────────────────────────────────
    if (!$full_name || !$school_matric || !$admission_year || !$faculty_code || !$dept_code || !$email_raw || !$password) {
        $conn->close();
        json_response(['status' => 'error', 'message' => 'All required fields must be filled.'], 400);
    }
    if (!$email) {
        $conn->close();
        json_response(['status' => 'error', 'message' => 'Please enter a valid email address.'], 400);
    }
    if (strlen($password) < 8) {
        $conn->close();
        json_response(['status' => 'error', 'message' => 'Password must be at least 8 characters.'], 400);
    }
    if (!preg_match('/^\d{4}$/', $admission_year)) {
        $conn->close();
        json_response(['status' => 'error', 'message' => 'Admission year must be a 4-digit year (e.g. 2022).'], 400);
    }

    // Validate school matric format: U22/FNS/CSC/0001
    if (!preg_match('/^U\d{2}\/[A-Z]{2,5}\/[A-Z]{2,5}\/\d{4}$/i', $school_matric)) {
        $conn->close();
        json_response(['status' => 'error', 'message' => 'Invalid matric number format. Expected format: U22/FNS/CSC/0001'], 400);
    }

    // ── Check school matric not already registered ──────────
    $stmt = $conn->prepare('SELECT id FROM users WHERE school_matric = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('s', $school_matric);
        $stmt->execute();
        $r = $stmt->get_result();
        $exists = $r->fetch_assoc();
        $r->free(); $stmt->close();
        if ($exists) {
            $conn->close();
            json_response(['status' => 'error', 'message' => 'This school matric number is already registered in the library system.'], 409);
        }
    }

    $year2 = substr($admission_year, -2); // "2022" → "22"

    // ── Verify faculty ──────────────────────────────────────
    $stmt    = $conn->prepare('SELECT id, name FROM faculties WHERE code = ? LIMIT 1');
    $faculty = null;
    if ($stmt) {
        $stmt->bind_param('s', $faculty_code);
        $stmt->execute();
        $result  = $stmt->get_result();
        if ($result) { $faculty = $result->fetch_assoc(); $result->free(); }
        $stmt->close();
    }
    if (!$faculty) {
        $conn->close();
        json_response(['status' => 'error', 'message' => "Faculty code '$faculty_code' not found."], 400);
    }

    // ── Verify department belongs to that faculty ───────────
    $stmt = $conn->prepare(
        'SELECT d.id, d.name FROM departments d
         JOIN faculties f ON f.id = d.faculty_id
         WHERE d.code = ? AND f.code = ? LIMIT 1'
    );
    $dept = null;
    if ($stmt) {
        $stmt->bind_param('ss', $dept_code, $faculty_code);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) { $dept = $result->fetch_assoc(); $result->free(); }
        $stmt->close();
    }
    if (!$dept) {
        $conn->close();
        json_response(['status' => 'error', 'message' => "Department '$dept_code' does not belong to faculty '$faculty_code'."], 400);
    }

    // ── Generate library registration number & insert user ──
    $conn->begin_transaction();
    try {
        // Generate LIB-YYYY-NNNN (e.g. LIB-2025-0001)
        $lib_data          = generate_lib_number($conn);
        $library_reg_number = $lib_data['lib_number'];
        $lib_serial         = $lib_data['serial'];

        $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
        $role          = 'student';
        $fac_id        = (int)$faculty['id'];
        $fac_name      = (string)$faculty['name'];
        $dep_id        = (int)$dept['id'];
        $dep_name      = (string)$dept['name'];

        // INSERT — library_reg_number is login credential
        //          school_matric is school-issued matric stored for records
        //          matric_number mirrors library_reg_number for compat
        //
        // Columns:  library_reg_number, matric_number, school_matric,
        //           full_name, email, phone, password_hash, role,
        //           admission_year, faculty_id, faculty_code, faculty_name,
        //           dept_id, dept_code, department_name, level, matric_serial
        // Types: s s s s s s s s s i s s i s s s i  = 17 params
        $stmt = $conn->prepare(
            'INSERT INTO users
             (library_reg_number, matric_number, school_matric,
              full_name, email, phone, password_hash, role,
              admission_year, faculty_id, faculty_code, faculty_name,
              dept_id, dept_code, department_name, level, matric_serial)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        if (!$stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }

        $stmt->bind_param(
            'sssssssssississsi',
            $library_reg_number,  // s  library_reg_number
            $library_reg_number,  // s  matric_number (same as lib reg for compat)
            $school_matric,       // s  school_matric (school-issued)
            $full_name,           // s  full_name
            $email,               // s  email
            $phone,               // s  phone
            $password_hash,       // s  password_hash
            $role,                // s  role
            $year2,               // s  admission_year
            $fac_id,              // i  faculty_id
            $faculty_code,        // s  faculty_code
            $fac_name,            // s  faculty_name
            $dep_id,              // i  dept_id
            $dept_code,           // s  dept_code
            $dep_name,            // s  department_name
            $level,               // s  level
            $lib_serial           // i  matric_serial
        );

        if (!$stmt->execute()) {
            throw new Exception('Execute failed: ' . $stmt->error);
        }

        $new_id = $stmt->insert_id;
        $stmt->close();
        $conn->commit();
        $conn->close();

        json_response([
            'status'               => 'success',
            'message'              => 'Registration successful! Your library number has been assigned.',
            'library_reg_number'   => $library_reg_number,
            'school_matric'        => $school_matric,
            'user_id'              => $new_id,
            'full_name'            => $full_name,
            'faculty'              => $fac_name,
            'department'           => $dep_name,
            'level'                => $level,
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        $err_msg = $e->getMessage();
        $conn->close();
        if (strpos($err_msg, 'Duplicate') !== false || strpos($err_msg, '1062') !== false) {
            json_response(['status' => 'error', 'message' => 'This email or matric number is already registered.'], 409);
        }
        json_response(['status' => 'error', 'message' => 'Registration failed. (' . $err_msg . ')'], 500);
    }
}


// ── REGISTER STAFF ────────────────────────────────────────────
function register_staff(): void {
    $conn = db_connect();

    $full_name = sanitise($_POST['full_name'] ?? '');
    $staff_id  = strtoupper(sanitise($_POST['staff_id']  ?? ''));
    $email_raw = trim($_POST['email'] ?? '');
    $email     = filter_var($email_raw, FILTER_VALIDATE_EMAIL);
    $phone     = sanitise($_POST['phone']     ?? '');
    $dept_code = strtoupper(sanitise($_POST['dept_code'] ?? ''));
    $password  = $_POST['password'] ?? '';

    if (!$full_name || !$staff_id || !$email_raw || !$password) {
        $conn->close();
        json_response(['status' => 'error', 'message' => 'Full name, Staff ID, email, and password are required.'], 400);
    }
    if (!$email) {
        $conn->close();
        json_response(['status' => 'error', 'message' => 'Please enter a valid email address.'], 400);
    }
    if (strlen($password) < 8) {
        $conn->close();
        json_response(['status' => 'error', 'message' => 'Password must be at least 8 characters.'], 400);
    }

    // Look up department name if dept_code provided
    $dept_name = $dept_code;
    if ($dept_code) {
        $stmt = $conn->prepare('SELECT name FROM departments WHERE code = ? LIMIT 1');
        if ($stmt) {
            $stmt->bind_param('s', $dept_code);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result) {
                $d = $result->fetch_assoc();
                if ($d) $dept_name = $d['name'];
                $result->free();
            }
            $stmt->close();
        }
    }

    $password_hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 10]);
    $role          = 'staff';
    $status        = 'pending'; // ← Must be approved by admin before login is allowed

    $stmt = $conn->prepare(
        'INSERT INTO users (matric_number, library_reg_number, full_name, email, phone,
                            password_hash, role, dept_code, department_name, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );

    if (!$stmt) {
        $conn->close();
        json_response(['status' => 'error', 'message' => 'Database error. Please try again.'], 500);
    }

    $stmt->bind_param('ssssssssss',
        $staff_id, $staff_id, $full_name, $email, $phone,
        $password_hash, $role, $dept_code, $dept_name, $status
    );

    if ($stmt->execute()) {
        $new_id = $stmt->insert_id;
        $stmt->close();
        $conn->close();
        audit_log('register_staff_pending', 'user', $new_id,
            "Staff '$full_name' ($staff_id) registered — pending admin approval");
        json_response([
            'status'  => 'success',
            'message' => 'Registration submitted! Your account is pending approval by the library administrator. You will be notified once approved.',
            'staff_id'=> $staff_id,
            'pending' => true,
        ]);
    } else {
        $err = $stmt->error;
        $stmt->close(); $conn->close();
        $msg = (strpos($err, '1062') !== false || strpos($err, 'Duplicate') !== false)
             ? 'This Staff ID or email is already registered.'
             : 'Registration failed. Please try again.';
        json_response(['status' => 'error', 'message' => $msg], 409);
    }
}


// ── LOGIN ─────────────────────────────────────────────────────
// Students log in with Library Registration Number (LIB-2025-0001)
// Staff/Admin use their staff ID.
// Both also accept school matric as fallback.
function login_user(): void {
    $conn  = db_connect();
    $input = strtoupper(trim($_POST['matric_number'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (!$input || !$password) {
        $conn->close();
        json_response(['status' => 'error', 'message' => 'Library registration number and password are required.'], 400);
    }

    // ── Rate limit check ───────────────────────────────────
    check_rate_limit($input);

    // ── Occasional cleanup of old attempt records ──────────
    if (rand(1, 20) === 1) cleanup_old_attempts();

    // ── Lookup user ────────────────────────────────────────
    $stmt = $conn->prepare(
        'SELECT id, library_reg_number, matric_number, school_matric,
                full_name, email, role, faculty_code, faculty_name,
                dept_code, department_name, level, admission_year,
                status, password_hash
         FROM users
         WHERE library_reg_number = ? OR matric_number = ? OR school_matric = ?
         LIMIT 1'
    );

    $user = null;
    if ($stmt) {
        $stmt->bind_param('sss', $input, $input, $input);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) { $user = $result->fetch_assoc(); $result->free(); }
        $stmt->close();
    }
    $conn->close();

    // ── Wrong credentials ──────────────────────────────────
    if (!$user || !password_verify($password, $user['password_hash'])) {
        record_failed_login($input);
        $conn2  = db_connect();
        $window = date('Y-m-d H:i:s', time() - (LOCKOUT_MINUTES * 60));
        $ip     = get_client_ip();
        $s2     = $conn2->prepare("SELECT COUNT(*) FROM login_attempts WHERE (identifier=? OR ip_address=?) AND attempted_at>?");
        $count  = 0;
        if ($s2) {
            $s2->bind_param('sss', $input, $ip, $window);
            $s2->execute();
            $r2 = $s2->get_result();
            if ($r2) { $count = (int)$r2->fetch_row()[0]; $r2->free(); }
            $s2->close();
        }
        $conn2->close();
        $remaining = max(0, MAX_LOGIN_ATTEMPTS - $count);
        $msg = $remaining > 0
            ? "Invalid library number or password. $remaining attempt(s) remaining."
            : "Account temporarily locked after too many failed attempts. Try again in " . LOCKOUT_MINUTES . " minutes.";
        json_response(['status' => 'error', 'message' => $msg], 401);
    }

    // ── Account status checks ──────────────────────────────
    if ($user['status'] === 'pending') {
        json_response([
            'status'  => 'error',
            'message' => 'Your account is awaiting admin approval. You will be notified once your account is activated.'
        ], 403);
    }
    if ($user['status'] === 'suspended') {
        json_response([
            'status'  => 'error',
            'message' => 'Your account has been suspended. Please contact the library administrator.'
        ], 403);
    }
    if ($user['status'] === 'inactive') {
        json_response([
            'status'  => 'error',
            'message' => 'Your account is inactive. Please contact the library.'
        ], 403);
    }

    // ── Successful login ───────────────────────────────────
    clear_login_attempts($input);

    start_session();
    session_regenerate_id(true);
    $_SESSION['user_id']            = $user['id'];
    $_SESSION['user_name']          = $user['full_name'];
    $_SESSION['user_role']          = $user['role'];
    $_SESSION['matric_number']      = $user['library_reg_number'] ?? $user['matric_number'];
    $_SESSION['library_reg_number'] = $user['library_reg_number'] ?? '';
    $_SESSION['school_matric']      = $user['school_matric'] ?? '';
    $_SESSION['last_activity']      = time();

    audit_log('login', 'user', (int)$user['id'], $user['full_name'] . ' logged in (' . $user['role'] . ')');

    unset($user['password_hash']);
    json_response(['status' => 'success', 'message' => 'Login successful', 'user' => $user]);
}


// ── LOGOUT ────────────────────────────────────────────────────
function logout_user(): void {
    start_session();
    $_SESSION = [];
    session_destroy();
    json_response(['status' => 'success', 'message' => 'Logged out successfully.']);
}


// ── RESERVE BOOK ─────────────────────────────────────────────
function reserve_book(): void {
    if (!is_logged_in()) json_response(['status' => 'error', 'message' => 'Please log in to reserve a book.'], 401);

    $conn    = db_connect();
    $user_id = (int)$_SESSION['user_id'];
    $book_id = (int)($_POST['book_id'] ?? 0);

    if (!$book_id) { $conn->close(); json_response(['status' => 'error', 'message' => 'Book ID is required.'], 400); }

    // Check book exists
    $stmt = $conn->prepare('SELECT id, title, available_copies FROM books WHERE id = ? LIMIT 1');
    $book = null;
    if ($stmt) {
        $stmt->bind_param('i', $book_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) { $book = $result->fetch_assoc(); $result->free(); }
        $stmt->close();
    }
    if (!$book) { $conn->close(); json_response(['status' => 'error', 'message' => 'Book not found.'], 404); }

    // Check for duplicate reservation
    $stmt = $conn->prepare(
        "SELECT id FROM reservations WHERE user_id = ? AND book_id = ? AND status IN ('pending','ready') LIMIT 1"
    );
    $existing = null;
    if ($stmt) {
        $stmt->bind_param('ii', $user_id, $book_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) { $existing = $result->fetch_assoc(); $result->free(); }
        $stmt->close();
    }
    if ($existing) { $conn->close(); json_response(['status' => 'error', 'message' => 'You already have an active reservation for this book.'], 409); }

    $expiry = date('Y-m-d', strtotime('+7 days'));
    $status = $book['available_copies'] > 0 ? 'ready' : 'pending';

    $stmt = $conn->prepare('INSERT INTO reservations (user_id, book_id, expiry_date, status) VALUES (?, ?, ?, ?)');
    if ($stmt) {
        $stmt->bind_param('iiss', $user_id, $book_id, $expiry, $status);
        $ok = $stmt->execute();
        $stmt->close();
        $conn->close();
        if ($ok) {
            $msg = $status === 'ready'
                ? '"' . $book['title'] . '" is ready for pickup at the circulation desk.'
                : 'You will be notified when "' . $book['title'] . '" becomes available.';
            json_response(['status' => 'success', 'message' => $msg, 'reservation_status' => $status]);
        }
    }
    $conn->close();
    json_response(['status' => 'error', 'message' => 'Reservation failed. Please try again.'], 500);
}


// ── BORROW BOOK (Staff only) ──────────────────────────────────
function borrow_book(): void {
    if (!is_logged_in()) json_response(['status' => 'error', 'message' => 'Not authenticated.'], 401);
    if ($_SESSION['user_role'] === 'student') json_response(['status' => 'error', 'message' => 'Only staff can issue loans.'], 403);

    $conn      = db_connect();
    $user_id   = (int)($_POST['user_id']   ?? 0);
    $book_id   = (int)($_POST['book_id']   ?? 0);
    $loan_days = (int)($_POST['loan_days'] ?? 14);
    $staff_id  = (int)$_SESSION['user_id'];

    if (!$user_id || !$book_id) { $conn->close(); json_response(['status' => 'error', 'message' => 'user_id and book_id are required.'], 400); }

    $stmt = $conn->prepare('SELECT id, title, available_copies FROM books WHERE id = ? LIMIT 1');
    $book = null;
    if ($stmt) {
        $stmt->bind_param('i', $book_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) { $book = $result->fetch_assoc(); $result->free(); }
        $stmt->close();
    }
    if (!$book || $book['available_copies'] < 1) {
        $conn->close();
        json_response(['status' => 'error', 'message' => 'No available copies of this book.'], 409);
    }

    $borrow_date = date('Y-m-d');
    $due_date    = date('Y-m-d', strtotime("+{$loan_days} days"));
    $active      = 'active';

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare('INSERT INTO loans (user_id, book_id, issued_by, borrow_date, due_date, status) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->bind_param('iiisss', $user_id, $book_id, $staff_id, $borrow_date, $due_date, $active);
        $stmt->execute();
        $loan_id = $stmt->insert_id;
        $stmt->close();

        $stmt = $conn->prepare('UPDATE books SET available_copies = available_copies - 1 WHERE id = ?');
        $stmt->bind_param('i', $book_id);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        $conn->close();
        json_response(['status' => 'success', 'message' => '"' . $book['title'] . '" issued. Due: ' . $due_date, 'loan_id' => $loan_id, 'due_date' => $due_date]);
    } catch (Exception $e) {
        $conn->rollback();
        $conn->close();
        json_response(['status' => 'error', 'message' => 'Loan failed: ' . $e->getMessage()], 500);
    }
}


// ── RETURN BOOK ───────────────────────────────────────────────
function return_book(): void {
    if (!is_logged_in()) json_response(['status' => 'error', 'message' => 'Not authenticated.'], 401);

    $conn    = db_connect();
    $loan_id = (int)($_POST['loan_id'] ?? 0);
    if (!$loan_id) { $conn->close(); json_response(['status' => 'error', 'message' => 'loan_id is required.'], 400); }

    $stmt = $conn->prepare("SELECT id, book_id, user_id, due_date, status FROM loans WHERE id = ? LIMIT 1");
    $loan = null;
    if ($stmt) {
        $stmt->bind_param('i', $loan_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) { $loan = $result->fetch_assoc(); $result->free(); }
        $stmt->close();
    }

    if (!$loan || $loan['status'] === 'returned') {
        $conn->close();
        json_response(['status' => 'error', 'message' => 'Loan not found or already returned.'], 404);
    }

    $today        = date('Y-m-d');
    $fine         = 0.00;
    $fine_per_day = 50.00;

    if ($today > $loan['due_date']) {
        $days_late = (int)((strtotime($today) - strtotime($loan['due_date'])) / 86400);
        $fine      = round($days_late * $fine_per_day, 2);
    }

    $conn->begin_transaction();
    try {
        $returned = 'returned';
        $stmt = $conn->prepare('UPDATE loans SET status = ?, return_date = ?, fine_amount = ? WHERE id = ?');
        $stmt->bind_param('ssdi', $returned, $today, $fine, $loan_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare('UPDATE books SET available_copies = available_copies + 1 WHERE id = ?');
        $stmt->bind_param('i', $loan['book_id']);
        $stmt->execute();
        $stmt->close();

        if ($fine > 0) {
            $reason = 'Overdue return fine';
            $stmt   = $conn->prepare('INSERT INTO fines (loan_id, user_id, amount, reason) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('iids', $loan_id, $loan['user_id'], $fine, $reason);
            $stmt->execute();
            $stmt->close();
        }

        $conn->commit();
        $conn->close();
        json_response([
            'status'  => 'success',
            'message' => 'Book returned successfully.',
            'fine'    => $fine > 0 ? 'Fine of ₦' . number_format($fine, 2) . ' applied.' : 'No fine.'
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        $conn->close();
        json_response(['status' => 'error', 'message' => 'Return failed: ' . $e->getMessage()], 500);
    }
}


// ── RENEW LOAN ────────────────────────────────────────────────
function renew_loan(): void {
    if (!is_logged_in()) json_response(['status' => 'error', 'message' => 'Not authenticated.'], 401);

    $conn    = db_connect();
    $loan_id = (int)($_POST['loan_id'] ?? 0);
    if (!$loan_id) { $conn->close(); json_response(['status' => 'error', 'message' => 'loan_id is required.'], 400); }

    $stmt = $conn->prepare("SELECT id, due_date FROM loans WHERE id = ? AND status = 'active' LIMIT 1");
    $loan = null;
    if ($stmt) {
        $stmt->bind_param('i', $loan_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) { $loan = $result->fetch_assoc(); $result->free(); }
        $stmt->close();
    }

    if (!$loan) { $conn->close(); json_response(['status' => 'error', 'message' => 'Active loan not found.'], 404); }

    $new_due = date('Y-m-d', strtotime($loan['due_date'] . ' +14 days'));
    $stmt    = $conn->prepare('UPDATE loans SET due_date = ? WHERE id = ?');
    if ($stmt) {
        $stmt->bind_param('si', $new_due, $loan_id);
        $stmt->execute();
        $stmt->close();
    }
    $conn->close();
    json_response(['status' => 'success', 'message' => 'Loan renewed. New due date: ' . $new_due, 'new_due_date' => $new_due]);
}


// ── PAY FINE ──────────────────────────────────────────────────
function pay_fine(): void {
    if (!is_logged_in()) json_response(['status' => 'error', 'message' => 'Not authenticated.'], 401);

    $conn    = db_connect();
    $loan_id = (int)($_POST['loan_id'] ?? 0);
    $now     = date('Y-m-d H:i:s');

    $stmt = $conn->prepare("UPDATE fines SET paid = 1, paid_at = ? WHERE loan_id = ? AND paid = 0");
    $affected = 0;
    if ($stmt) {
        $stmt->bind_param('si', $now, $loan_id);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
    }

    $stmt = $conn->prepare("UPDATE loans SET fine_paid = 1 WHERE id = ?");
    if ($stmt) { $stmt->bind_param('i', $loan_id); $stmt->execute(); $stmt->close(); }

    $conn->close();
    json_response([
        'status'  => $affected > 0 ? 'success' : 'info',
        'message' => $affected > 0 ? 'Fine marked as paid.' : 'No unpaid fine found for this loan.'
    ]);
}


// ============================================================
//  ── ADMIN FUNCTIONS (role = admin only) ──────────────────
// ============================================================

/** Guard: abort if caller is not an admin */
function require_admin(): void {
    if (!is_logged_in() || $_SESSION['user_role'] !== 'admin') {
        json_response(['status' => 'error', 'message' => 'Admin access required.'], 403);
    }
}

// ── ADMIN: Dashboard stats ────────────────────────────────────
function admin_get_stats(): void {
    require_admin();
    $conn   = db_connect();
    $result = $conn->query(
        'SELECT
            (SELECT COUNT(*) FROM users  WHERE role = "student")              AS total_students,
            (SELECT COUNT(*) FROM users  WHERE role = "staff")                AS total_staff,
            (SELECT COUNT(*) FROM books)                                       AS total_books,
            (SELECT COUNT(*) FROM loans  WHERE status = "active")             AS active_loans,
            (SELECT COUNT(*) FROM loans  WHERE status = "overdue")            AS overdue_loans,
            (SELECT COUNT(*) FROM loans)                                       AS total_loans,
            (SELECT COALESCE(SUM(amount),0) FROM fines WHERE paid = 0)        AS unpaid_fines,
            (SELECT COUNT(*) FROM reservations WHERE status = "pending")      AS pending_reservations'
    );
    $stats = [];
    if ($result) { $stats = $result->fetch_assoc(); $result->free(); }
    $conn->close();
    json_response(['status' => 'success', 'data' => $stats]);
}

// ── ADMIN: All loans with full student + book detail ──────────
function admin_get_loans(): void {
    require_admin();
    $conn   = db_connect();
    $filter = sanitise($_GET['filter'] ?? 'all');   // all | active | overdue | returned
    $search = sanitise($_GET['search'] ?? '');
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = 20;
    $offset = ($page - 1) * $limit;

    $where  = ['1=1'];
    $params = [];
    $types  = '';

    if ($filter !== 'all') {
        $where[]  = 'l.status = ?';
        $params[] = $filter;
        $types   .= 's';
    }
    if ($search) {
        $like     = "%$search%";
        $where[]  = '(u.matric_number LIKE ? OR u.full_name LIKE ? OR b.title LIKE ?)';
        $params[] = $like; $params[] = $like; $params[] = $like;
        $types   .= 'sss';
    }

    $whereStr = implode(' AND ', $where);

    // Total count
    $count_sql  = "SELECT COUNT(*) FROM loans l JOIN users u ON u.id = l.user_id JOIN books b ON b.id = l.book_id WHERE $whereStr";
    $count_stmt = $conn->prepare($count_sql);
    $total      = 0;
    if ($count_stmt) {
        if ($params) $count_stmt->bind_param($types, ...$params);
        $count_stmt->execute();
        $cr = $count_stmt->get_result();
        if ($cr) { $total = (int)$cr->fetch_row()[0]; $cr->free(); }
        $count_stmt->close();
    }

    // Paginated rows
    $sql = "SELECT
                l.id            AS loan_id,
                l.borrow_date,
                l.due_date,
                l.return_date,
                l.status,
                l.fine_amount,
                l.fine_paid,
                u.id            AS student_id,
                u.library_reg_number,
                u.matric_number,
                u.school_matric,
                u.full_name     AS student_name,
                u.faculty_name,
                u.department_name,
                u.level,
                u.phone,
                u.email,
                b.id            AS book_id,
                b.title         AS book_title,
                b.author        AS book_author,
                b.isbn,
                b.cover_emoji,
                b.cover_color,
                b.category,
                b.department_name AS book_dept,
                b.location
            FROM loans l
            JOIN users u ON u.id = l.user_id
            JOIN books b ON b.id = l.book_id
            WHERE $whereStr
            ORDER BY l.created_at DESC
            LIMIT ? OFFSET ?";

    $params[] = $limit;
    $params[] = $offset;
    $types   .= 'ii';

    $stmt  = $conn->prepare($sql);
    $loans = [];
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) $loans[] = $row;
            $result->free();
        }
        $stmt->close();
    }
    $conn->close();
    json_response([
        'status' => 'success',
        'data'   => $loans,
        'total'  => $total,
        'page'   => $page,
        'pages'  => (int)ceil($total / $limit)
    ]);
}

// ── ADMIN: All students ───────────────────────────────────────
function admin_get_students(): void {
    require_admin();
    $conn   = db_connect();
    $search = sanitise($_GET['search'] ?? '');
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = 20;
    $offset = ($page - 1) * $limit;

    $where  = ["role = 'student'"];
    $params = [];
    $types  = '';

    if ($search) {
        $like     = "%$search%";
        $where[]  = '(matric_number LIKE ? OR full_name LIKE ? OR email LIKE ? OR department_name LIKE ?)';
        $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
        $types   .= 'ssss';
    }

    $whereStr = implode(' AND ', $where);

    // Total count
    $cstmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE $whereStr");
    $total = 0;
    if ($cstmt) {
        if ($params) $cstmt->bind_param($types, ...$params);
        $cstmt->execute();
        $cr = $cstmt->get_result();
        if ($cr) { $total = (int)$cr->fetch_row()[0]; $cr->free(); }
        $cstmt->close();
    }

    $params[] = $limit;
    $params[] = $offset;
    $types   .= 'ii';

    $stmt     = $conn->prepare(
        "SELECT id, library_reg_number, matric_number, school_matric,
                full_name, email, phone, faculty_name, department_name,
                level, status, created_at
         FROM users WHERE $whereStr ORDER BY created_at DESC LIMIT ? OFFSET ?"
    );
    $students = [];
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) $students[] = $row;
            $result->free();
        }
        $stmt->close();
    }
    $conn->close();
    json_response([
        'status' => 'success',
        'data'   => $students,
        'total'  => $total,
        'page'   => $page,
        'pages'  => (int)ceil($total / $limit)
    ]);
}

// ── ADMIN: All staff accounts (active, suspended, pending, inactive) ──
function admin_get_staff(): void {
    require_admin();
    $conn   = db_connect();
    $search = sanitise($_GET['search'] ?? '');
    $status_filter = sanitise($_GET['status'] ?? ''); // '', active, pending, suspended, inactive
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = 20;
    $offset = ($page - 1) * $limit;

    $where  = ["role = 'staff'"];
    $params = [];
    $types  = '';

    if ($status_filter) {
        $where[]  = 'status = ?';
        $params[] = $status_filter;
        $types   .= 's';
    }
    if ($search) {
        $like     = "%$search%";
        $where[]  = '(matric_number LIKE ? OR library_reg_number LIKE ? OR full_name LIKE ? OR email LIKE ? OR department_name LIKE ?)';
        $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
        $types   .= 'sssss';
    }

    $whereStr = implode(' AND ', $where);

    // Total count
    $cstmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE $whereStr");
    $total = 0;
    if ($cstmt) {
        if ($params) $cstmt->bind_param($types, ...$params);
        $cstmt->execute();
        $cr = $cstmt->get_result();
        if ($cr) { $total = (int)$cr->fetch_row()[0]; $cr->free(); }
        $cstmt->close();
    }

    $params[] = $limit;
    $params[] = $offset;
    $types   .= 'ii';

    $stmt  = $conn->prepare(
        "SELECT id, library_reg_number, matric_number, full_name, email, phone,
                dept_code, department_name, status, created_at, updated_at
         FROM users WHERE $whereStr ORDER BY
            CASE status WHEN 'pending' THEN 0 ELSE 1 END,
            created_at DESC
         LIMIT ? OFFSET ?"
    );
    $staff = [];
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) {
            while ($row = $result->fetch_assoc()) $staff[] = $row;
            $result->free();
        }
        $stmt->close();
    }

    // Status summary counts
    $summary = ['active'=>0,'pending'=>0,'suspended'=>0,'inactive'=>0];
    $sr = $conn->query("SELECT status, COUNT(*) AS c FROM users WHERE role='staff' GROUP BY status");
    if ($sr) {
        while ($row = $sr->fetch_assoc()) {
            $summary[$row['status']] = (int)$row['c'];
        }
        $sr->free();
    }

    $conn->close();
    json_response([
        'status'  => 'success',
        'data'    => $staff,
        'total'   => $total,
        'page'    => $page,
        'pages'   => (int)ceil($total / $limit),
        'summary' => $summary,
    ]);
}
function admin_search_student(): void {
    require_admin();
    $conn   = db_connect();
    $q      = strtoupper(sanitise($_GET['matric'] ?? ''));
    if (!$q) { $conn->close(); json_response(['status' => 'error', 'message' => 'Search value required.'], 400); }

    // Search by library_reg_number OR school_matric OR name
    $like    = "%$q%";
    $stmt    = $conn->prepare(
        'SELECT id, library_reg_number, matric_number, school_matric,
                full_name, email, phone, faculty_name, department_name,
                level, status
         FROM users
         WHERE library_reg_number = ? OR matric_number = ?
            OR school_matric = ? OR full_name LIKE ?
         LIMIT 1'
    );
    $student = null;
    if ($stmt) {
        $stmt->bind_param('ssss', $q, $q, $q, $like);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) { $student = $result->fetch_assoc(); $result->free(); }
        $stmt->close();
    }
    $conn->close();
    if (!$student) json_response(['status' => 'error', 'message' => 'Student not found.'], 404);
    json_response(['status' => 'success', 'data' => $student]);
}

// ── ADMIN: Search book by title/ISBN (for issue-loan form) ───
function admin_search_book(): void {
    require_admin();
    $conn  = db_connect();
    $q     = sanitise($_GET['q'] ?? '');
    if (strlen($q) < 2) { $conn->close(); json_response(['status' => 'error', 'message' => 'Query too short.'], 400); }

    $like  = "%$q%";
    $stmt  = $conn->prepare(
        'SELECT id, title, author, isbn, available_copies, total_copies,
                cover_emoji, cover_color, department_name, resource_type, location
         FROM books WHERE title LIKE ? OR isbn LIKE ? OR author LIKE ?
         ORDER BY title ASC LIMIT 10'
    );
    $books = [];
    if ($stmt) {
        $stmt->bind_param('sss', $like, $like, $like);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) { while ($row = $result->fetch_assoc()) $books[] = $row; $result->free(); }
        $stmt->close();
    }
    $conn->close();
    json_response(['status' => 'success', 'data' => $books]);
}

// ── ADMIN: Full loan detail (student + book + fine history) ──
function admin_get_loan_detail(): void {
    require_admin();
    $conn    = db_connect();
    $loan_id = (int)($_GET['loan_id'] ?? 0);
    if (!$loan_id) { $conn->close(); json_response(['status' => 'error', 'message' => 'loan_id required.'], 400); }

    $stmt = $conn->prepare(
        'SELECT l.*,
                u.matric_number, u.full_name AS student_name, u.email, u.phone,
                u.faculty_name, u.department_name, u.level,
                b.title AS book_title, b.author, b.isbn, b.cover_emoji,
                b.cover_color, b.publisher, b.pub_year, b.location, b.department_name AS book_dept
         FROM loans l
         JOIN users u ON u.id = l.user_id
         JOIN books b ON b.id = l.book_id
         WHERE l.id = ? LIMIT 1'
    );
    $loan = null;
    if ($stmt) {
        $stmt->bind_param('i', $loan_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) { $loan = $result->fetch_assoc(); $result->free(); }
        $stmt->close();
    }
    if (!$loan) { $conn->close(); json_response(['status' => 'error', 'message' => 'Loan not found.'], 404); }

    // Fine records for this loan
    $stmt  = $conn->prepare('SELECT * FROM fines WHERE loan_id = ? ORDER BY created_at DESC');
    $fines = [];
    if ($stmt) {
        $stmt->bind_param('i', $loan_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) { while ($row = $result->fetch_assoc()) $fines[] = $row; $result->free(); }
        $stmt->close();
    }
    $conn->close();
    json_response(['status' => 'success', 'data' => ['loan' => $loan, 'fines' => $fines]]);
}

// ── ADMIN: Issue loan to a student ───────────────────────────
function admin_issue_loan(): void {
    require_admin();
    $conn      = db_connect();
    $matric    = strtoupper(sanitise($_POST['matric_number'] ?? ''));
    $book_id   = (int)($_POST['book_id']   ?? 0);
    $loan_days = (int)($_POST['loan_days'] ?? 14);
    $notes     = sanitise($_POST['notes']  ?? '');
    $staff_id  = (int)$_SESSION['user_id'];

    if (!$matric || !$book_id) {
        $conn->close();
        json_response(['status' => 'error', 'message' => 'Matric number and Book ID are required.'], 400);
    }

    // Get student
    $stmt    = $conn->prepare("SELECT id, full_name, status FROM users WHERE matric_number = ? LIMIT 1");
    $student = null;
    if ($stmt) {
        $stmt->bind_param('s', $matric);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) { $student = $result->fetch_assoc(); $result->free(); }
        $stmt->close();
    }
    if (!$student) { $conn->close(); json_response(['status' => 'error', 'message' => 'Student not found.'], 404); }
    if ($student['status'] !== 'active') {
        $conn->close();
        json_response(['status' => 'error', 'message' => 'Student account is ' . $student['status'] . '.'], 403);
    }

    // Get book
    $stmt = $conn->prepare('SELECT id, title, available_copies FROM books WHERE id = ? LIMIT 1');
    $book = null;
    if ($stmt) {
        $stmt->bind_param('i', $book_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) { $book = $result->fetch_assoc(); $result->free(); }
        $stmt->close();
    }
    if (!$book) { $conn->close(); json_response(['status' => 'error', 'message' => 'Book not found.'], 404); }
    if ($book['available_copies'] < 1) {
        $conn->close();
        json_response(['status' => 'error', 'message' => 'No available copies of "' . $book['title'] . '".'], 409);
    }

    // Check if student already has this book on loan
    $stmt = $conn->prepare("SELECT id FROM loans WHERE user_id = ? AND book_id = ? AND status = 'active' LIMIT 1");
    $existing = null;
    if ($stmt) {
        $stmt->bind_param('ii', $student['id'], $book_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) { $existing = $result->fetch_assoc(); $result->free(); }
        $stmt->close();
    }
    if ($existing) {
        $conn->close();
        json_response(['status' => 'error', 'message' => 'This student already has "' . $book['title'] . '" on loan.'], 409);
    }

    $borrow_date = date('Y-m-d');
    $due_date    = date('Y-m-d', strtotime("+{$loan_days} days"));
    $status      = 'active';
    $uid         = (int)$student['id'];

    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare(
            'INSERT INTO loans (user_id, book_id, issued_by, borrow_date, due_date, status, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param('iiissss', $uid, $book_id, $staff_id, $borrow_date, $due_date, $status, $notes);
        $stmt->execute();
        $loan_id = $stmt->insert_id;
        $stmt->close();

        $stmt = $conn->prepare('UPDATE books SET available_copies = available_copies - 1 WHERE id = ?');
        $stmt->bind_param('i', $book_id);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
        $conn->close();
        json_response([
            'status'   => 'success',
            'message'  => '"' . $book['title'] . '" issued to ' . $student['full_name'] . '. Due: ' . $due_date,
            'loan_id'  => $loan_id,
            'due_date' => $due_date,
            'student'  => $student['full_name'],
            'book'     => $book['title'],
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        $conn->close();
        json_response(['status' => 'error', 'message' => 'Failed to issue loan: ' . $e->getMessage()], 500);
    }
}

// ── ADMIN: Process a book return ─────────────────────────────
function admin_return_book(): void {
    require_admin();
    $conn    = db_connect();
    $loan_id = (int)($_POST['loan_id'] ?? 0);
    if (!$loan_id) { $conn->close(); json_response(['status' => 'error', 'message' => 'loan_id required.'], 400); }

    $stmt = $conn->prepare("SELECT id, book_id, user_id, due_date, status FROM loans WHERE id = ? LIMIT 1");
    $loan = null;
    if ($stmt) {
        $stmt->bind_param('i', $loan_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result) { $loan = $result->fetch_assoc(); $result->free(); }
        $stmt->close();
    }
    if (!$loan) { $conn->close(); json_response(['status' => 'error', 'message' => 'Loan not found.'], 404); }
    if ($loan['status'] === 'returned') { $conn->close(); json_response(['status' => 'info', 'message' => 'Book already returned.']); }

    $today        = date('Y-m-d');
    $fine         = 0.00;
    $fine_per_day = 50.00;
    if ($today > $loan['due_date']) {
        $days_late = (int)((strtotime($today) - strtotime($loan['due_date'])) / 86400);
        $fine      = round($days_late * $fine_per_day, 2);
    }

    $conn->begin_transaction();
    try {
        $returned = 'returned';
        $stmt = $conn->prepare('UPDATE loans SET status = ?, return_date = ?, fine_amount = ? WHERE id = ?');
        $stmt->bind_param('ssdi', $returned, $today, $fine, $loan_id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare('UPDATE books SET available_copies = available_copies + 1 WHERE id = ?');
        $stmt->bind_param('i', $loan['book_id']);
        $stmt->execute();
        $stmt->close();

        if ($fine > 0) {
            $reason = 'Overdue return fine';
            $stmt   = $conn->prepare('INSERT INTO fines (loan_id, user_id, amount, reason) VALUES (?, ?, ?, ?)');
            $stmt->bind_param('iids', $loan_id, $loan['user_id'], $fine, $reason);
            $stmt->execute();
            $stmt->close();
        }

        $conn->commit();
        $conn->close();
        json_response([
            'status'  => 'success',
            'message' => 'Book returned successfully.',
            'fine'    => $fine > 0 ? '₦' . number_format($fine, 2) : '0'
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        $conn->close();
        json_response(['status' => 'error', 'message' => 'Return failed: ' . $e->getMessage()], 500);
    }
}

// ── ADMIN: Waive (cancel) a fine ─────────────────────────────
function admin_waive_fine(): void {
    require_admin();
    $conn    = db_connect();
    $loan_id = (int)($_POST['loan_id'] ?? 0);
    $now     = date('Y-m-d H:i:s');
    if (!$loan_id) { $conn->close(); json_response(['status' => 'error', 'message' => 'loan_id required.'], 400); }

    $stmt = $conn->prepare("UPDATE fines SET paid = 1, paid_at = ?, reason = CONCAT(reason, ' [Waived by Admin]') WHERE loan_id = ?");
    if ($stmt) { $stmt->bind_param('si', $now, $loan_id); $stmt->execute(); $stmt->close(); }

    $stmt = $conn->prepare("UPDATE loans SET fine_paid = 1, fine_amount = 0 WHERE id = ?");
    if ($stmt) { $stmt->bind_param('i', $loan_id); $stmt->execute(); $stmt->close(); }

    $conn->close();
    json_response(['status' => 'success', 'message' => 'Fine waived successfully.']);
}

// ── ADMIN: Add a new book ─────────────────────────────────────
function admin_add_book(): void {
    require_admin();
    $conn = db_connect();

    $title       = sanitise($_POST['title']       ?? '');
    $author      = sanitise($_POST['author']      ?? '');
    $isbn        = sanitise($_POST['isbn']        ?? '') ?: null;
    $publisher   = sanitise($_POST['publisher']   ?? '') ?: null;
    $pub_year    = (int)($_POST['pub_year']       ?? 0) ?: null;
    $category    = sanitise($_POST['category']    ?? '');
    $dept_code   = strtoupper(sanitise($_POST['dept_code'] ?? ''));
    $dept_name   = sanitise($_POST['dept_name']   ?? '');
    $total       = max(1, (int)($_POST['total_copies'] ?? 1));
    $type        = sanitise($_POST['resource_type'] ?? 'physical');
    $location    = sanitise($_POST['location']    ?? '') ?: null;
    $emoji       = sanitise($_POST['cover_emoji'] ?? '📘');
    $color       = sanitise($_POST['cover_color'] ?? '#e8f5ec');

    if (!$title || !$author) {
        $conn->close();
        json_response(['status' => 'error', 'message' => 'Title and Author are required.'], 400);
    }

    $stmt = $conn->prepare(
        'INSERT INTO books (title, author, isbn, publisher, pub_year, category,
                            dept_code, department_name, total_copies, available_copies,
                            resource_type, location, cover_emoji, cover_color)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$stmt) { $conn->close(); json_response(['status' => 'error', 'message' => 'DB error: ' . $conn->error], 500); }

    $stmt->bind_param('ssssssssiissss',
        $title, $author, $isbn, $publisher, $pub_year,
        $category, $dept_code, $dept_name,
        $total, $total,
        $type, $location, $emoji, $color
    );

    if ($stmt->execute()) {
        $id = $stmt->insert_id;
        $stmt->close(); $conn->close();
        json_response(['status' => 'success', 'message' => '"' . $title . '" added to the catalog.', 'book_id' => $id]);
    } else {
        $err = $stmt->error; $stmt->close(); $conn->close();
        json_response(['status' => 'error', 'message' => 'Failed to add book: ' . $err], 500);
    }
}

// ── ADMIN: Update book available copies / details ─────────────
function admin_update_book(): void {
    require_admin();
    $conn    = db_connect();
    $book_id = (int)($_POST['book_id'] ?? 0);
    if (!$book_id) { $conn->close(); json_response(['status' => 'error', 'message' => 'book_id required.'], 400); }

    $title       = sanitise($_POST['title']        ?? '');
    $author      = sanitise($_POST['author']       ?? '');
    $isbn        = sanitise($_POST['isbn']         ?? '') ?: null;
    $publisher   = sanitise($_POST['publisher']    ?? '') ?: null;
    $pub_year    = (int)($_POST['pub_year']        ?? 0) ?: null;
    $category    = sanitise($_POST['category']     ?? '');
    $dept_code   = strtoupper(sanitise($_POST['dept_code']  ?? ''));
    $dept_name   = sanitise($_POST['dept_name']    ?? '');
    $resource    = sanitise($_POST['resource_type'] ?? 'physical');
    $total       = max(1, (int)($_POST['total_copies']     ?? 1));
    $available   = max(0, (int)($_POST['available_copies'] ?? 0));
    $location    = sanitise($_POST['location']     ?? '') ?: null;
    $emoji       = sanitise($_POST['cover_emoji']  ?? '📘');
    $color       = sanitise($_POST['cover_color']  ?? '#e8f5ec');
    $digital_url = sanitise($_POST['digital_url']  ?? '') ?: null;

    if (!$title || !$author) {
        $conn->close();
        json_response(['status' => 'error', 'message' => 'Title and Author are required.'], 400);
    }

    // Make sure available copies never exceeds total
    if ($available > $total) $available = $total;

    $stmt = $conn->prepare(
        'UPDATE books SET
            title            = ?,
            author           = ?,
            isbn             = ?,
            publisher        = ?,
            pub_year         = ?,
            category         = ?,
            dept_code        = ?,
            department_name  = ?,
            resource_type    = ?,
            total_copies     = ?,
            available_copies = ?,
            location         = ?,
            cover_emoji      = ?,
            cover_color      = ?,
            digital_url      = ?
         WHERE id = ?'
    );

    if (!$stmt) {
        $conn->close();
        json_response(['status' => 'error', 'message' => 'DB error: ' . $conn->error], 500);
    }

    // 16 params: s,s,s,s,i,s,s,s,s,i,i,s,s,s,s,i
    $stmt->bind_param(
        'ssssissssiissssi',
        $title, $author, $isbn, $publisher, $pub_year,
        $category, $dept_code, $dept_name, $resource,
        $total, $available, $location,
        $emoji, $color, $digital_url,
        $book_id
    );

    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        audit_log('update_book', 'book', $book_id, "Book '$title' updated");
        json_response(['status' => 'success', 'message' => '"' . $title . '" updated successfully.']);
    } else {
        $err = $stmt->error;
        $stmt->close(); $conn->close();
        // Duplicate ISBN
        $msg = (strpos($err, '1062') !== false)
            ? 'Another book already uses this ISBN.'
            : 'Update failed: ' . $err;
        json_response(['status' => 'error', 'message' => $msg], 500);
    }
}

// ── ADMIN: Post an announcement ───────────────────────────────
function admin_add_announcement(): void {
    require_admin();
    $conn      = db_connect();
    $title     = sanitise($_POST['title']     ?? '');
    $body      = sanitise($_POST['body']      ?? '');
    $tag       = sanitise($_POST['tag']       ?? 'Notice');
    $tag_color = sanitise($_POST['tag_color'] ?? '#1a5c2e');
    $admin_id  = (int)$_SESSION['user_id'];

    if (!$title || !$body) { $conn->close(); json_response(['status' => 'error', 'message' => 'Title and body are required.'], 400); }

    $stmt = $conn->prepare(
        'INSERT INTO announcements (title, body, tag, tag_color, created_by) VALUES (?, ?, ?, ?, ?)'
    );
    if ($stmt) {
        $stmt->bind_param('ssssi', $title, $body, $tag, $tag_color, $admin_id);
        if ($stmt->execute()) {
            $stmt->close(); $conn->close();
            json_response(['status' => 'success', 'message' => 'Announcement posted successfully.']);
        }
        $err = $stmt->error; $stmt->close(); $conn->close();
        json_response(['status' => 'error', 'message' => 'Failed: ' . $err], 500);
    }
    $conn->close();
    json_response(['status' => 'error', 'message' => 'DB error.'], 500);
}


// ============================================================
//  ── STUDENT PORTAL FUNCTIONS ─────────────────────────────
// ============================================================

/**
 * GET ?action=student_dashboard
 * Returns logged-in student's loans, fines summary, and reservations
 */
function student_dashboard(): void {
    if (!is_logged_in())
        json_response(['status' => 'error', 'message' => 'Not authenticated.'], 401);

    $conn    = db_connect();
    $user_id = (int)$_SESSION['user_id'];

    // Active + overdue loans
    $stmt  = $conn->prepare(
        "SELECT l.id, l.borrow_date, l.due_date, l.return_date,
                l.status, l.fine_amount, l.fine_paid,
                b.id AS book_id, b.title, b.author, b.cover_emoji,
                b.cover_color, b.isbn, b.department_name
         FROM loans l JOIN books b ON b.id = l.book_id
         WHERE l.user_id = ? AND l.status IN ('active','overdue')
         ORDER BY l.due_date ASC"
    );
    $active_loans = [];
    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $r = $stmt->get_result();
        if ($r) { while ($row = $r->fetch_assoc()) $active_loans[] = $row; $r->free(); }
        $stmt->close();
    }

    // Loan history (returned/lost, last 10)
    $stmt    = $conn->prepare(
        "SELECT l.id, l.borrow_date, l.due_date, l.return_date,
                l.status, l.fine_amount, l.fine_paid,
                b.title, b.author, b.cover_emoji
         FROM loans l JOIN books b ON b.id = l.book_id
         WHERE l.user_id = ? AND l.status IN ('returned','lost')
         ORDER BY l.return_date DESC LIMIT 10"
    );
    $history = [];
    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $r = $stmt->get_result();
        if ($r) { while ($row = $r->fetch_assoc()) $history[] = $row; $r->free(); }
        $stmt->close();
    }

    // Reservations
    $stmt         = $conn->prepare(
        "SELECT r.id, r.status, r.reserved_at, r.expiry_date,
                b.title, b.author, b.cover_emoji, b.cover_color, b.available_copies
         FROM reservations r JOIN books b ON b.id = r.book_id
         WHERE r.user_id = ? AND r.status IN ('pending','ready')
         ORDER BY r.reserved_at DESC"
    );
    $reservations = [];
    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $r = $stmt->get_result();
        if ($r) { while ($row = $r->fetch_assoc()) $reservations[] = $row; $r->free(); }
        $stmt->close();
    }

    // Unpaid fines total
    $stmt      = $conn->prepare(
        "SELECT COALESCE(SUM(amount),0) AS total FROM fines WHERE user_id = ? AND paid = 0"
    );
    $fine_total = 0;
    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $r = $stmt->get_result();
        if ($r) { $fine_total = (float)($r->fetch_row()[0] ?? 0); $r->free(); }
        $stmt->close();
    }

    // Count totals
    $stmt   = $conn->prepare(
        "SELECT
            COUNT(*) AS total_loans,
            SUM(status IN ('active','overdue')) AS active_count,
            SUM(status = 'overdue')             AS overdue_count,
            SUM(status = 'returned')            AS returned_count
         FROM loans WHERE user_id = ?"
    );
    $counts = ['total_loans'=>0,'active_count'=>0,'overdue_count'=>0,'returned_count'=>0];
    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $r = $stmt->get_result();
        if ($r) { $counts = $r->fetch_assoc() ?? $counts; $r->free(); }
        $stmt->close();
    }

    $conn->close();
    json_response([
        'status'       => 'success',
        'active_loans' => $active_loans,
        'history'      => $history,
        'reservations' => $reservations,
        'fine_total'   => $fine_total,
        'counts'       => $counts,
    ]);
}

/**
 * GET ?action=student_reservations
 */
function student_reservations(): void {
    if (!is_logged_in())
        json_response(['status' => 'error', 'message' => 'Not authenticated.'], 401);

    $conn    = db_connect();
    $user_id = (int)$_SESSION['user_id'];

    $stmt  = $conn->prepare(
        "SELECT r.id, r.status, r.reserved_at, r.expiry_date,
                b.id AS book_id, b.title, b.author,
                b.cover_emoji, b.cover_color, b.available_copies
         FROM reservations r JOIN books b ON b.id = r.book_id
         WHERE r.user_id = ?
         ORDER BY r.reserved_at DESC LIMIT 20"
    );
    $rows  = [];
    if ($stmt) {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $r = $stmt->get_result();
        if ($r) { while ($row = $r->fetch_assoc()) $rows[] = $row; $r->free(); }
        $stmt->close();
    }
    $conn->close();
    json_response(['status' => 'success', 'data' => $rows]);
}

/**
 * POST ?action=student_renew_loan
 * Body: loan_id
 * Students can only renew their own active loans
 */
function student_renew_loan(): void {
    if (!is_logged_in())
        json_response(['status' => 'error', 'message' => 'Not authenticated.'], 401);

    $conn    = db_connect();
    $user_id = (int)$_SESSION['user_id'];
    $loan_id = (int)($_POST['loan_id'] ?? 0);

    if (!$loan_id) { $conn->close(); json_response(['status' => 'error', 'message' => 'loan_id required.'], 400); }

    // Ensure the loan belongs to this student
    $stmt = $conn->prepare(
        "SELECT id, due_date, status FROM loans
         WHERE id = ? AND user_id = ? AND status IN ('active','overdue') LIMIT 1"
    );
    $loan = null;
    if ($stmt) {
        $stmt->bind_param('ii', $loan_id, $user_id);
        $stmt->execute();
        $r = $stmt->get_result();
        if ($r) { $loan = $r->fetch_assoc(); $r->free(); }
        $stmt->close();
    }
    if (!$loan) {
        $conn->close();
        json_response(['status' => 'error', 'message' => 'Active loan not found or does not belong to you.'], 404);
    }

    // Overdue loans: student must pay fine before renewing
    if ($loan['status'] === 'overdue') {
        $conn->close();
        json_response(['status' => 'error', 'message' => 'This loan is overdue. Please clear your fine at the library desk before renewing.'], 403);
    }

    $new_due = date('Y-m-d', strtotime($loan['due_date'] . ' +14 days'));
    $stmt    = $conn->prepare('UPDATE loans SET due_date = ? WHERE id = ?');
    if ($stmt) {
        $stmt->bind_param('si', $new_due, $loan_id);
        $stmt->execute();
        $stmt->close();
    }
    $conn->close();
    json_response([
        'status'       => 'success',
        'message'      => 'Loan renewed successfully. New due date: ' . $new_due,
        'new_due_date' => $new_due
    ]);
}

/**
 * POST ?action=student_cancel_reservation
 * Body: reservation_id
 */
function student_cancel_reservation(): void {
    if (!is_logged_in())
        json_response(['status' => 'error', 'message' => 'Not authenticated.'], 401);

    $conn           = db_connect();
    $user_id        = (int)$_SESSION['user_id'];
    $reservation_id = (int)($_POST['reservation_id'] ?? 0);

    if (!$reservation_id) { $conn->close(); json_response(['status' => 'error', 'message' => 'reservation_id required.'], 400); }

    $stmt = $conn->prepare(
        "UPDATE reservations SET status = 'cancelled'
         WHERE id = ? AND user_id = ? AND status IN ('pending','ready')"
    );
    if ($stmt) {
        $stmt->bind_param('ii', $reservation_id, $user_id);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        $conn->close();
        if ($affected > 0)
            json_response(['status' => 'success', 'message' => 'Reservation cancelled.']);
        json_response(['status' => 'error', 'message' => 'Reservation not found or already processed.'], 404);
    }
    $conn->close();
    json_response(['status' => 'error', 'message' => 'DB error.'], 500);
}


// ============================================================
//  ── ADDITIONAL ADMIN FUNCTIONS ───────────────────────────
// ============================================================

/**
 * GET ?action=admin_update_overdue
 * Marks all active loans past their due date as 'overdue'
 * Returns count of updated records
 */
function admin_update_overdue(): void {
    require_admin();
    $conn  = db_connect();
    $today = date('Y-m-d');

    $stmt = $conn->prepare(
        "UPDATE loans SET status = 'overdue'
         WHERE status = 'active' AND due_date < ?"
    );
    $updated = 0;
    if ($stmt) {
        $stmt->bind_param('s', $today);
        $stmt->execute();
        $updated = $stmt->affected_rows;
        $stmt->close();
    }
    $conn->close();
    json_response([
        'status'  => 'success',
        'message' => "$updated loan(s) marked as overdue.",
        'updated' => $updated
    ]);
}

/**
 * GET ?action=admin_get_fines&filter=unpaid&page=1
 * Returns all fine records with student + loan + book info
 */
function admin_get_fines(): void {
    require_admin();
    $conn   = db_connect();
    $filter = sanitise($_GET['filter'] ?? 'unpaid');  // all | unpaid | paid
    $search = sanitise($_GET['search'] ?? '');
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = 20;
    $offset = ($page - 1) * $limit;

    $where  = ['1=1'];
    $params = [];
    $types  = '';

    if ($filter === 'unpaid') { $where[] = 'f.paid = 0'; }
    if ($filter === 'paid')   { $where[] = 'f.paid = 1'; }
    if ($search) {
        $like     = "%$search%";
        $where[]  = '(u.matric_number LIKE ? OR u.full_name LIKE ? OR b.title LIKE ?)';
        $params[] = $like; $params[] = $like; $params[] = $like;
        $types   .= 'sss';
    }

    $whereStr = implode(' AND ', $where);

    // Total count
    $cstmt = $conn->prepare(
        "SELECT COUNT(*) FROM fines f
         JOIN loans l ON l.id = f.loan_id
         JOIN users u ON u.id = f.user_id
         JOIN books b ON b.id = l.book_id
         WHERE $whereStr"
    );
    $total = 0;
    if ($cstmt) {
        if ($params) $cstmt->bind_param($types, ...$params);
        $cstmt->execute();
        $r = $cstmt->get_result();
        if ($r) { $total = (int)$r->fetch_row()[0]; $r->free(); }
        $cstmt->close();
    }

    // Data rows
    $params[] = $limit;
    $params[] = $offset;
    $types   .= 'ii';

    $stmt  = $conn->prepare(
        "SELECT f.id AS fine_id, f.amount, f.reason, f.paid, f.paid_at, f.created_at,
                l.id AS loan_id, l.borrow_date, l.due_date, l.return_date, l.status AS loan_status,
                u.matric_number, u.full_name AS student_name, u.department_name, u.phone, u.email,
                b.title AS book_title, b.author, b.cover_emoji
         FROM fines f
         JOIN loans l ON l.id = f.loan_id
         JOIN users u ON u.id = f.user_id
         JOIN books b ON b.id = l.book_id
         WHERE $whereStr
         ORDER BY f.paid ASC, f.created_at DESC
         LIMIT ? OFFSET ?"
    );
    $fines = [];
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $r = $stmt->get_result();
        if ($r) { while ($row = $r->fetch_assoc()) $fines[] = $row; $r->free(); }
        $stmt->close();
    }

    // Summary totals
    $sum_stmt = $conn->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN paid=0 THEN amount ELSE 0 END),0) AS unpaid_total,
            COALESCE(SUM(CASE WHEN paid=1 THEN amount ELSE 0 END),0) AS paid_total,
            COUNT(*) AS total_count
         FROM fines"
    );
    $summary = ['unpaid_total'=>0,'paid_total'=>0,'total_count'=>0];
    if ($sum_stmt) {
        $sum_stmt->execute();
        $r = $sum_stmt->get_result();
        if ($r) { $summary = $r->fetch_assoc() ?? $summary; $r->free(); }
        $sum_stmt->close();
    }

    $conn->close();
    json_response([
        'status'  => 'success',
        'data'    => $fines,
        'total'   => $total,
        'page'    => $page,
        'pages'   => (int)ceil($total / $limit),
        'summary' => $summary,
    ]);
}

/**
 * POST ?action=admin_mark_fine_paid
 * Body: fine_id
 */
function admin_mark_fine_paid(): void {
    require_admin();
    $conn    = db_connect();
    $fine_id = (int)($_POST['fine_id'] ?? 0);
    if (!$fine_id) { $conn->close(); json_response(['status' => 'error', 'message' => 'fine_id required.'], 400); }

    $now  = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("UPDATE fines SET paid = 1, paid_at = ? WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param('si', $now, $fine_id);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        // Also update the loan's fine_paid flag
        $stmt2 = $conn->prepare(
            "UPDATE loans SET fine_paid = 1 WHERE id = (SELECT loan_id FROM fines WHERE id = ?)"
        );
        if ($stmt2) { $stmt2->bind_param('i', $fine_id); $stmt2->execute(); $stmt2->close(); }
    }
    $conn->close();
    json_response(['status' => 'success', 'message' => 'Fine marked as paid.']);
}

/**
 * POST ?action=admin_toggle_student
 * Body: student_id, action (suspend | activate)
 */
function admin_toggle_student(): void {
    require_admin();
    $conn       = db_connect();
    $student_id = (int)($_POST['student_id'] ?? 0);
    $act        = sanitise($_POST['action_type'] ?? '');

    if (!$student_id || !in_array($act, ['suspend','activate'])) {
        $conn->close();
        json_response(['status' => 'error', 'message' => 'Invalid parameters.'], 400);
    }

    $new_status = $act === 'suspend' ? 'suspended' : 'active';
    $stmt       = $conn->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'student'");
    if ($stmt) {
        $stmt->bind_param('si', $new_status, $student_id);
        $stmt->execute();
        $stmt->close();
    }
    $conn->close();
    json_response(['status' => 'success', 'message' => 'Student account ' . $new_status . '.']);
}

// ── ADMIN: Suspend / Re-activate a staff account ──────────────
function admin_toggle_staff(): void {
    require_admin();
    $conn     = db_connect();
    $staff_id = (int)($_POST['staff_id']   ?? 0);
    $act      = sanitise($_POST['action_type'] ?? '');

    if (!$staff_id || !in_array($act, ['suspend','activate'])) {
        $conn->close();
        json_response(['status' => 'error', 'message' => 'Invalid parameters.'], 400);
    }

    // Fetch name for audit log
    $stmt = $conn->prepare("SELECT full_name, matric_number FROM users WHERE id = ? AND role = 'staff' LIMIT 1");
    $info = null;
    if ($stmt) {
        $stmt->bind_param('i', $staff_id);
        $stmt->execute();
        $r = $stmt->get_result();
        if ($r) { $info = $r->fetch_assoc(); $r->free(); }
        $stmt->close();
    }
    if (!$info) { $conn->close(); json_response(['status' => 'error', 'message' => 'Staff member not found.'], 404); }

    $new_status = $act === 'suspend' ? 'suspended' : 'active';
    $stmt       = $conn->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'staff'");
    if ($stmt) {
        $stmt->bind_param('si', $new_status, $staff_id);
        $stmt->execute();
        $stmt->close();
    }
    $conn->close();

    audit_log(
        $act === 'suspend' ? 'suspend_staff' : 'activate_staff',
        'user', $staff_id,
        "Staff '" . $info['full_name'] . "' (" . $info['matric_number'] . ") $new_status"
    );

    json_response(['status' => 'success', 'message' => 'Staff account ' . $new_status . '.']);
}

/**
 * GET ?action=admin_get_books_list&search=&page=1
 * Paginated book catalog for admin
 */
function admin_get_books_list(): void {
    require_admin();
    $conn   = db_connect();
    $search = sanitise($_GET['search'] ?? '');
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = 20;
    $offset = ($page - 1) * $limit;

    $where  = ['1=1'];
    $params = [];
    $types  = '';

    if ($search) {
        $like     = "%$search%";
        $where[]  = '(title LIKE ? OR author LIKE ? OR isbn LIKE ? OR department_name LIKE ?)';
        $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
        $types   .= 'ssss';
    }

    $whereStr = implode(' AND ', $where);

    // Count
    $cstmt = $conn->prepare("SELECT COUNT(*) FROM books WHERE $whereStr");
    $total = 0;
    if ($cstmt) {
        if ($params) $cstmt->bind_param($types, ...$params);
        $cstmt->execute();
        $r = $cstmt->get_result();
        if ($r) { $total = (int)$r->fetch_row()[0]; $r->free(); }
        $cstmt->close();
    }

    $params[] = $limit;
    $params[] = $offset;
    $types   .= 'ii';

    $stmt  = $conn->prepare(
        "SELECT id, isbn, title, author, publisher, pub_year, category,
                dept_code, department_name, total_copies, available_copies,
                resource_type, location, cover_emoji, cover_color,
                digital_url, added_at
         FROM books WHERE $whereStr ORDER BY title ASC LIMIT ? OFFSET ?"
    );
    $books = [];
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $r = $stmt->get_result();
        if ($r) { while ($row = $r->fetch_assoc()) $books[] = $row; $r->free(); }
        $stmt->close();
    }
    $conn->close();
    json_response([
        'status' => 'success',
        'data'   => $books,
        'total'  => $total,
        'page'   => $page,
        'pages'  => (int)ceil($total / $limit),
    ]);
}


// ============================================================
//  STAFF APPROVAL FUNCTIONS
// ============================================================

/**
 * GET ?action=admin_get_pending_staff
 * Returns all staff accounts with status = 'pending'
 */
function admin_get_pending_staff(): void {
    require_admin();
    $conn  = db_connect();
    $stmt  = $conn->prepare(
        "SELECT id, matric_number, library_reg_number, full_name,
                email, phone, dept_code, department_name, created_at
         FROM users WHERE role = 'staff' AND status = 'pending'
         ORDER BY created_at ASC"
    );
    $rows  = [];
    if ($stmt) {
        $stmt->execute();
        $r = $stmt->get_result();
        if ($r) { while ($row = $r->fetch_assoc()) $rows[] = $row; $r->free(); }
        $stmt->close();
    }
    $conn->close();
    json_response(['status' => 'success', 'data' => $rows, 'count' => count($rows)]);
}

/**
 * POST ?action=admin_approve_staff
 * Body: staff_id (user ID)
 * Activates the staff account so they can log in.
 */
function admin_approve_staff(): void {
    require_admin();
    $conn     = db_connect();
    $staff_id = (int)($_POST['staff_id'] ?? 0);
    if (!$staff_id) { $conn->close(); json_response(['status' => 'error', 'message' => 'staff_id required.'], 400); }

    // Get staff details for audit log
    $stmt = $conn->prepare("SELECT full_name, matric_number FROM users WHERE id = ? LIMIT 1");
    $info = null;
    if ($stmt) {
        $stmt->bind_param('i', $staff_id);
        $stmt->execute();
        $r = $stmt->get_result();
        if ($r) { $info = $r->fetch_assoc(); $r->free(); }
        $stmt->close();
    }

    $active = 'active';
    $stmt   = $conn->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'staff' AND status = 'pending'");
    if ($stmt) {
        $stmt->bind_param('si', $active, $staff_id);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
    }
    $conn->close();

    if ($affected > 0) {
        audit_log('approve_staff', 'user', $staff_id,
            "Staff '" . ($info['full_name'] ?? '') . "' (" . ($info['matric_number'] ?? '') . ") approved");
        json_response([
            'status'  => 'success',
            'message' => ($info['full_name'] ?? 'Staff') . ' account approved. They can now log in.'
        ]);
    } else {
        json_response(['status' => 'error', 'message' => 'Staff account not found or already processed.'], 404);
    }
}

/**
 * POST ?action=admin_reject_staff
 * Body: staff_id, reason (optional)
 * Marks the account as inactive — staff cannot log in.
 */
function admin_reject_staff(): void {
    require_admin();
    $conn     = db_connect();
    $staff_id = (int)($_POST['staff_id'] ?? 0);
    $reason   = sanitise($_POST['reason'] ?? 'Registration rejected by admin');
    if (!$staff_id) { $conn->close(); json_response(['status' => 'error', 'message' => 'staff_id required.'], 400); }

    $stmt = $conn->prepare("SELECT full_name, matric_number FROM users WHERE id = ? LIMIT 1");
    $info = null;
    if ($stmt) {
        $stmt->bind_param('i', $staff_id);
        $stmt->execute();
        $r = $stmt->get_result();
        if ($r) { $info = $r->fetch_assoc(); $r->free(); }
        $stmt->close();
    }

    $inactive = 'inactive';
    $stmt     = $conn->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'staff'");
    if ($stmt) {
        $stmt->bind_param('si', $inactive, $staff_id);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
    }
    $conn->close();

    audit_log('reject_staff', 'user', $staff_id,
        "Staff '" . ($info['full_name'] ?? '') . "' rejected. Reason: $reason");

    json_response([
        'status'  => 'success',
        'message' => ($info['full_name'] ?? 'Staff') . ' registration rejected.'
    ]);
}


// ============================================================
//  AUDIT LOG VIEWER
// ============================================================

/**
 * GET ?action=admin_get_audit_log&page=1&action_filter=&search=
 */
function admin_get_audit_log(): void {
    require_admin();
    $conn   = db_connect();
    $filter = sanitise($_GET['action_filter'] ?? '');
    $search = sanitise($_GET['search']        ?? '');
    $page   = max(1, (int)($_GET['page']      ?? 1));
    $limit  = 30;
    $offset = ($page - 1) * $limit;

    $where  = ['1=1'];
    $params = [];
    $types  = '';

    if ($filter) {
        $where[]  = 'action = ?';
        $params[] = $filter;
        $types   .= 's';
    }
    if ($search) {
        $like     = "%$search%";
        $where[]  = '(user_name LIKE ? OR detail LIKE ? OR ip_address LIKE ?)';
        $params[] = $like; $params[] = $like; $params[] = $like;
        $types   .= 'sss';
    }

    $whereStr = implode(' AND ', $where);

    // Count
    $cstmt = $conn->prepare("SELECT COUNT(*) FROM audit_log WHERE $whereStr");
    $total = 0;
    if ($cstmt) {
        if ($params) $cstmt->bind_param($types, ...$params);
        $cstmt->execute();
        $r = $cstmt->get_result();
        if ($r) { $total = (int)$r->fetch_row()[0]; $r->free(); }
        $cstmt->close();
    }

    $params[] = $limit;
    $params[] = $offset;
    $types   .= 'ii';

    $stmt = $conn->prepare(
        "SELECT id, user_id, user_name, action, target_type, target_id,
                detail, ip_address, created_at
         FROM audit_log WHERE $whereStr
         ORDER BY created_at DESC LIMIT ? OFFSET ?"
    );
    $logs = [];
    if ($stmt) {
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $r = $stmt->get_result();
        if ($r) { while ($row = $r->fetch_assoc()) $logs[] = $row; $r->free(); }
        $stmt->close();
    }

    // Distinct action types for filter dropdown
    $acts   = [];
    $ar     = $conn->query("SELECT DISTINCT action FROM audit_log ORDER BY action ASC");
    if ($ar) { while ($row = $ar->fetch_row()) $acts[] = $row[0]; $ar->free(); }

    $conn->close();
    json_response([
        'status'  => 'success',
        'data'    => $logs,
        'total'   => $total,
        'page'    => $page,
        'pages'   => (int)ceil($total / $limit),
        'actions' => $acts,
    ]);
}


// ── ADMIN: Fetch single book's full details for edit form ─────
function admin_get_book_detail(): void {
    require_admin();
    $conn    = db_connect();
    $book_id = (int)($_GET['book_id'] ?? 0);
    if (!$book_id) { $conn->close(); json_response(['status' => 'error', 'message' => 'book_id required.'], 400); }

    $stmt = $conn->prepare(
        'SELECT id, title, author, isbn, publisher, pub_year, edition,
                category, dept_code, department_name, resource_type,
                total_copies, available_copies, location,
                cover_emoji, cover_color, digital_url, added_at
         FROM books WHERE id = ? LIMIT 1'
    );
    $book = null;
    if ($stmt) {
        $stmt->bind_param('i', $book_id);
        $stmt->execute();
        $r = $stmt->get_result();
        if ($r) { $book = $r->fetch_assoc(); $r->free(); }
        $stmt->close();
    }
    $conn->close();

    if (!$book) json_response(['status' => 'error', 'message' => 'Book not found.'], 404);
    json_response(['status' => 'success', 'data' => $book]);
}
