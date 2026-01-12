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

// Total Study Hours Calculation
$totalMins = 0;
foreach($sessions as $s) $totalMins += $s['duration_minutes'];
$totalHours = floor($totalMins / 60);
$remainingMins = $totalMins % 60;

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
        ? "Welcome to Group Study! 🌍 You're now visible to other students. Time to find your tribe!" 
        : "Visibility turned off. 🛡️ You're now focusing solo for a while.";
        
    header("Location: study.php");
    exit;
}

// Handle Send Request (Multi-Step Wizard flow)
if (isset($_POST['send_request'])) {
    $receiver_ids = isset($_POST['receiver_ids']) ? $_POST['receiver_ids'] : (isset($_POST['receiver_id']) ? [$_POST['receiver_id']] : []);
    $group_name = $_POST['group_name'] ?: "New Group Study";
    $subject_name = $_POST['subject_name'] ?: "General Study";
    
    if (empty($receiver_ids)) {
        $_SESSION['study_msg'] = "Please select at least one student to invite! ⚠️";
    } else {
        // 1. Create the Group in 'forming' state first
        $stmt = $pdo->prepare("INSERT INTO study_groups (name, subject, status) VALUES (?, ?, 'forming')");
        $stmt->execute([$group_name, $subject_name]);
        $group_id = $pdo->lastInsertId();
        
        // 2. Add current user as leader
        $pdo->prepare("INSERT INTO study_group_members (group_id, user_id, role) VALUES (?, ?, 'leader')")->execute([$group_id, $user_id]);
        
        // 3. Create requests linked to this group
        $stmt = $pdo->prepare("INSERT INTO study_requests (sender_id, receiver_id, group_name, subject_name, group_id) VALUES (?, ?, ?, ?, ?)");
        foreach ($receiver_ids as $receiver_id) {
            $stmt->execute([$user_id, $receiver_id, $group_name, $subject_name, $group_id]);
        }
        $_SESSION['study_msg'] = "Great job! 🌟 Your team is forming. Once your partners accept, you can submit the group to admin!";
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
            // Add member to the EXISTING group
            $pdo->prepare("INSERT INTO study_group_members (group_id, user_id, role) VALUES (?, ?, 'member')")->execute([$req['group_id'], $req['receiver_id']]);
            $_SESSION['study_msg'] = "Success! 🤝 You've joined the group. Note: The leader will submit the final roster for admin verification soon.";
        }
    }
    
    header("Location: study.php");
    exit;
}

// Handle Leader Submission to Admin
if (isset($_POST['submit_for_verification'])) {
    $group_id = $_POST['group_id'];
    
    // Check if user is leader
    $stmt = $pdo->prepare("SELECT id FROM study_group_members WHERE group_id = ? AND user_id = ? AND role = 'leader'");
    $stmt->execute([$group_id, $user_id]);
    
    if ($stmt->fetch()) {
        $pdo->prepare("UPDATE study_groups SET status = 'pending_verification' WHERE id = ?")->execute([$group_id]);
        
        // Auto-cancel remaining pending requests for this group
        $pdo->prepare("DELETE FROM study_requests WHERE group_id = ? AND status = 'pending'")->execute([$group_id]);
        
        $_SESSION['study_msg'] = "Sent! 🚀 Your group request is now with the admin for final approval. Outstanding invites have been withdrawn.";
    }
    
    header("Location: study.php");
    exit;
}

// Handle Cancel/Remove Sent Request
if (isset($_POST['cancel_request'])) {
    $request_id = $_POST['request_id'];
    $pdo->prepare("DELETE FROM study_requests WHERE id = ? AND sender_id = ?")->execute([$request_id, $user_id]);
    $_SESSION['study_msg'] = "Request withdrawn. 🧊 No worries, you can always reach out to new partners when you're ready.";
    header("Location: study.php");
    exit;
}

// Fetch Potential Partners (Users interested in study, excluding self)
$partners_stmt = $pdo->prepare("SELECT id, username FROM users WHERE interested_study = 1 AND id != ?");
$partners_stmt->execute([$user_id]);
$potential_partners = $partners_stmt->fetchAll();

// Fetch Sent Requests (All except accepted since they become groups)
$sent_req_stmt = $pdo->prepare("SELECT sr.*, u.username as receiver_name FROM study_requests sr JOIN users u ON sr.receiver_id = u.id WHERE sr.sender_id = ? AND sr.status != 'accepted' ORDER BY sr.created_at DESC");
$sent_req_stmt->execute([$user_id]);
$sent_requests_raw = $sent_req_stmt->fetchAll();

