<?php
// api/chat_mood.php - AI-Powered Version with Fallback
header('Content-Type: application/json');
session_start();

require_once 'config.php';

function sendError($message) {
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Invalid request method.');
}

$input = json_decode(file_get_contents('php://input'), true);
$message = trim($input['message'] ?? '');
$moodContext = $input['mood_context'] ?? 'Neutral';

if (empty($message)) {
    sendError('Message cannot be empty.');
}

// Initialize conversation history
if (!isset($_SESSION['chat_history'])) {
    $_SESSION['chat_history'] = [];
}

// AI API Call Function
function callAI($userMessage, $mood, $history) {
    global $apiKey, $model;
    
    if (empty($apiKey)) {
        return null; // Fallback to pattern matching
    }
    
    // Build conversation context
    $historyContext = "";
    foreach (array_slice($history, -3) as $h) {
        $historyContext .= "User: " . $h['message'] . "\n";
    }
    
    $systemPrompt = "You are a friendly, empathetic AI mood companion named Fiora. You help users with their emotional well-being, provide support, motivation, and helpful advice. Keep responses concise (2-3 sentences max), warm, and use emojis sparingly. The user's current mood is: $mood.";
    
    $prompt = "[INST] $systemPrompt\n\n$historyContext\nUser: $userMessage\n\nAssistant: [/INST]";
    
    $payload = json_encode([
        "inputs" => $prompt,
        "parameters" => [
            "max_new_tokens" => 150,
            "temperature" => 0.7,
            "return_full_text" => false
        ]
    ]);
    
    $ch = curl_init("https://api-inference.huggingface.co/models/$model");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer $apiKey",
            "Content-Type: application/json"
        ],
        CURLOPT_TIMEOUT => 15
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return null; // Fallback
    }
    
    $data = json_decode($response, true);
    
    if (isset($data[0]['generated_text'])) {
        $text = trim($data[0]['generated_text']);
        // Clean up any remaining prompt artifacts
        $text = preg_replace('/^\[\/INST\]\s*/', '', $text);
        $text = preg_replace('/^Assistant:\s*/i', '', $text);
        return $text;
    }
    
    return null;
}

// Helper to pick random response
function randomPick($array) {
    return $array[array_rand($array)];
}

// ADVANCED: Sentiment Intensity Analysis
function analyzeSentiment($message) {
    $messageLower = strtolower($message);
    $intensity = 1; // 1 = low, 2 = medium, 3 = high
    
    // High intensity indicators
    $highIntensity = ['very', 'extremely', 'really', 'so much', 'completely', 'totally', 'absolutely', '!!!', 'desperately', 'urgently'];
    foreach ($highIntensity as $word) {
        if (strpos($messageLower, $word) !== false) {
            $intensity = 3;
            break;
        }
    }
    
    // Medium intensity
    if ($intensity < 3) {
        $mediumIntensity = ['quite', 'pretty', 'fairly', 'somewhat', 'kind of', 'kinda', '!!'];
        foreach ($mediumIntensity as $word) {
            if (strpos($messageLower, $word) !== false) {
                $intensity = 2;
                break;
            }
        }
    }
    
    // Crisis detection
    $crisisWords = ['suicidal', 'kill myself', 'end it all', 'no point living', 'want to die'];
    $isCrisis = false;
    foreach ($crisisWords as $word) {
        if (strpos($messageLower, $word) !== false) {
            $isCrisis = true;
            break;
        }
    }
    
    return ['intensity' => $intensity, 'crisis' => $isCrisis];
}

// ADVANCED: Negation Detection
function hasNegation($message) {
    $negations = ['not', 'no', 'never', "don't", "doesn't", "didn't", "won't", "can't", "isn't", "aren't"];
    $messageLower = strtolower($message);
    
    foreach ($negations as $neg) {
        if (preg_match('/\b' . preg_quote($neg) . '\b/', $messageLower)) {
            return true;
        }
    }
    return false;
}

// ADVANCED: Detect Multiple Emotions
function detectEmotions($message) {
    $emotions = [];
    $messageLower = strtolower($message);
    
    if (preg_match('/(stress|anxious|overwhelm|worry|pressure|nervous|panic|tense)/', $messageLower)) {
        $emotions[] = 'stress';
    }
    if (preg_match('/(sad|depressed|down|low|lonely|empty|hopeless|hurt|cry)/', $messageLower)) {
        $emotions[] = 'sadness';
    }
    if (preg_match('/(angry|mad|furious|frustrated|annoyed|irritated)/', $messageLower)) {
        $emotions[] = 'anger';
    }
    if (preg_match('/(tired|exhausted|sleep|insomnia|fatigue)/', $messageLower)) {
        $emotions[] = 'fatigue';
    }
    if (preg_match('/(happy|great|awesome|amazing|wonderful|excited|joyful)/', $messageLower)) {
        $emotions[] = 'happiness';
    }
    
    return $emotions;
}

