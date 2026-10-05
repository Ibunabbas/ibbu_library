<?php
// ============================================================
//  FILE: admin.php
//  PURPOSE: Admin dashboard — manage loans, students, books
//  ACCESS:  Admin account only (role = 'admin')
// ============================================================
require_once 'db_config.php';
set_security_headers();
start_session();

// Redirect non-admins immediately
if (!is_logged_in() || $_SESSION['user_role'] !== 'admin') {
    redirect('index.php');
}

$admin_name = htmlspecialchars($_SESSION['user_name']);
$csrf       = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Admin Dashboard — IBBUL Smart Library</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{
  --gd:#1a5c2e;--gm:#2d8a4e;--gl:#e8f5ec;
  --gold:#c8960c;--goldl:#fef8e7;
  --txt:#1a1a1a;--muted:#5a5a5a;--border:#e0e0e0;
  --surf:#fff;--bg:#f4f6f4;--r:8px;--rl:14px;
  --red:#dc2626;--redl:#fef2f2;
  --blue:#1d4ed8;--bluel:#eff6ff;
  --amber:#b45309;--amberl:#fffbeb;
}
body{font-family:'Segoe UI',system-ui,sans-serif;color:var(--txt);background:var(--bg);display:flex;min-height:100vh}

/* ── SIDEBAR ── */
.sidebar{width:240px;background:var(--gd);color:#fff;display:flex;flex-direction:column;flex-shrink:0;position:fixed;top:0;left:0;height:100vh;z-index:100;overflow-y:auto}
.sb-brand{padding:20px 18px 14px;border-bottom:1px solid rgba(255,255,255,.12)}
.sb-brand .logo{width:38px;height:38px;background:var(--gold);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;color:var(--gd);margin-bottom:10px}
.sb-brand h2{font-size:14px;font-weight:700;line-height:1.3}
.sb-brand p{font-size:11px;opacity:.65;margin-top:2px}
.sb-admin{padding:12px 18px;background:rgba(255,255,255,.08);font-size:12px}
.sb-admin span{opacity:.7}
.sb-admin strong{display:block;font-size:13px;margin-top:2px}
nav.sb-nav{flex:1;padding:12px 0}
.sb-section{font-size:10px;font-weight:600;letter-spacing:1px;text-transform:uppercase;color:rgba(255,255,255,.45);padding:14px 18px 6px}
.sb-link{display:flex;align-items:center;gap:10px;padding:9px 18px;font-size:13.5px;color:rgba(255,255,255,.8);cursor:pointer;transition:background .15s;border:none;background:none;width:100%;text-align:left}
.sb-link:hover,.sb-link.active{background:rgba(255,255,255,.12);color:#fff}
.sb-link .ic{font-size:16px;width:20px;text-align:center}
.sb-footer{padding:14px 18px;border-top:1px solid rgba(255,255,255,.1);font-size:12px;color:rgba(255,255,255,.5)}
.sb-footer a{color:rgba(255,255,255,.6);font-size:12px}

/* ── MAIN ── */
.main{margin-left:240px;flex:1;display:flex;flex-direction:column;min-height:100vh}
.topbar{background:var(--surf);border-bottom:1px solid var(--border);padding:0 28px;height:58px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50}
.topbar h1{font-size:18px;font-weight:700;color:var(--gd)}
.topbar-right{display:flex;align-items:center;gap:12px;font-size:13px;color:var(--muted)}
.content{padding:28px;flex:1}

/* ── STATS CARDS ── */
.stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:28px}
.stat-card{background:var(--surf);border:1px solid var(--border);border-radius:var(--rl);padding:18px 20px}
.stat-card .sc-label{font-size:12px;color:var(--muted);margin-bottom:6px}
.stat-card .sc-value{font-size:28px;font-weight:700;color:var(--gd)}
.stat-card .sc-sub{font-size:11px;color:var(--muted);margin-top:4px}
.stat-card.red .sc-value{color:var(--red)}
.stat-card.amber .sc-value{color:var(--amber)}
.stat-card.blue .sc-value{color:var(--blue)}

/* ── PANELS / SECTIONS ── */
.panel{background:var(--surf);border:1px solid var(--border);border-radius:var(--rl);overflow:hidden;margin-bottom:24px}
.panel-head{padding:14px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}
.panel-head h2{font-size:15px;font-weight:600}
.panel-body{padding:20px}

/* ── PAGE SECTIONS (shown/hidden) ── */
.page-section{display:none}
.page-section.active{display:block}

/* ── TABLE ── */
.tbl-wrap{overflow-x:auto}
table{width:100%;border-collapse:collapse;font-size:13px}
th{background:var(--bg);padding:10px 12px;text-align:left;font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;border-bottom:2px solid var(--border)}
td{padding:11px 12px;border-bottom:1px solid var(--border);vertical-align:middle}
tr:last-child td{border-bottom:none}
tr:hover td{background:#fafaf9}
.td-matric{font-family:'Courier New',monospace;font-size:12px;font-weight:600;color:var(--gd)}
.td-name strong{display:block;font-size:13px}
.td-name span{font-size:11px;color:var(--muted)}

/* ── STATUS BADGES ── */
.badge{display:inline-block;font-size:11px;font-weight:600;padding:3px 10px;border-radius:20px;white-space:nowrap}
.b-active{background:#e8f5ec;color:#1a5c2e}
.b-overdue{background:var(--amberl);color:var(--amber)}
.b-returned{background:#e8f0fe;color:#1d4ed8}
.b-lost{background:var(--redl);color:var(--red)}
.b-pending{background:#fef9c3;color:#854d0e}

/* ── FORMS ── */
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.form-grid-3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px}
@media(max-width:700px){.form-grid,.form-grid-3{grid-template-columns:1fr}}
.fg{display:flex;flex-direction:column;gap:5px}
.fg label{font-size:12px;font-weight:600;color:var(--muted)}
.fg input,.fg select,.fg textarea{padding:9px 12px;border:1px solid var(--border);border-radius:var(--r);font-size:13.5px;color:var(--txt);background:var(--surf);outline:none;transition:border .15s;width:100%;font-family:inherit}
.fg input:focus,.fg select:focus,.fg textarea:focus{border-color:var(--gm)}
.fg.full{grid-column:1/-1}
.btn{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:var(--r);font-size:13.5px;font-weight:600;cursor:pointer;border:none;transition:opacity .15s}
.btn:hover{opacity:.88}
.btn-primary{background:var(--gd);color:#fff}
.btn-danger{background:var(--red);color:#fff}
.btn-amber{background:var(--amber);color:#fff}
.btn-outline{background:transparent;border:1.5px solid var(--gd);color:var(--gd)}
.btn-sm{padding:5px 12px;font-size:12px}
.btn-ghost{background:var(--bg);color:var(--txt);border:1px solid var(--border)}

/* ── SEARCH BAR ── */
.search-row{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.search-row input,.search-row select{padding:8px 12px;border:1px solid var(--border);border-radius:var(--r);font-size:13px;color:var(--txt);outline:none;min-width:180px}
.search-row input:focus{border-color:var(--gm)}

/* ── ALERT / MESSAGE ── */
.flash{padding:10px 14px;border-radius:var(--r);font-size:13px;margin-bottom:16px;display:none}
.flash.ok{background:var(--gl);color:var(--gd);border:1px solid #a5d6b0}
.flash.err{background:var(--redl);color:var(--red);border:1px solid #fca5a5}
.flash.info{background:var(--bluel);color:var(--blue);border:1px solid #93c5fd}

/* ── STUDENT LOOKUP CARD ── */
.lookup-card{background:var(--gl);border:1px solid #a5d6b0;border-radius:var(--r);padding:14px 18px;margin:12px 0;display:none}
.lookup-card h4{font-size:14px;font-weight:700;color:var(--gd)}
.lookup-card p{font-size:12.5px;color:var(--muted);margin-top:4px}
.lookup-card .lc-matric{font-family:'Courier New',monospace;font-size:13px;font-weight:700;color:var(--gd)}

/* ── BOOK SEARCH RESULTS ── */
.book-results{border:1px solid var(--border);border-radius:var(--r);overflow:hidden;display:none;margin-top:8px}
.book-result-item{padding:10px 14px;display:flex;align-items:center;gap:12px;cursor:pointer;transition:background .15s;border-bottom:1px solid var(--border)}
.book-result-item:last-child{border-bottom:none}
.book-result-item:hover{background:var(--gl)}
.bri-emoji{font-size:22px}
.bri-info strong{font-size:13px;display:block}
.bri-info span{font-size:11.5px;color:var(--muted)}
.bri-avail{margin-left:auto;font-size:11px;font-weight:600;padding:3px 10px;border-radius:10px}

/* ── SELECTED BOOK CARD ── */
.selected-book{background:var(--goldl);border:1px solid #d4a520;border-radius:var(--r);padding:12px 16px;margin-top:8px;display:none;align-items:center;gap:12px}
.selected-book .sb-emoji{font-size:28px}
.selected-book h4{font-size:13.5px;font-weight:700}
.selected-book p{font-size:12px;color:var(--muted)}

/* ── PAGINATION ── */
.pagination{display:flex;gap:6px;align-items:center;margin-top:16px;flex-wrap:wrap}
.page-btn{padding:5px 12px;border:1px solid var(--border);border-radius:6px;font-size:12.5px;cursor:pointer;background:var(--surf);color:var(--txt);transition:all .15s}
.page-btn.active,.page-btn:hover{background:var(--gd);color:#fff;border-color:var(--gd)}
.page-info{font-size:12px;color:var(--muted);margin-left:8px}

/* ── DETAIL MODAL ── */
.modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:500;display:none;align-items:center;justify-content:center;padding:20px}
.modal-overlay.open{display:flex}
.modal{background:var(--surf);border-radius:var(--rl);max-width:640px;width:100%;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.3)}
.modal-head{padding:16px 22px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.modal-head h3{font-size:16px;font-weight:700}
.modal-close{font-size:22px;cursor:pointer;color:var(--muted);background:none;border:none;line-height:1}
.modal-body{padding:22px}
.detail-row{display:flex;gap:16px;margin-bottom:12px;align-items:flex-start}
.detail-row .dl{font-size:12px;font-weight:600;color:var(--muted);min-width:130px}
.detail-row .dv{font-size:13.5px;color:var(--txt)}
.detail-section{font-size:11px;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;margin:18px 0 10px;padding-bottom:6px;border-bottom:1px solid var(--border)}

/* ── LOADING ── */
.loading{text-align:center;padding:40px;color:var(--muted);font-size:14px}
.spinner{display:inline-block;width:20px;height:20px;border:2px solid var(--border);border-top-color:var(--gm);border-radius:50%;animation:spin .6s linear infinite;vertical-align:middle;margin-right:8px}
@keyframes spin{to{transform:rotate(360deg)}}

/* ── EMPTY STATE ── */
.empty{text-align:center;padding:48px 20px;color:var(--muted)}
.empty .ei{font-size:40px;margin-bottom:12px}
.empty p{font-size:14px}

/* ── MISC ── */
.fine-amount{font-weight:700;color:var(--amber)}
.fine-paid{color:var(--gm);font-size:11px}
.tag{font-size:11px;padding:2px 8px;border-radius:4px;font-weight:500}
.divider{border:none;border-top:1px solid var(--border);margin:20px 0}
</style>
</head>
<body>

<!-- ════════ SIDEBAR ════════ -->
<aside class="sidebar">
  <div class="sb-brand">
    <div class="logo">IBB</div>
    <h2>IBBUL Smart Library</h2>
    <p>Admin Dashboard</p>
  </div>
  <div class="sb-admin">
    <span>Logged in as</span>
    <strong><?= $admin_name ?></strong>
  </div>
  <nav class="sb-nav">
    <div class="sb-section">Overview</div>
    <button class="sb-link active" onclick="showSection('dashboard')"><span class="ic">📊</span> Dashboard</button>
    <button class="sb-link" onclick="showSection('pending_staff')" id="pendingBtn">
      <span class="ic">⏳</span> Pending Staff
      <span id="pendingBadge" style="display:none;background:#dc2626;color:#fff;font-size:10px;font-weight:700;padding:2px 7px;border-radius:10px;margin-left:auto">0</span>
    </button>

    <div class="sb-section">Library Records</div>
    <button class="sb-link" onclick="showSection('loans')"><span class="ic">📋</span> All Loan Records</button>
    <button class="sb-link" onclick="showSection('students')"><span class="ic">👨‍🎓</span> Student Records</button>
    <button class="sb-link" onclick="showSection('staff')"><span class="ic">👨‍💼</span> Staff Records</button>
    <button class="sb-link" onclick="showSection('fines')"><span class="ic">💰</span> Fines Management</button>
    <button class="sb-link" onclick="showSection('issue')"><span class="ic">➕</span> Issue a Book</button>
    <button class="sb-link" onclick="showSection('return')"><span class="ic">↩️</span> Process Return</button>

    <div class="sb-section">Catalog</div>
    <button class="sb-link" onclick="showSection('books')"><span class="ic">📖</span> View All Books</button>
    <button class="sb-link" onclick="showSection('add_book')"><span class="ic">📚</span> Add New Book</button>

    <div class="sb-section">Communication</div>
    <button class="sb-link" onclick="showSection('announce')"><span class="ic">📢</span> Post Announcement</button>

    <div class="sb-section">Security</div>
    <button class="sb-link" onclick="showSection('audit_log')"><span class="ic">🔍</span> Audit Log</button>
  </nav>
  <div class="sb-footer">
    <a href="index.php">← Back to Website</a><br>
    <a href="#" onclick="logoutAdmin()" style="color:#fca5a5;margin-top:4px;display:block">Sign Out</a>
  </div>
</aside>

<!-- ════════ MAIN CONTENT ════════ -->
<div class="main">
  <div class="topbar">
    <h1 id="pageTitle">Dashboard</h1>
    <div class="topbar-right">
      <span>📅 <?= date('l, d F Y') ?></span>
      <span>|</span>
      <span>👤 <?= $admin_name ?></span>
    </div>
  </div>

  <div class="content">

    <!-- ── DASHBOARD ─────────────────────────────────────── -->
    <div id="sec-dashboard" class="page-section active">
      <div class="stats-row" id="statsRow">
        <div class="loading"><span class="spinner"></span> Loading stats…</div>
      </div>

      <!-- Recent Loans -->
      <div class="panel">
        <div class="panel-head">
          <h2>Recent Loan Activity</h2>
          <button class="btn btn-outline btn-sm" onclick="showSection('loans')">View All</button>
        </div>
        <div class="panel-body" style="padding:0">
          <div class="tbl-wrap">
            <table id="recentTable">
              <thead><tr>
                <th>Matric No.</th><th>Student</th><th>Book</th>
                <th>Borrow Date</th><th>Due Date</th><th>Status</th><th>Fine</th><th></th>
              </tr></thead>
              <tbody id="recentBody"><tr><td colspan="8" class="loading"><span class="spinner"></span> Loading…</td></tr></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- ── ALL LOANS ──────────────────────────────────────── -->
    <div id="sec-loans" class="page-section">
      <div class="panel">
        <div class="panel-head">
          <h2>All Loan Records</h2>
          <div class="search-row">
            <select id="loanFilter" onchange="loadLoans(1)">
              <option value="all">All Statuses</option>
              <option value="active">Active</option>
              <option value="overdue">Overdue</option>
              <option value="returned">Returned</option>
            </select>
            <input type="text" id="loanSearch" placeholder="Search matric, name or book…" oninput="debounce(()=>loadLoans(1),400)"/>
            <button class="btn btn-primary btn-sm" onclick="loadLoans(1)">Search</button>
          </div>
        </div>
        <div class="tbl-wrap">
          <table>
            <thead><tr>
              <th>Matric No.</th><th>Student</th><th>Department</th>
              <th>Book</th><th>Borrow Date</th><th>Due Date</th>
              <th>Return Date</th><th>Status</th><th>Fine</th><th>Actions</th>
            </tr></thead>
            <tbody id="loansBody"><tr><td colspan="10" class="loading"><span class="spinner"></span> Loading…</td></tr></tbody>
          </table>
        </div>
        <div style="padding:14px 20px;display:flex;align-items:center;flex-wrap:wrap;gap:8px">
          <div class="pagination" id="loansPagination"></div>
          <div class="page-info" id="loansInfo"></div>
        </div>
      </div>
    </div>

    <!-- ── STUDENTS ───────────────────────────────────────── -->
    <div id="sec-students" class="page-section">
      <div class="panel">
        <div class="panel-head">
          <h2>Student Records</h2>
          <div class="search-row">
            <input type="text" id="stuSearch" placeholder="Search name, matric, dept…" oninput="debounce(()=>loadStudents(1),400)"/>
            <button class="btn btn-primary btn-sm" onclick="loadStudents(1)">Search</button>
          </div>
        </div>
        <div class="tbl-wrap">
          <table>
            <thead><tr>
              <th>Matric Number</th><th>Full Name</th><th>Faculty</th>
              <th>Department</th><th>Level</th><th>Email</th><th>Phone</th>
              <th>Status</th><th>Joined</th>
            </tr></thead>
            <tbody id="studentsBody"><tr><td colspan="9" class="loading"><span class="spinner"></span> Loading…</td></tr></tbody>
          </table>
        </div>
        <div style="padding:14px 20px;display:flex;align-items:center;flex-wrap:wrap;gap:8px">
          <div class="pagination" id="stuPagination"></div>
          <div class="page-info" id="stuInfo"></div>
        </div>
      </div>
    </div>

    <!-- ── STAFF RECORDS ──────────────────────────────────── -->
    <div id="sec-staff" class="page-section">
      <!-- Summary cards -->
      <div class="stats-row" id="staffSummary" style="margin-bottom:20px">
        <div class="stat-card"><div class="sc-label">Active Staff</div><div class="sc-value" id="stfActive">—</div><div class="sc-sub">Can log in</div></div>
        <div class="stat-card amber"><div class="sc-label">Pending Approval</div><div class="sc-value" id="stfPending">—</div><div class="sc-sub">Awaiting review</div></div>
        <div class="stat-card red"><div class="sc-label">Suspended</div><div class="sc-value" id="stfSuspended">—</div><div class="sc-sub">Access blocked</div></div>
        <div class="stat-card"><div class="sc-label">Inactive / Rejected</div><div class="sc-value" id="stfInactive">—</div><div class="sc-sub">No access</div></div>
      </div>

      <div class="panel">
        <div class="panel-head">
          <h2>Staff Records</h2>
          <div class="search-row">
            <select id="staffStatusFilter" onchange="loadStaff(1)">
              <option value="">All Statuses</option>
              <option value="active">Active</option>
              <option value="pending">Pending</option>
              <option value="suspended">Suspended</option>
              <option value="inactive">Inactive</option>
            </select>
            <input type="text" id="staffSearch" placeholder="Search name, ID, dept, email…" oninput="debounce(()=>loadStaff(1),400)"/>
            <button class="btn btn-primary btn-sm" onclick="loadStaff(1)">Search</button>
            <button class="btn btn-outline btn-sm" onclick="showSection('pending_staff')">⏳ Pending Approvals</button>
          </div>
        </div>
        <div class="tbl-wrap">
          <table>
            <thead><tr>
              <th>Staff ID</th><th>Full Name</th><th>Department</th>
              <th>Email</th><th>Phone</th><th>Status</th>
              <th>Registered</th><th>Last Updated</th><th>Action</th>
            </tr></thead>
            <tbody id="staffBody"><tr><td colspan="9" class="loading"><span class="spinner"></span> Loading…</td></tr></tbody>
          </table>
        </div>
        <div style="padding:14px 20px;display:flex;align-items:center;flex-wrap:wrap;gap:8px">
          <div class="pagination" id="staffPagination"></div>
          <div class="page-info" id="staffInfo"></div>
        </div>
      </div>
    </div>

    <!-- ── ISSUE BOOK ─────────────────────────────────────── -->
    <div id="sec-issue" class="page-section">
      <div class="panel">
        <div class="panel-head"><h2>Issue a Book to a Student</h2></div>
        <div class="panel-body">
          <div id="issueFlash" class="flash"></div>

          <!-- Step 1: Find student -->
          <div style="margin-bottom:20px">
            <p style="font-size:13px;font-weight:600;color:var(--muted);margin-bottom:10px">STEP 1 — Find Student by Library Number, School Matric, or Name</p>
            <div style="display:flex;gap:10px">
              <input type="text" id="issueMatric" placeholder="e.g. LIB-2025-0001 or U22/FNS/CSC/0042"
                     style="padding:10px 14px;border:1px solid var(--border);border-radius:var(--r);font-size:14px;font-family:'Courier New',monospace;flex:1;outline:none"
                     oninput="this.value=this.value.toUpperCase()"/>
              <button class="btn btn-primary" onclick="lookupStudent()">Find Student</button>
            </div>
            <div class="lookup-card" id="studentCard">
              <div style="display:flex;justify-content:space-between;align-items:flex-start">
                <div>
                  <div class="lc-matric" id="lcMatric"></div>
                  <h4 id="lcName" style="margin-top:4px"></h4>
                  <p id="lcDept"></p>
                  <p id="lcLevel"></p>
                </div>
                <span class="badge" id="lcStatus"></span>
              </div>
            </div>
          </div>

          <hr class="divider"/>

          <!-- Step 2: Find book -->
          <div style="margin-bottom:20px">
            <p style="font-size:13px;font-weight:600;color:var(--muted);margin-bottom:10px">STEP 2 — Search for a Book</p>
            <div style="display:flex;gap:10px">
              <input type="text" id="bookSearch" placeholder="Search by title, author or ISBN…"
                     style="padding:10px 14px;border:1px solid var(--border);border-radius:var(--r);font-size:14px;flex:1;outline:none"
                     oninput="debounce(searchBook,400)"/>
            </div>
            <div class="book-results" id="bookResults"></div>
            <div class="selected-book" id="selectedBook">
              <div class="sb-emoji" id="sbEmoji"></div>
              <div>
                <h4 id="sbTitle"></h4>
                <p id="sbAuthor"></p>
                <p id="sbAvail" style="font-size:12px;font-weight:600;color:var(--gm)"></p>
              </div>
              <button class="btn btn-ghost btn-sm" onclick="clearBook()" style="margin-left:auto">✕ Clear</button>
            </div>
            <input type="hidden" id="selectedBookId"/>
          </div>

          <hr class="divider"/>

          <!-- Step 3: Loan details -->
          <div>
            <p style="font-size:13px;font-weight:600;color:var(--muted);margin-bottom:14px">STEP 3 — Loan Details</p>
            <div class="form-grid">
              <div class="fg">
                <label>Loan Duration (days)</label>
                <select id="loanDays">
                  <option value="7">7 days</option>
                  <option value="14" selected>14 days (default)</option>
                  <option value="21">21 days</option>
                  <option value="28">28 days</option>
                  <option value="42">42 days (Postgraduate)</option>
                </select>
              </div>
              <div class="fg">
                <label>Notes (optional)</label>
                <input type="text" id="loanNotes" placeholder="Any special notes…"/>
              </div>
            </div>
            <button class="btn btn-primary" style="margin-top:16px" onclick="issueLoan()">
              ✅ Issue Book
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ── PROCESS RETURN ─────────────────────────────────── -->
    <div id="sec-return" class="page-section">
      <div class="panel">
        <div class="panel-head"><h2>Process a Book Return</h2></div>
        <div class="panel-body">
          <div id="returnFlash" class="flash"></div>
          <p style="font-size:13.5px;color:var(--muted);margin-bottom:16px">
            Search by the student's <strong>Library Registration Number</strong> (e.g. LIB-2025-0001) or school matric number.
          </p>
          <div style="display:flex;gap:10px;margin-bottom:20px">
            <input type="text" id="retMatric" placeholder="e.g. LIB-2025-0001"
                   style="padding:10px 14px;border:1px solid var(--border);border-radius:var(--r);font-family:'Courier New',monospace;font-size:14px;flex:1;outline:none"
                   oninput="this.value=this.value.toUpperCase()"/>
            <button class="btn btn-primary" onclick="loadActiveLoans()">Find Active Loans</button>
          </div>
          <div class="tbl-wrap" id="activeLoansWrap" style="display:none">
            <table>
              <thead><tr>
                <th>Book</th><th>Borrow Date</th><th>Due Date</th>
                <th>Days Remaining</th><th>Status</th><th>Action</th>
              </tr></thead>
              <tbody id="activeLoansBody"></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <!-- ── ADD BOOK ───────────────────────────────────────── -->
    <div id="sec-add_book" class="page-section">
      <div class="panel">
        <div class="panel-head"><h2>Add a New Book to Catalog</h2></div>
        <div class="panel-body">
          <div id="addBookFlash" class="flash"></div>
          <div class="form-grid">
            <div class="fg full"><label>Book Title *</label><input type="text" id="abTitle" placeholder="e.g. Introduction to Algorithms"/></div>
            <div class="fg"><label>Author(s) *</label><input type="text" id="abAuthor" placeholder="e.g. Thomas H. Cormen"/></div>
            <div class="fg"><label>ISBN</label><input type="text" id="abISBN" placeholder="e.g. 978-0-262-03384-8"/></div>
            <div class="fg"><label>Publisher</label><input type="text" id="abPublisher" placeholder="e.g. MIT Press"/></div>
            <div class="fg"><label>Publication Year</label><input type="number" id="abYear" placeholder="e.g. 2022" min="1900" max="2099"/></div>
            <div class="fg">
              <label>Category</label>
              <select id="abCategory">
                <option value="science">Sciences</option>
                <option value="management">Management</option>
                <option value="arts">Arts & Humanities</option>
                <option value="law">Law</option>
                <option value="engineering">Engineering</option>
                <option value="education">Education</option>
                <option value="agriculture">Agriculture</option>
                <option value="general">General</option>
              </select>
            </div>
            <div class="fg"><label>Department Code</label><input type="text" id="abDeptCode" placeholder="e.g. CSC" style="text-transform:uppercase"/></div>
            <div class="fg"><label>Department Name</label><input type="text" id="abDeptName" placeholder="e.g. Computer Science"/></div>
            <div class="fg">
              <label>Resource Type</label>
              <select id="abType">
                <option value="physical">Physical Book</option>
                <option value="digital">Digital / E-Book</option>
                <option value="both">Both Physical & Digital</option>
              </select>
            </div>
            <div class="fg"><label>Total Copies</label><input type="number" id="abCopies" value="1" min="1"/></div>
            <div class="fg"><label>Shelf / Location</label><input type="text" id="abLocation" placeholder="e.g. Shelf A3, Row 2"/></div>
            <div class="fg">
              <label>Cover Colour</label>
              <select id="abColor">
                <option value="#e8f5ec">Green (Sciences)</option>
                <option value="#e8f0fe">Blue (Management)</option>
                <option value="#f3e5f5">Purple (Arts)</option>
                <option value="#fff3e0">Amber (Law)</option>
                <option value="#fce4ec">Pink (Medicine)</option>
                <option value="#e3f2fd">Light Blue (Engineering)</option>
              </select>
            </div>
            <div class="fg">
              <label>Cover Emoji</label>
              <select id="abEmoji">
                <option value="📘">📘 Blue Book</option>
                <option value="📗">📗 Green Book</option>
                <option value="📙">📙 Orange Book</option>
                <option value="📕">📕 Red Book</option>
                <option value="📓">📓 Notebook</option>
                <option value="📜">📜 Scroll</option>
                <option value="⚖️">⚖️ Law</option>
                <option value="🧪">🧪 Science</option>
                <option value="💻">💻 Computer</option>
                <option value="⚡">⚡ Engineering</option>
                <option value="📈">📈 Economics</option>
                <option value="🔬">🔬 Biology</option>
              </select>
            </div>
          </div>
          <button class="btn btn-primary" style="margin-top:20px" onclick="addBook()">📚 Add to Catalog</button>
        </div>
      </div>
    </div>

    <!-- ── ANNOUNCEMENT ───────────────────────────────────── -->
    <div id="sec-announce" class="page-section">
      <div class="panel">
        <div class="panel-head"><h2>Post a New Announcement</h2></div>
        <div class="panel-body">
          <div id="annFlash" class="flash"></div>
          <div class="form-grid">
            <div class="fg full"><label>Title *</label><input type="text" id="annTitle" placeholder="Announcement headline"/></div>
            <div class="fg full">
              <label>Body / Message *</label>
              <textarea id="annBody" rows="4" placeholder="Full announcement text…"></textarea>
            </div>
            <div class="fg">
              <label>Tag / Category</label>
              <select id="annTag">
                <option value="Notice">📌 Notice</option>
                <option value="New Addition">🆕 New Addition</option>
                <option value="Training">🎓 Training</option>
                <option value="System">🔧 System Update</option>
                <option value="Policy">📋 Policy</option>
                <option value="Partnership">🏛️ Partnership</option>
              </select>
            </div>
            <div class="fg">
              <label>Tag Colour</label>
              <select id="annColor">
                <option value="#1a5c2e">Green (Notice)</option>
                <option value="#c8960c">Gold (New Addition)</option>
                <option value="#1a56a4">Blue (Training)</option>
                <option value="#7c3aed">Purple (System)</option>
                <option value="#b45309">Amber (Policy)</option>
              </select>
            </div>
          </div>
          <button class="btn btn-primary" style="margin-top:20px" onclick="postAnnouncement()">📢 Post Announcement</button>
        </div>
      </div>
    </div>
    
        <!-- ── FINES MANAGEMENT ──────────────────────────────── -->
        <div id="sec-fines" class="page-section">
          <!-- Summary cards -->
          <div class="stats-row" id="finesSummary" style="margin-bottom:20px">
            <div class="stat-card amber"><div class="sc-label">Total Unpaid Fines</div><div class="sc-value" id="fUnpaid">—</div><div class="sc-sub">Outstanding amount</div></div>
            <div class="stat-card"><div class="sc-label">Total Collected</div><div class="sc-value" id="fPaid">—</div><div class="sc-sub">Paid fines</div></div>
            <div class="stat-card blue"><div class="sc-label">Total Fine Records</div><div class="sc-value" id="fCount">—</div><div class="sc-sub">All time</div></div>
          </div>
          <div class="panel">
            <div class="panel-head">
              <h2>Fine Records</h2>
              <div class="search-row">
                <select id="fineFilter" onchange="loadFines(1)">
                  <option value="unpaid">Unpaid Only</option>
                  <option value="paid">Paid</option>
                  <option value="all">All Fines</option>
                </select>
                <input type="text" id="fineSearch" placeholder="Search matric, name or book…" oninput="debounce(()=>loadFines(1),400)"/>
                <button class="btn btn-primary btn-sm" onclick="loadFines(1)">Search</button>
              </div>
            </div>
            <div class="tbl-wrap">
              <table>
                <thead><tr>
                  <th>Matric No.</th><th>Student</th><th>Department</th>
                  <th>Book Borrowed</th><th>Due Date</th><th>Return Date</th>
                  <th>Fine Amount</th><th>Date Issued</th><th>Status</th><th>Action</th>
                </tr></thead>
                <tbody id="finesBody">
                  <tr><td colspan="10" class="loading"><span class="spinner"></span> Loading…</td></tr>
                </tbody>
              </table>
            </div>
            <div style="padding:14px 20px;display:flex;align-items:center;flex-wrap:wrap;gap:8px">
              <div class="pagination" id="finesPagination"></div>
              <div class="page-info" id="finesInfo"></div>
            </div>
          </div>
        </div>
    
        <!-- ── BOOKS CATALOG ──────────────────────────────────── -->
        <div id="sec-books" class="page-section">
          <div class="panel">
            <div class="panel-head">
              <h2>Book Catalog</h2>
              <div class="search-row">
                <input type="text" id="bookCatSearch" placeholder="Search title, author, ISBN, dept…" oninput="debounce(()=>loadBooks(1),400)"/>
                <button class="btn btn-primary btn-sm" onclick="loadBooks(1)">Search</button>
                <button class="btn btn-outline btn-sm" onclick="showSection('add_book')">+ Add New Book</button>
              </div>
            </div>
            <div class="tbl-wrap">
              <table>
                <thead><tr>
                  <th></th><th>Title</th><th>Author</th><th>ISBN</th>
                  <th>Department</th><th>Category</th>
                  <th>Total Copies</th><th>Available</th>
                  <th>Type</th><th>Location</th><th>Added</th><th>Action</th>
                </tr></thead>
                <tbody id="booksBody">
                  <tr><td colspan="12" class="loading"><span class="spinner"></span> Loading…</td></tr>
                </tbody>
              </table>
            </div>
            <div style="padding:14px 20px;display:flex;align-items:center;flex-wrap:wrap;gap:8px">
              <div class="pagination" id="booksPagination"></div>
              <div class="page-info" id="booksInfo"></div>
            </div>
          </div>
    
          <!-- Full book edit form (slides in below table) -->
          <div id="editBookPanel" style="display:none;margin-top:20px">
            <div class="panel">
              <div class="panel-head">
                <h2 id="editBookPanelTitle">✏️ Edit Book</h2>
                <button class="btn btn-ghost btn-sm" onclick="closeBookEdit()">✕ Close</button>
              </div>
              <div class="panel-body">
                <div id="editBookFlash" class="flash"></div>
                <input type="hidden" id="editBookId"/>
    
                <!-- Row 1 -->
                <div class="form-grid">
                  <div class="fg full">
                    <label>Book Title <span style="color:red">*</span></label>
                    <input type="text" id="editTitle" placeholder="e.g. Introduction to Algorithms"/>
                  </div>
                  <div class="fg">
                    <label>Author(s) <span style="color:red">*</span></label>
                    <input type="text" id="editAuthor" placeholder="e.g. Thomas H. Cormen"/>
                  </div>
                  <div class="fg">
                    <label>ISBN</label>
                    <input type="text" id="editISBN" placeholder="e.g. 978-0-262-03384-8"/>
                  </div>
                  <div class="fg">
                    <label>Publisher</label>
                    <input type="text" id="editPublisher" placeholder="e.g. MIT Press"/>
                  </div>
                  <div class="fg">
                    <label>Publication Year</label>
                    <input type="number" id="editYear" placeholder="e.g. 2022" min="1900" max="2099"/>
                  </div>
                  <div class="fg">
                    <label>Category</label>
                    <select id="editCategory">
                      <option value="science">Sciences</option>
                      <option value="management">Management</option>
                      <option value="arts">Arts &amp; Humanities</option>
                      <option value="law">Law</option>
                      <option value="engineering">Engineering</option>
                      <option value="education">Education</option>
                      <option value="agriculture">Agriculture</option>
                      <option value="general">General</option>
                    </select>
                  </div>
                  <div class="fg">
                    <label>Department Code</label>
                    <input type="text" id="editDeptCode" placeholder="e.g. CSC" style="text-transform:uppercase"
                           oninput="this.value=this.value.toUpperCase()"/>
                  </div>
                  <div class="fg">
                    <label>Department Name</label>
                    <input type="text" id="editDeptName" placeholder="e.g. Computer Science"/>
                  </div>
                  <div class="fg">
                    <label>Resource Type</label>
                    <select id="editResourceType">
                      <option value="physical">Physical Book</option>
                      <option value="digital">Digital / E-Book</option>
                      <option value="both">Both Physical &amp; Digital</option>
                    </select>
                  </div>
                  <div class="fg">
                    <label>Digital URL <small style="font-weight:400">(if digital/both)</small></label>
                    <input type="url" id="editDigitalUrl" placeholder="https://…"/>
                  </div>
                  <div class="fg">
                    <label>Total Copies</label>
                    <input type="number" id="editTotal" min="1"
                           oninput="validateCopies()"/>
                  </div>
                  <div class="fg">
                    <label>Available Copies</label>
                    <input type="number" id="editAvail" min="0"
                           oninput="validateCopies()"/>
                  </div>
                  <div class="fg">
                    <label>Shelf / Location</label>
                    <input type="text" id="editLoc" placeholder="e.g. Shelf A3, Row 2"/>
                  </div>
                  <div class="fg">
                    <label>Cover Colour</label>
                    <select id="editColor" onchange="updateEditPreview()">
                      <option value="#e8f5ec">🟢 Green (Sciences)</option>
                      <option value="#e8f0fe">🔵 Blue (Management)</option>
                      <option value="#f3e5f5">🟣 Purple (Arts)</option>
                      <option value="#fff3e0">🟠 Amber (Law)</option>
                      <option value="#fce4ec">🔴 Pink (Medicine)</option>
                      <option value="#e3f2fd">💙 Light Blue (Engineering)</option>
                      <option value="#f9fbe7">🟡 Lime (Agriculture)</option>
                      <option value="#f5f5f5">⬜ Grey (General)</option>
                    </select>
                  </div>
                  <div class="fg">
                    <label>Cover Emoji</label>
                    <select id="editEmoji" onchange="updateEditPreview()">
                      <option value="📘">📘 Blue Book</option>
                      <option value="📗">📗 Green Book</option>
                      <option value="📙">📙 Orange Book</option>
                      <option value="📕">📕 Red Book</option>
                      <option value="📓">📓 Notebook</option>
                      <option value="📜">📜 Scroll</option>
                      <option value="⚖️">⚖️ Law</option>
                      <option value="🧪">🧪 Science</option>
                      <option value="💻">💻 Computer</option>
                      <option value="⚡">⚡ Engineering</option>
                      <option value="📈">📈 Economics</option>
                      <option value="🔬">🔬 Biology</option>
                      <option value="🌐">🌐 Networks</option>
                      <option value="📃">📃 Document</option>
                      <option value="🏛️">🏛️ Politics</option>
                      <option value="📡">📡 Communication</option>
                    </select>
                  </div>
                  <div class="fg" style="justify-content:flex-end">
                    <label>Cover Preview</label>
                    <div id="editPreview"
                         style="width:56px;height:56px;border-radius:10px;background:#e8f5ec;
                                display:flex;align-items:center;justify-content:center;
                                font-size:28px;border:1px solid var(--border)">
                      📘
                    </div>
                  </div>
                </div>
    
                <!-- Copies warning -->
                <div id="copiesWarning" style="display:none;background:#fef2f2;color:#dc2626;
                     border:1px solid #fca5a5;padding:8px 14px;border-radius:8px;font-size:13px;margin-top:10px">
                  ⚠️ Available copies cannot exceed total copies.
                </div>
    
                <div style="display:flex;gap:10px;margin-top:20px;flex-wrap:wrap">
                  <button class="btn btn-primary" onclick="saveBookEdit()">💾 Save Changes</button>
                  <button class="btn btn-ghost" onclick="closeBookEdit()">Cancel</button>
                </div>
              </div>
            </div>
          </div>
        </div>
    
        <!-- ── PENDING STAFF APPROVALS ───────────────────────── -->
        <div id="sec-pending_staff" class="page-section">
          <div class="panel">
            <div class="panel-head">
              <h2>⏳ Pending Staff Registrations</h2>
              <button class="btn btn-ghost btn-sm" onclick="loadPendingStaff()">🔄 Refresh</button>
            </div>
            <div class="panel-body">
              <p style="font-size:13.5px;color:var(--muted);margin-bottom:20px">
                Staff accounts below have registered but are awaiting your approval before they can log in.
                Review each request and either <strong style="color:var(--gm)">Approve</strong>
                (grants access) or <strong style="color:var(--red)">Reject</strong> (blocks access).
              </p>
              <div id="pendingStaffList">
                <div class="loading"><span class="spinner"></span> Loading…</div>
              </div>
            </div>
          </div>
        </div>
    
        <!-- ── AUDIT LOG ──────────────────────────────────────── -->
        <div id="sec-audit_log" class="page-section">
          <div class="panel">
            <div class="panel-head">
              <h2>🔍 Security Audit Log</h2>
              <div class="search-row">
                <select id="auditFilter" onchange="loadAuditLog(1)">
                  <option value="">All Actions</option>
                </select>
                <input type="text" id="auditSearch" placeholder="Search name, detail, IP…"
                       oninput="debounce(()=>loadAuditLog(1),400)"/>
                <button class="btn btn-primary btn-sm" onclick="loadAuditLog(1)">Search</button>
              </div>
            </div>
            <div class="tbl-wrap">
              <table>
                <thead><tr>
                  <th>Time</th><th>User</th><th>Action</th>
                  <th>Target</th><th>Detail</th><th>IP Address</th>
                </tr></thead>
                <tbody id="auditBody">
                  <tr><td colspan="6" class="loading"><span class="spinner"></span> Loading…</td></tr>
                </tbody>
              </table>
            </div>
            <div style="padding:14px 20px;display:flex;align-items:center;flex-wrap:wrap;gap:8px">
              <div class="pagination" id="auditPagination"></div>
              <div class="page-info" id="auditInfo"></div>
            </div>
          </div>
        </div>

  </div><!-- /content -->
</div><!-- /main -->

<!-- ════════ LOAN DETAIL MODAL ════════ -->
<div class="modal-overlay" id="loanModal">
  <div class="modal">
    <div class="modal-head">
      <h3>Loan Record Detail</h3>
      <button class="modal-close" onclick="closeModal()">✕</button>
    </div>
    <div class="modal-body" id="modalBody">
      <div class="loading"><span class="spinner"></span> Loading…</div>
    </div>
  </div>
</div>

<script>
// ============================================================
//  ADMIN DASHBOARD — JavaScript
// ============================================================
const API = 'library_actions.php';
const CSRF_TOKEN = '<?= htmlspecialchars($csrf) ?>';
let debounceTimer;
/** Append CSRF token to any FormData before sending */
function addCsrf(formData) {
  formData.append('_csrf_token', CSRF_TOKEN);
  return formData;
}

async function adminPost(formData) {
  return fetch(API, { method: 'POST', body: addCsrf(formData) });
}
function debounce(fn, ms){ clearTimeout(debounceTimer); debounceTimer = setTimeout(fn, ms); }

// ── Section navigation ────────────────────────────────────────
const sectionTitles = {
  dashboard:'Dashboard', loans:'All Loan Records', students:'Student Records',
  staff:'Staff Records',
  fines:'Fines Management', books:'Book Catalog',
  pending_staff:'Pending Staff Approvals', audit_log:'Security Audit Log',
  issue:'Issue a Book', return:'Process Return',
  add_book:'Add New Book', announce:'Post Announcement'
};

function showSection(id) {
  document.querySelectorAll('.page-section').forEach(s => s.classList.remove('active'));
  document.querySelectorAll('.sb-link').forEach(b => b.classList.remove('active'));
  document.getElementById('sec-' + id).classList.add('active');
  document.querySelector(`[onclick="showSection('${id}')"]`).classList.add('active');
  document.getElementById('pageTitle').textContent = sectionTitles[id] || id;

  if (id === 'dashboard')    loadDashboard();
  if (id === 'loans')        loadLoans(1);
  if (id === 'students')     loadStudents(1);
  if (id === 'staff')        loadStaff(1);
  if (id === 'fines')        loadFines(1);
  if (id === 'books')        loadBooks(1);
  if (id === 'pending_staff') loadPendingStaff();
  if (id === 'audit_log')    loadAuditLog(1);
}

// ── Shared flash helper ────────────────────────────────────────
function flash(elId, msg, type='ok') {
  const el = document.getElementById(elId);
  el.className = 'flash ' + type;
  el.innerHTML = msg;
  el.style.display = 'block';
  if (type === 'ok') setTimeout(() => el.style.display='none', 5000);
}

// ── Status badge HTML ─────────────────────────────────────────
function statusBadge(s) {
  const map = {active:'b-active',overdue:'b-overdue',returned:'b-returned',lost:'b-lost',pending:'b-pending'};
  return `<span class="badge ${map[s]||'b-pending'}">${s.charAt(0).toUpperCase()+s.slice(1)}</span>`;
}

// ── Fine display ──────────────────────────────────────────────
function fineHtml(amount, paid) {
  if (!amount || amount == 0) return '<span style="color:var(--muted)">—</span>';
  const formatted = '₦' + parseFloat(amount).toLocaleString('en-NG', {minimumFractionDigits:2});
  return paid
    ? `<span class="fine-paid">✓ ${formatted} (paid)</span>`
    : `<span class="fine-amount">${formatted}</span>`;
}

// ── Date helpers ──────────────────────────────────────────────
function daysLeft(dueDate) {
  const diff = Math.ceil((new Date(dueDate) - new Date()) / 86400000);
  if (diff < 0) return `<span style="color:var(--red);font-weight:600">${Math.abs(diff)} days overdue</span>`;
  if (diff === 0) return `<span style="color:var(--amber);font-weight:600">Due today</span>`;
  return `<span style="color:var(--gm)">${diff} days left</span>`;
}

// ============================================================
//  DASHBOARD
// ============================================================
async function loadDashboard() {
  // Stats
  const statsRow = document.getElementById('statsRow');
  try {
    const res   = await fetch(`${API}?action=admin_get_stats`);
    const data  = await res.json();
    if (data.status === 'success') {
      const s = data.data;
      statsRow.innerHTML = `
        <div class="stat-card"><div class="sc-label">Total Students</div><div class="sc-value">${fmt(s.total_students)}</div><div class="sc-sub">Registered members</div></div>
        <div class="stat-card blue"><div class="sc-label">Total Books</div><div class="sc-value">${fmt(s.total_books)}</div><div class="sc-sub">In catalog</div></div>
        <div class="stat-card"><div class="sc-label">Active Loans</div><div class="sc-value">${fmt(s.active_loans)}</div><div class="sc-sub">Currently borrowed</div></div>
        <div class="stat-card red"><div class="sc-label">Overdue Loans</div><div class="sc-value">${fmt(s.overdue_loans)}</div><div class="sc-sub">Past due date</div></div>
        <div class="stat-card amber"><div class="sc-label">Unpaid Fines</div><div class="sc-value">₦${parseFloat(s.unpaid_fines||0).toLocaleString('en-NG',{minimumFractionDigits:2})}</div><div class="sc-sub">Outstanding</div></div>
        <div class="stat-card"><div class="sc-label">Pending Holds</div><div class="sc-value">${fmt(s.pending_reservations)}</div><div class="sc-sub">Reservations</div></div>`;
    }
  } catch { statsRow.innerHTML = '<p style="color:var(--red)">Failed to load stats.</p>'; }

  // Recent loans (first 10)
  try {
    const res  = await fetch(`${API}?action=admin_get_loans&filter=all&page=1`);
    const data = await res.json();
    renderLoansTable('recentBody', data.data || [], true);
  } catch { document.getElementById('recentBody').innerHTML = '<tr><td colspan="8">Failed to load recent loans.</td></tr>'; }
}

function fmt(n){ return parseInt(n||0).toLocaleString(); }

// ============================================================
//  LOANS TABLE
// ============================================================
let loansPage = 1;
async function loadLoans(page=1) {
  loansPage = page;
  const filter = document.getElementById('loanFilter').value;
  const search = document.getElementById('loanSearch').value.trim();
  document.getElementById('loansBody').innerHTML = '<tr><td colspan="10" class="loading"><span class="spinner"></span> Loading…</td></tr>';

  try {
    const res  = await fetch(`${API}?action=admin_get_loans&filter=${filter}&search=${encodeURIComponent(search)}&page=${page}`);
    const data = await res.json();
    renderLoansTable('loansBody', data.data || [], false);
    renderPagination('loansPagination', data.page, data.pages, loadLoans);
    document.getElementById('loansInfo').textContent = `Showing page ${data.page} of ${data.pages} (${fmt(data.total)} records)`;
  } catch { document.getElementById('loansBody').innerHTML = '<tr><td colspan="10" style="color:var(--red)">Failed to load loans.</td></tr>'; }
}

function renderLoansTable(tbodyId, loans, compact=false) {
  const tbody = document.getElementById(tbodyId);
  if (!loans.length) {
    tbody.innerHTML = `<tr><td colspan="${compact?8:10}" class="empty"><div class="ei">📭</div><p>No loan records found.</p></td></tr>`;
    return;
  }
  tbody.innerHTML = loans.map(l => `
    <tr>
      <td>
        <div class="td-matric" style="color:var(--gd)">${l.matric_number}</div>
        ${l.school_matric ? `<div style="font-size:10px;color:var(--muted);font-family:'Courier New',monospace">${l.school_matric}</div>` : ''}
      </td>
      <td class="td-name"><strong>${esc(l.student_name)}</strong><span>${esc(l.department_name||'')}</span></td>
      ${!compact ? `<td style="font-size:12px;color:var(--muted)">${esc(l.faculty_name||'')}</td>` : ''}
      <td><span style="font-size:18px">${l.cover_emoji||'📘'}</span> ${esc(l.book_title)}<br><small style="color:var(--muted)">${esc(l.book_author)}</small></td>
      <td>${l.borrow_date}</td>
      <td>${l.due_date}</td>
      ${!compact ? `<td>${l.return_date || '<span style="color:var(--muted)">—</span>'}</td>` : ''}
      <td>${statusBadge(l.status)}</td>
      <td>${fineHtml(l.fine_amount, l.fine_paid)}</td>
      <td>
        <button class="btn btn-ghost btn-sm" onclick="viewLoanDetail(${l.loan_id})">Detail</button>
        ${l.status !== 'returned' ? `<button class="btn btn-amber btn-sm" onclick="quickReturn(${l.loan_id})">Return</button>` : ''}
        ${l.fine_amount > 0 && !l.fine_paid ? `<button class="btn btn-sm" style="background:#fef9c3;color:#854d0e" onclick="waiveFine(${l.loan_id})">Waive Fine</button>` : ''}
      </td>
    </tr>`).join('');
}

// ============================================================
//  STUDENTS TABLE
// ============================================================
async function loadStudents(page=1) {
  const search = document.getElementById('stuSearch').value.trim();
  document.getElementById('studentsBody').innerHTML = '<tr><td colspan="9" class="loading"><span class="spinner"></span> Loading…</td></tr>';

  try {
    const res  = await fetch(`${API}?action=admin_get_students&search=${encodeURIComponent(search)}&page=${page}`);
    const data = await res.json();
    const students = data.data || [];

    if (!students.length) {
      document.getElementById('studentsBody').innerHTML = '<tr><td colspan="9" class="empty"><div class="ei">👨‍🎓</div><p>No students found.</p></td></tr>';
    } else {
      document.getElementById('studentsBody').innerHTML = students.map(s => `
        <tr>
          <td class="td-matric">${s.matric_number}</td>
          <td><strong>${esc(s.full_name)}</strong></td>
          <td style="font-size:12px">${esc(s.faculty_name||'')}</td>
          <td style="font-size:12px">${esc(s.department_name||'')}</td>
          <td><span class="badge b-active">${s.level||'—'}</span></td>
          <td style="font-size:12px">${esc(s.email)}</td>
          <td style="font-size:12px">${s.phone||'—'}</td>
          <td>${statusBadge(s.status)}</td>
          <td style="font-size:12px;color:var(--muted)">${new Date(s.created_at).toLocaleDateString()}</td>
        </tr>`).join('');
    }
    renderPagination('stuPagination', data.page, data.pages, loadStudents);
    document.getElementById('stuInfo').textContent = `Page ${data.page} of ${data.pages} (${fmt(data.total)} students)`;
  } catch { document.getElementById('studentsBody').innerHTML = '<tr><td colspan="9" style="color:var(--red)">Failed to load students.</td></tr>'; }
}

// ============================================================
//  ISSUE LOAN
// ============================================================
let selectedStudentId = null;

async function lookupStudent() {
  const q    = document.getElementById('issueMatric').value.trim().toUpperCase();
  if (!q) { flash('issueFlash','Enter a library number, school matric, or student name.','err'); return; }
  const card = document.getElementById('studentCard');
  card.style.display = 'none';
  selectedStudentId  = null;
  try {
    const res  = await fetch(`${API}?action=admin_search_student&matric=${encodeURIComponent(q)}`);
    const data = await res.json();
    if (data.status === 'success') {
      const s = data.data;
      selectedStudentId = s.library_reg_number || s.matric_number;
      document.getElementById('lcMatric').innerHTML =
        `<span style="color:var(--gd);font-size:15px;font-weight:700">${s.library_reg_number || s.matric_number}</span>
         ${s.school_matric ? `<span style="font-size:11px;color:var(--muted);margin-left:8px">School: ${s.school_matric}</span>` : ''}`;
      document.getElementById('lcName').textContent   = s.full_name;
      document.getElementById('lcDept').textContent   = (s.faculty_name||'') + ' · ' + (s.department_name||'');
      document.getElementById('lcLevel').textContent  = 'Level: ' + (s.level||'—') + ' · ' + s.email;
      document.getElementById('lcStatus').textContent = s.status;
      document.getElementById('lcStatus').className   = 'badge ' + (s.status==='active' ? 'b-active' : 'b-overdue');
      card.style.display = 'block';
      flash('issueFlash', `✅ Student found: <strong>${s.full_name}</strong>`, 'ok');
      // Store the library reg number as the identifier for issueLoan
      document.getElementById('issueMatric').value = s.library_reg_number || s.matric_number;
    } else {
      flash('issueFlash', '❌ ' + data.message, 'err');
    }
  } catch { flash('issueFlash','Network error. Try again.','err'); }
}

let bookSearchTimer;
async function searchBook() {
  const q = document.getElementById('bookSearch').value.trim();
  const resultsEl = document.getElementById('bookResults');
  if (q.length < 2) { resultsEl.style.display='none'; return; }

  resultsEl.style.display = 'block';
  resultsEl.innerHTML = '<div style="padding:10px 14px;color:var(--muted)"><span class="spinner"></span> Searching…</div>';

  try {
    const res  = await fetch(`${API}?action=admin_search_book&q=${encodeURIComponent(q)}`);
    const data = await res.json();
    if (data.status === 'success' && data.data.length > 0) {
      resultsEl.innerHTML = data.data.map(b => `
        <div class="book-result-item" onclick="selectBook(${b.id},'${esc(b.title)}','${esc(b.author)}','${b.cover_emoji}',${b.available_copies})">
          <div class="bri-emoji">${b.cover_emoji}</div>
          <div class="bri-info">
            <strong>${esc(b.title)}</strong>
            <span>${esc(b.author)} · ${b.department_name||''}</span>
          </div>
          <span class="bri-avail ${b.available_copies > 0 ? 'b-active' : 'b-lost'}" style="background:${b.available_copies>0?'#e8f5ec':'#fef2f2'};color:${b.available_copies>0?'#1a5c2e':'#dc2626'}">
            ${b.available_copies}/${b.total_copies} available
          </span>
        </div>`).join('');
    } else {
      resultsEl.innerHTML = '<div style="padding:10px 14px;color:var(--muted)">No books found.</div>';
    }
  } catch { resultsEl.innerHTML = '<div style="padding:10px 14px;color:var(--red)">Search failed.</div>'; }
}

function selectBook(id, title, author, emoji, avail) {
  document.getElementById('selectedBookId').value  = id;
  document.getElementById('sbTitle').textContent   = title;
  document.getElementById('sbAuthor').textContent  = 'by ' + author;
  document.getElementById('sbEmoji').textContent   = emoji;
  document.getElementById('sbAvail').textContent   = avail + ' cop' + (avail!==1?'ies':'y') + ' available';
  document.getElementById('selectedBook').style.display = 'flex';
  document.getElementById('bookResults').style.display  = 'none';
  document.getElementById('bookSearch').value = title;
}

function clearBook() {
  document.getElementById('selectedBookId').value = '';
  document.getElementById('selectedBook').style.display = 'none';
  document.getElementById('bookSearch').value = '';
}

async function issueLoan() {
  const matric  = document.getElementById('issueMatric').value.trim().toUpperCase();
  const book_id = document.getElementById('selectedBookId').value;
  const days    = document.getElementById('loanDays').value;
  const notes   = document.getElementById('loanNotes').value;

  if (!matric)  { flash('issueFlash','Please look up a student first.','err'); return; }
  if (!book_id) { flash('issueFlash','Please select a book.','err'); return; }

  const body = new FormData();
  body.append('action','admin_issue_loan');
  body.append('matric_number', matric);
  body.append('book_id', book_id);
  body.append('loan_days', days);
  body.append('notes', notes);

  try {
    const res  = await adminPost(body);
    const data = await res.json();
    if (data.status === 'success') {
      flash('issueFlash',
        `✅ <strong>${data.book}</strong> issued to <strong>${data.student}</strong>. Due date: <strong>${data.due_date}</strong>`,
        'ok');
      clearBook();
      document.getElementById('loanNotes').value = '';
    } else {
      flash('issueFlash', '❌ ' + data.message, 'err');
    }
  } catch { flash('issueFlash','Network error. Try again.','err'); }
}

// ============================================================
//  PROCESS RETURN
// ============================================================
async function loadActiveLoans() {
  const matric = document.getElementById('retMatric').value.trim().toUpperCase();
  if (!matric) { flash('returnFlash','Enter a matric number.','err'); return; }

  const wrap = document.getElementById('activeLoansWrap');
  wrap.style.display = 'block';
  document.getElementById('activeLoansBody').innerHTML = '<tr><td colspan="6" class="loading"><span class="spinner"></span> Loading…</td></tr>';

  try {
    const res  = await fetch(`${API}?action=admin_get_loans&filter=active&search=${encodeURIComponent(matric)}&page=1`);
    const data = await res.json();
    const loans = data.data || [];

    if (!loans.length) {
      document.getElementById('activeLoansBody').innerHTML = '<tr><td colspan="6" class="empty"><div class="ei">✅</div><p>No active loans found for this matric number.</p></td></tr>';
      return;
    }
    document.getElementById('activeLoansBody').innerHTML = loans.map(l => `
      <tr>
        <td><span style="font-size:18px">${l.cover_emoji||'📘'}</span> <strong>${esc(l.book_title)}</strong><br><small style="color:var(--muted)">${esc(l.book_author)}</small></td>
        <td>${l.borrow_date}</td>
        <td>${l.due_date}</td>
        <td>${daysLeft(l.due_date)}</td>
        <td>${statusBadge(l.status)}</td>
        <td>
          <button class="btn btn-primary btn-sm" onclick="processReturn(${l.loan_id}, this)">↩️ Process Return</button>
        </td>
      </tr>`).join('');
  } catch { document.getElementById('activeLoansBody').innerHTML = '<tr><td colspan="6" style="color:var(--red)">Failed to load loans.</td></tr>'; }
}

async function processReturn(loanId, btn) {
  if (!confirm('Confirm book return? Fine will be calculated if overdue.')) return;
  btn.disabled = true;
  btn.textContent = 'Processing…';

  const body = new FormData();
  body.append('action','admin_return_book');
  body.append('loan_id', loanId);

  try {
    const res  = await adminPost(body);
    const data = await res.json();
    if (data.status === 'success') {
      flash('returnFlash',
        `✅ Book returned. ${data.fine !== '0' ? '⚠️ Fine applied: <strong>'+data.fine+'</strong>' : 'No fine applied.'}`,
        'ok');
      loadActiveLoans();
    } else {
      flash('returnFlash','❌ ' + data.message,'err');
      btn.disabled = false;
      btn.textContent = '↩️ Process Return';
    }
  } catch {
    flash('returnFlash','Network error.','err');
    btn.disabled = false;
    btn.textContent = '↩️ Process Return';
  }
}

// ── Quick return from loans table ──────────────────────────────
async function quickReturn(loanId) {
  if (!confirm('Confirm book return? Fine will be calculated if overdue.')) return;
  const body = new FormData();
  body.append('action','admin_return_book');
  body.append('loan_id', loanId);
  const res  = await adminPost(body);
  const data = await res.json();
  alert(data.message);
  loadLoans(loansPage);
}

// ── Waive fine ─────────────────────────────────────────────────
async function waiveFine(loanId) {
  if (!confirm('Waive this fine? This action cannot be undone.')) return;
  const body = new FormData();
  body.append('action','admin_waive_fine');
  body.append('loan_id', loanId);
  const res  = await adminPost(body);
  const data = await res.json();
  alert(data.message);
  loadLoans(loansPage);
}

// ============================================================
//  LOAN DETAIL MODAL
// ============================================================
async function viewLoanDetail(loanId) {
  document.getElementById('loanModal').classList.add('open');
  document.getElementById('modalBody').innerHTML = '<div class="loading"><span class="spinner"></span> Loading…</div>';

  try {
    const res  = await fetch(`${API}?action=admin_get_loan_detail&loan_id=${loanId}`);
    const data = await res.json();
    if (data.status !== 'success') { document.getElementById('modalBody').innerHTML = '<p style="color:var(--red)">'+data.message+'</p>'; return; }

    const l = data.data.loan;
    const fines = data.data.fines;

    document.getElementById('modalBody').innerHTML = `
      <!-- Book info -->
      <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px;padding:14px;background:${l.cover_color||'#e8f5ec'};border-radius:var(--r)">
        <span style="font-size:40px">${l.cover_emoji||'📘'}</span>
        <div>
          <div style="font-size:17px;font-weight:700">${esc(l.book_title)}</div>
          <div style="font-size:13px;color:var(--muted)">${esc(l.author)} ${l.isbn?'· ISBN: '+l.isbn:''}</div>
          <div style="font-size:12px;color:var(--muted)">${esc(l.publisher||'')} ${l.pub_year?'('+l.pub_year+')':''}</div>
          ${l.location?`<div style="font-size:12px;color:var(--gm)">📍 ${esc(l.location)}</div>`:''}
        </div>
      </div>

      <!-- Student info -->
      <div class="detail-section">Student Information</div>
      <div class="detail-row"><div class="dl">Matric Number</div><div class="dv td-matric">${esc(l.matric_number)}</div></div>
      <div class="detail-row"><div class="dl">Full Name</div><div class="dv">${esc(l.student_name)}</div></div>
      <div class="detail-row"><div class="dl">Faculty</div><div class="dv">${esc(l.faculty_name||'—')}</div></div>
      <div class="detail-row"><div class="dl">Department</div><div class="dv">${esc(l.department_name||'—')}</div></div>
      <div class="detail-row"><div class="dl">Level</div><div class="dv">${l.level||'—'}</div></div>
      <div class="detail-row"><div class="dl">Email</div><div class="dv">${esc(l.email||'—')}</div></div>
      <div class="detail-row"><div class="dl">Phone</div><div class="dv">${l.phone||'—'}</div></div>

      <!-- Loan info -->
      <div class="detail-section">Loan Details</div>
      <div class="detail-row"><div class="dl">Loan ID</div><div class="dv">#${l.id}</div></div>
      <div class="detail-row"><div class="dl">Status</div><div class="dv">${statusBadge(l.status)}</div></div>
      <div class="detail-row"><div class="dl">Borrow Date</div><div class="dv">${l.borrow_date}</div></div>
      <div class="detail-row"><div class="dl">Due Date</div><div class="dv">${l.due_date} ${l.status!=='returned'?daysLeft(l.due_date):''}</div></div>
      <div class="detail-row"><div class="dl">Return Date</div><div class="dv">${l.return_date||'<span style="color:var(--muted)">Not returned yet</span>'}</div></div>
      <div class="detail-row"><div class="dl">Fine Amount</div><div class="dv">${fineHtml(l.fine_amount, l.fine_paid)}</div></div>
      ${l.notes?`<div class="detail-row"><div class="dl">Notes</div><div class="dv">${esc(l.notes)}</div></div>`:''}

      <!-- Fine records -->
      ${fines.length ? `
        <div class="detail-section">Fine Records</div>
        ${fines.map(f=>`
          <div class="detail-row">
            <div class="dl">${f.created_at.split(' ')[0]}</div>
            <div class="dv">₦${parseFloat(f.amount).toLocaleString('en-NG',{minimumFractionDigits:2})} — ${f.reason}
              ${f.paid?'<span class="badge b-returned" style="margin-left:6px">Paid/Waived</span>':
                       '<span class="badge b-overdue" style="margin-left:6px">Unpaid</span>'}
            </div>
          </div>`).join('')}
      `:''}

      <!-- Actions -->
      <div style="display:flex;gap:10px;margin-top:20px;flex-wrap:wrap">
        ${l.status !== 'returned' ? `<button class="btn btn-primary" onclick="closeModal();quickReturn(${l.id})">↩️ Process Return</button>` : ''}
        ${l.fine_amount > 0 && !l.fine_paid ? `<button class="btn btn-amber" onclick="closeModal();waiveFine(${l.id})">Waive Fine</button>` : ''}
        <button class="btn btn-ghost" onclick="closeModal()">Close</button>
      </div>`;
  } catch {
    document.getElementById('modalBody').innerHTML = '<p style="color:var(--red)">Failed to load loan detail.</p>';
  }
}

function closeModal() {
  document.getElementById('loanModal').classList.remove('open');
}
document.getElementById('loanModal').addEventListener('click', e => {
  if (e.target === document.getElementById('loanModal')) closeModal();
});

// ============================================================
//  ADD BOOK
// ============================================================
async function addBook() {
  const body = new FormData();
  body.append('action','admin_add_book');
  body.append('title',         document.getElementById('abTitle').value.trim());
  body.append('author',        document.getElementById('abAuthor').value.trim());
  body.append('isbn',          document.getElementById('abISBN').value.trim());
  body.append('publisher',     document.getElementById('abPublisher').value.trim());
  body.append('pub_year',      document.getElementById('abYear').value.trim());
  body.append('category',      document.getElementById('abCategory').value);
  body.append('dept_code',     document.getElementById('abDeptCode').value.trim().toUpperCase());
  body.append('dept_name',     document.getElementById('abDeptName').value.trim());
  body.append('resource_type', document.getElementById('abType').value);
  body.append('total_copies',  document.getElementById('abCopies').value);
  body.append('location',      document.getElementById('abLocation').value.trim());
  body.append('cover_color',   document.getElementById('abColor').value);
  body.append('cover_emoji',   document.getElementById('abEmoji').value);

  try {
    const res  = await adminPost(body);
    const data = await res.json();
    flash('addBookFlash', data.status==='success' ? '✅ '+data.message : '❌ '+data.message, data.status==='success'?'ok':'err');
    if (data.status === 'success') {
      ['abTitle','abAuthor','abISBN','abPublisher','abYear','abDeptCode','abDeptName','abLocation'].forEach(id=>document.getElementById(id).value='');
      document.getElementById('abCopies').value = 1;
    }
  } catch { flash('addBookFlash','Network error.','err'); }
}

// ============================================================
//  ANNOUNCEMENT
// ============================================================
async function postAnnouncement() {
  const body = new FormData();
  body.append('action',    'admin_add_announcement');
  body.append('title',     document.getElementById('annTitle').value.trim());
  body.append('body',      document.getElementById('annBody').value.trim());
  body.append('tag',       document.getElementById('annTag').value);
  body.append('tag_color', document.getElementById('annColor').value);

  try {
    const res  = await adminPost(body);
    const data = await res.json();
    flash('annFlash', data.status==='success'?'✅ '+data.message:'❌ '+data.message, data.status==='success'?'ok':'err');
    if (data.status==='success') {
      document.getElementById('annTitle').value = '';
      document.getElementById('annBody').value  = '';
    }
  } catch { flash('annFlash','Network error.','err'); }
}

// ============================================================
//  PAGINATION
// ============================================================
function renderPagination(elId, current, total, callback) {
  const el = document.getElementById(elId);
  if (total <= 1) { el.innerHTML=''; return; }
  let html = '';
  for (let p = 1; p <= total; p++) {
    html += `<button class="page-btn ${p===current?'active':''}" onclick="${callback.name}(${p})">${p}</button>`;
  }
  el.innerHTML = html;
}

// ============================================================
//  LOGOUT
// ============================================================
async function logoutAdmin() {
  const body = new FormData();
  body.append('action','logout_user');
  await adminPost(body);
  window.location.href = 'index.php';
}

// ── Escape HTML for display ────────────────────────────────────
function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

// ── Overdue update button (called from dashboard) ─────────────
async function runOverdueUpdate() {
  const btn = document.getElementById('overdueBtn');
  if (btn) { btn.disabled=true; btn.textContent='Updating…'; }
  try {
    const res  = await fetch(`${API}?action=admin_update_overdue`);
    const data = await res.json();
    alert(data.message);
    loadDashboard();
  } catch { alert('Failed to update overdue status.'); }
  finally { if (btn) { btn.disabled=false; btn.textContent='🔄 Mark Overdue Loans'; } }
}

// ============================================================
//  FINES MANAGEMENT
// ============================================================
let finesPage = 1;
async function loadFines(page=1) {
  finesPage = page;
  const filter = document.getElementById('fineFilter').value;
  const search = document.getElementById('fineSearch').value.trim();
  document.getElementById('finesBody').innerHTML =
    '<tr><td colspan="10" class="loading"><span class="spinner"></span> Loading…</td></tr>';

  try {
    const res  = await fetch(`${API}?action=admin_get_fines&filter=${filter}&search=${encodeURIComponent(search)}&page=${page}`);
    const data = await res.json();

    // Update summary cards
    if (data.summary) {
      const s = data.summary;
      document.getElementById('fUnpaid').textContent = '₦'+parseFloat(s.unpaid_total||0).toLocaleString('en-NG',{minimumFractionDigits:2});
      document.getElementById('fPaid').textContent   = '₦'+parseFloat(s.paid_total||0).toLocaleString('en-NG',{minimumFractionDigits:2});
      document.getElementById('fCount').textContent  = fmt(s.total_count||0);
    }

    const fines = data.data || [];
    if (!fines.length) {
      document.getElementById('finesBody').innerHTML =
        `<tr><td colspan="10" class="empty"><div class="ei">✅</div><p>No ${filter} fines found.</p></td></tr>`;
    } else {
      document.getElementById('finesBody').innerHTML = fines.map(f => `
        <tr>
          <td class="td-matric">${esc(f.matric_number)}</td>
          <td class="td-name"><strong>${esc(f.student_name)}</strong><br><span style="font-size:11px">${f.phone||'—'}</span></td>
          <td style="font-size:12px">${esc(f.department_name||'—')}</td>
          <td>${f.cover_emoji||'📘'} ${esc(f.book_title)}<br><small style="color:var(--muted)">${esc(f.author)}</small></td>
          <td style="font-size:12px">${f.due_date}</td>
          <td style="font-size:12px">${f.return_date||'<span style="color:var(--red)">Not returned</span>'}</td>
          <td><strong class="fine-amount">₦${parseFloat(f.amount).toLocaleString('en-NG',{minimumFractionDigits:2})}</strong></td>
          <td style="font-size:12px">${new Date(f.created_at).toLocaleDateString()}</td>
          <td>${f.paid
            ? `<span class="badge b-returned">Paid ${f.paid_at?new Date(f.paid_at).toLocaleDateString():''}</span>`
            : `<span class="badge b-overdue">Unpaid</span>`}</td>
          <td>
            ${!f.paid ? `
              <button class="btn btn-primary btn-sm" onclick="markFinePaid(${f.fine_id})">Mark Paid</button>
              <button class="btn btn-ghost btn-sm" style="margin-top:4px" onclick="waiveFineById(${f.loan_id})">Waive</button>
            ` : '<span style="color:var(--muted);font-size:12px">—</span>'}
          </td>
        </tr>`).join('');
    }

    renderPagination('finesPagination', data.page, data.pages, loadFines);
    document.getElementById('finesInfo').textContent =
      `Page ${data.page} of ${data.pages} (${fmt(data.total)} records)`;
  } catch(e) {
    document.getElementById('finesBody').innerHTML =
      `<tr><td colspan="10" style="color:var(--red)">Failed to load fines: ${e.message}</td></tr>`;
  }
}

async function markFinePaid(fineId) {
  if (!confirm('Mark this fine as paid?')) return;
  const body = new FormData();
  body.append('action','admin_mark_fine_paid');
  body.append('fine_id', fineId);
  const res  = await fetch(API, {method:'POST', body});
  const data = await res.json();
  alert(data.message);
  loadFines(finesPage);
}

async function waiveFineById(loanId) {
  if (!confirm('Waive this fine? Cannot be undone.')) return;
  const body = new FormData();
  body.append('action','admin_waive_fine');
  body.append('loan_id', loanId);
  const res  = await fetch(API, {method:'POST', body});
  const data = await res.json();
  alert(data.message);
  loadFines(finesPage);
}

// ============================================================
//  BOOKS CATALOG
// ============================================================
let booksPage = 1;
async function loadBooks(page=1) {
  booksPage = page;
  const search = document.getElementById('bookCatSearch').value.trim();
  document.getElementById('booksBody').innerHTML =
    '<tr><td colspan="12" class="loading"><span class="spinner"></span> Loading…</td></tr>';

  try {
    const res  = await fetch(`${API}?action=admin_get_books_list&search=${encodeURIComponent(search)}&page=${page}`);
    const data = await res.json();
    const books = data.data || [];

    if (!books.length) {
      document.getElementById('booksBody').innerHTML =
        '<tr><td colspan="12" class="empty"><div class="ei">📚</div><p>No books found.</p></td></tr>';
    } else {
      const typeMap = {physical:'Physical',digital:'Digital',both:'Physical + Digital'};
      document.getElementById('booksBody').innerHTML = books.map(b => `
        <tr>
          <td style="font-size:24px;text-align:center">${b.cover_emoji||'📘'}</td>
          <td><strong style="font-size:13px">${esc(b.title)}</strong></td>
          <td style="font-size:12px;color:var(--muted)">${esc(b.author)}</td>
          <td style="font-size:11px;font-family:'Courier New',monospace">${b.isbn||'—'}</td>
          <td style="font-size:12px">${esc(b.department_name||'—')} <span style="color:var(--muted);font-size:11px">[${b.dept_code||''}]</span></td>
          <td><span class="tag" style="background:var(--gl);color:var(--gd)">${b.category||'—'}</span></td>
          <td style="text-align:center;font-weight:600">${b.total_copies}</td>
          <td style="text-align:center">
            <span style="font-weight:700;color:${b.available_copies>0?'var(--gm)':'var(--red)'}">${b.available_copies}</span>
          </td>
          <td style="font-size:12px">${typeMap[b.resource_type]||b.resource_type}</td>
          <td style="font-size:12px;color:var(--muted)">${b.location||'—'}</td>
          <td style="font-size:11px;color:var(--muted)">${new Date(b.added_at).toLocaleDateString()}</td>
          <td>
            <button class="btn btn-primary btn-sm" onclick="editBook(${b.id})">✏️ Edit</button>
          </td>
        </tr>`).join('');
    }
    renderPagination('booksPagination', data.page, data.pages, loadBooks);
    document.getElementById('booksInfo').textContent =
      `Page ${data.page} of ${data.pages} (${fmt(data.total)} books)`;
  } catch(e) {
    document.getElementById('booksBody').innerHTML =
      `<tr><td colspan="12" style="color:var(--red)">Failed to load books: ${e.message}</td></tr>`;
  }
}

/** Fetch the complete book record from the server, then open the edit form */
async function editBook(bookId) {
  try {
    const res  = await fetch(`${API}?action=admin_get_book_detail&book_id=${bookId}`);
    const data = await res.json();
    if (data.status !== 'success') { alert('Could not load book: ' + data.message); return; }
    openBookEdit(data.data);
  } catch { alert('Network error. Could not load book details.'); }
}

function openBookEdit(book) {
  document.getElementById('editBookId').value           = book.id;
  document.getElementById('editTitle').value            = book.title            || '';
  document.getElementById('editAuthor').value           = book.author           || '';
  document.getElementById('editISBN').value             = book.isbn             || '';
  document.getElementById('editPublisher').value        = book.publisher        || '';
  document.getElementById('editYear').value             = book.pub_year         || '';
  document.getElementById('editDeptCode').value         = (book.dept_code       || '').toUpperCase();
  document.getElementById('editDeptName').value         = book.department_name  || '';
  document.getElementById('editTotal').value            = book.total_copies     || 1;
  document.getElementById('editAvail').value            = book.available_copies || 0;
  document.getElementById('editLoc').value              = book.location         || '';
  document.getElementById('editDigitalUrl').value       = book.digital_url      || '';
  document.getElementById('editBookPanelTitle').textContent = '✏️ Editing: ' + book.title;

  setSelectVal('editCategory',     book.category      || 'general');
  setSelectVal('editResourceType', book.resource_type || 'physical');
  setSelectVal('editColor',        book.cover_color   || '#e8f5ec');
  setSelectVal('editEmoji',        book.cover_emoji   || '📘');

  // Update live preview
  updateEditPreview();

  const panel = document.getElementById('editBookPanel');
  panel.style.display = 'block';
  document.getElementById('editBookFlash').style.display = 'none';
  document.getElementById('copiesWarning').style.display = 'none';
  panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function setSelectVal(id, value) {
  const sel = document.getElementById(id);
  if (!sel) return;
  for (let i = 0; i < sel.options.length; i++) {
    if (sel.options[i].value === value) { sel.selectedIndex = i; return; }
  }
  // If no exact match found, leave at default (index 0)
}

function updateEditPreview() {
  const emoji = document.getElementById('editEmoji')?.value || '📘';
  const color = document.getElementById('editColor')?.value || '#e8f5ec';
  const prev  = document.getElementById('editPreview');
  if (prev) { prev.style.background = color; prev.textContent = emoji; }
}

function closeBookEdit() {
  document.getElementById('editBookPanel').style.display = 'none';
}

function validateCopies() {
  const t = parseInt(document.getElementById('editTotal').value) || 0;
  const a = parseInt(document.getElementById('editAvail').value) || 0;
  document.getElementById('copiesWarning').style.display = a > t ? 'block' : 'none';
}

async function saveBookEdit() {
  const total = parseInt(document.getElementById('editTotal').value) || 1;
  const avail = parseInt(document.getElementById('editAvail').value) || 0;
  if (avail > total) {
    flash('editBookFlash', '❌ Available copies cannot exceed total copies.', 'err'); return;
  }
  const body = new FormData();
  body.append('action',           'admin_update_book');
  body.append('book_id',          document.getElementById('editBookId').value);
  body.append('title',            document.getElementById('editTitle').value.trim());
  body.append('author',           document.getElementById('editAuthor').value.trim());
  body.append('isbn',             document.getElementById('editISBN').value.trim());
  body.append('publisher',        document.getElementById('editPublisher').value.trim());
  body.append('pub_year',         document.getElementById('editYear').value.trim());
  body.append('category',         document.getElementById('editCategory').value);
  body.append('dept_code',        document.getElementById('editDeptCode').value.trim().toUpperCase());
  body.append('dept_name',        document.getElementById('editDeptName').value.trim());
  body.append('resource_type',    document.getElementById('editResourceType').value);
  body.append('total_copies',     total);
  body.append('available_copies', avail);
  body.append('location',         document.getElementById('editLoc').value.trim());
  body.append('cover_color',      document.getElementById('editColor').value);
  body.append('cover_emoji',      document.getElementById('editEmoji').value);
  body.append('digital_url',      document.getElementById('editDigitalUrl').value.trim());
  try {
    const res  = await adminPost(body);
    const data = await res.json();
    flash('editBookFlash',
      data.status === 'success' ? '✅ ' + data.message : '❌ ' + data.message,
      data.status === 'success' ? 'ok' : 'err');
    if (data.status === 'success') loadBooks(booksPage);
  } catch { flash('editBookFlash', '❌ Network error. Please try again.', 'err'); }
}

// ── Student account toggle ────────────────────────────────────
async function toggleStudent(studentId, action) {
  const label = action === 'suspend' ? 'suspend' : 're-activate';
  if (!confirm(`Are you sure you want to ${label} this student account?`)) return;

  const body = new FormData();
  body.append('action',      'admin_toggle_student');
  body.append('student_id',  studentId);
  body.append('action_type', action);

  const res  = await fetch(API, {method:'POST', body});
  const data = await res.json();
  alert(data.message);
  loadStudents(1);
}

// ── Enhance loadDashboard to add overdue button ───────────────
const _origLoadDashboard = loadDashboard;
// Overdue button injected into dashboard after stats load
function injectOverdueBtn() {
  const statsRow = document.getElementById('statsRow');
  if (statsRow && !document.getElementById('overdueBtn')) {
    const wrap = document.createElement('div');
    wrap.style.cssText = 'grid-column:1/-1;display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-top:4px';
    wrap.innerHTML = `
      <button id="overdueBtn" class="btn btn-amber" onclick="runOverdueUpdate()">
        🔄 Mark Overdue Loans
      </button>
      <span style="font-size:12px;color:var(--muted)">
        Click to update loan statuses where due date has passed.
        Run this daily for accurate records.
      </span>`;
    statsRow.appendChild(wrap);
  }
}

// Patch loadDashboard to inject overdue button after stats
async function loadDashboard() {
  const statsRow = document.getElementById('statsRow');
  try {
    const res  = await fetch(`${API}?action=admin_get_stats`);
    const data = await res.json();
    if (data.status === 'success') {
      const s = data.data;
      statsRow.innerHTML = `
        <div class="stat-card"><div class="sc-label">Total Students</div><div class="sc-value">${fmt(s.total_students)}</div><div class="sc-sub">Registered members</div></div>
        <div class="stat-card blue"><div class="sc-label">Total Books</div><div class="sc-value">${fmt(s.total_books)}</div><div class="sc-sub">In catalog</div></div>
        <div class="stat-card"><div class="sc-label">Active Loans</div><div class="sc-value">${fmt(s.active_loans)}</div><div class="sc-sub">Currently borrowed</div></div>
        <div class="stat-card red"><div class="sc-label">Overdue Loans</div><div class="sc-value">${fmt(s.overdue_loans)}</div><div class="sc-sub">Past due date</div></div>
        <div class="stat-card amber"><div class="sc-label">Unpaid Fines</div><div class="sc-value">₦${parseFloat(s.unpaid_fines||0).toLocaleString('en-NG',{minimumFractionDigits:2})}</div><div class="sc-sub">Outstanding</div></div>
        <div class="stat-card"><div class="sc-label">Pending Holds</div><div class="sc-value">${fmt(s.pending_reservations)}</div><div class="sc-sub">Reservations</div></div>`;
      injectOverdueBtn();
    }
  } catch { statsRow.innerHTML = '<p style="color:var(--red)">Failed to load stats.</p>'; }

  try {
    const res  = await fetch(`${API}?action=admin_get_loans&filter=all&page=1`);
    const data = await res.json();
    renderLoansTable('recentBody', data.data || [], true);
  } catch { document.getElementById('recentBody').innerHTML = '<tr><td colspan="8">Failed to load recent loans.</td></tr>'; }
}

// ── Enhance student table to include Suspend/Activate button ──
const _origLoadStudents = loadStudents;
async function loadStudents(page=1) {
  const search = document.getElementById('stuSearch').value.trim();
  document.getElementById('studentsBody').innerHTML =
    '<tr><td colspan="10" class="loading"><span class="spinner"></span> Loading…</td></tr>';

  try {
    const res  = await fetch(`${API}?action=admin_get_students&search=${encodeURIComponent(search)}&page=${page}`);
    const data = await res.json();
    const students = data.data || [];

    document.querySelector('#sec-students thead tr').innerHTML = `
      <th>Library Reg. No.</th><th>School Matric</th><th>Full Name</th><th>Faculty</th>
      <th>Department</th><th>Level</th><th>Email</th><th>Phone</th>
      <th>Status</th><th>Joined</th><th>Action</th>`;

    if (!students.length) {
      document.getElementById('studentsBody').innerHTML =
        '<tr><td colspan="11" class="empty"><div class="ei">👨‍🎓</div><p>No students found.</p></td></tr>';
    } else {
      document.getElementById('studentsBody').innerHTML = students.map(s => `
        <tr>
          <td class="td-matric" style="color:var(--gd)">${s.library_reg_number || s.matric_number}</td>
          <td class="td-matric" style="color:var(--muted);font-size:11px">${s.school_matric || '—'}</td>
          <td><strong>${esc(s.full_name)}</strong></td>
          <td style="font-size:12px">${esc(s.faculty_name||'')}</td>
          <td style="font-size:12px">${esc(s.department_name||'')}</td>
          <td><span class="badge b-active">${s.level||'—'}</span></td>
          <td style="font-size:12px">${esc(s.email)}</td>
          <td style="font-size:12px">${s.phone||'—'}</td>
          <td>${statusBadge(s.status)}</td>
          <td style="font-size:12px;color:var(--muted)">${new Date(s.created_at).toLocaleDateString()}</td>
          <td>
            ${s.status === 'active'
              ? `<button class="btn btn-danger btn-sm" onclick="toggleStudent(${s.id},'suspend')">Suspend</button>`
              : `<button class="btn btn-primary btn-sm" onclick="toggleStudent(${s.id},'activate')">Activate</button>`}
          </td>
        </tr>`).join('');
    }
    renderPagination('stuPagination', data.page, data.pages, loadStudents);
    document.getElementById('stuInfo').textContent =
      `Page ${data.page} of ${data.pages} (${fmt(data.total)} students)`;
  } catch { document.getElementById('studentsBody').innerHTML = '<tr><td colspan="10" style="color:var(--red)">Failed to load students.</td></tr>'; }
}

// ============================================================
//  STAFF RECORDS
// ============================================================
async function loadStaff(page=1) {
  const search = document.getElementById('staffSearch').value.trim();
  const status = document.getElementById('staffStatusFilter').value;
  document.getElementById('staffBody').innerHTML =
    '<tr><td colspan="9" class="loading"><span class="spinner"></span> Loading…</td></tr>';

  try {
    const res  = await fetch(`${API}?action=admin_get_staff&search=${encodeURIComponent(search)}&status=${encodeURIComponent(status)}&page=${page}`);
    const data = await res.json();
    const staff = data.data || [];

    // Update summary cards
    if (data.summary) {
      document.getElementById('stfActive').textContent    = fmt(data.summary.active    || 0);
      document.getElementById('stfPending').textContent   = fmt(data.summary.pending   || 0);
      document.getElementById('stfSuspended').textContent = fmt(data.summary.suspended || 0);
      document.getElementById('stfInactive').textContent  = fmt(data.summary.inactive  || 0);
    }

    if (!staff.length) {
      document.getElementById('staffBody').innerHTML =
        '<tr><td colspan="9" class="empty"><div class="ei">👨‍💼</div><p>No staff records found.</p></td></tr>';
    } else {
      document.getElementById('staffBody').innerHTML = staff.map(s => {
        let actionBtn = '';
        if (s.status === 'active') {
          actionBtn = `<button class="btn btn-danger btn-sm" onclick="toggleStaff(${s.id},'suspend')">Suspend</button>`;
        } else if (s.status === 'suspended' || s.status === 'inactive') {
          actionBtn = `<button class="btn btn-primary btn-sm" onclick="toggleStaff(${s.id},'activate')">Activate</button>`;
        } else if (s.status === 'pending') {
          actionBtn = `<span style="font-size:11px;color:var(--muted)">See Pending Approvals</span>`;
        }
        return `
        <tr>
          <td class="td-matric" style="color:var(--gd)">${esc(s.library_reg_number || s.matric_number)}</td>
          <td><strong>${esc(s.full_name)}</strong></td>
          <td style="font-size:12px">${esc(s.department_name||'—')} ${s.dept_code?`<span style="color:var(--muted);font-size:11px">[${esc(s.dept_code)}]</span>`:''}</td>
          <td style="font-size:12px">${esc(s.email)}</td>
          <td style="font-size:12px">${s.phone||'—'}</td>
          <td>${statusBadge(s.status)}</td>
          <td style="font-size:12px;color:var(--muted)">${new Date(s.created_at).toLocaleDateString()}</td>
          <td style="font-size:12px;color:var(--muted)">${new Date(s.updated_at).toLocaleDateString()}</td>
          <td>${actionBtn}</td>
        </tr>`;
      }).join('');
    }
    renderPagination('staffPagination', data.page, data.pages, loadStaff);
    document.getElementById('staffInfo').textContent =
      `Page ${data.page} of ${data.pages} (${fmt(data.total)} staff member(s))`;
  } catch(e) {
    document.getElementById('staffBody').innerHTML =
      `<tr><td colspan="9" style="color:var(--red)">Failed to load staff: ${e.message}</td></tr>`;
  }
}

async function toggleStaff(staffId, action) {
  const label = action === 'suspend' ? 'suspend' : 're-activate';
  if (!confirm(`Are you sure you want to ${label} this staff account?`)) return;

  const body = new FormData();
  body.append('action',      'admin_toggle_staff');
  body.append('staff_id',    staffId);
  body.append('action_type', action);

  try {
    const data = await (await adminPost(body)).json();
    alert(data.message);
    loadStaff(1);
  } catch { alert('Network error. Please try again.'); }
}

// ── Initial load ───────────────────────────────────────────────
loadDashboard();

// ── Check for pending staff and show sidebar badge ─────────────
async function checkPendingBadge() {
  try {
    const res  = await fetch(`${API}?action=admin_get_pending_staff`);
    const data = await res.json();
    const count = (data.data || []).length;
    const badge = document.getElementById('pendingBadge');
    if (count > 0) { badge.textContent = count; badge.style.display = 'inline-block'; }
    else badge.style.display = 'none';
  } catch {}
}
checkPendingBadge();

// ============================================================
//  PENDING STAFF APPROVALS
// ============================================================
async function loadPendingStaff() {
  const el = document.getElementById('pendingStaffList');
  el.innerHTML = '<div class="loading"><span class="spinner"></span> Loading…</div>';
  try {
    const res  = await fetch(`${API}?action=admin_get_pending_staff`);
    const data = await res.json();
    const list = data.data || [];

    // Update sidebar badge
    const badge = document.getElementById('pendingBadge');
    badge.textContent   = list.length;
    badge.style.display = list.length > 0 ? 'inline-block' : 'none';

    if (!list.length) {
      el.innerHTML = `<div class="empty"><div class="ei">✅</div>
        <p>No pending staff registrations at this time.</p></div>`;
      return;
    }

    el.innerHTML = list.map(s => `
      <div style="background:var(--surf);border:1px solid var(--border);border-left:4px solid var(--amber);
                  border-radius:var(--rl);padding:20px;margin-bottom:14px;
                  display:flex;align-items:flex-start;gap:16px;flex-wrap:wrap">
        <div style="flex:1;min-width:200px">
          <div style="font-size:15px;font-weight:700;margin-bottom:4px">${esc(s.full_name)}</div>
          <div style="font-family:'Courier New',monospace;font-size:13px;color:var(--gd);margin-bottom:4px">
            ${esc(s.matric_number || s.library_reg_number)}
          </div>
          <div style="font-size:12.5px;color:var(--muted)">
            📧 ${esc(s.email)} &nbsp;·&nbsp;
            🏢 ${esc(s.department_name || 'No department')}
            ${s.phone ? '&nbsp;·&nbsp; 📞 ' + esc(s.phone) : ''}
          </div>
          <div style="font-size:11px;color:var(--muted);margin-top:6px">
            Registered: ${new Date(s.created_at).toLocaleString()}
          </div>
        </div>
        <div style="display:flex;flex-direction:column;gap:8px;flex-shrink:0">
          <button class="btn btn-primary" onclick="approveStaff(${s.id}, this)">✅ Approve</button>
          <button class="btn btn-danger"  onclick="rejectStaff(${s.id}, this)">❌ Reject</button>
        </div>
      </div>`).join('');
  } catch(e) {
    el.innerHTML = `<p style="color:var(--red)">Failed to load: ${e.message}</p>`;
  }
}

async function approveStaff(staffId, btn) {
  if (!confirm('Approve this staff account? They will be able to log in immediately.')) return;
  btn.disabled = true; btn.textContent = 'Approving…';
  const body = new FormData();
  body.append('action',   'admin_approve_staff');
  body.append('staff_id', staffId);
  try {
    const data = await (await adminPost(body)).json();
    alert(data.message);
    loadPendingStaff();
  } catch { alert('Network error.'); btn.disabled=false; }
}

async function rejectStaff(staffId, btn) {
  const reason = prompt('Reason for rejection (optional):');
  if (reason === null) return;
  btn.disabled = true; btn.textContent = 'Rejecting…';
  const body = new FormData();
  body.append('action',   'admin_reject_staff');
  body.append('staff_id', staffId);
  body.append('reason',   reason || 'Not approved by administrator');
  try {
    const data = await (await adminPost(body)).json();
    alert(data.message);
    loadPendingStaff();
  } catch { alert('Network error.'); btn.disabled=false; }
}

// ============================================================
//  AUDIT LOG
// ============================================================
let auditPage = 1;
async function loadAuditLog(page=1) {
  auditPage = page;
  const filter = document.getElementById('auditFilter').value;
  const search = document.getElementById('auditSearch').value.trim();
  document.getElementById('auditBody').innerHTML =
    '<tr><td colspan="6" class="loading"><span class="spinner"></span> Loading…</td></tr>';

  try {
    const res  = await fetch(`${API}?action=admin_get_audit_log&action_filter=${encodeURIComponent(filter)}&search=${encodeURIComponent(search)}&page=${page}`);
    const data = await res.json();

    // Populate action filter dropdown
    if (data.actions && data.actions.length) {
      const sel = document.getElementById('auditFilter');
      const cur = sel.value;
      sel.innerHTML = '<option value="">All Actions</option>' +
        data.actions.map(a => `<option value="${esc(a)}" ${a===cur?'selected':''}>${esc(a)}</option>`).join('');
    }

    const actionColors = {
      'login':'#e8f5ec','logout':'#f3f4f6','issue_loan':'#e8f0fe',
      'approve_staff':'#e8f5ec','reject_staff':'#fef2f2',
      'register_staff_pending':'#fffbeb','admin_return_book':'#e8f0fe',
    };

    const logs = data.data || [];
    if (!logs.length) {
      document.getElementById('auditBody').innerHTML =
        '<tr><td colspan="6" class="empty"><div class="ei">📋</div><p>No records found.</p></td></tr>';
    } else {
      document.getElementById('auditBody').innerHTML = logs.map(l => `
        <tr>
          <td style="font-size:11.5px;white-space:nowrap">${new Date(l.created_at).toLocaleString()}</td>
          <td><strong style="font-size:13px">${esc(l.user_name||'Guest')}</strong>
              ${l.user_id?`<br><span style="font-size:10px;color:var(--muted)">ID:${l.user_id}</span>`:''}
          </td>
          <td><span style="font-size:11.5px;font-weight:600;padding:3px 10px;border-radius:10px;
                           background:${actionColors[l.action]||'#f3f4f6'}">
              ${esc(l.action)}
          </span></td>
          <td style="font-size:12px;color:var(--muted)">
            ${l.target_type?esc(l.target_type)+(l.target_id?' #'+l.target_id:''):'—'}
          </td>
          <td style="font-size:12.5px;max-width:240px">${esc(l.detail||'—')}</td>
          <td style="font-size:11px;font-family:'Courier New',monospace;color:var(--muted)">${esc(l.ip_address||'—')}</td>
        </tr>`).join('');
    }
    renderPagination('auditPagination', data.page, data.pages, loadAuditLog);
    document.getElementById('auditInfo').textContent =
      `Page ${data.page} of ${data.pages} (${fmt(data.total)} records)`;
  } catch(e) {
    document.getElementById('auditBody').innerHTML =
      `<tr><td colspan="6" style="color:var(--red)">Failed to load audit log: ${e.message}</td></tr>`;
  }
}

// ── Escape HTML ────────────────────────────────────────────────
function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
</script>
</body>
</html>
