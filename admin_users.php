<?php
session_start();
require_once 'connect.php';
require_once 'includes/audit_logger.php';

// Authorization: Support both admin auth systems
$isAdmin = false;
if (isset($_SESSION['admin_loggedin']) && $_SESSION['admin_loggedin'] === true) {
    $isAdmin = true;
}
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true && isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT usertype FROM users WHERE id = :id");
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();
    if ($user && $user['usertype'] === 'admin') $isAdmin = true;
}
if (!$isAdmin) { header("Location: login.php"); exit(); }

// Get pending sellers for sidebar
$pendingSellers = 0;
$res = $conn->query("SELECT COUNT(*) as cnt FROM sellers WHERE status = 'pending'");
if ($res && $row = $res->fetch_assoc()) $pendingSellers = $row['cnt'];

$message = "";
$current_user_id = $_SESSION['user_id'] ?? 0;

// Handle actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'], $_POST['target_user_id'])) {
    $target_id = (int)$_POST['target_user_id'];
    
    if ($target_id == $current_user_id) {
        $message = "error|You cannot modify your own account.";
    } else {
        switch ($_POST['action']) {
            case 'suspend':
                $stmt = $pdo->prepare("UPDATE users SET status = 'suspended' WHERE id = :id");
                if ($stmt->execute([':id' => $target_id])) {
                    log_activity($current_user_id, 'User Suspended', "Suspended user ID: $target_id");
                    $message = "success|User account has been suspended.";
                }
                break;

            case 'reactivate':
                $stmt = $pdo->prepare("UPDATE users SET status = 'active' WHERE id = :id");
                if ($stmt->execute([':id' => $target_id])) {
                    log_activity($current_user_id, 'User Reactivated', "Reactivated user ID: $target_id");
                    $message = "success|User account has been reactivated.";
                }
                break;

            case 'make_admin':
                $stmt = $pdo->prepare("UPDATE users SET usertype = 'admin' WHERE id = :id");
                if ($stmt->execute([':id' => $target_id])) {
                    log_activity($current_user_id, 'Role Update', "Promoted user ID: $target_id to Admin");
                    $message = "success|User promoted to Admin.";
                }
                break;

            case 'make_user':
                $stmt = $pdo->prepare("UPDATE users SET usertype = 'user' WHERE id = :id");
                if ($stmt->execute([':id' => $target_id])) {
                    log_activity($current_user_id, 'Role Update', "Demoted user ID: $target_id to User");
                    $message = "success|User demoted to regular user.";
                }
                break;

            case 'delete':
                $confirm = $_POST['confirm_text'] ?? '';
                if (strtoupper($confirm) === 'DELETE') {
                    try {
                        $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
                        if ($stmt->execute([':id' => $target_id])) {
                            log_activity($current_user_id, 'User Deleted', "Permanently deleted user ID: $target_id");
                            $message = "success|User permanently deleted.";
                        }
                    } catch (PDOException $e) {
                        if ($e->getCode() == 23000) { // Integrity constraint violation
                            $message = "error|Cannot delete this user because they have associated records (swaps, orders, etc.). Please Suspend the account instead.";
                        } else {
                            $message = "error|An error occurred while trying to delete the user: " . $e->getMessage();
                        }
                    }
                } else {
                    $message = "error|You must type DELETE to confirm permanent deletion.";
                }
                break;
        }
    }
}

// Fetch all users
$users = $pdo->query("SELECT id, firstname, lastname, email, username, usertype, status, auth_provider, is_2fa_enabled, login_count, created_at FROM users ORDER BY created_at DESC")->fetchAll();

