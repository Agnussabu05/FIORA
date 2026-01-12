<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
$page = 'study';

// Handle Add Session
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $subject = $_POST['subject'];
    $duration = $_POST['duration']; // in minutes
    $date = $_POST['date'];
    $notes = $_POST['notes'];
    
    $stmt = $pdo->prepare("INSERT INTO study_sessions (user_id, subject, duration_minutes, session_date, notes) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $subject, $duration, $date, $notes]);
    header("Location: study.php");
    exit;
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM study_sessions WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: study.php");
    exit;
}

// Fetch Sessions
$stmt = $pdo->prepare("SELECT * FROM study_sessions WHERE user_id = ? ORDER BY session_date ASC");
$stmt->execute([$user_id]);
$sessions = $stmt->fetchAll();

// Study Collective Logic
$user_stmt = $pdo->prepare("SELECT interested_study FROM users WHERE id = ?");
$user_stmt->execute([$user_id]);
$user_info = $user_stmt->fetch();
$interested_study = $user_info['interested_study'] ?? 0;

// Handle Study Interest Toggle
if (isset($_POST['toggle_interest'])) {
    $new_status = $interested_study ? 0 : 1;
    $pdo->prepare("UPDATE users SET interested_study = ? WHERE id = ?")->execute([$new_status, $user_id]);
    
    $_SESSION['study_msg'] = $new_status 
        ? "Welcome to Group Study! 🌍 You're now visible to other students." 
        : "Visibility turned off. 🛡️ Focusing solo.";
        
    header("Location: study.php");
    exit;
}

// Handle Send Request (Multi-Step Wizard flow)
if (isset($_POST['send_request'])) {
    $receiver_ids = isset($_POST['receiver_ids']) ? $_POST['receiver_ids'] : [];
    $group_name = $_POST['group_name'] ?: "New Group Study";
    $subject_name = $_POST['subject_name'] ?: "General Study";
    
    if (empty($receiver_ids)) {
        $_SESSION['study_msg'] = "Please select at least one student to invite! ⚠️";
    } else {
        // 1. Create the Group
        $stmt = $pdo->prepare("INSERT INTO study_groups (name, subject, status) VALUES (?, ?, 'forming')");
        $stmt->execute([$group_name, $subject_name]);
        $group_id = $pdo->lastInsertId();
        
        // 2. Add leader
        $pdo->prepare("INSERT INTO study_group_members (group_id, user_id, role) VALUES (?, ?, 'leader')")->execute([$group_id, $user_id]);
        
        // 3. Create requests
        $stmt = $pdo->prepare("INSERT INTO study_requests (sender_id, receiver_id, group_name, subject_name, group_id) VALUES (?, ?, ?, ?, ?)");
        foreach ($receiver_ids as $receiver_id) {
            $stmt->execute([$user_id, $receiver_id, $group_name, $subject_name, $group_id]);
        }
        $_SESSION['study_msg'] = "Invites sent! 🚀 Your squad is forming.";
    }
    header("Location: study.php");
    exit;
}

// Handle Accept/Reject Request
if (isset($_POST['update_request'])) {
    $request_id = $_POST['request_id'];
    $new_status = $_POST['update_request'];
    
    $pdo->prepare("UPDATE study_requests SET status = ? WHERE id = ?")->execute([$new_status, $request_id]);
    
    if ($new_status === 'accepted') {
        $req_stmt = $pdo->prepare("SELECT group_id, receiver_id FROM study_requests WHERE id = ?");
        $req_stmt->execute([$request_id]);
        $req = $req_stmt->fetch();
        
        if ($req['group_id']) {
            $pdo->prepare("INSERT INTO study_group_members (group_id, user_id, role) VALUES (?, ?, 'member')")->execute([$req['group_id'], $req['receiver_id']]);
            $_SESSION['study_msg'] = "You've joined the group! 🤝";
        }
    }
    header("Location: study.php");
    exit;
}

