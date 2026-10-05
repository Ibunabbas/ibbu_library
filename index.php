<?php
// ============================================================
//  FILE: index.php
//  PURPOSE: Main IBBUL Smart Library website
// ============================================================
require_once 'db_config.php';
set_security_headers();
start_session();
$csrf = generate_csrf_token();

$conn = db_connect();

// ── 1. Faculties ────────────────────────────────────────────
$faculties  = [];
$fac_result = $conn->query(
    'SELECT id, code, name FROM faculties ORDER BY name ASC'
);
if ($fac_result) {
    while ($row = $fac_result->fetch_assoc()) {
        $faculties[] = $row;
    }
    $fac_result->free();   // <-- free before next query
}

// ── 2. Departments (grouped by faculty_code) ────────────────
$all_departments = [];
$dept_result = $conn->query(
    'SELECT d.id, d.code, d.name, f.code AS faculty_code
     FROM departments d
     JOIN  faculties  f ON f.id = d.faculty_id
     ORDER BY f.name ASC, d.name ASC'
);
if ($dept_result) {
    while ($row = $dept_result->fetch_assoc()) {
        $all_departments[$row['faculty_code']][] = $row;
    }
    $dept_result->free();  // <-- free before next query
}

// ── 3. Catalog books (limit 12) ─────────────────────────────
$all_books  = [];
$books_stmt = $conn->prepare(
    'SELECT id, title, author, department_name, dept_code,
            category, resource_type, available_copies,
            cover_emoji, cover_color
     FROM books
     ORDER BY title ASC
     LIMIT 12'
);
if ($books_stmt) {
    $books_stmt->execute();
    $books_result = $books_stmt->get_result();  // store first
    if ($books_result) {
        while ($row = $books_result->fetch_assoc()) {
            $all_books[] = $row;
        }
        $books_result->free();
    }
    $books_stmt->close();
}

// ── 4. Announcements ────────────────────────────────────────
$announcements = [];
$ann_stmt      = $conn->prepare(
    'SELECT title, body, tag, tag_color, published_at
     FROM   announcements
     WHERE  is_active = 1
     ORDER  BY published_at DESC
     LIMIT  6'
);
if ($ann_stmt) {
    $ann_stmt->execute();
    $ann_result = $ann_stmt->get_result();  // store first
    if ($ann_result) {
        while ($row = $ann_result->fetch_assoc()) {
            $announcements[] = $row;
        }
        $ann_result->free();
    }
    $ann_stmt->close();
}

// ── 5. Logged-in user data (only when a session exists) ─────
$user_info  = null;
$user_loans = [];

if (is_logged_in()) {
    $uid = (int)$_SESSION['user_id'];

    // 5a. User profile
    $ui_stmt = $conn->prepare(
        'SELECT matric_number, full_name, department_name,
                faculty_name, faculty_code, dept_code, level, role
         FROM   users
         WHERE  id = ?
         LIMIT  1'
    );
    if ($ui_stmt) {
        $ui_stmt->bind_param('i', $uid);
        $ui_stmt->execute();
        $ui_result = $ui_stmt->get_result();  // store first
        if ($ui_result) {
            $user_info = $ui_result->fetch_assoc();
            $ui_result->free();
        }
        $ui_stmt->close();
    }

    // 5b. Active loans
    $ln_stmt = $conn->prepare(
        'SELECT l.id, l.due_date, l.status, l.fine_amount,
                b.title, b.cover_emoji
         FROM   loans l
         JOIN   books b ON b.id = l.book_id
         WHERE  l.user_id  = ?
           AND  l.status  != "returned"
         ORDER  BY l.due_date ASC
         LIMIT  5'
    );
    if ($ln_stmt) {
        $ln_stmt->bind_param('i', $uid);
        $ln_stmt->execute();
        $ln_result = $ln_stmt->get_result();  // store first
        if ($ln_result) {
            while ($row = $ln_result->fetch_assoc()) {
                $user_loans[] = $row;
            }
            $ln_result->free();
        }
        $ln_stmt->close();
    }
}

// ── 6. Stats ────────────────────────────────────────────────
$stats = ['total_copies'=>0,'total_titles'=>0,'total_students'=>0,'digital_count'=>0];
$stats_result = $conn->query(
    'SELECT
        (SELECT COALESCE(SUM(total_copies),0) FROM books)                              AS total_copies,
        (SELECT COUNT(*)                       FROM books)                              AS total_titles,
        (SELECT COUNT(*)                       FROM users WHERE role = "student")       AS total_students,
        (SELECT COUNT(*)                       FROM books
          WHERE resource_type IN ("digital","both"))                                   AS digital_count'
);
if ($stats_result) {
    $row = $stats_result->fetch_assoc();
    if ($row) $stats = $row;
    $stats_result->free();
}

// ── All DB work done — close the connection ──────────────────
$conn->close();