// Time-aware helper
function getTimeContext() {
    $hour = (int)date('H');
    if ($hour >= 5 && $hour < 12) return 'morning';
    if ($hour >= 12 && $hour < 17) return 'afternoon';
    if ($hour >= 17 && $hour < 21) return 'evening';
    return 'night';
}

// ADVANCED CHATBOT
function generateResponse($message, $mood, $history) {
    $messageLower = strtolower($message);
    $wordCount = str_word_count($message);
    $sentiment = analyzeSentiment($message);
    $hasNeg = hasNegation($message);
    $emotions = detectEmotions($message);
    $timeOfDay = getTimeContext();
    
    // CRISIS INTERVENTION
    if ($sentiment['crisis']) {
        return randomPick([
            "I'm really concerned about what you're sharing. Please reach out to a mental health professional or crisis helpline immediately. You matter. 🆘 National Lifeline: 988",
            "What you're feeling sounds serious. Please contact a crisis helpline right away - they're available 24/7. Your life has value. 🆘"
        ]);
    }
    
    // NEGATION HANDLING
    if ($hasNeg && count($emotions) > 0) {
        return randomPick([
            "That's good to hear! So what ARE you feeling right now? 💭",
            "I'm glad that's not the case! Want to share what's actually on your mind? ✨"
        ]);
    }
    
    // EXPLICIT "I FEEL" DETECTION (Higher Priority)
    if (preg_match('/\b(i feel|i am|im|i\'m)\s+(.*)/', $messageLower, $matches)) {
        $feeling = trim($matches[2]);
        if (preg_match('/\b(bad|awful|terrible|crappy|shitty)\b/', $feeling)) {
            return "I'm sorry you're feeling that way. Do you want to vent about what's causing it? I'm here. 💙";
        }
        if (preg_match('/\b(good|great|fine|okay)\b/', $feeling)) {
            return "Glad to hear that! What's making you feel that way? ✨";
        }
    }
    
    // MULTIPLE EMOTIONS
    if (count($emotions) >= 2) {
        return randomPick([
            "I can tell there's a lot going on - multiple emotions at once. That's normal. What's strongest? 💙",
            "Mixed feelings are tough. What's the one thing weighing heaviest? 💭"
        ]);
    }
    
    // TIME-AWARE GREETINGS
    if (preg_match('/^(hi|hello|hey|hola|sup|greetings|good morning|good evening)\b/', $messageLower)) {
        $greetings = [
            'morning' => [
                'Stressed' => "Good morning! Day started heavy? Let's take it one step at a time. Coffee first? ☕💙",
                'Sad' => "Morning! Rough start? I'm here. Sometimes mornings are hardest. You're not alone. 🌅",
                'Neutral' => "Good morning! How's the day shaping up? I'm here to chat. ☀️",
                'Good' => "Morning! Love the positive energy! What's making this morning great? 🌟",
                'Incredible' => "Good morning superstar! Starting the day on fire! 🚀"
            ],
            'night' => [
                'Stressed' => "Hey! Late night worries? Let's work through what's keeping you up. 🌙",
                'Sad' => "Hi friend. Nighttime amplifies everything. You're not alone. Want to talk? 💙",
                'Neutral' => "Hello! Late night thoughts? I'm here whenever you need. 🌟",
                'Good' => "Hey! Nice to hear from you this evening! 🌙✨",
                'Incredible' => "Hello night owl! Love the energy even at this hour! 🌙✨"
            ]
        ];
        
        $greetingSet = $greetings[$timeOfDay] ?? $greetings['night'];
        return $greetingSet[$mood] ?? randomPick($greetingSet);
    }
    
    $intensity = $sentiment['intensity'];
    
    // HEALTH / PHYSICAL PAIN (New Category)
    if (preg_match('/\b(headache|pain|sick|ill|stomach|hurt|body|nausea|dizzy)\b/', $messageLower)) {
        return randomPick([
            "Physical pain makes everything harder. Have you taken a moment to rest or drink water? Please be gentle with your body. 🩹",
            "I'm sorry you're not feeling well physically. It's okay to do nothing today but recover. Health first. 💙",
            "That sounds uncomfortable. Listen to your body - it's telling you to slow down. Hope you feel better soon! 🌿"
        ]);
    }

    // MOTIVATION / PROCRASTINATION (New Category)
    if (preg_match('/\b(lazy|procrastinat|motivat|bored|stuck|do nothing)\b/', $messageLower)) {
        return randomPick([
            "Procrastination is often just fear or overwhelm in disguise. Try the '5 Minute Rule': commit to doing the task for just 5 minutes. ⏱️",
            "Feeling stuck? Momentum beats motivation. Just do one tiny, imperfect thing. Action creates inspiration, not the other way around. 🚀",
            "It's okay to have low energy days. Maybe your body needs rest, not a pep talk? 💭"
        ]);
    }
    
    // STRESS - with intensity
    if (preg_match('/(stress|stressed|stressing|anxious|anxiety|overwhelm|overwhelmed|worry|worried|worrying|pressure|nervous|panic|tense|tension)/i', $messageLower)) {
        $responses = [
            1 => [
                "Here's what helps with stress: 1) Take 5 deep breaths 2) Write down what's bothering you 3) Pick ONE thing to tackle first. You got this! 💪",
                "Stress tip: Try the 5-4-3-2-1 technique - name 5 things you see, 4 you can touch, 3 you hear, 2 you smell, 1 you taste. It grounds you instantly! 🌿"
            ],
            2 => [
                "When stressed, break it down: 1) What's the REAL problem? 2) What CAN you control? 3) Take one small action now. Progress beats perfection! 🎯",
                "Stress management tips: Take a 10-min walk, drink water, write a quick brain dump of everything on your mind, then prioritize top 3. 📝"
            ],
            3 => [
                "For intense stress: STOP → BREATHE (4 sec in, 6 sec out, 5 times) → Ask 'What's the ONE thing I can do RIGHT NOW?' → Do it. You're stronger than this moment! 🔥",
                "Emergency stress protocol: 1) 60 seconds of slow breathing 2) Step away from the situation 3) Call someone you trust 4) Remember: this will pass! 💙"
            ]
        ];
        return randomPick($responses[$intensity] ?? $responses[2]);
    }
    
    // SADNESS - with intensity
    if (preg_match('/\b(sad|depressed|down|low|lonely|empty|hopeless|hurt|cry|crying|upset|grief)\b/', $messageLower)) {
        $responses = [
            1 => [
                "Feeling low is okay. What's one tiny comfort? Even a song or warm drink. 🌸",
                "On low days: be extra kind to yourself. Today, just existing is enough. 💙"
            ],
            2 => [
                "That sounds painful. You don't have to 'fix' yourself now. Tomorrow might be lighter. 🌈",
                "Small steps: eaten? Had water? Talked to anyone? Start there. 💜"
            ],
            3 => [
                "This sounds extremely heavy. Please reach out to someone you trust or a counselor. You matter. 🆘",
                "That's a lot of pain. Please talk to a professional. Meanwhile: you're here, you're trying, that's brave. 💙"
            ]
        ];
        return randomPick($responses[$intensity] ?? $responses[2]);
    }
    
    // ANGER
    if (preg_match('/\b(angry|mad|furious|frustrated|annoyed|irritated|pissed|hate)\b/', $messageLower)) {
        return randomPick([
            "Anger is valid! Before reacting: 10 deep breaths, walk, or punch a pillow. Then decide with a clear head. 🔥",
            "I hear the frustration. Anger often masks hurt. Once cool: what's the real need? 💭",
            "Channel it productively: exercise, journal, or express to someone safe. Don't bottle it! 💪"
        ]);
    }
    
    // SLEEP - contextual
    if (preg_match('/\b(tired|exhausted|sleep|insomnia|sleepy|fatigue|awake)\b/', $messageLower)) {
        if ($timeOfDay == 'night' || $timeOfDay == 'morning') {
            return randomPick([
                "Can't sleep? 4-7-8 breathing (in-4, hold-7, out-8). No screens. Cool, dark room. 📚",
                "Insomnia: write tomorrow's worries, deal with them THEN. Your job now is rest. 😴",
                "Sleep hygiene: dim lights 1hr before bed, cool room, no screens 30min before. 🌙"
            ]);
        } else {
            return randomPick([
                "Exhausted? Power nap: 20 min max. Dark room. You'll wake refreshed! ⚡",
                "Fatigue makes everything harder. Hydrate, eat protein, rest if you can. 💤"
            ]);
        }
    }
    
    // WORK/STUDY
    if (preg_match('/\b(work|job|exam|test|deadline|assignment|study|project|boss|manager|professor|career)\b/', $messageLower)) {
        return randomPick([
            "Deadline stress? Eisenhower Matrix: Urgent+Important (do now), Important (schedule), rest (delegate/drop). 🎯",
            "Study overwhelm? Pomodoro: 25min focus, 5min break. Quality beats quantity. 📚",
            "Work piling up? Time blocking: specific hours for specific tasks. Productivity = focus, not hours. ⏰",
            "Exam anxiety? Active recall: close book, write what you remember. Beats re-reading 10x! 💡"
        ]);
    }
    
    // RELATIONSHIPS
    if (preg_match('/\b(friend|relationship|family|alone|social|people|partner|boyfriend|girlfriend|conflict|breakup|divorce)\b/', $messageLower)) {
        return randomPick([
            "Relationships are complex. You can love someone AND need distance. Your peace matters. Boundaries ≠ selfishness. 💕",
            "People stuff is hard! Ask: is this adding to or draining my life? You're allowed to step back. 🌿",
            "Conflict? Before talking: know your boundaries, stay calm, use 'I' statements. 'I feel X when Y' beats 'You always Z'. 🗣️"
        ]);
    }
    
    // POSITIVE
    if (preg_match('/\b(happy|great|awesome|amazing|wonderful|fantastic|excellent|better|excited|pumped|yay)\b/', $messageLower)) {
        return randomPick([
            "YES! Write down 3 specific things that made today great. Trains your brain to notice more good! 🌟",
            "Love this energy! What created it? Identify the pattern - more of THAT! ✨",
            "Amazing! Share it with someone - positive emotions multiply when expressed! 🎉"
        ]);
    }
    
    // GRATITUDE
    if (preg_match('/\b(thank|thanks|grateful|appreciate|thx)\b/', $messageLower)) {
        return randomPick([
            "You're welcome! Pro tip: gratitude journal - 3 things daily. Rewires your brain! 📝✨",
            "My pleasure! Expressing gratitude shows emotional intelligence. That's a superpower! 💜"
        ]);
    }
    
    // QUESTIONS
    if (preg_match('/\b(how are you|what.*you|who are you|tell me about yourself)\b/', $messageLower)) {
        return randomPick([
            "I'm Fiora - that friend who always has time to listen and never judges. 😊 How are YOU?",
            "I'm your 24/7 wellness companion! No rushing, just support. What's on your mind? 💜"
        ]);
    }
    
    // HELP
    if (preg_match('/\b(help|advice|what should i|don\'t know|confused|lost|stuck)\b/', $messageLower)) {
        return randomPick([
            "Let's figure this out. First: what are your options? List them all. Seeing them brings clarity. 🤔",
            "Stuck? Magic wand test: if you could do ANYTHING, what? Now, smallest step toward that? 💭"
        ]);
    }
    
    // CASUAL / FILLER (New Category)
    if (preg_match('/\b(meh|blah|ugh|okay|whatever|boring|bored)\b/', $messageLower)) {
        return randomPick([
            "Sounds like 'meh' energy today. That's valid. Sometimes doing absolutely nothing is exactly what you need. ☁️",
            "I get it. Some days just lack spark. Anything small that might lift the mood? Or just riding it out? 🐢"
        ]);
    }
    
    // SHORT MESSAGES
    if ($wordCount <= 3) {
        return randomPick([
            "I'm listening. What's behind that feeling? 💙",
            "Go on... sometimes starting is hardest. I'm here. ✨",
            "I hear you. Want to say more? 💭"
        ]);
    }
    
    // MOOD-BASED DEFAULTS
    $moodResponses = [
        'Stressed' => [
            "There's a lot on your mind. You don't need all answers now. Just the next step. What's ONE thing you control? 💙",
            "Stress hack: if everything feels urgent, NOTHING is. Priority test: what breaks if you don't do it TODAY? 🎯"
        ],
        'Sad' => [
            "I hear you. Low moments are human. They don't last forever. What's one gentle thing you can do for yourself? 🌸",
            "Healing isn't linear - some days are survival days. That's okay. Be kind to yourself. 💜"
        ],
        'Neutral' => [
            "Thanks for sharing. What's the main thing on your mind? 💭",
            "I appreciate you opening up. Want to talk through something specific? 💙"
        ],
        'Good' => [
            "Love this! What specifically is making today good? Let's identify the pattern! ✨",
            "Great! What are you most proud of or grateful for right now? 🌟"
        ],
        'Incredible' => [
            "Your energy is contagious! Don't just enjoy it - analyze it: what created this? 🚀",
            "Amazing vibes! Share the secret - others could learn from this! 🎉"
        ]
    ];
    
    return randomPick($moodResponses[$mood] ?? $moodResponses['Neutral']);
}

// Store in history
$_SESSION['chat_history'][] = [
    'message' => $message,
    'mood' => $moodContext,
    'timestamp' => time()
];

// Keep last 5
if (count($_SESSION['chat_history']) > 5) {
    $_SESSION['chat_history'] = array_slice($_SESSION['chat_history'], -5);
}

// Generate response - Try AI first, fallback to pattern matching
$reply = callAI($message, $moodContext, $_SESSION['chat_history']);

if (empty($reply)) {
    // Fallback to pattern matching if AI fails
    $reply = generateResponse($message, $moodContext, $_SESSION['chat_history']);
}

echo json_encode([
    'success' => true,
    'reply' => $reply
]);
?>