// Handle Leader Submission
if (isset($_POST['submit_for_verification'])) {
    $group_id = $_POST['group_id'];
    // Check for members (leader + at least one other)
    $stmt = $pdo->prepare("SELECT id FROM study_group_members WHERE group_id = ? AND user_id = ? AND role = 'leader'");
    $stmt->execute([$group_id, $user_id]);
    
    // Count members
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM study_group_members WHERE group_id = ?");
    $count_stmt->execute([$group_id]);
    $member_count = $count_stmt->fetchColumn();
    
    if ($stmt->fetch() && $member_count > 1) {
        $pdo->prepare("UPDATE study_groups SET status = 'pending_verification' WHERE id = ?")->execute([$group_id]);
        $pdo->prepare("DELETE FROM study_requests WHERE group_id = ? AND status = 'pending'")->execute([$group_id]);
        $_SESSION['study_msg'] = "Submitted for Admin Approval! ⏳";
    }
    header("Location: study.php");
    exit;
}

// Handle Cancel Request
if (isset($_POST['cancel_request'])) {
    $request_id = $_POST['request_id'];
    $pdo->prepare("DELETE FROM study_requests WHERE id = ? AND sender_id = ?")->execute([$request_id, $user_id]);
    $_SESSION['study_msg'] = "Invitation withdrawn.";
    header("Location: study.php");
    exit;
}

// Data Fetching
$partners_stmt = $pdo->prepare("SELECT id, username FROM users WHERE interested_study = 1 AND id != ?");
$partners_stmt->execute([$user_id]);
$potential_partners = $partners_stmt->fetchAll();

$sent_requests = [];
$sent_req_stmt = $pdo->prepare("SELECT sr.*, u.username as receiver_name FROM study_requests sr JOIN users u ON sr.receiver_id = u.id WHERE sr.sender_id = ? AND sr.status != 'accepted' ORDER BY sr.created_at DESC");
$sent_req_stmt->execute([$user_id]);
foreach ($sent_req_stmt->fetchAll() as $req) {
    $key = ($req['group_name'] ?: 'Study Group') . '||' . ($req['subject_name'] ?: 'General');
    if (!isset($sent_requests[$key])) {
        $sent_requests[$key] = ['group_name' => $req['group_name'], 'subject_name' => $req['subject_name'], 'recipients' => []];
    }
    $sent_requests[$key]['recipients'][] = $req;
}

$received_req_stmt = $pdo->prepare("SELECT sr.*, u.username as sender_name FROM study_requests sr JOIN users u ON sr.sender_id = u.id WHERE sr.receiver_id = ? AND sr.status = 'pending'");
$received_req_stmt->execute([$user_id]);
$received_requests = $received_req_stmt->fetchAll();

$groups_stmt = $pdo->prepare("SELECT sg.*, sgm_my.role as my_role, (SELECT GROUP_CONCAT(CONCAT(u.username, ',', sgm.role) SEPARATOR '|') FROM study_group_members sgm JOIN users u ON sgm.user_id = u.id WHERE sgm.group_id = sg.id) as members_list FROM study_groups sg JOIN study_group_members sgm_my ON sg.id = sgm_my.group_id WHERE sgm_my.user_id = ?");
$groups_stmt->execute([$user_id]);
$my_groups = $groups_stmt->fetchAll();