// Compute stats
$totalUsers = count($users);
$totalSellers = 0;
$totalAdmins = 0;
$totalSuspended = 0;
foreach ($users as $u) {
    if ($u['usertype'] === 'seller') $totalSellers++;
    if ($u['usertype'] === 'admin') $totalAdmins++;
    if ($u['status'] === 'suspended') $totalSuspended++;
}

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - BookWagon Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .user-avatar {
            width: 36px; height: 36px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; font-size: 13px; color: #fff; flex-shrink: 0;
        }

        /* Stats Row */
        .user-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
        .mini-stat {
            background: var(--card-bg); border: 1px solid var(--border);
            border-radius: 12px; padding: 16px 20px;
            display: flex; align-items: center; gap: 14px;
        }
        .mini-stat-icon {
            width: 40px; height: 40px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center; font-size: 16px;
        }
        .mini-stat-value { font-size: 22px; font-weight: 700; line-height: 1; }
        .mini-stat-label { font-size: 12px; color: var(--text-muted); margin-top: 2px; }

        /* Search & Filters */
        .toolbar {
            display: flex; align-items: center; justify-content: space-between;
            gap: 12px; flex-wrap: wrap; padding: 16px 22px;
            border-bottom: 1px solid var(--border);
        }
        .search-box {
            display: flex; align-items: center; gap: 8px;
            background: var(--bg); border: 1px solid var(--border);
            border-radius: 10px; padding: 8px 14px; min-width: 260px;
            transition: border-color 0.2s;
        }
        .search-box:focus-within { border-color: var(--primary); }
        .search-box input {
            border: none; background: transparent; outline: none;
            font-size: 13px; font-family: inherit; color: var(--text-dark); width: 100%;
        }
        .search-box i { color: var(--text-light); font-size: 14px; }

        .filter-pills { display: flex; gap: 6px; }
        .filter-pill {
            padding: 6px 14px; border-radius: 8px; font-size: 12px; font-weight: 600;
            border: 1px solid var(--border); color: var(--text-muted);
            background: var(--card-bg); cursor: pointer; transition: all 0.15s ease;
        }
        .filter-pill:hover { border-color: var(--primary); color: var(--primary); }
        .filter-pill.active { background: var(--primary); color: #fff; border-color: var(--primary); }
        .filter-pill .pill-count {
            display: inline-flex; align-items: center; justify-content: center;
            background: rgba(0,0,0,0.1); border-radius: 50px;
            font-size: 10px; padding: 1px 6px; margin-left: 4px;
        }
        .filter-pill.active .pill-count { background: rgba(255,255,255,0.3); }

        /* Status indicator */
        .status-indicator {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 4px 10px; border-radius: 50px; font-size: 12px; font-weight: 600;
        }
        .status-indicator.active { background: #ecfdf5; color: #047857; }
        .status-indicator.suspended { background: #fef2f2; color: #b91c1c; }
        .status-indicator-dot {
            width: 6px; height: 6px; border-radius: 50%;
        }
        .status-indicator.active .status-indicator-dot { background: #10b981; }
        .status-indicator.suspended .status-indicator-dot { background: #ef4444; }

        /* User detail expandable */
        .user-detail-row { display: none; }
        .user-detail-row.show { display: table-row; }
        .user-detail-row td {
            background: #fafbfc;
            padding: 16px 22px !important;
        }
        .detail-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 14px;
        }
        .detail-item-label { font-size: 11px; color: var(--text-light); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px; }
        .detail-item-value { font-size: 13px; font-weight: 600; color: var(--text-dark); }

        /* Delete modal */
        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5); z-index: 200;
            align-items: center; justify-content: center;
        }
        .modal-overlay.show { display: flex; }
        .modal-box {
            background: #fff; border-radius: 16px; padding: 28px;
            max-width: 400px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.2);
        }
        .modal-box h3 { font-size: 16px; font-weight: 700; margin-bottom: 8px; }
        .modal-box p { font-size: 13px; color: var(--text-muted); line-height: 1.5; margin-bottom: 16px; }
        .modal-input {
            width: 100%; padding: 10px 14px; border: 1px solid var(--border);
            border-radius: 8px; font-size: 13px; font-family: inherit;
            margin-bottom: 16px; outline: none;
        }
        .modal-input:focus { border-color: var(--danger); }
        .modal-actions { display: flex; gap: 8px; justify-content: flex-end; }
        .modal-btn {
            padding: 8px 18px; border-radius: 8px; font-size: 13px; font-weight: 600;
            border: none; cursor: pointer; transition: all 0.15s;
        }
        .modal-btn.cancel { background: var(--bg); color: var(--text-muted); }
        .modal-btn.cancel:hover { background: var(--border); }
        .modal-btn.danger { background: var(--danger); color: #fff; }
        .modal-btn.danger:hover { background: #dc2626; }

        @media (max-width: 768px) {
            .user-stats { grid-template-columns: repeat(2, 1fr); }
            .toolbar { flex-direction: column; align-items: stretch; }
            .search-box { min-width: 100%; }
        }
    </style>
</head>
<body>

    <?php include "Admin/admin_sidebar.php"; ?>

    <!-- Delete Confirmation Modal -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal-box">
            <h3 style="color: var(--danger);"><i class="fa-solid fa-triangle-exclamation" style="margin-right: 6px;"></i>Permanently Delete User</h3>
            <p>This action <strong>cannot be undone</strong>. All user data, orders, and history will be permanently lost. Type <strong>DELETE</strong> to confirm.</p>
            <form method="POST" id="deleteForm">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="target_user_id" id="deleteUserId">
                <input type="text" name="confirm_text" class="modal-input" placeholder="Type DELETE to confirm" autocomplete="off" id="deleteConfirmInput">
                <div class="modal-actions">
                    <button type="button" class="modal-btn cancel" onclick="closeDeleteModal()">Cancel</button>
                    <button type="submit" class="modal-btn danger" id="deleteSubmitBtn" disabled>Delete Forever</button>
                </div>
            </form>
        </div>
    </div>

    <main class="main-content">
        <div class="topbar">
            <div style="display: flex; align-items: center; gap: 12px;">
                <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="fa-solid fa-bars"></i></button>
                <div class="topbar-title">
                    <h1>Manage Users</h1>
                    <p>View accounts, manage roles, and control access</p>
                </div>
            </div>
            <div class="topbar-date">
                <i class="fa-regular fa-calendar"></i>
                <?php echo date('l, F j, Y'); ?>
            </div>
        </div>

        <div class="page-content">

            <!-- User Stats -->
            <div class="user-stats">
                <div class="mini-stat">
                    <div class="mini-stat-icon" style="background: #eff6ff; color: #2563eb;"><i class="fa-solid fa-users"></i></div>
                    <div>
                        <div class="mini-stat-value"><?php echo $totalUsers; ?></div>
                        <div class="mini-stat-label">Total Users</div>
                    </div>
                </div>
                <div class="mini-stat">
                    <div class="mini-stat-icon" style="background: #ecfdf5; color: #059669;"><i class="fa-solid fa-store"></i></div>
                    <div>
                        <div class="mini-stat-value"><?php echo $totalSellers; ?></div>
                        <div class="mini-stat-label">Sellers</div>
                    </div>
                </div>
                <div class="mini-stat">
                    <div class="mini-stat-icon" style="background: #fff7ed; color: #ea580c;"><i class="fa-solid fa-user-shield"></i></div>
                    <div>
                        <div class="mini-stat-value"><?php echo $totalAdmins; ?></div>
                        <div class="mini-stat-label">Admins</div>
                    </div>
                </div>
                <div class="mini-stat">
                    <div class="mini-stat-icon" style="background: #fef2f2; color: #ef4444;"><i class="fa-solid fa-user-slash"></i></div>
                    <div>
                        <div class="mini-stat-value"><?php echo $totalSuspended; ?></div>
                        <div class="mini-stat-label">Suspended</div>
                    </div>
                </div>
            </div>

            <!-- Users Table Card -->
            <div class="content-card">
                <div class="card-header-bar">
                    <h3><i class="fa-solid fa-users-gear" style="color: var(--primary); margin-right: 8px;"></i>All Users</h3>
                </div>

                <?php if (!empty($message)):
                    $parts = explode('|', $message);
                    $msgType = $parts[0];
                    $msgText = $parts[1];
                ?>
                    <div class="alert-bar <?php echo $msgType; ?>" style="margin-top: 0; margin-bottom: 0; border-radius: 0; border-left: none; border-right: none;">
                        <i class="fa-solid fa-<?php echo ($msgType === 'success') ? 'check-circle' : 'exclamation-circle'; ?>" style="margin-right: 6px;"></i>
                        <?php echo $msgText; ?>
                    </div>
                <?php endif; ?>

                <!-- Toolbar -->
                <div class="toolbar">
                    <div class="search-box">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" id="searchInput" placeholder="Search by name or email..." oninput="filterUsers()">
                    </div>
                    <div class="filter-pills">
                        <span class="filter-pill active" data-filter="all" onclick="setFilter('all', this)">All <span class="pill-count"><?php echo $totalUsers; ?></span></span>
                        <span class="filter-pill" data-filter="seller" onclick="setFilter('seller', this)">Sellers <span class="pill-count"><?php echo $totalSellers; ?></span></span>
                        <span class="filter-pill" data-filter="admin" onclick="setFilter('admin', this)">Admins <span class="pill-count"><?php echo $totalAdmins; ?></span></span>
                        <span class="filter-pill" data-filter="suspended" onclick="setFilter('suspended', this)">Suspended <span class="pill-count"><?php echo $totalSuspended; ?></span></span>
                    </div>
                </div>

                <div style="overflow-x: auto;">
                    <table class="data-table" id="usersTable">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Account</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u):
                                $colors = ['#ea580c','#2563eb','#059669','#9333ea','#e11d48','#0891b2'];
                                $colorIdx = $u['id'] % count($colors);
                                $fn = $u['firstname'] ?? '';
                                $ln = $u['lastname'] ?? '';
                                $initials = strtoupper(substr($fn,0,1) . substr($ln,0,1));
                                if (empty(trim($initials))) $initials = strtoupper(substr($u['email'],0,1));
                                $fullName = trim($fn . ' ' . $ln);
                                if (empty($fullName)) $fullName = $u['username'] ?? $u['email'];
                                $userStatus = $u['status'] ?? 'active';
                            ?>
                            <!-- Main Row -->
                            <tr class="user-row" 
                                data-type="<?php echo $u['usertype'] ?? 'user'; ?>" 
                                data-status="<?php echo $userStatus; ?>"
                                data-name="<?php echo strtolower($fullName); ?>"
                                data-email="<?php echo strtolower($u['email']); ?>">
                                <td>
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <div class="user-avatar" style="background: <?php echo ($userStatus === 'suspended') ? '#9ca3af' : $colors[$colorIdx]; ?>;">
                                            <?php echo $initials; ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 600; <?php if($userStatus === 'suspended') echo 'opacity:0.6;'; ?>">
                                                <?php echo htmlspecialchars($fullName); ?>
                                            </div>
                                            <div style="font-size: 11px; color: var(--text-light);">
                                                ID #<?php echo $u['id']; ?>
                                                <?php if($u['auth_provider'] === 'google'): ?>
                                                    &middot; <i class="fa-brands fa-google" style="color: #ea4335;"></i>
                                                <?php endif; ?>
                                                <?php if($u['is_2fa_enabled']): ?>
                                                    &middot; <i class="fa-solid fa-shield-halved" style="color: #10b981;" title="2FA Enabled"></i>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td style="color: var(--text-muted); font-size: 13px;"><?php echo htmlspecialchars($u['email']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo ($u['usertype'] === 'admin') ? 'admin' : (($u['usertype'] === 'seller') ? 'seller' : 'user'); ?>">
                                        <?php echo ucfirst($u['usertype'] ?? 'user'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-indicator <?php echo $userStatus; ?>">
                                        <span class="status-indicator-dot"></span>
                                        <?php echo ucfirst($userStatus); ?>
                                    </span>
                                </td>
                                <td style="color: var(--text-muted); font-size: 12px; white-space: nowrap;"><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                                <td>
                                    <?php if($u['id'] != $current_user_id): ?>
                                        <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                            <!-- View Details Toggle -->
                                            <button class="action-btn view" onclick="toggleDetail(<?php echo $u['id']; ?>)">
                                                <i class="fa-solid fa-eye"></i> View
                                            </button>

                                            <!-- Suspend / Reactivate -->
                                            <?php if($userStatus === 'active'): ?>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Suspend this user? They will not be able to log in.');">
                                                    <input type="hidden" name="target_user_id" value="<?php echo $u['id']; ?>">
                                                    <input type="hidden" name="action" value="suspend">
                                                    <button type="submit" class="action-btn reject"><i class="fa-solid fa-ban"></i> Suspend</button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Reactivate this user?');">
                                                    <input type="hidden" name="target_user_id" value="<?php echo $u['id']; ?>">
                                                    <input type="hidden" name="action" value="reactivate">
                                                    <button type="submit" class="action-btn approve"><i class="fa-solid fa-rotate-left"></i> Reactivate</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="font-size: 12px; color: var(--text-light); font-style: italic;">
                                            <i class="fa-solid fa-user-check" style="margin-right: 4px;"></i>It's you
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <!-- Expandable Detail Row -->
                            <tr class="user-detail-row" id="detail-<?php echo $u['id']; ?>">
                                <td colspan="6">
                                    <div class="detail-grid">
                                        <div>
                                            <div class="detail-item-label">Username</div>
                                            <div class="detail-item-value"><?php echo htmlspecialchars($u['username'] ?? '-'); ?></div>
                                        </div>
                                        <div>
                                            <div class="detail-item-label">Auth Provider</div>
                                            <div class="detail-item-value">
                                                <?php if($u['auth_provider'] === 'google'): ?>
                                                    <i class="fa-brands fa-google" style="color: #ea4335; margin-right: 4px;"></i>Google
                                                <?php else: ?>
                                                    <i class="fa-solid fa-envelope" style="margin-right: 4px;"></i>Email
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="detail-item-label">2FA Status</div>
                                            <div class="detail-item-value">
                                                <?php if($u['is_2fa_enabled']): ?>
                                                    <span style="color: #059669;"><i class="fa-solid fa-shield-halved"></i> Enabled</span>
                                                <?php else: ?>
                                                    <span style="color: var(--text-light);"><i class="fa-solid fa-shield"></i> Disabled</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="detail-item-label">Login Count</div>
                                            <div class="detail-item-value"><?php echo $u['login_count'] ?? 0; ?></div>
                                        </div>
                                        <div>
                                            <div class="detail-item-label">User Type</div>
                                            <div class="detail-item-value"><?php echo ucfirst($u['usertype'] ?? 'user'); ?></div>
                                        </div>
                                        <div>
                                            <div class="detail-item-label">Registered</div>
                                            <div class="detail-item-value"><?php echo date('M d, Y \a\t g:i A', strtotime($u['created_at'])); ?></div>
                                        </div>
                                    </div>

                                    <?php if($u['id'] != $current_user_id): ?>
                                    <div style="display: flex; gap: 8px; margin-top: 16px; padding-top: 14px; border-top: 1px solid var(--border); align-items: center;">
                                        <!-- Role Actions -->
                                        <?php if($u['usertype'] == 'admin'): ?>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Demote this admin to regular user?');">
                                                <input type="hidden" name="target_user_id" value="<?php echo $u['id']; ?>">
                                                <input type="hidden" name="action" value="make_user">
                                                <button type="submit" class="action-btn promote"><i class="fa-solid fa-arrow-down"></i> Demote to User</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Promote this user to Admin? They will have full system access.');">
                                                <input type="hidden" name="target_user_id" value="<?php echo $u['id']; ?>">
                                                <input type="hidden" name="action" value="make_admin">
                                                <button type="submit" class="action-btn promote"><i class="fa-solid fa-arrow-up"></i> Promote to Admin</button>
                                            </form>
                                        <?php endif; ?>

                                        <!-- Danger Zone: Delete -->
                                        <div style="margin-left: auto;">
                                            <button class="action-btn delete" onclick="openDeleteModal(<?php echo $u['id']; ?>, '<?php echo htmlspecialchars(addslashes($fullName)); ?>')">
                                                <i class="fa-solid fa-trash"></i> Delete Permanently
                                            </button>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script>
        let currentFilter = 'all';

        function filterUsers() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            document.querySelectorAll('.user-row').forEach(row => {
                const name = row.dataset.name;
                const email = row.dataset.email;
                const type = row.dataset.type;
                const status = row.dataset.status;

                let matchSearch = name.includes(search) || email.includes(search);
                let matchFilter = currentFilter === 'all' 
                    || (currentFilter === 'suspended' && status === 'suspended')
                    || (currentFilter !== 'suspended' && type === currentFilter);

                row.style.display = (matchSearch && matchFilter) ? '' : 'none';

                // Also hide detail row
                const detailRow = document.getElementById('detail-' + row.querySelector('[name="target_user_id"]')?.value);
                if (detailRow && row.style.display === 'none') {
                    detailRow.classList.remove('show');
                }
            });
        }

        function setFilter(filter, el) {
            currentFilter = filter;
            document.querySelectorAll('.filter-pill').forEach(p => p.classList.remove('active'));
            el.classList.add('active');
            filterUsers();
        }

        function toggleDetail(id) {
            const row = document.getElementById('detail-' + id);
            if (row) row.classList.toggle('show');
        }

        function openDeleteModal(userId, userName) {
            document.getElementById('deleteUserId').value = userId;
            document.getElementById('deleteConfirmInput').value = '';
            document.getElementById('deleteSubmitBtn').disabled = true;
            document.getElementById('deleteModal').classList.add('show');
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('show');
        }

        // Enable delete button only when "DELETE" is typed
        document.getElementById('deleteConfirmInput').addEventListener('input', function() {
            document.getElementById('deleteSubmitBtn').disabled = (this.value.toUpperCase() !== 'DELETE');
        });

        // Close modal on overlay click
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) closeDeleteModal();
        });
    </script>
</body>
</html>
