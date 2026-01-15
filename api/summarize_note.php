<?php
// api/summarize_note.php
header('Content-Type: application/json');

// ---------------------------------------------------------
// 1. CONFIGURATION
// ---------------------------------------------------------
$apiKey = 'AIzaSyBQFulc_N0ZyuiGAIfQgunHSUw6xBIRroo'; 

// ---------------------------------------------------------
// 2. HELPER FUNCTIONS
// ---------------------------------------------------------

function sendError($message) {
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

// ---------------------------------------------------------
// 3. MAIN LOGIC
// ---------------------------------------------------------

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Invalid request method.');
}

// Initialize parts array with the instruction
$userParts = [];
$userParts[] = [
    "text" => "Please provide a clear, structured summary of the following notes. Combine the information from all pages into a cohesive study guide. Use bullet points, bold key terms, and ignore duplicate information."
];

$hasContent = false;

// 1. Handle Multiple Files (note_files[])
if (isset($_FILES['note_files'])) {
    $files = $_FILES['note_files'];
    $count = count($files['name']);

    for ($i = 0; $i < $count; $i++) {
        if ($files['error'][$i] === UPLOAD_ERR_OK) {
            $fileTmpPath = $files['tmp_name'][$i];
            $fileType = mime_content_type($fileTmpPath);
            
            // Validate
            $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
            if (in_array($fileType, $allowedTypes)) {
                $fileData = file_get_contents($fileTmpPath);
                $base64Data = base64_encode($fileData);
                
                $userParts[] = [
                    "inline_data" => [
                        "mime_type" => $fileType,
                        "data" => $base64Data
                    ]
                ];
                $hasContent = true;
            }
        }
    }
}

// 2. Handle Single File Legacy (note_file) - Just in case
if (isset($_FILES['note_file']) && $_FILES['note_file']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['note_file']['tmp_name'];
    $fileType = mime_content_type($fileTmpPath);
    $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
    
    if (in_array($fileType, $allowedTypes)) {
        $fileData = file_get_contents($fileTmpPath);
        $base64Data = base64_encode($fileData);
        $userParts[] = [
            "inline_data" => ["mime_type" => $fileType, "data" => $base64Data]
        ];
        $hasContent = true;
    }
}

// 3. Handle Text Input
if (isset($_POST['note_text']) && !empty(trim($_POST['note_text']))) {
    $userParts[] = [
        "text" => "Additional User Notes/Context:\n\n" . $_POST['note_text']
    ];
    $hasContent = true;
}

if (!$hasContent) {
    sendError('Please upload at least one valid file or enter text.');
}

// ---------------------------------------------------------
// 4. API CALL
// ---------------------------------------------------------

// Using Gemini 2.5 Flash as established
$apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-pro:generateContent?key=" . $apiKey;

$payload = [
    "contents" => [
        [
            "parts" => $userParts
        ]
    ]
];

// Send Request via cURL
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

$response = curl_exec($ch);

if (curl_errno($ch)) {
    sendError('Network error: ' . curl_error($ch));
}

curl_close($ch);

// Parse Response
$responseData = json_decode($response, true);

if (!isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
    if (isset($responseData['error']['message'])) {
        sendError('API Error: ' . $responseData['error']['message']);
    } else {
        sendError('Unknown backend error.');
    }
}

$summary = $responseData['candidates'][0]['content']['parts'][0]['text'];
echo json_encode(['success' => true, 'summary' => $summary]);
?>