$active_groups_stmt = $pdo->prepare("SELECT sg.*, (SELECT GROUP_CONCAT(u.username SEPARATOR ', ') FROM study_group_members sgm JOIN users u ON sgm.user_id = u.id WHERE sgm.group_id = sg.id) as members_list FROM study_groups sg WHERE sg.status = 'active' ORDER BY sg.created_at DESC");
$active_groups_stmt->execute();
$all_active_groups = $active_groups_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiora Study</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        :root {
            --glass-strong: rgba(255, 255, 255, 0.85);
            --glass-subtle: rgba(255, 255, 255, 0.4);
            --accent-study: #6B8C73;
            --accent-glow: 0 8px 32px rgba(107, 140, 115, 0.15);
        }
        
        body { font-family: 'Outfit', sans-serif; }
        
        .study-grid {
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 24px;
            padding-bottom: 40px;
        }
        
        .section-title {
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #666;
            margin-bottom: 16px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .card-premium {
            background: var(--glass-strong);
            border: 1px solid rgba(255,255,255,0.6);
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.03);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        /* Session List */
        .session-row {
            display: flex;
            align-items: center;
            padding: 16px;
            border-radius: 16px;
            background: white;
            margin-bottom: 12px;
            transition: transform 0.2s;
            border: 1px solid rgba(0,0,0,0.04);
        }
        .session-row:hover { transform: translateX(4px); box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .session-icon {
            width: 48px; height: 48px;
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            color: #15803d;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.2rem;
            margin-right: 16px;
        }
        
        /* Wizard Styling */
        .wizard-container {
            background: white;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 10px 40px -10px rgba(0,0,0,0.1);
        }
        .wizard-header {
            background: #f8fafc;
            padding: 20px 30px;
            border-bottom: 1px solid #e2e8f0;
            display: flex; justify-content: space-between; align-items: center;
        }
        .step-dots { display: flex; gap: 8px; }
        .dot { width: 10px; height: 10px; border-radius: 50%; background: #cbd5e1; transition: all 0.3s; }
        .dot.active { background: var(--accent-study); transform: scale(1.2); }
        .dot.done { background: #bbf7d0; }
        
        .wizard-body { padding: 30px; }
        .step-page { display: none; animation: slideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
        .step-page.active { display: block; }
        
        /* User Selection Grid */
        .user-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 12px;
        }
        .user-select-card {
            position: relative;
            cursor: pointer;
        }
        .user-select-card input { position: absolute; opacity: 0; }
        .user-card-inner {
            border: 2px solid #f1f5f9;
            border-radius: 16px;
            padding: 15px;
            text-align: center;
            transition: all 0.2s;
            background: #fff;
        }
        .user-select-card input:checked + .user-card-inner {
            border-color: var(--accent-study);
            background: #f0fdf4;
            box-shadow: 0 4px 12px rgba(107, 140, 115, 0.2);
        }
        .avatar-lg {
            width: 48px; height: 48px;
            background: #e2e8f0;
            border-radius: 50%;
            margin: 0 auto 10px;
            display: flex; align-items: center; justify-content: center;
            font-weight: 700; color: #64748b;
        }
        
        @keyframes slideUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: 0; } }

        /* Timer Block */
        .timer-block {
            text-align: center;
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
            color: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px -10px rgba(15, 23, 42, 0.3);
            position: sticky; top: 20px;
        }
        .time-digits {
            font-family: 'JetBrains Mono', monospace;
            font-size: 4rem;
            font-weight: 700;
            letter-spacing: -2px;
            margin: 10px 0;
            line-height: 1;
        }
        
        /* Status Badges */
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .badge-active { background: #dcfce7; color: #166534; }
        .badge-forming { background: #e0e7ff; color: #3730a3; }
        .badge-pending { background: #ffedd5; color: #9a3412; }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 30px;">
                <div>
                    <h1 style="font-size: 2.2rem; font-weight: 600; color: #1e293b;">Study Space</h1>
                    <p style="color: #64748b; font-size: 1rem;">Collaborate, focus, and track your progress.</p>
                </div>
                <button onclick="document.getElementById('addSessionModal').classList.add('active')" 
                        class="btn btn-primary" style="padding: 12px 24px; border-radius: 12px; font-weight: 600;">
                    + Plan Session
                </button>
            </div>

            <div class="study-grid">
                <!-- Left Column -->
                <div style="display: flex; flex-direction: column; gap: 30px;">
                    
                    <!-- Group Study Collective -->
                    <div class="card-premium">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <h2 style="font-size: 1.2rem; margin: 0; display: flex; align-items: center; gap: 10px;">
                                <span style="font-size: 1.4rem;">🤝</span> Study Groups
                            </h2>
                            <form method="POST">
                                <button type="submit" name="toggle_interest" 
                                        style="background: <?php echo $interested_study ? '#dcfce7' : '#f1f5f9'; ?>; 
                                               color: <?php echo $interested_study ? '#166534' : '#64748b'; ?>; 
                                               border: none; padding: 8px 16px; border-radius: 20px; font-weight: 600; cursor: pointer; transition: all 0.2s;">
                                    <?php echo $interested_study ? '● Visible' : '○ Hidden'; ?>
                                </button>
                            </form>
                        </div>

                        <?php if ($interested_study): ?>
                            
                            <!-- Invites -->
                            <?php if (!empty($received_requests)): ?>
                                <div style="margin-bottom: 24px; background: #fffbeb; border: 1px solid #fcd34d; padding: 16px; border-radius: 12px;">
                                    <div class="section-title" style="color: #92400e; margin-bottom: 12px;">Pending Invites</div>
                                    <?php foreach($received_requests as $req): ?>
                                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 0;">
                                            <div>
                                                <div style="font-weight: 700; color: #92400e;"><?php echo htmlspecialchars($req['group_name']); ?></div>
                                                <div style="font-size: 0.85rem; color: #b45309;">From: <?php echo htmlspecialchars($req['sender_name']); ?></div>
                                            </div>
                                            <form method="POST" style="display: flex; gap: 8px;">
                                                <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                                <button name="update_request" value="accepted" class="btn" style="padding: 6px 14px; background: #166534; color: white;">Accept</button>
                                                <button name="update_request" value="rejected" class="btn" style="padding: 6px 14px; background: rgba(0,0,0,0.1);">Ignore</button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Create Group Wizard -->
                            <div class="wizard-container">
                                <form method="POST" id="wizardForm">
                                    <div class="wizard-header">
                                        <span style="font-weight: 700; color: #334155;">Start a Group</span>
                                        <div class="step-dots">
                                            <div class="dot active"></div><div class="dot"></div><div class="dot"></div>
                                        </div>
                                    </div>
                                    <div class="wizard-body">
                                        <!-- Step 1 -->
                                        <div class="step-page active" id="step1">
                                            <div style="margin-bottom: 20px; font-weight: 600; color: #475569;">Who are you studying with?</div>
                                            <?php if ($potential_partners): ?>
                                            <div class="user-grid">
                                                <?php foreach($potential_partners as $p): ?>
                                                <label class="user-select-card">
                                                    <input type="checkbox" name="receiver_ids[]" value="<?php echo $p['id']; ?>" data-name="<?php echo htmlspecialchars($p['username']); ?>">
                                                    <div class="user-card-inner">
                                                        <div class="avatar-lg"><?php echo strtoupper(substr($p['username'], 0, 1)); ?></div>
                                                        <div style="font-size: 0.9rem; font-weight: 600; color: #334155;"><?php echo htmlspecialchars($p['username']); ?></div>
                                                    </div>
                                                </label>
                                                <?php endforeach; ?>
                                            </div>
                                            <div style="text-align: right; margin-top: 24px;">
                                                <button type="button" onclick="nextStep(2)" class="btn btn-primary">Next: Details →</button>
                                            </div>
                                            <?php else: ?>
                                                <div style="text-align: center; color: #94a3b8; padding: 20px;">
                                                    Everyones hiding! 🙈<br>No other students are currently 'Visible'.
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Step 2 -->
                                        <div class="step-page" id="step2">
                                            <div class="form-group">
                                                <label style="display: block; font-size: 0.8rem; font-weight: 700; color: #64748b; margin-bottom: 8px;">GROUP NAME</label>
                                                <input type="text" name="group_name" id="gName" class="form-input" placeholder="e.g. Midnight Coders" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #cbd5e1;">
                                            </div>
                                            <div class="form-group" style="margin-top: 15px;">
                                                <label style="display: block; font-size: 0.8rem; font-weight: 700; color: #64748b; margin-bottom: 8px;">SUBJECT / TOPIC</label>
                                                <input type="text" name="subject_name" id="gSubject" class="form-input" placeholder="e.g. Advanced Calculus" style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #cbd5e1;">
                                            </div>
                                            <div style="display: flex; justify-content: space-between; margin-top: 24px;">
                                                <button type="button" onclick="nextStep(1)" class="btn btn-secondary">Back</button>
                                                <button type="button" onclick="nextStep(3)" class="btn btn-primary">Next: Review →</button>
                                            </div>
                                        </div>

                                        <!-- Step 3 -->
                                        <div class="step-page" id="step3">
                                            <div style="background: #f8fafc; border-radius: 12px; padding: 20px; border: 1px dashed #cbd5e1; text-align: center;">
                                                <div style="font-size: 1.5rem; margin-bottom: 5px;">📨</div>
                                                <h3 id="revName" style="margin: 0; color: #334155;">-</h3>
                                                <p id="revSub" style="color: #64748b; margin: 4px 0 15px;">-</p>
                                                <div id="revCount" style="font-size: 0.9rem; font-weight: 600; color: var(--accent-study);"></div>
                                            </div>
                                            <div style="display: flex; justify-content: space-between; margin-top: 24px;">
                                                <button type="button" onclick="nextStep(2)" class="btn btn-secondary">Back</button>
                                                <button type="submit" name="send_request" class="btn btn-primary" style="background: #10b981;">Launch Group 🚀</button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        <?php else: ?>
                            <div style="text-align: center; padding: 40px; color: #94a3b8; border: 2px dashed #e2e8f0; border-radius: 20px;">
                                <div style="font-size: 2rem; margin-bottom: 10px;">👻</div>
                                Toggle visibility to join the community!
                            </div>
                        <?php endif; ?>

                        <!-- Active Groups List -->
                        <div style="margin-top: 40px;">
                            <div class="section-title">Your Squads</div>
                            <?php if ($my_groups): foreach($my_groups as $g): ?>
                                <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; margin-bottom: 12px;">
                                    <div style="display: flex; justify-content: space-between; align-items: start;">
                                        <div>
                                            <div style="font-weight: 700; color: #1e293b; font-size: 1.1rem;"><?php echo htmlspecialchars($g['name']); ?></div>
                                            <div style="color: #64748b; font-size: 0.9rem;"><?php echo htmlspecialchars($g['subject']); ?></div>
                                            <div style="margin-top: 8px;">
                                                <?php 
                                                $mems = explode('|', $g['members_list']);
                                                foreach($mems as $m): $parts = explode(',', $m); 
                                                ?>
                                                <span style="display: inline-block; background: #f1f5f9; padding: 2px 8px; border-radius: 12px; font-size: 0.75rem; margin-right: 4px; color: #475569;">
                                                    <?php echo htmlspecialchars($parts[0]); ?> 
                                                    <?php if(strpos($parts[1], 'leader')!==false) echo '👑'; ?>
                                                </span>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <div>
                                            <?php if($g['status'] == 'active'): ?>
                                                <span class="badge badge-active">Active</span>
                                            <?php elseif($g['status'] == 'forming'): ?>
                                                <span class="badge badge-forming">Forming</span>
                                                <?php if($g['my_role']=='leader'): ?>
                                                    <?php 
                                                        $member_count = count(explode('|', $g['members_list']));
                                                        if ($member_count > 1): 
                                                    ?>
                                                        <form method="POST" style="margin-top: 8px;">
                                                            <input type="hidden" name="group_id" value="<?php echo $g['id']; ?>">
                                                            <button name="submit_for_verification" class="btn" style="font-size: 0.7rem; padding: 4px 8px; background: #334155; color: white;">Request Approval</button>
                                                        </form>
                                                    <?php else: ?>
                                                        <div style="font-size: 0.65rem; color: #94a3b8; font-style: italic; margin-top: 5px;">
                                                            Min. 1 accepted member needed to request approval.
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge badge-pending">Pending Admin</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>

                </div>

                <!-- Right Column (Timer & Upcoming) -->
                <div>
                    <!-- Timer -->
                    <div class="timer-block">
                        <div style="font-size: 0.9rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; opacity: 0.7;">Focus Mode</div>
                        <div class="time-digits" id="timer">25:00</div>
                        <div style="display: flex; gap: 10px; justify-content: center; margin-top: 20px;">
                            <button onclick="toggleTimer()" id="mainBtn" class="btn" style="background: white; color: #0f172a; font-weight: 700; padding: 12px 24px;">START</button>
                            <button onclick="resetTimer()" class="btn" style="background: rgba(255,255,255,0.1); color: white;">↺</button>
                        </div>
                    </div>

                    <!-- Upcoming Sessions -->
                    <div style="margin-top: 30px;">
                        <div class="section-title">Planned Sessions</div>
                        <?php if ($sessions): foreach($sessions as $s): ?>
                            <div class="session-row">
                                <div class="session-icon">📅</div>
                                <div>
                                    <div style="font-weight: 700; color: #1e293b;"><?php echo htmlspecialchars($s['subject']); ?></div>
                                    <div style="font-size: 0.8rem; color: #64748b;">
                                        <?php echo date('M j, H:i', strtotime($s['session_date'])); ?> • <?php echo $s['duration_minutes']; ?>m
                                    </div>
                                </div>
                                <a href="?delete=<?php echo $s['id']; ?>" style="margin-left: auto; color: #cbd5e1; text-decoration: none;">✕</a>
                            </div>
                        <?php endforeach; else: ?>
                            <div style="color: #94a3b8; font-style: italic; text-align: center; margin-top: 20px;">No solo sessions planned.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Simple JS for Wizard & Timer -->
    <script>
        // Wizard
        function nextStep(n) {
            // Validation
            if (n === 2) {
                if (document.querySelectorAll('input[name="receiver_ids[]"]:checked').length === 0) {
                    alert("Please pick at least one partner."); return;
                }
            }
            if (n === 3) {
                document.getElementById('revName').innerText = document.getElementById('gName').value || 'Untitled Group';
                document.getElementById('revSub').innerText = document.getElementById('gSubject').value || 'General Study';
                let count = document.querySelectorAll('input[name="receiver_ids[]"]:checked').length;
                document.getElementById('revCount').innerText = `${count} Invites Ready`;
            }

            document.querySelectorAll('.step-page').forEach(el => el.classList.remove('active'));
            document.getElementById('step' + n).classList.add('active');
            
            document.querySelectorAll('.dot').forEach((d, i) => {
                d.classList.toggle('active', i === n-1);
                d.classList.toggle('done', i < n-1);
            });
        }

        // Timer
        let time = 1500;
        let active = false;
        let interval;
        const display = document.getElementById('timer');
        const btn = document.getElementById('mainBtn');

        function update() {
            let m = Math.floor(time / 60).toString().padStart(2, '0');
            let s = (time % 60).toString().padStart(2, '0');
            display.innerText = `${m}:${s}`;
        }

        function toggleTimer() {
            if (active) {
                clearInterval(interval);
                active = false;
                btn.innerText = "RESUME";
            } else {
                active = true;
                btn.innerText = "PAUSE";
                interval = setInterval(() => {
                    if (time > 0) {
                        time--;
                        update();
                    } else {
                        clearInterval(interval);
                        alert("Focus complete!");
                    }
                }, 1000);
            }
        }

        function resetTimer() {
            clearInterval(interval);
            active = false;
            time = 1500;
            update();
            btn.innerText = "START";
        }
    </script>

    <!-- Modal for Add Session -->
    <div id="addSessionModal" style="position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 1000;" 
         onclick="if(event.target === this) this.classList.remove('active')">
        <div class="card-premium" style="width: 400px; background: white;">
            <h3 style="margin-bottom: 20px;">New Session</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <input type="text" name="subject" class="form-input" placeholder="Subject" required style="width: 100%; margin-bottom: 12px;">
                <input type="number" name="duration" class="form-input" placeholder="Minutes" value="60" required style="width: 100%; margin-bottom: 12px;">
                <input type="datetime-local" name="date" class="form-input" required style="width: 100%; margin-bottom: 12px;">
                <textarea name="notes" class="form-input" placeholder="Goals..." style="width: 100%; margin-bottom: 20px;"></textarea>
                <button class="btn btn-primary" style="width: 100%;">Schedule</button>
            </form>
        </div>
    </div>
</body>
</html>
