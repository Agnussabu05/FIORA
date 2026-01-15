<?php
// api/test_gemini.php

// 1. YOUR KEY
$apiKey = 'AIzaSyBQFulc_N0ZyuiGAIfQgunHSUw6xBIRroo'; 

// 2. CHECK MODELS
$url = "https://generativelanguage.googleapis.com/v1beta/models?key=" . $apiKey;
$response = file_get_contents($url);
$data = json_decode($response, true);

echo "<h1>Gemini API Diagnosis</h1>";

if (isset($data['error'])) {
    echo "<h3 style='color: red'>API Error:</h3>";
    echo "Message: " . $data['error']['message'] . "<br>";
    echo "This usually means the API Key is invalid or the API is not enabled for this project.";
    exit;
}

echo "<h3>Available Models for your Key:</h3>";
echo "<p>We are looking for models that support 'generateContent'.</p>";
echo "<ul style='font-family: monospace; background: #f4f4f4; padding: 20px;'>";

$foundFlash = false;

if (isset($data['models'])) {
    foreach ($data['models'] as $model) {
        if (isset($model['supportedGenerationMethods']) && in_array('generateContent', $model['supportedGenerationMethods'])) {
            $color = 'black';
            if (strpos($model['name'], 'flash') !== false) {
                $color = 'green';
                $foundFlash = true;
            }
            echo "<li style='color: $color'>" . $model['name'] . "</li>";
        }
    }
}

echo "</ul>";

if ($foundFlash) {
    echo "<h3 style='color: green'>Good News! Flash model is available.</h3>";
} else {
    echo "<h3 style='color: red'>Warning: No Flash model found. You might need to use 'models/gemini-pro'.</h3>";
}
?>
