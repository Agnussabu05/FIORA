<?php
// api/test_gemini.php

// 1. YOUR KEY
require_once 'config.php';

// 2. CHECK MODELS
$url = "https://generativelanguage.googleapis.com/v1beta/models?key=" . $apiKey;
$response = file_get_contents($url);
$data = json_decode($response, true);

echo "\n--- AVAILABLE MODELS ---\n";
if (isset($data['models'])) {
    foreach ($data['models'] as $model) {
        if (isset($model['supportedGenerationMethods']) && in_array('generateContent', $model['supportedGenerationMethods'])) {
            echo $model['name'] . "\n";
        }
    }
}
echo "----------------------\n";
?>
