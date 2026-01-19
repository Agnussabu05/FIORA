<?php
// Test script to diagnose chatbot issues
header('Content-Type: text/plain');

echo "=== CHATBOT DIAGNOSTIC TEST ===\n\n";

// 1. Check if config file exists
echo "1. Config File Check:\n";
if (file_exists('api/config.php')) {
    echo "   ✓ api/config.php exists\n";
    require_once 'api/config.php';
} else {
    echo "   ✗ api/config.php NOT FOUND\n";
    exit;
}

// 2. Check API Key
echo "\n2. API Key Check:\n";
$apiKey = getenv("HF_TOKEN");
if ($apiKey && $apiKey !== 'YOUR_NEW_API_KEY_HERE') {
    echo "   ✓ HF_TOKEN environment variable is set\n";
    echo "   Key length: " . strlen($apiKey) . " characters\n";
} else {
    echo "   ✗ HF_TOKEN not set or invalid\n";
    echo "   Current value: " . ($apiKey ?: 'NULL') . "\n";
}

// 3. Check Model
echo "\n3. Model Configuration:\n";
if (isset($model)) {
    echo "   ✓ Model: $model\n";
} else {
    echo "   ✗ Model not set\n";
}

// 4. Test cURL
echo "\n4. cURL Test:\n";
if (function_exists('curl_version')) {
    echo "   ✓ cURL is available\n";
    $version = curl_version();
    echo "   Version: " . $version['version'] . "\n";
} else {
    echo "   ✗ cURL NOT available\n";
}

// 5. Test actual API call (if API key is set)
echo "\n5. API Connection Test:\n";
if ($apiKey && $apiKey !== 'YOUR_NEW_API_KEY_HERE') {
    $apiUrl = "https://api-inference.huggingface.co/models/$model";
    
    $payload = [
        "inputs" => "<s>[INST] Say 'Hello, I am working!' in 5 words or less. [/INST]",
        "parameters" => [
            "max_new_tokens" => 50,
            "temperature" => 0.7
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
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    
    echo "   Attempting to connect to Hugging Face API...\n";
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        echo "   ✗ cURL Error: " . curl_error($ch) . "\n";
    } else {
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        echo "   HTTP Status Code: $httpCode\n";
        
        $responseData = json_decode($response, true);
        
        if ($httpCode == 200) {
            echo "   ✓ API Connection Successful!\n";
            echo "   Response preview: " . substr($response, 0, 200) . "...\n";
        } else {
            echo "   ✗ API Error\n";
            echo "   Response: " . $response . "\n";
        }
    }
    curl_close($ch);
} else {
    echo "   ⊘ Skipped (API key not configured)\n";
}

echo "\n=== END OF DIAGNOSTIC ===\n";
?>