// ── HTML helpers ─────────────────────────────────────────────
function avail_badge(array $b): string {
    if ($b['resource_type'] === 'digital')
        return '<span class="book-badge badge-digital">Digital Only</span>';
    if ((int)$b['available_copies'] < 1)
        return '<span class="book-badge badge-loan">On Loan</span>';
    return '<span class="book-badge badge-avail">Available</span>';
}
function loan_badge(string $s): string {
    $cls = ['active'=>'ps-ok','overdue'=>'ps-due','returned'=>'ps-new'];
    $lbl = ['active'=>'On Loan','overdue'=>'Overdue','returned'=>'Returned'];
    return '<span class="p-status '.($cls[$s]??'ps-ok').'">'.($lbl[$s]??ucfirst($s)).'</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Smart Library System — IBBUL</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --gd:#1a5c2e;--gm:#2d8a4e;--gl:#e8f5ec;
  --gold:#c8960c;--goldl:#fef8e7;
  --txt:#1a1a1a;--muted:#5a5a5a;--border:#e0e0e0;
  --surf:#fff;--bg:#f7f8f5;--r:10px;--rl:16px;
}
body{font-family:'Segoe UI',system-ui,sans-serif;color:var(--txt);background:var(--bg);line-height:1.6}
a{color:inherit;text-decoration:none}
nav{background:var(--gd);color:#fff;padding:0 2rem;display:flex;align-items:center;justify-content:space-between;height:64px;position:sticky;top:0;z-index:200;box-shadow:0 2px 8px rgba(0,0,0,.2)}
.nav-brand{display:flex;align-items:center;gap:12px}
.nav-logo{width:42px;height:42px;background:var(--gold);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;color:var(--gd);flex-shrink:0}
.nav-title{font-size:15px;font-weight:600;line-height:1.3}
.nav-title span{font-size:11px;font-weight:400;opacity:.8;display:block}
.nav-links{display:flex;gap:4px;align-items:center}
.nav-links a{padding:6px 13px;border-radius:6px;font-size:13.5px;color:rgba(255,255,255,.85);transition:background .15s}
.nav-links a:hover{background:rgba(255,255,255,.12);color:#fff}
.nav-user{font-size:12px;color:#ffd966;padding:0 8px}
.nav-cta{background:var(--gold)!important;color:var(--gd)!important;font-weight:600!important;padding:7px 18px!important;border-radius:7px!important}
.nav-cta:hover{background:#e0a80e!important}
.alert-bar{background:var(--gold);color:var(--gd);text-align:center;padding:10px 2rem;font-size:13px;font-weight:500}
.alert-bar a{text-decoration:underline;font-weight:700}
.hero{background:linear-gradient(135deg,#1a5c2e,#2d8a4e 55%,#1e7a42);color:#fff;padding:80px 2rem 90px;text-align:center;position:relative;overflow:hidden}
.hero-badge{display:inline-block;background:rgba(200,150,12,.25);border:1px solid rgba(200,150,12,.5);color:#ffd966;font-size:12px;font-weight:600;padding:5px 14px;border-radius:20px;margin-bottom:20px;letter-spacing:.5px}
.hero h1{font-size:clamp(26px,4vw,46px);font-weight:700;line-height:1.2;margin-bottom:14px}
.hero h1 span{color:#ffd966}
.hero p{font-size:16px;opacity:.88;max-width:540px;margin:0 auto 32px}
.hero-search{max-width:600px;margin:0 auto 28px;display:flex;background:#fff;border-radius:50px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.2)}
.hero-search select{border:none;outline:none;padding:0 14px;font-size:13px;color:var(--muted);background:#f5f5f5;border-right:1px solid var(--border);min-width:110px;cursor:pointer}
.hero-search input{flex:1;border:none;outline:none;padding:14px 18px;font-size:15px;color:var(--txt)}
.hero-search button{background:var(--gold);border:none;cursor:pointer;padding:0 24px;font-size:14px;font-weight:600;color:var(--gd);transition:background .15s}
.hero-search button:hover{background:#e0a80e}
.hero-stats{display:flex;justify-content:center;gap:40px;flex-wrap:wrap}
.hero-stat strong{display:block;font-size:24px;font-weight:700;color:#ffd966}
.hero-stat span{font-size:12px;opacity:.75}
.search-results{max-width:900px;margin:20px auto 0;display:none;text-align:left}
.sr-title{font-size:13px;font-weight:600;color:rgba(255,255,255,.8);margin-bottom:12px}
section{padding:64px 2rem}
.container{max-width:1100px;margin:0 auto}
.section-label{font-size:12px;font-weight:600;color:var(--gm);letter-spacing:1px;text-transform:uppercase;margin-bottom:8px}
.section-title{font-size:clamp(22px,2.5vw,32px);font-weight:700;margin-bottom:10px}
.section-sub{font-size:15px;color:var(--muted);max-width:520px}
#features{background:var(--surf)}
.features-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px;margin-top:40px}
.feature-card{border:1px solid var(--border);border-radius:var(--rl);padding:24px;background:var(--surf);transition:box-shadow .2s,transform .2s}
.feature-card:hover{box-shadow:0 8px 24px rgba(0,0,0,.09);transform:translateY(-2px)}
.fi{width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:22px;margin-bottom:14px}
.fi-g{background:var(--gl)}.fi-gold{background:var(--goldl)}.fi-b{background:#e8f0fe}.fi-p{background:#f0ebfe}
.feature-card h3{font-size:15px;font-weight:600;margin-bottom:6px}
.feature-card p{font-size:13.5px;color:var(--muted);line-height:1.55}
#catalog{background:var(--bg)}
.catalog-tabs{display:flex;gap:6px;margin:28px 0 24px;flex-wrap:wrap}
.tab-btn{padding:7px 18px;border-radius:20px;font-size:13px;font-weight:500;border:1px solid var(--border);background:var(--surf);color:var(--muted);cursor:pointer;transition:all .15s}
.tab-btn.active,.tab-btn:hover{background:var(--gd);color:#fff;border-color:var(--gd)}
.books-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(155px,1fr));gap:16px}
.book-card{background:var(--surf);border-radius:var(--r);border:1px solid var(--border);overflow:hidden;transition:box-shadow .2s,transform .2s;cursor:pointer}
.book-card:hover{box-shadow:0 6px 18px rgba(0,0,0,.1);transform:translateY(-3px)}
.book-cover{height:130px;display:flex;align-items:center;justify-content:center;position:relative}
.book-badge{position:absolute;top:7px;right:7px;font-size:10px;font-weight:600;padding:3px 8px;border-radius:10px}
.badge-avail{background:#e8f5ec;color:#1a5c2e}.badge-loan{background:#fff3e0;color:#b45309}.badge-digital{background:#e8f0fe;color:#1a56a4}
.book-info{padding:10px 12px 14px}
.book-info h4{font-size:13px;font-weight:600;margin-bottom:3px;line-height:1.4}
.book-info p{font-size:11.5px;color:var(--muted)}
.book-dept{font-size:11px;color:var(--gm);font-weight:500;margin-top:4px}
#services{background:var(--surf)}
.services-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:24px;margin-top:40px}
.service-card{border-radius:var(--rl);padding:28px;border:1px solid var(--border)}
.service-card.hl{background:var(--gd);color:#fff;border-color:var(--gd)}
.service-card.hl p{color:rgba(255,255,255,.78)}
.service-card.hl .sl{color:#ffd966}
.si{font-size:28px;margin-bottom:14px;display:block}
.service-card h3{font-size:17px;font-weight:600;margin-bottom:8px}
.service-card p{font-size:13.5px;color:var(--muted);line-height:1.6;margin-bottom:16px}
.sl{font-size:13px;font-weight:600;color:var(--gm);display:flex;align-items:center;gap:4px}
.sl::after{content:'→'}
#portal{background:var(--bg)}
.portal-grid{display:grid;grid-template-columns:1fr 1fr;gap:32px;margin-top:40px;align-items:start}
@media(max-width:700px){.portal-grid{grid-template-columns:1fr}}
.portal-mockup{background:var(--surf);border:1px solid var(--border);border-radius:var(--rl);overflow:hidden;box-shadow:0 4px 16px rgba(0,0,0,.07)}
.pm-header{background:var(--gd);color:#fff;padding:13px 18px;display:flex;align-items:center;gap:10px}
.dots{display:flex;gap:5px}
.dot{width:10px;height:10px;border-radius:50%}
.d1{background:#ff5f57}.d2{background:#febc2e}.d3{background:#28c840}
.pm-header span{font-size:13px;opacity:.7;margin-left:auto}
.pm-body{padding:18px}
.p-row{display:flex;align-items:center;gap:11px;padding:9px 0;border-bottom:1px solid var(--border)}
.p-row:last-child{border-bottom:none}
.p-icon{width:34px;height:34px;border-radius:8px;background:var(--gl);display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0}
.p-text{flex:1}
.p-text strong{font-size:13px;display:block}
.p-text span{font-size:11.5px;color:var(--muted)}
.p-status{font-size:11px;font-weight:600;padding:3px 10px;border-radius:10px;white-space:nowrap}
.ps-ok{background:#e8f5ec;color:#1a5c2e}.ps-due{background:#fff3e0;color:#b45309}.ps-new{background:#e8f0fe;color:#1a56a4}
.portal-desc h3{font-size:22px;font-weight:700;margin-bottom:12px}
.portal-desc p{font-size:14px;color:var(--muted);margin-bottom:20px;line-height:1.7}
.portal-list{list-style:none;display:flex;flex-direction:column;gap:9px;margin-bottom:28px}
.portal-list li{font-size:13.5px;padding-left:22px;position:relative;color:var(--muted)}
.portal-list li::before{content:'✓';position:absolute;left:0;color:var(--gm);font-weight:700}
.btn-primary{display:inline-block;background:var(--gd);color:#fff;padding:12px 28px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;border:none;transition:background .15s}
.btn-primary:hover{background:#14492b}
.btn-outline{display:inline-block;border:1.5px solid var(--gd);color:var(--gd);padding:11px 24px;border-radius:8px;font-size:14px;font-weight:600;margin-left:10px;transition:all .15s;cursor:pointer}
.btn-outline:hover{background:var(--gl)}
#how{background:var(--surf)}
.steps{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));margin-top:40px;position:relative}
.steps::before{content:'';position:absolute;top:28px;left:10%;right:10%;height:2px;background:var(--border);z-index:0}
.step{text-align:center;padding:0 16px;position:relative;z-index:1}
.step-num{width:56px;height:56px;border-radius:50%;background:var(--gd);color:#fff;display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:700;margin:0 auto 16px;border:4px solid var(--surf)}
.step h4{font-size:15px;font-weight:600;margin-bottom:6px}
.step p{font-size:13px;color:var(--muted)}
.stats-band{background:var(--gd);color:#fff;padding:48px 2rem}
.stats-inner{max-width:1100px;margin:0 auto;display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:24px;text-align:center}
.stat-item strong{display:block;font-size:36px;font-weight:700;color:#ffd966}
.stat-item span{font-size:14px;opacity:.78}
#announcements{background:var(--bg)}
.news-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;margin-top:36px}
.news-card{background:var(--surf);border:1px solid var(--border);border-radius:var(--rl);padding:20px;border-left:4px solid var(--gm)}
.news-tag{font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px}
.news-card h4{font-size:14.5px;font-weight:600;margin-bottom:6px;line-height:1.4}
.news-card p{font-size:13px;color:var(--muted);line-height:1.55}
.news-date{font-size:12px;color:var(--muted);margin-top:12px}
#login{background:var(--surf)}
.login-wrap{max-width:520px;margin:40px auto 0}
.login-card{background:var(--surf);border:1px solid var(--border);border-radius:var(--rl);padding:36px;box-shadow:0 4px 20px rgba(0,0,0,.07)}
.login-tabs{display:flex;border:1px solid var(--border);border-radius:8px;overflow:hidden;margin-bottom:28px}
.ltab{flex:1;padding:10px;text-align:center;font-size:13.5px;font-weight:500;cursor:pointer;transition:background .15s;color:var(--muted)}
.ltab.active{background:var(--gd);color:#fff}
.role-tabs{display:flex;gap:8px;margin-bottom:20px}
.rtab{flex:1;padding:8px;text-align:center;font-size:13px;font-weight:500;border:1px solid var(--border);border-radius:8px;cursor:pointer;color:var(--muted);transition:all .15s}
.rtab.active{background:var(--gl);color:var(--gd);border-color:var(--gm)}
.form-group{margin-bottom:16px}
.form-group label{display:block;font-size:13px;font-weight:500;margin-bottom:6px;color:var(--muted)}
.form-group input,.form-group select{width:100%;padding:11px 14px;border:1px solid var(--border);border-radius:8px;font-size:14px;color:var(--txt);background:var(--surf);outline:none;transition:border .15s}
.form-group input:focus,.form-group select:focus{border-color:var(--gm)}
.form-group input::placeholder{color:#bbb}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.matric-preview-box{background:var(--gl);border:1px solid #a5d6b0;border-radius:8px;padding:12px 16px;margin-bottom:16px;display:none}
.mp-label{font-size:11px;font-weight:600;color:var(--gm);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px}
.mp-num{font-size:20px;font-weight:700;color:var(--gd);letter-spacing:1px;font-family:'Courier New',monospace}
.mp-note{font-size:11px;color:var(--muted);margin-top:4px}
.login-btn{width:100%;padding:13px;background:var(--gd);color:#fff;border:none;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer;transition:background .15s;margin-top:4px}
.login-btn:hover{background:#14492b}
.login-btn:disabled{opacity:.6;cursor:not-allowed}
.login-footer{text-align:center;font-size:13px;color:var(--muted);margin-top:16px}
.login-footer a{color:var(--gm);font-weight:500}
.msg-ok{background:#e8f5ec;color:#1a5c2e;border:1px solid #a5d6b0;padding:12px 16px;border-radius:8px;font-size:13px;margin-bottom:14px;line-height:1.6}
.msg-err{background:#fce4ec;color:#b71c1c;border:1px solid #f48fb1;padding:12px 16px;border-radius:8px;font-size:13px;margin-bottom:14px}
.success-matric{text-align:center;padding:16px 0}
.success-matric .sm-icon{font-size:44px;margin-bottom:12px}
.success-matric h3{font-size:18px;font-weight:700;color:var(--gd);margin-bottom:6px}
.sm-matric{font-size:26px;font-weight:700;font-family:'Courier New',monospace;color:var(--gd);background:var(--gl);padding:10px 20px;border-radius:8px;display:inline-block;margin:10px 0;letter-spacing:2px;border:2px dashed var(--gm)}
footer{background:#111a13;color:rgba(255,255,255,.75);padding:56px 2rem 28px}
.footer-inner{max-width:1100px;margin:0 auto}
.footer-grid{display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:40px;margin-bottom:40px}
@media(max-width:700px){.footer-grid{grid-template-columns:1fr 1fr}}
.f-logo{display:flex;align-items:center;gap:10px;margin-bottom:14px}
.f-logo-icon{width:40px;height:40px;background:var(--gold);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;color:var(--gd)}
.f-logo-txt{font-size:15px;font-weight:600;color:#fff;line-height:1.3}
.f-logo-txt small{font-size:11px;font-weight:400;color:rgba(255,255,255,.5);display:block}
.footer-brand p{font-size:13px;line-height:1.7;max-width:240px}
footer h5{font-size:13px;font-weight:600;color:#fff;margin-bottom:14px;text-transform:uppercase;letter-spacing:.5px}
footer ul{list-style:none;display:flex;flex-direction:column;gap:8px}
footer ul li a{font-size:13px;color:rgba(255,255,255,.6);transition:color .15s}
footer ul li a:hover{color:var(--gold)}
.footer-bottom{border-top:1px solid rgba(255,255,255,.08);padding-top:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px}
.footer-bottom p,.credits a{font-size:12.5px}
.credits{display:flex;gap:16px}
.credits a{color:rgba(255,255,255,.5)}
.credits a:hover{color:var(--gold)}
@media(max-width:768px){.nav-links{display:none}.steps::before{display:none}.form-row{grid-template-columns:1fr}}
</style>
</head>
<body>

<div class="alert-bar">
  📢 New e-resources added for 2024/2025 Session! Access over 5,000 new academic journals.
  <a href="#catalog">Browse now →</a>
</div>

<nav>
  <div class="nav-brand">
    <div class="nav-logo">IBB</div>
    <div class="nav-title">
      IBBUL Smart Library
      <span>Ibrahim Badamasi Babangida University, Lapai</span>
    </div>
  </div>
  <div class="nav-links">
    <a href="#features">Services</a>
    <a href="#catalog">Catalog</a>
    <a href="#portal">My Portal</a>
    <a href="#how">How It Works</a>
    <a href="#announcements">News</a>
    <?php if (is_logged_in()): ?>
      <?php if ($_SESSION['user_role'] === 'admin'): ?>
        <a href="admin.php" style="background:var(--gold);color:var(--gd)!important;font-weight:700;padding:6px 14px;border-radius:6px">🛡 Admin Panel</a>
      <?php else: ?>
        <span class="nav-user">👤 <?= htmlspecialchars($_SESSION['user_name']) ?></span>
      <?php endif; ?>
      <a href="#" onclick="logoutUser()">Sign Out</a>
    <?php else: ?>
      <a href="#login" class="nav-cta">Sign In</a>
    <?php endif; ?>
  </div>
</nav>

<div class="hero">
  <div class="hero-badge">✦ Smart Library System — IBBUL, Lapai</div>
  <h1>Your Gateway to <span>Knowledge</span><br>at IBBUL</h1>
  <p>Discover books, journals, e-resources, and research materials — anywhere, anytime.</p>
  <div class="hero-search">
    <select id="searchType">
      <option value="">All</option>
      <option value="title">Title</option>
      <option value="author">Author</option>
      <option value="isbn">ISBN</option>
    </select>
    <input type="text" id="searchInput" placeholder="Search titles, authors, ISBN, departments…"/>
    <button onclick="searchBooks()">Search</button>
  </div>
  <div id="searchResults" class="search-results">
    <div class="sr-title" id="srTitle"></div>
    <div class="books-grid" id="srGrid"></div>
  </div>
  <div class="hero-stats">
    <div class="hero-stat"><strong><?= number_format($stats['total_copies']) ?>+</strong><span>Library Copies</span></div>
    <div class="hero-stat"><strong><?= number_format($stats['total_titles']) ?>+</strong><span>Unique Titles</span></div>
    <div class="hero-stat"><strong><?= number_format($stats['total_students']) ?>+</strong><span>Registered Students</span></div>
    <div class="hero-stat"><strong>24/7</strong><span>Online Access</span></div>
  </div>
</div>

<section id="features">
  <div class="container">
    <div class="section-label">What We Offer</div>
    <div class="section-title">Everything You Need to Succeed</div>
    <p class="section-sub">A fully integrated digital library experience for all IBBUL students, staff, and researchers.</p>
    <div class="features-grid">
      <?php foreach ([
        ['📚','fi-g','Online Book Catalog','Search the full catalog — physical and digital — with real-time availability.'],
        ['💻','fi-b','E-Library Portal','Access thousands of e-books, academic journals, and research databases.'],
        ['🔖','fi-gold','Online Reservations','Reserve books remotely and get SMS alerts when your resource is ready.'],
        ['📝','fi-p','Self-Service Borrowing','Check in and out at self-service kiosks using your student ID.'],
        ['🔔','fi-g','Smart Notifications','Reminders for due dates, overdue alerts, and book availability notices.'],
        ['📊','fi-b','Research Support','Citation help, inter-library loans, and the IBBUL thesis repository.'],
        ['🗂️','fi-gold','Department Collections','Curated book lists for every faculty and department at IBBUL.'],
        ['📱','fi-p','Mobile Access','Read e-books, check your account, and renew loans from your smartphone.'],
      ] as [$icon,$cls,$title,$desc]): ?>
        <div class="feature-card">
          <div class="fi <?= $cls ?>"><?= $icon ?></div>
          <h3><?= $title ?></h3>
          <p><?= $desc ?></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section id="catalog">
  <div class="container">
    <div class="section-label">Browse Resources</div>
    <div class="section-title">Featured Library Catalog</div>
    <div class="catalog-tabs">
      <button class="tab-btn active" onclick="filterCatalog(this,'all')">All</button>
      <button class="tab-btn" onclick="filterCatalog(this,'science')">Sciences</button>
      <button class="tab-btn" onclick="filterCatalog(this,'arts')">Arts &amp; Humanities</button>
      <button class="tab-btn" onclick="filterCatalog(this,'management')">Management</button>
      <button class="tab-btn" onclick="filterCatalog(this,'law')">Law</button>
      <button class="tab-btn" onclick="filterCatalog(this,'digital')">Digital Only</button>
    </div>
    <div class="books-grid" id="catalogGrid">
      <?php foreach ($all_books as $b): ?>
        <div class="book-card"
             data-cat="<?= htmlspecialchars($b['category']) ?>"
             data-type="<?= htmlspecialchars($b['resource_type']) ?>">
          <div class="book-cover" style="background:<?= htmlspecialchars($b['cover_color']) ?>">
            <span style="font-size:40px"><?= htmlspecialchars($b['cover_emoji']) ?></span>
            <?= avail_badge($b) ?>
          </div>
          <div class="book-info">
            <h4><?= htmlspecialchars($b['title']) ?></h4>
            <p><?= htmlspecialchars($b['author']) ?></p>
            <div class="book-dept"><?= htmlspecialchars($b['department_name'] ?? '') ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section id="services">
  <div class="container">
    <div class="section-label">Library Services</div>
    <div class="section-title">Comprehensive Academic Support</div>
    <div class="services-grid">
      <div class="service-card hl"><span class="si">🎓</span><h3>Student Portal</h3><p>Manage loans, reservations, reading lists, and fines from one dashboard. Available 24/7.</p><a href="#login" class="sl">Access Portal</a></div>
      <div class="service-card"><span class="si">📖</span><h3>Course Reserve</h3><p>Lecturers can submit reading lists for priority shelving for quick student access.</p><a href="#" class="sl">View Reserves</a></div>
      <div class="service-card"><span class="si">🔍</span><h3>Inter-Library Loans</h3><p>Request materials from partner universities and research institutes across Nigeria.</p><a href="#" class="sl">Request a Loan</a></div>
      <div class="service-card"><span class="si">🗃️</span><h3>Institutional Repository</h3><p>Browse and submit IBBUL theses, dissertations, and faculty research outputs.</p><a href="#" class="sl">Explore Repository</a></div>
      <div class="service-card"><span class="si">💡</span><h3>Information Literacy</h3><p>Free workshops on APA 7th edition, database usage, and literature review writing.</p><a href="#announcements" class="sl">See Schedule</a></div>
      <div class="service-card"><span class="si">🖨️</span><h3>Printing &amp; Scanning</h3><p>Pay-per-page printing, scanning, and photocopying during library hours.</p><a href="#" class="sl">View Rates</a></div>
    </div>
  </div>
</section>

<section id="portal">
  <div class="container">
    <?php if (is_logged_in() && $_SESSION['user_role'] === 'student'): ?>
    <!-- ── LOGGED-IN INTERACTIVE STUDENT DASHBOARD ── -->
    <div class="section-label">My Library Account</div>
    <div class="section-title">Welcome, <?= htmlspecialchars($_SESSION['user_name']) ?> 👋</div>
    <?php if ($user_info): ?>
    <p style="font-size:13px;color:var(--muted);margin-bottom:28px">
      <span style="font-family:'Courier New',monospace;font-weight:700;color:var(--gd);font-size:15px">
        <?= htmlspecialchars($user_info['matric_number']) ?>
      </span>
      &nbsp;·&nbsp;
      <?php if (!empty($user_info['school_matric'])): ?>
        School Matric: <span style="font-family:'Courier New',monospace;font-weight:600">
          <?= htmlspecialchars($user_info['school_matric']) ?>
        </span>
        &nbsp;·&nbsp;
      <?php endif; ?>
      <?= htmlspecialchars($user_info['faculty_name'] ?? '') ?>
      &nbsp;·&nbsp;
      <?= htmlspecialchars($user_info['department_name'] ?? '') ?>
    </p>
    <?php endif; ?>

    <!-- Quick stats -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:14px;margin-bottom:28px">
      <div style="background:var(--surf);border:1px solid var(--border);border-radius:var(--rl);padding:16px 18px">
        <div style="font-size:11px;color:var(--muted);margin-bottom:4px">Active Loans</div>
        <div style="font-size:28px;font-weight:700;color:var(--gd)" id="ps-active">—</div>
      </div>
      <div style="background:var(--surf);border:1px solid var(--border);border-radius:var(--rl);padding:16px 18px">
        <div style="font-size:11px;color:var(--muted);margin-bottom:4px">Overdue</div>
        <div style="font-size:28px;font-weight:700;color:#dc2626" id="ps-overdue">—</div>
      </div>
      <div style="background:var(--surf);border:1px solid var(--border);border-radius:var(--rl);padding:16px 18px">
        <div style="font-size:11px;color:var(--muted);margin-bottom:4px">Books Returned</div>
        <div style="font-size:28px;font-weight:700;color:#1d4ed8" id="ps-returned">—</div>
      </div>
      <div style="background:#fffbeb;border:1px solid #fcd34d;border-radius:var(--rl);padding:16px 18px">
        <div style="font-size:11px;color:var(--muted);margin-bottom:4px">Unpaid Fines</div>
        <div style="font-size:28px;font-weight:700;color:#b45309" id="ps-fines">—</div>
      </div>
    </div>

    <!-- Tab buttons -->
    <div style="display:flex;gap:6px;margin-bottom:20px;flex-wrap:wrap">
      <button class="tab-btn active" onclick="portalTab(this,'pt-loans')">📚 Current Loans</button>
      <button class="tab-btn" onclick="portalTab(this,'pt-history')">🕐 Loan History</button>
      <button class="tab-btn" onclick="portalTab(this,'pt-reservations')">🔖 Reservations</button>
    </div>

    <!-- Flash message -->
    <div id="portalFlash" style="display:none;padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:14px"></div>

    <!-- Current Loans -->
    <div id="pt-loans">
      <div id="activeLoansPortal" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px">
        <p style="color:var(--muted);font-size:14px;padding:40px;grid-column:1/-1;text-align:center">Loading your loans…</p>
      </div>
    </div>

    <!-- Loan History -->
    <div id="pt-history" style="display:none">
      <div style="overflow-x:auto;background:var(--surf);border:1px solid var(--border);border-radius:var(--rl)">
        <table style="width:100%;border-collapse:collapse;font-size:13px">
          <thead><tr style="background:var(--bg)">
            <th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;border-bottom:2px solid var(--border)">Book</th>
            <th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;border-bottom:2px solid var(--border)">Borrowed</th>
            <th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;border-bottom:2px solid var(--border)">Returned</th>
            <th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;border-bottom:2px solid var(--border)">Fine</th>
          </tr></thead>
          <tbody id="historyTableBody">
            <tr><td colspan="4" style="text-align:center;padding:30px;color:var(--muted)">Loading history…</td></tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Reservations -->
    <div id="pt-reservations" style="display:none">
      <div id="reservationsPortal" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:14px">
        <p style="color:var(--muted);font-size:14px;padding:30px;grid-column:1/-1;text-align:center">Loading reservations…</p>
      </div>
    </div>

    <?php else: ?>
    <!-- ── NOT LOGGED IN — PREVIEW ── -->
    <div class="portal-grid">
      <div>
        <div class="portal-mockup">
          <div class="pm-header">
            <div class="dots"><div class="dot d1"></div><div class="dot d2"></div><div class="dot d3"></div></div>
            <span>My Library Account</span>
          </div>
          <div class="pm-body">
            <div style="padding:4px 0 14px;border-bottom:1px solid var(--border);margin-bottom:12px">
              <div style="font-size:11px;color:var(--muted)">Sign in to view your account</div>
              <div style="font-size:16px;font-weight:700">IBBUL Smart Library Portal</div>
              <div style="font-size:11px;color:var(--muted);font-family:'Courier New',monospace">e.g. U22/FNS/CSC/0001</div>
            </div>
            <div class="p-row"><div class="p-icon">📘</div><div class="p-text"><strong>Data Structures &amp; Algorithms</strong><span>Due: 28 Apr 2025</span></div><span class="p-status ps-ok">On Loan</span></div>
            <div class="p-row"><div class="p-icon">📗</div><div class="p-text"><strong>Operating Systems Concepts</strong><span>Was due: 15 Apr 2025</span></div><span class="p-status ps-due">Overdue</span></div>
            <div class="p-row"><div class="p-icon">💻</div><div class="p-text"><strong>IEEE Xplore Journal Access</strong><span>E-Resource · Active</span></div><span class="p-status ps-new">Active</span></div>
          </div>
        </div>
      </div>
      <div class="portal-desc">
        <div class="section-label" style="margin-bottom:10px">My Library Account</div>
        <h3>Your Personal Library Dashboard</h3>
        <p>Every IBBUL student gets a personal library portal — track borrowing, renew books, pay fines, and access e-resources all in one place.</p>
        <ul class="portal-list">
          <li>View all borrowed books and due dates</li>
          <li>Renew loans without visiting the library</li>
          <li>Get notified before books become overdue</li>
          <li>Reserve books and manage your hold queue</li>
          <li>Download e-books and journal articles</li>
          <li>Track your fine balance online</li>
        </ul>
        <a href="#login" class="btn-primary">Access My Portal</a>
        <a href="#login" class="btn-outline" onclick="openRegister()">Register Now</a>
      </div>
    </div>
    <?php endif; ?>
  </div>
</section>

<section id="how">
  <div class="container">
    <div class="section-label" style="text-align:center">Getting Started</div>
    <div class="section-title" style="text-align:center">How the Smart Library Works</div>
    <div class="steps">
      <div class="step"><div class="step-num">1</div><h4>Register Your Account</h4><p>Fill the form with your admission year, faculty, and department. Your matric number is auto-generated.</p></div>
      <div class="step"><div class="step-num">2</div><h4>Search &amp; Discover</h4><p>Use the smart catalog to find books, journals, and e-resources by title, author, or department.</p></div>
      <div class="step"><div class="step-num">3</div><h4>Reserve or Access</h4><p>Reserve physical books online or access digital resources immediately from any device.</p></div>
      <div class="step"><div class="step-num">4</div><h4>Borrow &amp; Return</h4><p>Pick up reserved items at the circulation desk. Return on time or renew loans online.</p></div>
    </div>
  </div>
</section>

<div class="stats-band">
  <div class="stats-inner">
    <div class="stat-item"><strong><?= number_format($stats['total_copies']) ?>+</strong><span>Physical Books &amp; Resources</span></div>
    <div class="stat-item"><strong><?= number_format($stats['digital_count']) ?>+</strong><span>Digital Resources</span></div>
    <div class="stat-item"><strong><?= count($faculties) ?></strong><span>Faculties Covered</span></div>
    <div class="stat-item"><strong><?= number_format($stats['total_students']) ?>+</strong><span>Registered Students</span></div>
    <div class="stat-item"><strong>99%</strong><span>System Uptime SLA</span></div>
  </div>
</div>

<section id="announcements">
  <div class="container">
    <div class="section-label">Library News</div>
    <div class="section-title">Announcements &amp; Events</div>
    <div class="news-grid">
      <?php foreach ($announcements as $ann): ?>
        <div class="news-card" style="border-left-color:<?= htmlspecialchars($ann['tag_color']) ?>">
          <div class="news-tag" style="color:<?= htmlspecialchars($ann['tag_color']) ?>"><?= htmlspecialchars($ann['tag']) ?></div>
          <h4><?= htmlspecialchars($ann['title']) ?></h4>
          <p><?= htmlspecialchars($ann['body']) ?></p>
          <div class="news-date"><?= date('F j, Y', strtotime($ann['published_at'])) ?></div>
        </div>
      <?php endforeach; ?>
      <?php if (empty($announcements)): ?>
        <p style="color:var(--muted);font-size:14px">No announcements at this time.</p>
      <?php endif; ?>
    </div>
  </div>
</section>

<section id="login" style="background:var(--surf)">
  <div class="container" style="text-align:center">
    <div class="section-label">Secure Access</div>
    <div class="section-title">Sign In or Register</div>
    <p class="section-sub" style="margin:0 auto">Students, staff, and administrators — use your IBBUL credentials to access your library account.</p>
    <div class="login-wrap">
      <div class="login-card">
        <div class="login-tabs">
          <div class="ltab active" id="tabSignin"   onclick="switchMain('signin')">Sign In</div>
          <div class="ltab"        id="tabRegister" onclick="switchMain('register')">New Registration</div>
        </div>
        <div id="formMsg" style="display:none"></div>

        <!-- SIGN IN -->
        <div id="signinPane">
          <div style="background:var(--gl);border:1px solid #a5d6b0;border-radius:8px;padding:10px 14px;margin-bottom:18px;font-size:12.5px;color:var(--gd);line-height:1.7">
            <strong>Students</strong> — sign in with your <strong>Library Registration Number</strong>
            (e.g. <code style="font-family:monospace">LIB-2025-0001</code>) issued at registration<br>
            <strong>Staff / Admin</strong> — sign in with your Staff or Admin ID
            (e.g. <code style="font-family:monospace">STAFF-2024-0042</code> or <code style="font-family:monospace">ADMIN-001</code>)
          </div>
          <div class="form-group">
            <label>Library Registration / Staff / Admin Number</label>
            <input type="text" id="loginMatric"
                   placeholder="e.g. LIB-2025-0001"
                   oninput="this.value=this.value.toUpperCase()"
                   style="font-family:'Courier New',monospace;font-size:15px;letter-spacing:1px"/>
          </div>
          <div class="form-group">
            <label>Password</label>
            <input type="password" id="loginPass" placeholder="Enter your password"/>
          </div>
          <button class="login-btn" id="signinBtn" onclick="loginUser()">Sign In to Library Portal</button>
          <div class="login-footer"><a href="#">Forgot Password?</a> · <a href="#" onclick="switchMain('register')">New student? Register here</a></div>
        </div>

        <!-- REGISTER -->
        <div id="registerPane" style="display:none">
          <div class="role-tabs">
            <div class="rtab active" id="roleStudent" onclick="switchRole('student')">👨‍🎓 Student</div>
            <div class="rtab"        id="roleStaff"   onclick="switchRole('staff')">👨‍💼 Staff</div>
          </div>

          <!-- Student form -->
          <div id="studentForm">
            <div style="background:var(--gl);border:1px solid #a5d6b0;border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:13px;color:var(--gd);line-height:1.6">
              📋 Enter your <strong>school-assigned matric number</strong>. The library will generate a
              <strong>Library Registration Number</strong> (e.g. <code style="font-family:monospace;font-size:13px">LIB-2025-0001</code>)
              which you will use to log into the portal.
            </div>

            <div class="form-group">
              <label>Full Name <span style="color:red">*</span></label>
              <input type="text" id="sName" placeholder="e.g. Abdullahi Musa Ibrahim"/>
            </div>

            <div class="form-group">
              <label>School Matric Number <span style="color:red">*</span></label>
              <input type="text" id="sSchoolMatric"
                     placeholder="e.g. U22/FNS/CSC/0042"
                     oninput="this.value=this.value.toUpperCase()"
                     style="font-family:'Courier New',monospace;font-size:15px;letter-spacing:1px"/>
              <small style="font-size:11px;color:var(--muted)">As printed on your university ID card or admission letter</small>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label>Admission Year <span style="color:red">*</span></label>
                <select id="sYear">
                  <option value="">Select year…</option>
                  <?php for ($y = (int)date('Y'); $y >= 2010; $y--): ?>
                    <option value="<?= $y ?>"><?= $y ?></option>
                  <?php endfor; ?>
                </select>
              </div>
              <div class="form-group">
                <label>Level <span style="color:red">*</span></label>
                <select id="sLevel">
                  <option value="100">100 Level</option>
                  <option value="200">200 Level</option>
                  <option value="300">300 Level</option>
                  <option value="400">400 Level</option>
                  <option value="500">500 Level</option>
                  <option value="PG">Postgraduate</option>
                </select>
              </div>
            </div>

            <div class="form-group">
              <label>Faculty <span style="color:red">*</span></label>
              <select id="sFaculty" onchange="loadDepartments()">
                <option value="">— Select your faculty —</option>
                <?php foreach ($faculties as $fac): ?>
                  <option value="<?= htmlspecialchars($fac['code']) ?>">
                    [<?= htmlspecialchars($fac['code']) ?>] <?= htmlspecialchars($fac['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label>Department <span style="color:red">*</span></label>
              <select id="sDept" disabled>
                <option value="">— Select faculty first —</option>
              </select>
            </div>

            <div class="form-group">
              <label>Email Address <span style="color:red">*</span></label>
              <input type="email" id="sEmail" placeholder="yourname@ibbul.edu.ng"/>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label>Phone Number</label>
                <input type="tel" id="sPhone" placeholder="+234 8XX XXX XXXX"/>
              </div>
              <div class="form-group">
                <label>Password <span style="color:red">*</span> <small style="font-weight:400">(min. 8 chars)</small></label>
                <input type="password" id="sPass" placeholder="Create a strong password"/>
              </div>
            </div>

            <button class="login-btn" id="studentRegBtn" onclick="registerStudent()">Register &amp; Get Library Number</button>
            <div class="login-footer">Already registered? <a href="#" onclick="switchMain('signin')">Sign in here</a></div>
          </div>

          <!-- Staff form -->
          <div id="staffForm" style="display:none">
            <div class="form-row">
              <div class="form-group">
                <label>Full Name <span style="color:red">*</span></label>
                <input type="text" id="stName" placeholder="e.g. Dr. Fatima Abdullahi"/>
              </div>
              <div class="form-group">
                <label>Staff ID <span style="color:red">*</span></label>
                <input type="text" id="stID" placeholder="e.g. STAFF-2024-0042" style="font-family:'Courier New',monospace"/>
              </div>
            </div>
            <div class="form-group">
              <label>Department</label>
              <select id="stDept">
                <option value="">— Select department (optional) —</option>
                <?php foreach ($faculties as $fac): ?>
                  <optgroup label="<?= htmlspecialchars($fac['name']) ?>">
                    <?php foreach ($all_departments[$fac['code']] ?? [] as $dept): ?>
                      <option value="<?= htmlspecialchars($dept['code']) ?>">
                        [<?= htmlspecialchars($dept['code']) ?>] <?= htmlspecialchars($dept['name']) ?>
                      </option>
                    <?php endforeach; ?>
                  </optgroup>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-row">
              <div class="form-group">
                <label>Email Address <span style="color:red">*</span></label>
                <input type="email" id="stEmail" placeholder="staff@ibbul.edu.ng"/>
              </div>
              <div class="form-group">
                <label>Phone Number</label>
                <input type="tel" id="stPhone" placeholder="+234 8XX XXX XXXX"/>
              </div>
            </div>
            <div class="form-group">
              <label>Password <span style="color:red">*</span></label>
              <input type="password" id="stPass" placeholder="Create a strong password"/>
            </div>
            <button class="login-btn" id="staffRegBtn" onclick="registerStaff()">Create Staff Account</button>
            <div class="login-footer">Already registered? <a href="#" onclick="switchMain('signin')">Sign in here</a></div>
          </div>
        </div>
      </div>
      <p style="font-size:12px;color:var(--muted);margin-top:14px;text-align:center">
        🔒 Your data is protected. Contact us: <strong>library@ibbul.edu.ng</strong>
      </p>
    </div>
  </div>
</section>

<footer>
  <div class="footer-inner">
    <div class="footer-grid">
      <div class="footer-brand">
        <div class="f-logo">
          <div class="f-logo-icon">IBB</div>
          <div class="f-logo-txt">IBBUL Smart Library<small>Ibrahim Badamasi Babangida University, Lapai</small></div>
        </div>
        <p>Empowering academic excellence through intelligent access to knowledge resources for all IBBUL community members.</p>
      </div>
      <div>
        <h5>Quick Links</h5>
        <ul>
          <li><a href="#catalog">Book Catalog</a></li>
          <li><a href="#portal">My Account</a></li>
          <li><a href="#">E-Resources</a></li>
          <li><a href="#">IBBUL Repository</a></li>
          <li><a href="#">New Arrivals</a></li>
        </ul>
      </div>
      <div>
        <h5>Services</h5>
        <ul>
          <li><a href="#">Borrowing Policy</a></li>
          <li><a href="#">Inter-Library Loans</a></li>
          <li><a href="#">Course Reserve</a></li>
          <li><a href="#">Study Rooms</a></li>
          <li><a href="#">Printing &amp; Copying</a></li>
        </ul>
      </div>
      <div>
        <h5>Contact</h5>
        <ul>
          <li><a href="#">📍 Main Library Building, IBBUL Campus, Lapai, Niger State</a></li>
          <li><a href="mailto:library@ibbul.edu.ng">✉️ library@ibbul.edu.ng</a></li>
          <li><a href="#">📞 +234 800 IBBUL LIB</a></li>
          <li><a href="#">🕐 Mon–Fri: 8am–9pm</a></li>
          <li><a href="#">🕐 Sat: 9am–5pm</a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <p>© <?= date('Y') ?> Ibrahim Badamasi Babangida University, Lapai. Smart Library System. All rights reserved.</p>
      <div class="credits">
        <a href="#">Privacy Policy</a><a href="#">Terms of Use</a><a href="#">Accessibility</a><a href="#">Help &amp; FAQ</a>
      </div>
    </div>
  </div>
</footer>

<script>
const ALL_DEPARTMENTS = <?= json_encode($all_departments) ?>;
// CSRF token — automatically appended to every POST request
const CSRF_TOKEN = '<?= htmlspecialchars($csrf) ?>';

function switchMain(tab) {
  document.getElementById('signinPane').style.display   = tab==='signin'   ? 'block' : 'none';
  document.getElementById('registerPane').style.display = tab==='register' ? 'block' : 'none';
  document.getElementById('tabSignin').classList.toggle('active',   tab==='signin');
  document.getElementById('tabRegister').classList.toggle('active', tab==='register');
  document.getElementById('formMsg').style.display = 'none';
}
function openRegister() {
  switchMain('register');
  document.getElementById('login').scrollIntoView({behavior:'smooth'});
}
function switchRole(role) {
  document.getElementById('studentForm').style.display = role==='student' ? 'block' : 'none';
  document.getElementById('staffForm').style.display   = role==='staff'   ? 'block' : 'none';
  document.getElementById('roleStudent').classList.toggle('active', role==='student');
  document.getElementById('roleStaff').classList.toggle('active',   role==='staff');
  document.getElementById('formMsg').style.display = 'none';
}
function loadDepartments() {
  const fc   = document.getElementById('sFaculty').value;
  const sel  = document.getElementById('sDept');
  sel.innerHTML = '<option value="">— Select your department —</option>';
  if (!fc) { sel.disabled = true; return; }
  (ALL_DEPARTMENTS[fc] || []).forEach(d => {
    const o = document.createElement('option');
    o.value = d.code;
    o.textContent = `[${d.code}] ${d.name}`;
    sel.appendChild(o);
  });
  sel.disabled = (ALL_DEPARTMENTS[fc] || []).length === 0;
}
function showMsg(html, type='success') {
  const el = document.getElementById('formMsg');
  el.className     = type==='success' ? 'msg-ok' : 'msg-err';
  el.innerHTML     = html;
  el.style.display = 'block';
  el.scrollIntoView({behavior:'smooth', block:'nearest'});
}
async function loginUser() {
  const btn = document.getElementById('signinBtn');
  btn.disabled = true; btn.textContent = 'Signing in…';
  const body = new FormData();
  body.append('action',        'login_user');
  body.append('_csrf_token',   CSRF_TOKEN);
  body.append('matric_number', document.getElementById('loginMatric').value.trim().toUpperCase());
  body.append('password',      document.getElementById('loginPass').value);
  try {
    const r = await fetch('library_actions.php', {method:'POST', body});
    const d = await r.json();
    if (d.status === 'success') {
      const role = d.user?.role || '';
      if (role === 'admin') {
        showMsg('✅ Welcome Admin! Redirecting to Admin Panel…');
        setTimeout(() => window.location.href = 'admin.php', 1000);
      } else if (role === 'staff') {
        showMsg('✅ Welcome ' + d.user.full_name + '! Redirecting to Admin Panel…');
        setTimeout(() => window.location.href = 'admin.php', 1000);
      } else {
        showMsg('✅ ' + d.message + ' Reloading…');
        setTimeout(() => location.reload(), 1000);
      }
    } else {
      showMsg('❌ ' + d.message, 'error');
    }
  } catch { showMsg('❌ Network error. Please try again.', 'error'); }
  finally { btn.disabled=false; btn.textContent='Sign In to Library Portal'; }
}
async function registerStudent() {
  const btn = document.getElementById('studentRegBtn');
  btn.disabled = true; btn.textContent = 'Registering…';
  const body = new FormData();
  body.append('action',         'register_student');
  body.append('_csrf_token',    CSRF_TOKEN);
  body.append('full_name',      document.getElementById('sName').value.trim());
  body.append('school_matric',  document.getElementById('sSchoolMatric').value.trim().toUpperCase());
  body.append('admission_year', document.getElementById('sYear').value);
  body.append('faculty_code',   document.getElementById('sFaculty').value);
  body.append('dept_code',      document.getElementById('sDept').value);
  body.append('level',          document.getElementById('sLevel').value);
  body.append('email',          document.getElementById('sEmail').value.trim());
  body.append('phone',          document.getElementById('sPhone').value.trim());
  body.append('password',       document.getElementById('sPass').value);
  try {
    const r = await fetch('library_actions.php', {method:'POST', body});
    const d = await r.json();
    if (d.status === 'success') {
      showMsg(`
        <div style="text-align:center;padding:10px 0">
          <div style="font-size:44px;margin-bottom:12px">🎉</div>
          <h3 style="font-size:18px;font-weight:700;color:var(--gd);margin-bottom:6px">Registration Successful!</h3>
          <p style="font-size:13px;color:var(--muted);margin-bottom:16px">Use your Library Registration Number to log in.</p>

          <div style="margin-bottom:14px">
            <div style="font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px">
              📚 Library Registration Number (Login with this)
            </div>
            <div style="font-size:26px;font-weight:700;font-family:'Courier New',monospace;color:var(--gd);
                        background:var(--gl);padding:10px 20px;border-radius:8px;display:inline-block;
                        letter-spacing:2px;border:2px dashed var(--gm)">${d.library_reg_number}</div>
          </div>

          <div style="margin-bottom:14px">
            <div style="font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">
              🎓 School Matric Number (stored for records)
            </div>
            <div style="font-size:15px;font-weight:600;font-family:'Courier New',monospace;color:var(--muted)">${d.school_matric}</div>
          </div>

          <div style="background:#fff8e1;border:1px solid #fcd34d;border-radius:8px;padding:10px 14px;
                      font-size:13px;color:#92400e;text-align:left;margin-top:12px">
            ⚠️ <strong>Write this down:</strong> Your Library Number is
            <strong>${d.library_reg_number}</strong>. You need it to log in.
          </div>
          <div style="font-size:12px;color:var(--muted);margin-top:12px">
            ${d.full_name} &nbsp;·&nbsp; ${d.faculty} &nbsp;·&nbsp; ${d.department} &nbsp;·&nbsp; Level ${d.level}
          </div>
        </div>`);
      ['sName','sSchoolMatric','sEmail','sPhone','sPass'].forEach(id => document.getElementById(id).value='');
      document.getElementById('sYear').selectedIndex    = 0;
      document.getElementById('sFaculty').selectedIndex = 0;
      document.getElementById('sDept').innerHTML = '<option value="">— Select faculty first —</option>';
      document.getElementById('sDept').disabled  = true;
    } else showMsg('❌ '+d.message, 'error');
  } catch { showMsg('❌ Network error. Please try again.', 'error'); }
  finally { btn.disabled=false; btn.textContent='Register & Get Library Number'; }
}
async function registerStaff() {
  const btn = document.getElementById('staffRegBtn');
  btn.disabled = true; btn.textContent = 'Registering…';
  const body = new FormData();
  body.append('action',      'register_staff');
  body.append('_csrf_token', CSRF_TOKEN);
  body.append('full_name',   document.getElementById('stName').value.trim());
  body.append('staff_id',    document.getElementById('stID').value.trim());
  body.append('dept_code',   document.getElementById('stDept').value);
  body.append('email',       document.getElementById('stEmail').value.trim());
  body.append('phone',       document.getElementById('stPhone').value.trim());
  body.append('password',    document.getElementById('stPass').value);
  try {
    const r = await fetch('library_actions.php', {method:'POST', body});
    const d = await r.json();
    if (d.status === 'success') {
      showMsg(`
        <div style="text-align:center;padding:10px 0">
          <div style="font-size:36px;margin-bottom:8px">⏳</div>
          <strong style="font-size:15px;color:var(--gd)">Registration Submitted!</strong><br>
          <p style="font-size:13px;color:var(--muted);margin-top:8px">
            Your staff registration for <strong>${d.staff_id}</strong> has been received
            and is <strong>pending approval</strong> by the library administrator.<br><br>
            You will be able to log in once the admin approves your account.
            Please check back later or contact the library office.
          </p>
        </div>`);
    } else {
      showMsg('❌ ' + d.message, 'error');
    }
  } catch { showMsg('❌ Network error. Please try again.', 'error'); }
  finally { btn.disabled=false; btn.textContent='Submit Registration for Approval'; }
}
async function logoutUser() {
  const body = new FormData();
  body.append('action',      'logout_user');
  body.append('_csrf_token', CSRF_TOKEN);
  await fetch('library_actions.php', {method:'POST', body});
  location.reload();
}
function filterCatalog(btn, cat) {
  document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('#catalogGrid .book-card').forEach(card => {
    const isDigital = card.dataset.type==='digital'||card.dataset.type==='both';
    const show = cat==='all' ? true : cat==='digital' ? isDigital : card.dataset.cat===cat;
    card.style.display = show ? 'block' : 'none';
  });
}
let srTimer;
function searchBooks() {
  clearTimeout(srTimer);
  const q    = document.getElementById('searchInput').value.trim();
  const wrap = document.getElementById('searchResults');
  if (q.length < 2) { wrap.style.display='none'; return; }
  document.getElementById('srTitle').textContent = 'Searching…';
  document.getElementById('srGrid').innerHTML    = '';
  wrap.style.display = 'block';
  srTimer = setTimeout(async () => {
    try {
      const r = await fetch(`library_actions.php?action=search_books&q=${encodeURIComponent(q)}`);
      const d = await r.json();
      if (d.status==='success' && d.count > 0) {
        document.getElementById('srTitle').textContent = `${d.count} result(s) for "${q}"`;
        document.getElementById('srGrid').innerHTML = d.data.map(b=>`
          <div class="book-card">
            <div class="book-cover" style="background:${b.cover_color}">
              <span style="font-size:40px">${b.cover_emoji}</span>
              <span class="book-badge ${b.resource_type==='digital'?'badge-digital':b.available_copies>0?'badge-avail':'badge-loan'}">
                ${b.resource_type==='digital'?'Digital':b.available_copies>0?'Available':'On Loan'}
              </span>
            </div>
            <div class="book-info">
              <h4>${b.title}</h4><p>${b.author}</p>
              <div class="book-dept">${b.department_name??''}</div>
            </div>
          </div>`).join('');
      } else {
        document.getElementById('srTitle').textContent = `No results found for "${q}"`;
      }
    } catch { document.getElementById('srTitle').textContent='Search failed. Try again.'; }
  }, 400);
}
document.getElementById('searchInput').addEventListener('keyup', searchBooks);

// ============================================================
//  STUDENT PORTAL — Dynamic Dashboard (only when logged in)
// ============================================================
<?php if (is_logged_in() && $_SESSION['user_role'] === 'student'): ?>
(async function initPortal() {
  try {
    const res  = await fetch('library_actions.php?action=student_dashboard');
    const data = await res.json();
    if (data.status !== 'success') return;

    // Update stat counters
    const c = data.counts || {};
    document.getElementById('ps-active').textContent   = c.active_count   || 0;
    document.getElementById('ps-overdue').textContent  = c.overdue_count  || 0;
    document.getElementById('ps-returned').textContent = c.returned_count || 0;
    document.getElementById('ps-fines').textContent    =
      '₦' + parseFloat(data.fine_total || 0).toLocaleString('en-NG', {minimumFractionDigits:2});

    // Render active loans as cards
    const loansEl = document.getElementById('activeLoansPortal');
    if (!data.active_loans || data.active_loans.length === 0) {
      loansEl.innerHTML = `
        <div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--muted)">
          <div style="font-size:40px;margin-bottom:12px">✅</div>
          <p>You have no active loans. <a href="#catalog" style="color:var(--gm);font-weight:600">Browse the catalog</a> to find a book.</p>
        </div>`;
    } else {
      loansEl.innerHTML = data.active_loans.map(l => {
        const today   = new Date();
        const due     = new Date(l.due_date);
        const diffMs  = due - today;
        const diffDays = Math.ceil(diffMs / 86400000);
        const isOver  = l.status === 'overdue' || diffDays < 0;
        const borderC = isOver ? '#dc2626' : diffDays <= 3 ? '#b45309' : '#a5d6b0';
        const dueLabel = isOver
          ? `<span style="color:#dc2626;font-weight:700">⚠ ${Math.abs(diffDays)} day(s) overdue</span>`
          : diffDays === 0
            ? `<span style="color:#b45309;font-weight:700">Due today!</span>`
            : `<span style="color:var(--gm)">${diffDays} day(s) remaining</span>`;

        return `
          <div style="background:var(--surf);border:1px solid var(--border);border-left:4px solid ${borderC};
                      border-radius:var(--rl);padding:18px;position:relative">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px">
              <div style="width:48px;height:48px;border-radius:10px;background:${l.cover_color||'#e8f5ec'};
                          display:flex;align-items:center;justify-content:center;font-size:24px;flex-shrink:0">
                ${l.cover_emoji||'📘'}
              </div>
              <div style="flex:1;min-width:0">
                <div style="font-size:14px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${escHtml(l.title)}</div>
                <div style="font-size:12px;color:var(--muted)">${escHtml(l.author)}</div>
                <div style="font-size:11px;color:var(--gm);font-weight:500">${escHtml(l.department_name||'')}</div>
              </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:12px;margin-bottom:14px">
              <div><span style="color:var(--muted)">Borrowed:</span> <strong>${l.borrow_date}</strong></div>
              <div><span style="color:var(--muted)">Due:</span> <strong>${l.due_date}</strong></div>
              <div style="grid-column:1/-1">${dueLabel}</div>
              ${l.fine_amount > 0 ? `<div style="grid-column:1/-1;background:#fffbeb;padding:6px 10px;border-radius:6px;color:#b45309;font-weight:600">
                ⚠ Fine: ₦${parseFloat(l.fine_amount).toLocaleString('en-NG',{minimumFractionDigits:2})} — Pay at the library desk
              </div>` : ''}
            </div>
            ${!isOver ? `
              <button onclick="renewLoan(${l.id}, this)"
                style="width:100%;padding:8px;background:var(--gd);color:#fff;border:none;
                       border-radius:6px;font-size:13px;font-weight:600;cursor:pointer">
                🔄 Renew Loan (+14 days)
              </button>` : `
              <div style="font-size:12px;color:#dc2626;text-align:center;padding:6px;
                          background:#fef2f2;border-radius:6px">
                Overdue loans cannot be renewed. Visit the library to return this book.
              </div>`}
          </div>`;
      }).join('');
    }

    // Render history table
    const histBody = document.getElementById('historyTableBody');
    if (!data.history || data.history.length === 0) {
      histBody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:30px;color:var(--muted)">No borrowing history yet.</td></tr>';
    } else {
      histBody.innerHTML = data.history.map(l => `
        <tr style="border-bottom:1px solid var(--border)">
          <td style="padding:10px 12px">
            <span style="font-size:18px">${l.cover_emoji||'📘'}</span>
            <strong style="margin-left:6px">${escHtml(l.title)}</strong>
            <div style="font-size:11px;color:var(--muted);margin-left:28px">${escHtml(l.author)}</div>
          </td>
          <td style="padding:10px 12px;font-size:12px">${l.borrow_date}</td>
          <td style="padding:10px 12px;font-size:12px">${l.return_date||'—'}</td>
          <td style="padding:10px 12px">
            ${l.fine_amount > 0
              ? `<span style="color:${l.fine_paid?'var(--gm)':'#b45309'};font-weight:600">
                  ₦${parseFloat(l.fine_amount).toLocaleString('en-NG',{minimumFractionDigits:2})}
                  ${l.fine_paid ? ' ✓' : ' (unpaid)'}
                 </span>`
              : '<span style="color:var(--muted)">No fine</span>'}
          </td>
        </tr>`).join('');
    }

    // Render reservations
    const resEl = document.getElementById('reservationsPortal');
    if (!data.reservations || data.reservations.length === 0) {
      resEl.innerHTML = `
        <div style="grid-column:1/-1;text-align:center;padding:40px;color:var(--muted)">
          <div style="font-size:40px;margin-bottom:12px">🔖</div>
          <p>No active reservations. Browse the catalog and reserve a book.</p>
        </div>`;
    } else {
      resEl.innerHTML = data.reservations.map(r => {
        const statusColor = r.status==='ready' ? '#1a5c2e' : '#b45309';
        const statusBg    = r.status==='ready' ? '#e8f5ec'  : '#fffbeb';
        return `
          <div style="background:var(--surf);border:1px solid var(--border);border-radius:var(--rl);padding:16px">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
              <div style="width:42px;height:42px;border-radius:8px;background:${r.cover_color||'#e8f5ec'};
                          display:flex;align-items:center;justify-content:center;font-size:22px">
                ${r.cover_emoji||'📘'}
              </div>
              <div>
                <div style="font-size:14px;font-weight:700">${escHtml(r.title)}</div>
                <div style="font-size:12px;color:var(--muted)">${escHtml(r.author)}</div>
              </div>
            </div>
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
              <span style="font-size:12px;font-weight:600;padding:4px 12px;border-radius:10px;
                           background:${statusBg};color:${statusColor}">
                ${r.status === 'ready' ? '✅ Ready for pickup' : '⏳ On hold — waiting'}
              </span>
              <button onclick="cancelReservation(${r.id}, this)"
                style="padding:5px 12px;border:1px solid var(--border);border-radius:6px;
                       font-size:12px;cursor:pointer;background:var(--surf)">
                Cancel
              </button>
            </div>
            ${r.expiry_date ? `<div style="font-size:11px;color:var(--muted);margin-top:8px">Expires: ${r.expiry_date}</div>` : ''}
          </div>`;
      }).join('');
    }

  } catch(e) {
    console.error('Portal load failed:', e);
  }
})();

function portalTab(btn, tabId) {
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  ['pt-loans','pt-history','pt-reservations'].forEach(id => {
    document.getElementById(id).style.display = id === tabId ? 'block' : 'none';
  });
}

async function renewLoan(loanId, btn) {
  btn.disabled = true;
  btn.textContent = 'Renewing…';
  const body = new FormData();
  body.append('action','student_renew_loan');
  body.append('loan_id', loanId);
  try {
    const res  = await fetch('library_actions.php', {method:'POST', body});
    const data = await res.json();
    const flash = document.getElementById('portalFlash');
    flash.style.cssText = `display:block;background:${data.status==='success'?'#e8f5ec':'#fce4ec'};
      color:${data.status==='success'?'#1a5c2e':'#b71c1c'};border-radius:8px;padding:10px 14px;font-size:13px;margin-bottom:14px`;
    flash.textContent = (data.status === 'success' ? '✅ ' : '❌ ') + data.message;
    if (data.status === 'success') setTimeout(() => location.reload(), 1500);
    else { btn.disabled = false; btn.textContent = '🔄 Renew Loan (+14 days)'; }
  } catch {
    btn.disabled = false;
    btn.textContent = '🔄 Renew Loan (+14 days)';
  }
}

async function cancelReservation(resId, btn) {
  if (!confirm('Cancel this reservation?')) return;
  btn.disabled = true;
  const body = new FormData();
  body.append('action','student_cancel_reservation');
  body.append('reservation_id', resId);
  const res  = await fetch('library_actions.php', {method:'POST', body});
  const data = await res.json();
  alert(data.message);
  if (data.status === 'success') location.reload();
  else btn.disabled = false;
}

function escHtml(s) {
  return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
<?php endif; ?>
document.querySelectorAll('a[href^="#"]').forEach(a => {
  a.addEventListener('click', e => {
    const t = document.querySelector(a.getAttribute('href'));
    if (t) { e.preventDefault(); t.scrollIntoView({behavior:'smooth',block:'start'}); }
  });
});
</script>
</body>
</html>
