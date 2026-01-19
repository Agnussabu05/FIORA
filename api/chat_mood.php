<?php
// api/chat_mood.php
header('Content-Type: application/json');

// 1. CONFIGURATION
require_once 'config.php'; // Loads $apiKey and $model

function sendError($message) {
    file_put_contents('debug_log.txt', date('[Y-m-d H:i:s] ') . $message . PHP_EOL, FILE_APPEND);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Invalid request method.');
}

if ($apiKey === 'YOUR_NEW_API_KEY_HERE') {
    sendError('API Key not configured. Please edit api/config.php');
}

$input = json_decode(file_get_contents('php://input'), true);
$message = $input['message'] ?? '';
$moodContext = $input['mood_context'] ?? 'Unknown';

if (empty($message)) {
    sendError('Message cannot be empty.');
}

// 2. CONSTRUCT PROMPT (Mistral Format)
$systemInstruction = "You are Fiora, a warm, empathetic mental wellness companion. The user feels: '$moodContext'. Validate feelings, offer gentle perspective. Keep it short (under 3 sentences). Do not give medical advice.";

// Mistral/Llama Instruction Format
$formattedPrompt = "<s>[INST] " . $systemInstruction . "\n\nUser: " . $message . " [/INST]";

// 3. API CALL (Hugging Face)
$apiUrl = "https://api-inference.huggingface.co/models/$model";

$payload = [
    "inputs" => $formattedPrompt,
    "parameters" => [
        "max_new_tokens" => 250,
        "temperature" => 0.7,
        "top_p" => 0.9,
        "return_full_text" => false // Important to get only the answer
    ]
];

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $apiKey
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
// Fix for local XAMPP SSL issues
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); 
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

$response = curl_exec($ch);

if (curl_errno($ch)) {
    sendError('Network error: ' . curl_error($ch));
}
curl_close($ch);

$responseData = json_decode($response, true);

// Handle HF Errors
if (isset($responseData['error'])) {
    // If model is loading
    if (strpos($responseData['error'], 'currently loading') !== false) {
        // Wait 20 seconds and try again? Or just tell user.
        sendError("Brain warming up! Please try again in 20 seconds. 🥶 -> �");
    }
    sendError('HF Error: ' . $responseData['error']);
}

// Handle Response
// HF returns a list: [ {"generated_text": "..."} ]
if (isset($responseData[0]['generated_text'])) {
    $reply = trim($responseData[0]['generated_text']);
    
    // Cleanup if full text returned
    $reply = str_replace($formattedPrompt, '', $reply);
    
    echo json_encode(['success' => true, 'reply' => $reply]);
} else {
    sendError("Unknown response format: " . substr($response, 0, 100));
}
?>
