<?php
// Simple API test
require_once 'api/config.php';

echo "Testing Hugging Face API...\n\n";
echo "API Key loaded: " . ($apiKey ? "YES (length: " . strlen($apiKey) . ")" : "NO") . "\n";
echo "Model: $model\n\n";

if (!$apiKey || $apiKey === 'YOUR_NEW_API_KEY_HERE') {
    die("ERROR: API key not configured!\n");
}

$apiUrl = "https://api-inference.huggingface.co/models/$model";

$payload = [
    "inputs" => "<s>[INST] Hello! [/INST]",
    "parameters" => [
        "max_new_tokens" => 50,
        "temperature" => 0.7,
        "return_full_text" => false
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

echo "Sending request...\n";
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

echo "HTTP Code: $httpCode\n";

if (curl_errno($ch)) {
    echo "cURL Error: " . curl_error($ch) . "\n";
} else {
    echo "Response:\n";
    echo $response . "\n";
}

curl_close($ch);
?>
