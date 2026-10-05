<?php
// ============================================================
//  FILE: db_config.php
//  PURPOSE: Database connection, security helpers, session
//           management, CSRF protection, rate limiting,
//           audit logging, library number generator.
// ============================================================

// ── Database credentials ────────────────────────────────────
define('DB_HOST',  'localhost');
define('DB_USER',  'root');           // Change to your MySQL username
define('DB_PASS',  '');               // Change to your MySQL password
define('DB_NAME',  'ibbul_library');
define('DB_PORT',   3306);

// ── Security settings ───────────────────────────────────────
define('MAX_LOGIN_ATTEMPTS', 5);      // lock after 5 failed attempts
define('LOCKOUT_MINUTES',    15);     // locked for 15 minutes
define('SESSION_TIMEOUT',    1800);   // 30-minute idle timeout (seconds)
define('CSRF_TOKEN_NAME',    '_csrf_token');


// ════════════════════════════════════════════════════════════
//  DATABASE
// ════════════════════════════════════════════════════════════

function db_connect(): mysqli {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    if ($conn->connect_error) {
        http_response_code(500);
        die(json_encode(['status' => 'error', 'message' => 'DB connection failed.']));
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}


// ════════════════════════════════════════════════════════════
//  SECURITY HEADERS
//  Call set_security_headers() at the top of every page.
// ════════════════════════════════════════════════════════════

function set_security_headers(): void {
    // Prevent clickjacking (embedding in iframes)
    header('X-Frame-Options: DENY');
    // Prevent MIME-type sniffing
    header('X-Content-Type-Options: nosniff');
    // Enable browser XSS filter (legacy browsers)
    header('X-XSS-Protection: 1; mode=block');
    // Restrict referrer information
    header('Referrer-Policy: strict-origin-when-cross-origin');
    // Content Security Policy — tightened for this app
    header("Content-Security-Policy: default-src 'self'; "
         . "script-src 'self' 'unsafe-inline'; "
         . "style-src 'self' 'unsafe-inline'; "
         . "img-src 'self' data:; "
         . "connect-src 'self'; "
         . "frame-ancestors 'none';");
    // Cache control for sensitive pages
    header('Cache-Control: no-store, no-cache, must-revalidate, private');
    header('Pragma: no-cache');
}


// ════════════════════════════════════════════════════════════
//  SECURE SESSION
// ════════════════════════════════════════════════════════════

function start_session(): void {
    if (session_status() !== PHP_SESSION_NONE) return;

    // Harden session cookie
    session_set_cookie_params([
        'lifetime' => 0,               // expires when browser closes
        'path'     => '/',
        'secure'   => false,           // set true if using HTTPS in production
        'httponly' => true,            // JS cannot access session cookie
        'samesite' => 'Strict',        // no cross-site sending
    ]);

    ini_set('session.use_strict_mode',    '1');
    ini_set('session.use_only_cookies',   '1');
    ini_set('session.cookie_httponly',    '1');
    ini_set('session.gc_maxlifetime',     (string)SESSION_TIMEOUT);

    session_start();

    // Session timeout — log out idle users automatically
    if (isset($_SESSION['last_activity'])) {
        if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
            session_unset();
            session_destroy();
            session_start();
            return;
        }
    }
    $_SESSION['last_activity'] = time();

    // Session fingerprinting — detect session hijacking
    $fingerprint = hash('sha256',
        ($_SERVER['HTTP_USER_AGENT'] ?? '') .
        substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 16) // partial IP (handles DHCP changes)
    );
    if (isset($_SESSION['fingerprint'])) {
        if ($_SESSION['fingerprint'] !== $fingerprint) {
            // Fingerprint mismatch — possible session hijack
            session_unset();
            session_destroy();
            session_start();
            $_SESSION['fingerprint'] = $fingerprint;
            return;
        }
    } else {
        $_SESSION['fingerprint'] = $fingerprint;
    }
}

function is_logged_in(): bool {
    start_session();
    return isset($_SESSION['user_id']);
}

function redirect(string $url): void {
    header("Location: $url");
    exit;
}


// ════════════════════════════════════════════════════════════
//  CSRF PROTECTION
//  Prevents forged form submissions from other websites.
// ════════════════════════════════════════════════════════════

function generate_csrf_token(): string {
    start_session();
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function csrf_field(): string {
    $token = generate_csrf_token();
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . htmlspecialchars($token) . '">';
}

function verify_csrf(bool $die_on_fail = true): bool {
    start_session();
    $token = $_POST[CSRF_TOKEN_NAME] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $valid = hash_equals($_SESSION[CSRF_TOKEN_NAME] ?? '', $token);
    if (!$valid && $die_on_fail) {
        json_response(['status' => 'error', 'message' => 'Invalid request token. Please refresh and try again.'], 403);
    }
    return $valid;
}


// ════════════════════════════════════════════════════════════
//  RATE LIMITING  (login brute-force protection)
// ════════════════════════════════════════════════════════════

function get_client_ip(): string {
    foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $key) {
        if (!empty($_SERVER[$key])) {
            return trim(explode(',', $_SERVER[$key])[0]);
        }
    }
    return '0.0.0.0';
}

/**
 * Call before processing a login attempt.
 * Returns true if allowed; calls json_response and exits if locked out.
 */
