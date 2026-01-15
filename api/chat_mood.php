<?php
// api/chat_mood.php
header('Content-Type: application/json');

// 1. CONFIGURATION
$apiKey = 'AIzaSyBQFulc_N0ZyuiGAIfQgunHSUw6xBIRroo'; 

function sendError($message) {
    file_put_contents('debug_log.txt', date('[Y-m-d H:i:s] ') . $message . PHP_EOL, FILE_APPEND);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Invalid request method.');
}

$input = json_decode(file_get_contents('php://input'), true);
$message = $input['message'] ?? '';
$moodContext = $input['mood_context'] ?? 'Unknown';

if (empty($message)) {
    sendError('Message cannot be empty.');
}

// 2. CONSTRUCT PROMPT
// We want the AI to be a supportive friend.
$systemInstruction = "You are Fiora, a warm, empathetic, and supportive mental wellness companion. 
The user currently feels: '$moodContext'. 
Your goal is to listen, validate their feelings, and offer gentle encouragement or perspective. 
Keep responses concise (under 3 sentences) and conversational. Do not give medical advice.";

$prompt = $systemInstruction . "\n\nUser: " . $message . "\nFiora:";

// 3. API CALL (Gemini 1.5 Flash)
// 3. API CALL
$model = "gemini-2.5-pro";
$apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent?key=" . $apiKey;



$payload = [
    "contents" => [
        [
            "parts" => [
                ["text" => $prompt]
            ]
        ]
    ]
];

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

$response = curl_exec($ch);

if (curl_errno($ch)) {
    sendError('Network error: ' . curl_error($ch));
}
curl_close($ch);

$responseData = json_decode($response, true);

if (!isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
    
    $errMsg = "Unknown error";
    if (isset($responseData['error']['message'])) {
        $rawErr = $responseData['error']['message'];
        
        // Check for rate limit keywords
        if (strpos($rawErr, '429') !== false || strpos(strtolower($rawErr), 'quota') !== false) {
             $errMsg = "I'm receiving too many messages at once! Please give me 30 seconds to catch up. 😅";
        } else {
             $errMsg = "Brain freeze! ($rawErr)";
        }
        
        // Log locally
        file_put_contents('debug_log.txt', date('[Y-m-d H:i:s] ') . $model . " Error: " . $rawErr . PHP_EOL, FILE_APPEND);
    } else {
        $errMsg = "No response from AI ($model). Raw: " . substr($response, 0, 100);
    }
    
    sendError($errMsg);
}

$reply = $responseData['candidates'][0]['content']['parts'][0]['text'];

echo json_encode(['success' => true, 'reply' => $reply]);
?>