$sent_requests = [];
foreach ($sent_requests_raw as $req) {
    $gn = $req['group_name'] ?: 'Study Group';
    $sn = $req['subject_name'] ?: 'General';
    $key = $gn . '||' . $sn;
    
    if (!isset($sent_requests[$key])) {
        $sent_requests[$key] = [
            'group_name' => $gn,
            'subject_name' => $sn,
            'recipients' => []
        ];
    }
    $sent_requests[$key]['recipients'][] = $req;
}

// Fetch Received Requests
$received_req_stmt = $pdo->prepare("SELECT sr.*, u.username as sender_name FROM study_requests sr JOIN users u ON sr.sender_id = u.id WHERE sr.receiver_id = ? AND sr.status = 'pending'");
$received_req_stmt->execute([$user_id]);
$received_requests = $received_req_stmt->fetchAll();

// Fetch My Study Groups with Member List
$groups_stmt = $pdo->prepare("
    SELECT sg.*, sgm_my.role as my_role,
    (SELECT GROUP_CONCAT(CONCAT(u.username, CASE WHEN u.id = ? THEN ' (You)' ELSE '' END, ' (', sgm.role, ')') SEPARATOR ', ') 
     FROM study_group_members sgm 
     JOIN users u ON sgm.user_id = u.id 
     WHERE sgm.group_id = sg.id) as members_list
    FROM study_groups sg 
    JOIN study_group_members sgm_my ON sg.id = sgm_my.group_id 
    WHERE sgm_my.user_id = ?
");
$groups_stmt->execute([$user_id, $user_id]);
$my_groups = $groups_stmt->fetchAll();

// Fetch Global Active Groups (Community)
$active_groups_stmt = $pdo->prepare("
    SELECT sg.*, 
    (SELECT GROUP_CONCAT(CONCAT(u.username, CASE WHEN u.id = ? THEN ' (You)' ELSE '' END, ' (', sgm.role, ')') SEPARATOR ', ') 
     FROM study_group_members sgm 
     JOIN users u ON sgm.user_id = u.id 
     WHERE sgm.group_id = sg.id) as members_list
    FROM study_groups sg 
    WHERE sg.status = 'active'
    ORDER BY sg.created_at DESC
");
$active_groups_stmt->execute([$user_id]);
$all_active_groups = $active_groups_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiora - Study Planner</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=JetBrains+Mono:wght@700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .study-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }
        .timer-display {
            font-family: 'JetBrains Mono', monospace;
            font-size: 5rem;
            font-weight: 700;
            margin: 20px 0;
            color: var(--text-main);
            text-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .timer-controls {
            display: flex; gap: 12px; justify-content: center;
            margin-bottom: 20px;
        }
        .session-item {
            background: rgba(255,255,255,0.4);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            padding: 18px; border-radius: 18px;
            margin-bottom: 15px;
            display: flex; justify-content: space-between; align-items: center;
            transition: transform 0.2s;
        }
        .session-item:hover { transform: scale(1.01); background: rgba(255,255,255,0.6); }
        
        .mode-btn {
            font-size: 0.85rem; padding: 10px 18px; border-radius: 12px;
            background: rgba(0,0,0,0.05); color: #222222 !important;
            border: 1px solid rgba(0,0,0,0.1); cursor: pointer;
            font-weight: 700; transition: all 0.3s;
        }
        .mode-btn:hover { background: rgba(0,0,0,0.1); }
        .mode-btn.active { background: #222222; color: #ffffff !important; border-color: #222222; }
        
        .timer-card {
            text-align: center; position: sticky; top: 20px;
            background: var(--glass-bg); padding: 35px; border-radius: 25px;
            border: 2px solid var(--glass-border);
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        }

        /* Visibility Fixes for Control Buttons */
        #startBtn { background: #222222 !important; color: #ffffff !important; }
        #pauseBtn { background: #D9A066 !important; color: #ffffff !important; border: none; }
        .btn-restart { background: #9E9080 !important; color: #ffffff !important; border: none; }
        
        /* Visibility Improvements */
        h1, h3 { color: #222222 !important; font-weight: 800; }
        .text-high-contrast { color: #000000 !important; font-weight: 700; }
        .muted-better { color: #333333 !important; font-weight: 600; opacity: 0.8; }

        /* Multi-step Form Styles */
        .step-container { display: none; transition: all 0.3s ease; }
        .step-container.active { display: block; animation: fadeIn 0.4s ease-out; }
        .step-indicator {
            display: flex; justify-content: space-between; margin-bottom: 30px;
            padding: 0 10%; position: relative;
        }
        .step-indicator::before {
            content: ""; position: absolute; top: 15px; left: 15%; right: 15%;
            height: 2px; background: rgba(0,0,0,0.05); z-index: 1;
        }
        .step-dot {
            width: 32px; height: 32px; border-radius: 50%; background: white;
            border: 2px solid rgba(0,0,0,0.1); display: flex; align-items: center;
            justify-content: center; font-size: 0.8rem; font-weight: 800;
            z-index: 2; position: relative; transition: all 0.3s;
        }
        .step-dot.active { background: var(--primary); color: white; border-color: var(--primary); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .step-dot.completed { background: #059669; color: white; border-color: #059669; }

        .personnel-list {
            display: none;
            opacity: 0;
            transform: translateY(-10px);
            transition: all 0.3s ease;
        }
        .personnel-list.visible {
            display: flex !important;
            opacity: 1;
            transform: translateY(0);
        }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        .partner-card:has(input:checked) {
            border-color: var(--primary) !important;
            background: rgba(0, 0, 0, 0.05) !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <header class="header">
                <div class="welcome-text">
                    <h1>Study Planner 📚</h1>
                    <p class="muted-better">Focus, learn, and master your crafts.</p>
                </div>
                <button class="btn btn-primary" onclick="openModal()">+ Schedule Session</button>
            </header>

            <?php if (isset($_SESSION['study_msg'])): ?>
                <div style="background: rgba(16, 185, 129, 0.1); color: #065f46; padding: 15px 20px; border-radius: 15px; border: 1px solid rgba(16, 185, 129, 0.2); margin-bottom: 25px; display: flex; align-items: center; justify-content: space-between; animation: fadeInUp 0.5s ease-out;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <span style="font-size: 1.2rem;">✨</span>
                        <span style="font-weight: 600;"><?php echo $_SESSION['study_msg']; unset($_SESSION['study_msg']); ?></span>
                    </div>
                    <button onclick="this.parentElement.remove()" style="background: none; border: none; color: inherit; cursor: pointer; font-size: 1.1rem; opacity: 0.6;">✕</button>
                </div>
            <?php endif; ?>

            <div class="study-container">
                <div>
                    <!-- Upcoming Timeline -->
                    <div class="glass-card" style="padding: 25px; border-radius: 20px; margin-bottom: 30px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <h3 style="margin: 0;">Upcoming Sessions</h3>
                            <span style="font-size: 0.8rem; background: var(--secondary); color: white; padding: 4px 12px; border-radius: 20px; font-weight: 600;">
                                <?php echo count($sessions); ?> Planned
                            </span>
                        </div>
                        <div style="margin-top: 15px;">
                            <?php if (count($sessions) > 0): ?>
                                <?php foreach($sessions as $session): ?>
                                    <div class="session-item">
                                        <div style="display: flex; gap: 20px; align-items: center;">
                                            <div style="width: 45px; height: 45px; background: rgba(0,0,0,0.04); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">📖</div>
                                            <div>
                                                <div class="text-high-contrast" style="font-size: 1.1rem;"><?php echo htmlspecialchars($session['subject']); ?></div>
                                                <div style="font-size: 0.85rem; color: #666; font-weight: 500;">
                                                    <span style="color: var(--primary);">●</span> <?php echo date('M d, H:i', strtotime($session['session_date'])); ?> 
                                                    <span style="margin: 0 5px; opacity: 0.5;">|</span> 
                                                    <strong><?php echo $session['duration_minutes']; ?> mins</strong>
                                                </div>
                                                <?php if($session['notes']): ?>
                                                    <div style="font-size: 0.8rem; color: #777; font-style: italic; margin-top: 6px; background: rgba(0,0,0,0.03); padding: 4px 8px; border-radius: 6px;">
                                                        "<?php echo htmlspecialchars($session['notes']); ?>"
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <a href="?delete=<?php echo $session['id']; ?>" class="btn" style="background: rgba(192, 108, 108, 0.1); color: var(--danger); min-width: 40px; padding: 8px;" onclick="return confirm('Delete this session?')">✕</a>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div style="text-align: center; padding: 40px; color: var(--text-muted);">
                                    <div style="font-size: 3rem; margin-bottom: 15px;">🌱</div>
                                    <p>Your study schedule is clear. Ready to start?</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Group Study Section -->
                    <div class="glass-card" style="padding: 25px; border-radius: 20px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <h3 style="margin: 0;">Group Study 🤝</h3>
                            <form method="POST">
                                <button type="submit" name="toggle_interest" class="mode-btn <?php echo $interested_study ? 'active' : ''; ?>">
                                    <?php echo $interested_study ? '✅ Active' : 'Join Group Study'; ?>
                                </button>
                            </form>
                        </div>

                        <?php if ($interested_study): ?>
                            <!-- Invitations Section -->
                            <?php if (count($received_requests) > 0): ?>
                                <div style="margin-bottom: 25px;">
                                    <h4 style="font-size: 0.9rem; margin-bottom: 12px; color: var(--primary);">📥 New Invitations</h4>
                                    <?php foreach($received_requests as $req): ?>
                                        <div class="session-item" style="padding: 12px; flex-direction: column; align-items: flex-start; gap: 8px;">
                                            <div style="display: flex; justify-content: space-between; width: 100%; align-items: center;">
                                                <span class="text-high-contrast" style="font-weight: 700;">🤝 <?php echo htmlspecialchars($req['sender_name']); ?></span>
                                                <div style="display: flex; gap: 8px;">
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                                        <button type="submit" name="update_request" value="accepted" class="btn btn-primary" style="padding: 8px 16px; font-size: 0.85rem; font-weight: 800; border-radius: 10px;">Accept Invite</button>
                                                        <button type="submit" name="update_request" value="rejected" class="btn btn-secondary" style="padding: 8px 16px; font-size: 0.85rem; font-weight: 800; border-radius: 10px; color: #666 !important;">Not Interested</button>
                                                    </form>
                                                </div>
                                            </div>
                                            <div style="font-size: 0.8rem; color: #666; background: rgba(0,0,0,0.03); padding: 8px; border-radius: 8px; width: 100%;">
                                                <strong>Group:</strong> <?php echo htmlspecialchars($req['group_name']); ?><br>
                                                <strong>Subject:</strong> <?php echo htmlspecialchars($req['subject_name']); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Group Study Section (Discovery) -->
                            <div style="margin-bottom: 25px;">
                                <h4 style="font-size: 0.9rem; margin-bottom: 15px; color: var(--primary); font-weight: 700;">🤝 FORM A GROUP STUDY</h4>
                                <?php if (count($potential_partners) > 0): ?>
                                    <form method="POST" id="groupWizardForm">
                                        <!-- Progress Indicator -->
                                        <div class="step-indicator">
                                            <div class="step-dot active" id="dot1">1</div>
                                            <div class="step-dot" id="dot2">2</div>
                                            <div class="step-dot" id="dot3">3</div>
                                        </div>

                                        <!-- Step 1: Select Partners -->
                                        <div class="step-container active" id="step1">
                                            <h5 style="font-size: 0.85rem; margin-bottom: 12px; color: #444;">Step 1: Pick Study Partners</h5>
                                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 12px; margin-bottom: 20px;">
                                                <?php foreach($potential_partners as $p): ?>
                                                    <label class="partner-card" style="background: rgba(255,255,255,0.6); padding: 15px; border-radius: 16px; text-align: center; border: 1px solid var(--glass-border); position: relative; cursor: pointer; display: block; transition: all 0.2s;">
                                                        <input type="checkbox" name="receiver_ids[]" value="<?php echo $p['id']; ?>" data-name="<?php echo htmlspecialchars($p['username']); ?>" class="partner-checkbox" style="position: absolute; top: 12px; left: 12px; transform: scale(1.2);">
                                                        <div style="font-weight: 700; font-size: 1rem; margin: 10px 0 5px 0; color: #333;">
                                                            <?php echo htmlspecialchars($p['username']); ?>
                                                        </div>
                                                        <div style="font-size: 0.65rem; color: #777; font-weight: 700; letter-spacing: 0.5px;">AVAILABLE</div>
                                                    </label>
                                                <?php endforeach; ?>
                                            </div>
                                            <div style="text-align: right;">
                                                <button type="button" class="btn btn-primary" onclick="goToStep(2)" style="padding: 10px 25px;">Next Step →</button>
                                            </div>
                                        </div>

                                        <!-- Step 2: Group Details -->
                                        <div class="step-container" id="step2">
                                            <h5 style="font-size: 0.85rem; margin-bottom: 12px; color: #444;">Step 2: Name & Subject</h5>
                                            <div style="background: rgba(255,255,255,0.5); padding: 25px; border-radius: 20px; border: 1px solid var(--glass-border);">
                                                <div class="form-group" style="margin-bottom: 15px;">
                                                    <label style="font-size: 0.7rem; color: #666; font-weight: 700;">PROPOSED GROUP NAME</label>
                                                    <input type="text" id="wizard_group_name" name="group_name" placeholder="Ex: Calculus Crusaders" class="form-input" style="padding: 12px; border-radius: 12px;">
                                                </div>
                                                <div class="form-group" style="margin-bottom: 0;">
                                                    <label style="font-size: 0.7rem; color: #666; font-weight: 700;">STUDY SUBJECT</label>
                                                    <input type="text" id="wizard_subject" name="subject_name" placeholder="Ex: Advanced Mathematics" class="form-input" style="padding: 12px; border-radius: 12px;">
                                                </div>
                                            </div>
                                            <div style="margin-top: 20px; display: flex; justify-content: space-between;">
                                                <button type="button" class="btn btn-secondary" onclick="goToStep(1)">← Back</button>
                                                <button type="button" class="btn btn-primary" onclick="goToStep(3)">Review Details →</button>
                                            </div>
                                        </div>

                                        <!-- Step 3: Review & Send -->
                                        <div class="step-container" id="step3">
                                            <h5 style="font-size: 0.85rem; margin-bottom: 12px; color: #444;">Step 3: Final Review</h5>
                                            <div style="background: rgba(255,255,255,0.8); padding: 20px; border-radius: 20px; border: 1px solid var(--glass-border);">
                                                <div style="margin-bottom: 15px;">
                                                    <div style="font-size: 0.7rem; color: #777; font-weight: 700;">INVITING:</div>
                                                    <div id="review_partners" style="font-weight: 700; color: #333;">-</div>
                                                </div>
                                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                                    <div>
                                                        <div style="font-size: 0.7rem; color: #777; font-weight: 700;">GROUP NAME:</div>
                                                        <div id="review_group" style="font-weight: 700; color: var(--primary);">-</div>
                                                    </div>
                                                    <div>
                                                        <div style="font-size: 0.7rem; color: #777; font-weight: 700;">SUBJECT:</div>
                                                        <div id="review_subject" style="font-weight: 700; color: var(--secondary);">-</div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div style="margin-top: 20px; display: flex; justify-content: space-between;">
                                                <button type="button" class="btn btn-secondary" onclick="goToStep(2)">← Edit Details</button>
                                                <button type="submit" name="send_request" class="btn btn-primary" style="padding: 10px 30px; font-weight: 800;">DISPATCH INVITES 🚀</button>
                                            </div>
                                        </div>
                                    </form>
                                <?php else: ?>
                                    <p style="font-size: 0.85rem; color: #666; font-style: italic;">No new students looking for group study right now.</p>
                                <?php endif; ?>
                            </div>

                            <!-- Sent Requests Section (Organized Group-Wise) -->
                            <?php if (count($sent_requests) > 0): ?>
                                <div style="margin-bottom: 25px;">
                                    <h4 style="font-size: 0.9rem; margin-bottom: 12px; color: var(--text-muted); font-weight: 700;">📤 OUTGOING GROUP REQUESTS</h4>
                                    <div style="display: flex; flex-direction: column; gap: 15px;">
                                        <?php foreach($sent_requests as $group): ?>
                                            <div class="session-item" style="padding: 20px; background: rgba(255,255,255,0.4); border-radius: 18px; border: 1px solid rgba(255,255,255,0.5); flex-direction: column; align-items: flex-start; gap: 12px;">
                                                <div style="display: flex; justify-content: space-between; width: 100%; align-items: center;">
                                                    <div style="font-weight: 800; font-size: 1rem; color: var(--primary);">
                                                        📜 <?php echo htmlspecialchars($group['group_name']); ?>
                                                        <span style="opacity: 0.4; margin: 0 8px;">•</span>
                                                        <span style="font-weight: 600; color: #666; font-size: 0.9rem;">� <?php echo htmlspecialchars($group['subject_name']); ?></span>
                                                    </div>
                                                    <span style="font-size: 0.65rem; font-weight: 900; color: #d97706; padding: 4px 12px; border-radius: 20px; background: rgba(255,255,255,0.8); border: 1px solid rgba(0,0,0,0.05); text-transform: uppercase;">Awaiting Members</span>
                                                </div>
                                                
                                                <div style="width: 100%;">
                                                    <div style="font-size: 0.65rem; color: #777; font-weight: 800; text-transform: uppercase; margin-bottom: 6px; letter-spacing: 0.5px;">Invited Scholars:</div>
                                                    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                                                        <?php foreach($group['recipients'] as $r): ?>
                                                            <div style="background: rgba(255,255,255,0.6); padding: 5px 12px; border-radius: 10px; display: flex; align-items: center; border: 1px solid rgba(255,255,255,0.8); gap: 10px;">
                                                                <span style="font-size: 0.8rem; font-weight: 700; color: #333;">👤 <?php echo htmlspecialchars($r['receiver_name']); ?></span>
                                                                <form method="POST" style="margin: 0; display: flex; align-items: center;" onsubmit="return confirm('Withdraw this invitation?')">
                                                                    <input type="hidden" name="request_id" value="<?php echo $r['id']; ?>">
                                                                    <button type="submit" name="cancel_request" title="Withdraw Invite" style="background: #fee2e2; border: none; color: #ef4444; font-size: 0.75rem; padding: 2px 6px; cursor: pointer; border-radius: 4px; display: flex; align-items: center; justify-content: center;">✕</button>
                                                                </form>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- My Groups Section -->
                            <div>
                                <h4 style="font-size: 0.9rem; margin-bottom: 15px; color: var(--secondary); font-weight: 700;">🏢 MY GROUPS</h4>
                                <?php if (count($my_groups) > 0): ?>
                                    <div style="display: flex; flex-direction: column; gap: 12px;">
                                        <?php foreach($my_groups as $g): ?>
                                            <div class="session-item" style="padding: 18px; border-radius: 18px; background: rgba(255,255,255,0.4); border: 1px solid rgba(255,255,255,0.6); flex-direction: column; align-items: flex-start; gap: 10px;">
                                                <div style="display: flex; justify-content: space-between; width: 100%; align-items: flex-start;">
                                                    <div>
                                                        <div class="text-high-contrast" style="font-weight: 800; font-size: 1.1rem; margin-bottom: 2px;">
                                                            📜 <?php echo htmlspecialchars($g['name']); ?>
                                                        </div>
                                                        <div style="font-weight: 600; color: var(--primary); font-size: 0.9rem; margin-bottom: 8px;">
                                                            📖 Topic: <?php echo htmlspecialchars($g['subject'] ?: 'General Study'); ?>
                                                        </div>
                                                        <div style="font-size: 0.75rem; font-weight: 700; margin-top: 4px;">
                                                            <?php if ($g['status'] == 'active'): ?>
                                                                <span style="color: #059669; background: rgba(5, 150, 105, 0.1); padding: 2px 10px; border-radius: 20px;">✅ VERIFIED & ACTIVE</span>
                                                            <?php elseif ($g['status'] == 'forming'): ?>
                                                                <span style="color: #4F46E5; background: rgba(79, 70, 229, 0.1); padding: 2px 10px; border-radius: 20px;">⏳ BUILDING TEAM</span>
                                                            <?php else: ?>
                                                                <span style="color: #d97706; background: rgba(217, 119, 6, 0.1); padding: 2px 10px; border-radius: 20px;">⌛ PENDING VERIFICATION</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <?php if ($g['status'] == 'forming' && $g['my_role'] == 'leader'): ?>
                                                        <form method="POST">
                                                            <input type="hidden" name="group_id" value="<?php echo $g['id']; ?>">
                                                            <button type="submit" name="submit_for_verification" class="btn btn-primary" style="padding: 12px 24px; font-size: 0.9rem; font-weight: 800; border-radius: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border: 2px solid rgba(255,255,255,0.2);">REQUEST ADMIN GRANT �</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                                <div style="width: 100%; border-top: 1px solid rgba(0,0,0,0.05); padding-top: 15px; margin-top: 5px;">
                                                    <button type="button" onclick="this.nextElementSibling.classList.toggle('visible'); this.innerText = this.innerText.includes('TEAM') ? 'Hide Personnel ↑' : 'TEAM MEMBERS ↓';" 
                                                            style="background: rgba(0,0,0,0.05); border: 1px solid rgba(0,0,0,0.1); color: var(--secondary); font-size: 0.7rem; font-weight: 800; cursor: pointer; padding: 6px 14px; border-radius: 10px; text-transform: uppercase; transition: all 0.2s;">
                                                        TEAM MEMBERS ↓
                                                    </button>
                                                    <div class="personnel-list" style="flex-wrap: wrap; gap: 8px; margin-top: 12px;">
                                                        <?php 
                                                        $members = explode(', ', $g['members_list']);
                                                        foreach ($members as $m): 
                                                            $is_leader = strpos($m, '(leader)') !== false;
                                                        ?>
                                                            <div style="background: <?php echo $is_leader ? 'rgba(0,0,0,0.05)' : 'rgba(255,255,255,0.6)'; ?>; 
                                                                        padding: 6px 14px; border-radius: 12px; border: 1px solid rgba(0,0,0,0.05); 
                                                                        font-size: 0.85rem; font-weight: 700; color: #333; display: flex; align-items: center; gap: 6px;">
                                                                <span><?php echo $is_leader ? '👑' : '👤'; ?></span>
                                                                <?php echo htmlspecialchars($m); ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p style="font-size: 0.85rem; color: #666; font-style: italic;">No active groups. Invite someone to start!</p>
                                <?php endif; ?>
                            </div>

                            <!-- Global Active Groups Section -->
                            <div style="margin-top: 30px;">
                                <h4 style="font-size: 0.9rem; margin-bottom: 15px; color: var(--secondary); font-weight: 700;">🌐 ACTIVE TRIBES (COMMUNITY)</h4>
                                <?php if (count($all_active_groups) > 0): ?>
                                    <div style="display: flex; flex-direction: column; gap: 12px;">
                                        <?php foreach($all_active_groups as $g): ?>
                                            <div class="session-item" style="padding: 15px; border-radius: 18px; background: rgba(0,0,0,0.02); border: 1px dashed rgba(0,0,0,0.1); flex-direction: column; align-items: flex-start; gap: 8px;">
                                                <div class="text-high-contrast" style="font-weight: 700; font-size: 1rem; margin-bottom: 4px;">
                                                    🛡️ <?php echo htmlspecialchars($g['name']); ?>
                                                </div>
                                                <div style="font-weight: 600; color: var(--primary); font-size: 0.85rem; margin-bottom: 8px;">
                                                    📖 Topic: <?php echo htmlspecialchars($g['subject'] ?: 'General Study'); ?>
                                                </div>
                                                <button type="button" onclick="this.nextElementSibling.classList.toggle('visible'); this.innerText = this.innerText.includes('View') ? 'Hide Personnel ↑' : 'View Personnel ↓';" 
                                                        style="background: rgba(0,0,0,0.03); border: 1px solid rgba(0,0,0,0.05); color: var(--secondary); font-size: 0.65rem; font-weight: 800; cursor: pointer; padding: 4px 10px; border-radius: 8px; text-transform: uppercase; transition: all 0.2s;">
                                                    View Personnel ↓
                                                </button>
                                                <div class="personnel-list" style="flex-wrap: wrap; gap: 6px; margin-top: 10px;">
                                                    <?php 
                                                    $gm = explode(', ', $g['members_list']);
                                                    foreach ($gm as $m): 
                                                    ?>
                                                        <span style="font-size: 0.75rem; background: #fff; padding: 3px 10px; border-radius: 10px; border: 1px solid rgba(0,0,0,0.05); color: #555;">
                                                            👤 <?php echo htmlspecialchars($m); ?>
                                                        </span>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <div style="padding: 20px; text-align: center; background: rgba(0,0,0,0.01); border-radius: 15px; border: 1px dashed rgba(0,0,0,0.05);">
                                        <p style="font-size: 0.8rem; color: #999; margin: 0;">No tribes active yet. Be the first to verify your group!</p>
                                    </div>
                                <?php endif; ?>
                            </div>

                        <?php else: ?>
                            <div style="text-align: center; padding: 20px; background: rgba(0,0,0,0.02); border-radius: 15px;">
                                <p style="font-size: 0.9rem; color: #666; margin-bottom: 0;">Toggle interest to discover other students and form study groups.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Focus Timer Section -->
                <div>
                    <div class="timer-card">
                        <h3 style="margin-bottom: 25px; letter-spacing: 1px;">FOCUS TIMER</h3>
                        
                        <div style="display: flex; gap: 10px; justify-content: center; margin-bottom: 20px;">
                            <button class="mode-btn active" id="pomoMode" onclick="setMode(25, 'pomo')">Pomodoro</button>
                            <button class="mode-btn" id="deepMode" onclick="setMode(50, 'deep')">Deep Work</button>
                        </div>

                        <div class="timer-display" id="timer">25:00</div>
                        
                        <div class="timer-controls">
                            <button class="btn btn-primary" style="padding: 12px 30px; font-weight: 700; min-width: 120px;" onclick="startTimer()" id="startBtn">START</button>
                            <button class="btn btn-secondary" style="padding: 12px 30px; font-weight: 700; min-width: 120px; display:none;" onclick="pauseTimer()" id="pauseBtn">PAUSE</button>
                            <button class="btn btn-restart" style="padding: 12px 20px; font-weight: 700;" onclick="restartTimer()">RESTART</button>
                        </div>
                        
                        <p style="margin-top: 25px; font-size: 0.85rem; color: #666; line-height: 1.5;">
                            Stay concentrated and avoid distractions.<br>A chime will sound when the session ends.
                        </p>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal -->
    <div class="modal" id="studyModal">
        <div class="glass-card" style="width: 420px; padding: 30px;">
            <h3 style="margin-bottom: 25px;">Schedule Session</h3>
            <form action="study.php" method="POST">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label>Module / Subject</label>
                    <input type="text" name="subject" class="form-input" required placeholder="Calculus, Web Dev, etc.">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px;">
                    <div class="form-group">
                        <label>Duration (Mins)</label>
                        <input type="number" name="duration" class="form-input" required value="60">
                    </div>
                    <div class="form-group">
                        <label>Date & Time</label>
                        <input type="datetime-local" name="date" class="form-input" required value="<?php echo date('Y-m-d\TH:i'); ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label>Focus Goals (Optional)</label>
                    <textarea name="notes" class="form-input" rows="3" placeholder="What are we mastering today?"></textarea>
                </div>
                <div style="display: flex; gap: 12px; justify-content: flex-end; margin-top: 30px;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()" style="color: #000 !important; font-weight: 800;">Dismiss</button>
                    <button type="submit" class="btn btn-primary" style="padding: 10px 25px;">Schedule</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal() { document.getElementById('studyModal').classList.add('active'); }
        function closeModal() { document.getElementById('studyModal').classList.remove('active'); }
        document.getElementById('studyModal').addEventListener('click', (e) => {
            if (e.target === document.getElementById('studyModal')) closeModal();
        });

        // Timer Logic
        let currentModeMins = 25;
        let timeLeft = 25 * 60;
        let timerId = null;
        let isRunning = false;

        const timerEl = document.getElementById('timer');
        const startBtn = document.getElementById('startBtn');
        const pauseBtn = document.getElementById('pauseBtn');

        function updateDisplay() {
            const m = Math.floor(timeLeft / 60).toString().padStart(2, '0');
            const s = (timeLeft % 60).toString().padStart(2, '0');
            timerEl.innerText = `${m}:${s}`;
        }

        function startTimer() {
            if (isRunning) return;
            isRunning = true;
            startBtn.style.display = 'none';
            pauseBtn.style.display = 'inline-block';
            pauseBtn.innerText = 'PAUSE';
            
            timerId = setInterval(() => {
                if (timeLeft > 0) {
                    timeLeft--;
                    updateDisplay();
                } else {
                    clearInterval(timerId);
                    isRunning = false;
                    new Audio('https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3').play();
                    alert("Focus session complete! Take a break. 🎯");
                    restartTimer();
                }
            }, 1000);
        }

        function pauseTimer() {
            clearInterval(timerId);
            isRunning = false;
            startBtn.style.display = 'inline-block';
            startBtn.innerText = 'RESUME';
            pauseBtn.style.display = 'none';
        }

        function restartTimer() {
            clearInterval(timerId);
            isRunning = false;
            timeLeft = currentModeMins * 60;
            startBtn.style.display = 'inline-block';
            startBtn.innerText = 'START';
            pauseBtn.style.display = 'none';
            updateDisplay();
        }

        function setMode(mins, modeId) {
            currentModeMins = mins;
            document.querySelectorAll('.mode-btn').forEach(btn => btn.classList.remove('active'));
            document.getElementById(modeId + 'Mode').classList.add('active');
            restartTimer();
        }

        // Group Study Wizard Logic
        function goToStep(step) {
            if (step === 2) {
                // Validation for Step 1: Check if at least one checkbox is checked
                const checked = document.querySelectorAll('.partner-checkbox:checked');
                if (checked.length === 0) {
                    alert('Please select at least one study partner to continue! 🤝');
                    return;
                }
            }

            if (step === 3) {
                // Prepare Review Step
                const partners = Array.from(document.querySelectorAll('.partner-checkbox:checked'))
                                    .map(cb => cb.dataset.name).join(', ');
                const groupName = document.getElementById('wizard_group_name').value || 'New Group Study';
                const subject = document.getElementById('wizard_subject').value || 'General Study';

                document.getElementById('review_partners').innerText = partners;
                document.getElementById('review_group').innerText = groupName;
                document.getElementById('review_subject').innerText = subject;
            }

            // Toggle visibility
            document.querySelectorAll('.step-container').forEach(c => c.classList.remove('active'));
            document.getElementById('step' + step).classList.add('active');

            // Update indicators
            document.querySelectorAll('.step-dot').forEach((dot, idx) => {
                dot.classList.remove('active', 'completed');
                if (idx + 1 < step) dot.classList.add('completed');
                if (idx + 1 === step) dot.classList.add('active');
            });
        }
    </script>
</body>
</html>
