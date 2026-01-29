<?php
require_once 'includes/db.php';

// Find user with username containing 'agnussabu2028'
$stmt = $pdo->prepare("SELECT id, username, full_name FROM users WHERE username = ?");
$stmt->execute(['agnussabu2028']);
$user = $stmt->fetch();

if (!$user) {
    echo "User 'agnussabu2028' not found. Searching for similar usernames...\n";
    $stmt = $pdo->query("SELECT id, username, full_name FROM users WHERE username LIKE '%agnus%' OR username LIKE '%sabu%'");
    $users = $stmt->fetchAll();
    if (count($users) > 0) {
        echo "Found similar users:\n";
        foreach ($users as $u) {
            echo "  ID: {$u['id']}, Username: {$u['username']}, Name: {$u['full_name']}\n";
        }
    } else {
        echo "No similar users found.\n";
    }
    exit;
}

$user_id = $user['id'];
echo "Found user: {$user['username']} (ID: $user_id)\n\n";

// Future dates for goals
$goals = [
    [
        'title' => 'Complete Web Development Course',
        'description' => 'Finish the full-stack web development course including HTML, CSS, JavaScript, PHP and MySQL',
        'target_date' => date('Y-m-d', strtotime('+30 days')), // 30 days from now
        'category' => 'education'
    ],
    [
        'title' => 'Read 5 Books',
        'description' => 'Read at least 5 self-improvement and technical books this quarter',
        'target_date' => date('Y-m-d', strtotime('+60 days')), // 60 days from now
        'category' => 'personal'
    ],
    [
        'title' => 'Build Portfolio Website',
        'description' => 'Create a personal portfolio website showcasing projects and skills',
        'target_date' => date('Y-m-d', strtotime('+45 days')), // 45 days from now
        'category' => 'career'
    ]
];

// Insert goals
$stmt = $pdo->prepare("INSERT INTO goals (user_id, title, description, target_date, category, status) VALUES (?, ?, ?, ?, ?, 'active')");

echo "Adding 3 goals with future dates:\n";
echo "================================\n\n";

foreach ($goals as $goal) {
    $stmt->execute([$user_id, $goal['title'], $goal['description'], $goal['target_date'], $goal['category']]);
    echo "✅ Goal: {$goal['title']}\n";
    echo "   Description: {$goal['description']}\n";
    echo "   Target Date: {$goal['target_date']}\n";
    echo "   Category: {$goal['category']}\n\n";
}

echo "================================\n";
echo "✨ Successfully added 3 goals for user '{$user['username']}'!\n";
?>
