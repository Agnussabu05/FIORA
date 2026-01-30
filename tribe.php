<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$group_id = $_GET['id'] ?? 0;
$schedule_error = null;
$schedule_data = [];

// 1. Verify Membership & Fetch Group Details
$stmt = $pdo->prepare("
    SELECT sg.*, 
    (SELECT role FROM study_group_members WHERE group_id = sg.id AND user_id = ?) as my_role
    FROM study_groups sg 
    WHERE sg.id = ?
");
$stmt->execute([$user_id, $group_id]);
$group = $stmt->fetch();

// Security: If group doesn't exist or user is not a member (and not admin), kick them out
if (!$group || empty($group['my_role'])) {
    if ($_SESSION['role'] !== 'admin') {
        header("Location: study.php");
        exit;
    }
}

// Strict Access Control: Only ACTIVE groups can be accessed
if ($group['status'] !== 'active' && $_SESSION['role'] !== 'admin') {
    $_SESSION['study_msg'] = "🚫 This tribe is not active yet! Forming or Pending Approval.";
    header("Location: study.php");
    exit;
}

// 2. Handle New Message / File Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_msg'])) {
    $msg = trim($_POST['message']);
    $file_path = null;
    $file_name = null;

    // Handle File
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/tribe_files/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $fileName = basename($_FILES['attachment']['name']);
        $targetPath = $uploadDir . time() . '_' . $fileName;
        
        // Allow basic extensions
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (in_array($ext, ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'txt'])) {
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $targetPath)) {
                $file_path = $targetPath;
                $file_name = $fileName;
            }
        }
    }

    if (!empty($msg) || $file_path) {
        $stmt = $pdo->prepare("INSERT INTO study_group_messages (group_id, user_id, message, file_path, file_name) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$group_id, $user_id, $msg, $file_path, $file_name]);
        // log_activity($pdo, $user_id, 'tribe_msg', 'Sent message in group ' . $group_id, $group_id);
    }
    
    // Redirect to avoid resubmission
    header("Location: tribe.php?id=$group_id");
    exit;
}

// 2.5 Handle Meet Link (Start/End)
// 2.5 Handle Meet Scheduling (New Table)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['schedule_meet']) && !empty($_POST['meet_url']) && !empty($_POST['scheduled_time'])) {
        // Enforce Active Status
        if ($group['status'] !== 'active') {
            header("Location: tribe.php?id=$group_id"); // Silent fail or could add msg
            exit;
        }

        $url = trim($_POST['meet_url']);
        $time = $_POST['scheduled_time'];
        $title = trim($_POST['session_title'] ?: 'Study Session');
        
        // Validate Time (Prevent Past)
        if (strtotime($time) < time()) {
             $schedule_error = "❌ Cannot schedule a session in the past!";
             $schedule_data = $_POST;
             // Do not redirect, let page render to show error
        } else {
            // Basic validation
            if (strpos($url, 'meet.google.com') !== false || strpos($url, 'zoom.us') !== false) {
                $pdo->prepare("INSERT INTO study_group_sessions (group_id, title, meet_link, scheduled_at) VALUES (?, ?, ?, ?)")
                    ->execute([$group_id, $title, $url, $time]);
                
                // Announce
                $prettyTime = date('M j, g:i A', strtotime($time));
                $sysMsg = "📅 New Session Scheduled: '$title' for $prettyTime";
                $pdo->prepare("INSERT INTO study_group_messages (group_id, user_id, message) VALUES (?, ?, ?)")
                    ->execute([$group_id, $user_id, $sysMsg]);
            }
            header("Location: tribe.php?id=$group_id");
            exit;
        }
    }
    
    // (Old End Meet logic removed/deprecated)
    
    // 2.9 Handle "Open/Unlock" Session (Leader only)
    if (isset($_POST['open_session_id']) && $group['my_role'] == 'leader') {
        $sid = $_POST['open_session_id'];
        // Removed group_id check for robustness, ID is unique anyway
        $pdo->prepare("UPDATE study_group_sessions SET status = 'live' WHERE id = ?")->execute([$sid]);
        
        // Notify
        $sysMsg = "🔓 The room is now open! Join in.";
        $pdo->prepare("INSERT INTO study_group_messages (group_id, user_id, message) VALUES (?, ?, ?)")
            ->execute([$group_id, $user_id, $sysMsg]);
            
        header("Location: tribe.php?id=$group_id");
        exit;
    }
    // 2.10 Handle "Lock" Session (Leader only)
    if (isset($_POST['lock_session_id']) && $group['my_role'] == 'leader') {
        $sid = $_POST['lock_session_id'];
        $pdo->prepare("UPDATE study_group_sessions SET status = 'locked' WHERE id = ?")->execute([$sid]);
        
        // Notify
        $sysMsg = "🔒 The room is temporarily locked.";
        $pdo->prepare("INSERT INTO study_group_messages (group_id, user_id, message) VALUES (?, ?, ?)")
            ->execute([$group_id, $user_id, $sysMsg]);
            
        header("Location: tribe.php?id=$group_id");
        exit;
    }
}