function check_rate_limit(string $identifier): void {
    $conn   = db_connect();
    $ip     = get_client_ip();
    $window = date('Y-m-d H:i:s', time() - (LOCKOUT_MINUTES * 60));

    // Count recent failures for this identifier OR this IP
    $stmt = $conn->prepare(
        "SELECT COUNT(*) FROM login_attempts
         WHERE (identifier = ? OR ip_address = ?) AND attempted_at > ?"
    );
    $count = 0;
    if ($stmt) {
        $stmt->bind_param('sss', $identifier, $ip, $window);
        $stmt->execute();
        $r = $stmt->get_result();
        if ($r) { $count = (int)$r->fetch_row()[0]; $r->free(); }
        $stmt->close();
    }
    $conn->close();

    if ($count >= MAX_LOGIN_ATTEMPTS) {
        json_response([
            'status'  => 'error',
            'message' => "Too many failed login attempts. Please wait " . LOCKOUT_MINUTES . " minutes before trying again."
        ], 429);
    }
}

/** Record a failed login attempt */
function record_failed_login(string $identifier): void {
    $conn = db_connect();
    $ip   = get_client_ip();
    $stmt = $conn->prepare("INSERT INTO login_attempts (identifier, ip_address) VALUES (?, ?)");
    if ($stmt) { $stmt->bind_param('ss', $identifier, $ip); $stmt->execute(); $stmt->close(); }
    $conn->close();
}

/** Clear login attempts after a successful login */
function clear_login_attempts(string $identifier): void {
    $conn = db_connect();
    $stmt = $conn->prepare("DELETE FROM login_attempts WHERE identifier = ?");
    if ($stmt) { $stmt->bind_param('s', $identifier); $stmt->execute(); $stmt->close(); }
    $conn->close();
}

/** Remove old login attempt records (call periodically or on login) */
function cleanup_old_attempts(): void {
    $conn   = db_connect();
    $cutoff = date('Y-m-d H:i:s', time() - 86400); // remove records > 24h old
    $conn->query("DELETE FROM login_attempts WHERE attempted_at < '$cutoff'");
    $conn->close();
}


// ════════════════════════════════════════════════════════════
//  AUDIT LOGGING
// ════════════════════════════════════════════════════════════

/**
 * Write an entry to the audit_log table.
 * $action      e.g. 'login', 'logout', 'issue_loan', 'approve_staff'
 * $target_type e.g. 'loan', 'user', 'book'
 * $target_id   the ID of the affected record
 * $detail      plain-English description
 */
function audit_log(string $action, string $target_type = '', int $target_id = 0, string $detail = ''): void {
    start_session();
    $conn      = db_connect();
    $user_id   = $_SESSION['user_id']   ?? null;
    $user_name = $_SESSION['user_name'] ?? 'Guest';
    $ip        = get_client_ip();

    $stmt = $conn->prepare(
        'INSERT INTO audit_log (user_id, user_name, action, target_type, target_id, detail, ip_address)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    if ($stmt) {
        $tid = $target_id ?: null;
        $tt  = $target_type ?: null;
        $stmt->bind_param('isssiss', $user_id, $user_name, $action, $tt, $tid, $detail, $ip);
        $stmt->execute();
        $stmt->close();
    }
    $conn->close();
}


// ════════════════════════════════════════════════════════════
//  HELPERS
// ════════════════════════════════════════════════════════════

function sanitise(string $value): string {
    return htmlspecialchars(strip_tags(trim($value)));
}

function json_response(array $data, int $code = 200): void {
    if (ob_get_level() > 0) ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, private');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}


// ════════════════════════════════════════════════════════════
//  LIBRARY REGISTRATION NUMBER GENERATOR
//  Format: LIB-{YEAR}-{4-digit-serial}  e.g. LIB-2025-0001
// ════════════════════════════════════════════════════════════

function generate_lib_number(mysqli $conn): array {
    $year = date('Y');
    $stmt = $conn->prepare(
        'INSERT INTO lib_serials (reg_year, last_serial) VALUES (?, 1)
         ON DUPLICATE KEY UPDATE last_serial = last_serial + 1'
    );
    $stmt->bind_param('s', $year);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare('SELECT last_serial FROM lib_serials WHERE reg_year = ?');
    $stmt->bind_param('s', $year);
    $stmt->execute();
    $res    = $stmt->get_result();
    $serial = (int)$res->fetch_row()[0];
    $res->free();
    $stmt->close();

    return ['lib_number' => sprintf('LIB-%s-%04d', $year, $serial), 'serial' => $serial, 'year' => $year];
}

function generate_matric(mysqli $conn, string $year2, string $faculty_code, string $dept_code): array {
    $stmt = $conn->prepare(
        'INSERT INTO matric_serials (admission_year, faculty_code, dept_code, last_serial)
         VALUES (?, ?, ?, 1)
         ON DUPLICATE KEY UPDATE last_serial = last_serial + 1'
    );
    $stmt->bind_param('sss', $year2, $faculty_code, $dept_code);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare(
        'SELECT last_serial FROM matric_serials
         WHERE admission_year = ? AND faculty_code = ? AND dept_code = ?'
    );
    $stmt->bind_param('sss', $year2, $faculty_code, $dept_code);
    $stmt->execute();
    $res    = $stmt->get_result();
    $serial = (int)$res->fetch_row()[0];
    $res->free();
    $stmt->close();

    return ['matric' => sprintf('U%s/%s/%s/%04d', $year2, strtoupper($faculty_code), strtoupper($dept_code), $serial), 'serial' => $serial];
}