// 2.8 Fetch Upcoming Sessions
$sess_stmt = $pdo->prepare("SELECT * FROM study_group_sessions WHERE group_id = ? AND status != 'ended' ORDER BY scheduled_at ASC");
$sess_stmt->execute([$group_id]);
$sessions = $sess_stmt->fetchAll();

// 3. Fetch Messages
$msg_stmt = $pdo->prepare("
    SELECT m.*, u.username, u.id as sender_id 
    FROM study_group_messages m 
    JOIN users u ON m.user_id = u.id 
    WHERE m.group_id = ? 
    ORDER BY m.created_at ASC
");
$msg_stmt->execute([$group_id]);
$messages = $msg_stmt->fetchAll();

// 4. Fetch Members
$mem_stmt = $pdo->prepare("
    SELECT u.username, sgm.role 
    FROM study_group_members sgm 
    JOIN users u ON sgm.user_id = u.id 
    WHERE sgm.group_id = ?
");
$mem_stmt->execute([$group_id]);
$members = $mem_stmt->fetchAll();

$page = 'study';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($group['name']); ?> - Tribe</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .tribe-container {
            display: grid;
            grid-template-columns: 1fr 280px;
            gap: 20px;
            height: calc(100vh - 100px);
        }
        .chat-section {
            background: white;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .chat-header {
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
            background: rgba(248, 250, 252, 0.5);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            background: #f8fafc;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .message-bubble {
            max-width: 70%;
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 0.95rem;
            position: relative;
            line-height: 1.4;
        }
        .msg-own {
            align-self: flex-end;
            background: #2563eb;
            color: white;
            border-bottom-right-radius: 2px;
        }
        .msg-other {
            align-self: flex-start;
            background: white;
            border: 1px solid #e2e8f0;
            color: #1e293b;
            border-bottom-left-radius: 2px;
        }
        .msg-meta {
            font-size: 0.7rem;
            margin-bottom: 4px;
            opacity: 0.8;
            font-weight: 600;
        }
        .file-attachment {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(0,0,0,0.1);
            padding: 8px;
            border-radius: 8px;
            margin-top: 5px;
            font-size: 0.85rem;
            text-decoration: none;
            color: inherit;
        }
        .file-attachment:hover {
            background: rgba(0,0,0,0.2);
        }
        .chat-input {
            padding: 20px;
            background: white;
            border-top: 1px solid #e2e8f0;
        }
        .input-form {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .input-box {
            flex: 1;
            padding: 12px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-family: inherit;
            resize: none;
            height: 45px;
        }
        .attach-btn {
            cursor: pointer;
            padding: 10px;
            color: #64748b;
            font-size: 1.2rem;
        }
        .send-btn {
            background: #2563eb;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
        }
        
        /* Members Sidebar */
        .members-panel {
            background: white;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            padding: 20px;
            overflow-y: auto;
        }
        .member-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .avatar-small {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #64748b;
            font-size: 0.8rem;
        }
        
        .file-preview { font-size: 0.8rem; color: #64748b; margin-top: 5px; display: none; }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <main class="main-content">
            <div style="margin-bottom: 15px;">
                <a href="study.php" style="color: #64748b; text-decoration: none; font-size: 0.9rem;">← Back to Space</a>
            </div>

            <div class="tribe-container">
                <!-- Chat Section -->
                <div class="chat-section">
                    <div class="chat-header">
                        <div>
                            <h2 style="margin: 0; font-size: 1.2rem; color: #1e293b;"><?php echo htmlspecialchars($group['name']); ?></h2>
                            <span style="font-size: 0.85rem; color: #64748b; font-weight: 500;">📖 <?php echo htmlspecialchars($group['subject']); ?></span>
                        </div>
                        
                        <!-- HEADER ACTIONS -->
                        <div style="display: flex; align-items: center; gap: 10px;">
                             <?php 
                             // Check if any session is LIVE right now
                             $live_session = null;
                             foreach($sessions as $s) {
                                 if (time() >= strtotime($s['scheduled_at'])) {
                                     $live_session = $s; break; 
                                 }
                             }
                             ?>
                             
                             <?php if ($live_session): ?>
                                <a href="<?php echo htmlspecialchars($live_session['meet_link']); ?>" target="_blank" class="btn" style="background: #ef4444; color: white; text-decoration: none; padding: 8px 16px; border-radius: 20px; font-weight: 700; display: flex; align-items: center; gap: 6px; animation: pulse 2s infinite;">
                                    <span>📹</span> JOIN NOW
                                </a>
                             <?php endif; ?>

                            <?php if($group['my_role'] == 'leader' && $group['status'] == 'active'): ?>
                                <button onclick="document.getElementById('scheduleModal').style.display='flex'" class="btn" style="background: #fff; border: 1px dashed #cbd5e1; color: #475569; padding: 8px 16px; border-radius: 20px; font-weight: 600; font-size: 0.85rem;">
                                    + Schedule Live
                                </button>
                            <?php elseif($group['my_role'] == 'leader' && $group['status'] != 'active'): ?>
                                <button disabled class="btn" style="background: #e2e8f0; color: #94a3b8; padding: 8px 16px; border-radius: 20px; font-weight: 600; font-size: 0.8rem; cursor: not-allowed;" title="Wait for Admin Approval">
                                    ❌ Live Disabled
                                </button>
                            <?php endif; ?>
                            <div style="font-size: 1.5rem; margin-left: 10px;">🏰</div>
                        </div>
                    </div>
                    
                    <div class="chat-messages" id="chatContainer">
                        <?php if (empty($messages)): ?>
                            <div style="text-align: center; margin-top: 50px; color: #94a3b8;">
                                <div style="font-size: 3rem; margin-bottom: 10px;">👋</div>
                                <div>Welcome to the Tribe Hall!</div>
                                <div style="font-size: 0.9rem;">Ask a doubt or share a note to start.</div>
                            </div>
                        <?php else: foreach($messages as $m): $is_me = ($m['user_id'] == $user_id); ?>
                            <div class="message-bubble <?php echo $is_me ? 'msg-own' : 'msg-other'; ?>">
                                <div class="msg-meta" style="display: flex; justify-content: space-between; gap: 10px;">
                                    <span><?php echo $is_me ? 'You' : htmlspecialchars($m['username']); ?></span>
                                    <span><?php echo date('H:i', strtotime($m['created_at'])); ?></span>
                                </div>
                                
                                <?php if (!empty($m['message'])): ?>
                                    <div><?php echo nl2br(htmlspecialchars($m['message'])); ?></div>
                                <?php endif; ?>
                                
                                <?php if (!empty($m['file_path'])): ?>
                                    <a href="<?php echo htmlspecialchars($m['file_path']); ?>" target="_blank" class="file-attachment">
                                        <span>📎</span>
                                        <span style="text-decoration: underline;"><?php echo htmlspecialchars($m['file_name']); ?></span>
                                        <span style="font-size: 0.7rem;">(Open)</span>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; endif; ?>
                    </div>
                    
                    <div class="chat-input">
                        <form method="POST" enctype="multipart/form-data" class="input-form">
                            <label class="attach-btn" title="Upload Note/File">
                                📎
                                <input type="file" name="attachment" style="display: none;" onchange="showPreview(this)">
                            </label>
                            <div style="flex: 1;">
                                <input type="text" name="message" class="input-box" placeholder="Ask a doubt or send a message..." autocomplete="off" style="width: 100%;">
                                <div id="filePreview" class="file-preview"></div>
                            </div>
                            <button type="submit" name="send_msg" class="send-btn">Send ➤</button>
                        </form>
                    </div>
                </div>
                
                <!-- Sidebar -->
                <div class="members-panel">
                    <!-- Upcoming Sessions List -->
                    <div style="margin-bottom: 25px;">
                        <h3 style="margin-top: 0; font-size: 1rem; color: #475569; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">📅 Upcoming Meets</h3>
                        <?php if($sessions): ?>
                            <div style="display: flex; flex-direction: column; gap: 10px;">
                                <?php foreach($sessions as $sess): 
                                    // Logic: Members can join if time is close OR if status is 'live' (unlocked by leader)
                                    // BUT NOT if status is 'locked' (even if time is reached)
                                    $link_opens_at = strtotime($sess['scheduled_at']) - 600; // 10 mins before
                                    $is_open_by_time = (time() >= $link_opens_at);
                                    $is_open_manually = ($sess['status'] == 'live');
                                    $is_locked = ($sess['status'] == 'locked');
                                    
                                    // Members can join if (Time OR Manual) AND Not Locked
                                    $member_can_join = ($is_open_by_time || $is_open_manually) && !$is_locked;
                                ?>
                                <div style="background: #f8fafc; padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0;">
                                    <div style="font-size: 0.9rem; font-weight: 700; color: #334155;">
                                        <?php echo htmlspecialchars($sess['title']); ?>
                                        <?php if($is_open_manually) echo '<span style="color:#166534; font-size:0.7rem; background:#dcfce7; padding:2px 6px; border-radius:4px; margin-left:6px;">LIVE</span>'; ?>
                                    </div>
                                    <div style="font-size: 0.8rem; color: #64748b; margin-bottom: 8px;">
                                        <?php echo date('M j, g:i A', strtotime($sess['scheduled_at'])); ?>
                                    </div>
                                    
                                    <?php if ($member_can_join || $group['my_role'] == 'leader'): ?>
                                        <a href="<?php echo htmlspecialchars($sess['meet_link']); ?>" target="_blank" style="display: block; text-align: center; background: #ef4444; color: white; padding: 6px; border-radius: 6px; text-decoration: none; font-size: 0.8rem; font-weight: 700;">
                                            JOIN ROOM <?php if(!$member_can_join) echo '(Leader Access)'; ?>
                                        </a>
                                        
                                        <!-- Leader Unlock/Lock Option -->
                                        <?php if($group['my_role'] == 'leader'): ?>
                                            <?php if(!$member_can_join): ?>
                                                <form method="POST" style="margin-top: 6px;">
                                                    <input type="hidden" name="open_session_id" value="<?php echo $sess['id']; ?>">
                                                    <button style="width: 100%; border: 1px dashed #2563eb; background: #eff6ff; color: #2563eb; padding: 4px; border-radius: 6px; font-size: 0.75rem; cursor: pointer;">
                                                        🔓 Unlock for Squad Now
                                                    </button>
                                                </form>
                                            <?php elseif($is_open_manually): ?>
                                                <form method="POST" style="margin-top: 6px;">
                                                    <input type="hidden" name="lock_session_id" value="<?php echo $sess['id']; ?>">
                                                    <button style="width: 100%; border: 1px dashed #ef4444; background: #fef2f2; color: #ef4444; padding: 4px; border-radius: 6px; font-size: 0.75rem; cursor: pointer;">
                                                        🔒 Lock Again
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <button disabled style="width: 100%; border: none; background: #e2e8f0; color: #94a3b8; padding: 6px; border-radius: 6px; font-size: 0.75rem;">
                                            🔒 Opens 10m before start
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div style="font-size: 0.85rem; color: #94a3b8; font-style: italic;">No sessions scheduled.</div>
                        <?php endif; ?>
                    </div>

                    <h3 style="margin-top: 0; font-size: 1rem; color: #475569; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">Tribe Members</h3>
                    <div style="display: flex; flex-direction: column;">
                        <?php foreach($members as $mem): ?>
                            <div class="member-item">
                                <div class="avatar-small">
                                    <?php echo strtoupper(substr($mem['username'], 0, 1)); ?>
                                </div>
                                <div>
                                    <div style="font-size: 0.9rem; font-weight: 600; color: #334155;">
                                        <?php echo htmlspecialchars($mem['username']); ?>
                                        <?php if($mem['username'] == $_SESSION['username']) echo ' <span style="color:#94a3b8; font-weight:400;">(You)</span>'; ?>
                                    </div>
                                    <div style="font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; font-weight: 700;">
                                        <?php 
                                            echo $mem['role']; 
                                            if($mem['role'] == 'leader') echo ' 👑';
                                        ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if(!empty($group['description'])): ?>
                    <div style="margin-top: 30px;">
                        <h4 style="font-size: 0.85rem; color: #94a3b8; text-transform: uppercase;">Mission</h4>
                        <div style="font-size: 0.9rem; color: #475569; font-style: italic; background: #f8fafc; padding: 10px; border-radius: 8px;">
                            "<?php echo htmlspecialchars($group['description']); ?>"
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <div id="scheduleModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 1000;">
        <div style="background: white; padding: 30px; border-radius: 20px; width: 400px; box-shadow: 0 10px 25px rgba(0,0,0,0.2);">
            <h3 style="margin-top: 0;">Schedule Meet 🗓️</h3>
            <p style="color: #64748b; font-size: 0.9rem;">Plan a future study session for your tribe.</p>
            
            <form method="POST">
                <?php if ($schedule_error): ?>
                    <div style="background: #fee2e2; color: #b91c1c; padding: 10px; border-radius: 8px; margin-bottom: 15px; font-size: 0.9rem; font-weight: 700;">
                        <?php echo $schedule_error; ?>
                    </div>
                <?php endif; ?>

                <div style="margin-bottom: 20px;">
                    <label style="display: block; font-size: 0.8rem; font-weight: 700; color: #64748b; margin-bottom: 6px;">SESSION TITLE</label>
                    <input type="text" name="session_title" placeholder="e.g. Chapter 4 Review" style="width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; margin-bottom: 15px;" value="<?php echo htmlspecialchars($schedule_data['session_title'] ?? ''); ?>">

                    <label style="display: block; font-size: 0.8rem; font-weight: 700; color: #64748b; margin-bottom: 6px;">MEET LINK</label>
                    <input type="text" name="meet_url" placeholder="Paste meet.google.com link..." required style="width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; margin-bottom: 15px;" value="<?php echo htmlspecialchars($schedule_data['meet_url'] ?? ''); ?>">
                    
                    <label style="display: block; font-size: 0.8rem; font-weight: 700; color: #64748b; margin-bottom: 6px;">DATE & TIME</label>
                    <input type="datetime-local" name="scheduled_time" required style="width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; font-family: inherit;" value="<?php echo htmlspecialchars($schedule_data['scheduled_time'] ?? ''); ?>">
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" onclick="document.getElementById('scheduleModal').style.display='none'" class="btn" style="background: #f1f5f9; color: #475569;">Cancel</button>
                    <button type="submit" name="schedule_meet" class="btn" style="background: #2563eb; color: white;">Schedule It 📅</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Auto scroll to bottom
        const container = document.getElementById('chatContainer');
        container.scrollTop = container.scrollHeight;

        function showPreview(input) {
            const preview = document.getElementById('filePreview');
            if (input.files && input.files[0]) {
                preview.style.display = 'block';
                preview.innerText = '📎 Attached: ' + input.files[0].name;
            } else {
                preview.style.display = 'none';
            }
        }
        
        // Real-time Validation (Required + Date)
        function validateTribeField(input) {
            const errorId = input.id + '-error';
            let errorEl = document.getElementById(errorId);
            if (!errorEl) {
                errorEl = document.createElement('div');
                errorEl.id = errorId;
                errorEl.style.color = '#ef4444';
                errorEl.style.fontSize = '0.85rem';
                errorEl.style.marginTop = '4px';
                errorEl.style.fontWeight = '600';
                input.parentNode.appendChild(errorEl);
            }

            errorEl.innerText = '';
            input.style.borderColor = '';
            
            const val = input.value.trim();

            // 1. Mandatory Check
            if (input.hasAttribute('required') && !val) {
                 errorEl.innerText = '⚠️ This field is mandatory!';
                 input.style.borderColor = '#ef4444';
                 return;
            }
            
            if(!val) return;

            // 2. Date Check
            if (input.type === 'datetime-local') {
                const selected = new Date(val);
                const now = new Date();
                if (selected < now) {
                    errorEl.innerText = '⚠️ strictly no past time allowd!';
                    input.style.borderColor = '#ef4444';
                }
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
             // Attach validation to all fields in the schedule form
            const form = document.querySelector('form');
            if (form) {
                const inputs = form.querySelectorAll('input, select, textarea');
                inputs.forEach(inp => {
                    // Ensure ID for error mapping
                    if(!inp.id) inp.id = 'field_' + Math.random().toString(36).substr(2, 9);
                    
                    inp.addEventListener('input', () => validateTribeField(inp));
                    inp.addEventListener('blur', () => validateTribeField(inp));
                    inp.addEventListener('change', () => validateTribeField(inp));
                });
            }
        });

        <?php if ($schedule_error): ?>
            document.getElementById('scheduleModal').style.display='flex';
        <?php endif; ?>
    </script>
</body>
</html>
