# Implementation Plan - AI Note Summarizer (PDF & Image support)

This feature allows students to upload their study notes (in PDF or Image format) and receive an instant AI-generated summary using Google's Gemini API (or similar).

## Prerequisites
1.  **API Key**: You will need a Google Gemini API Key. You can get one for free at [aistudio.google.com](https://aistudio.google.com).
2.  **PHP Configuration**: Ensure `file_uploads` is enabled in `php.ini` (standard in XAMPP).

## Proposed Files

### 1. Backend Handler: `api/summarize_note.php`
This script will:
*   Receive the uploaded file via POST.
*   Validate the file type (PDF, JPEG, PNG).
*   Convert the file content to Base64.
*   Send a CURL request to the Gemini 1.5 Flash API endpoint.
*   Return the generated summary as JSON.

### 2. Frontend Update: `study.php`
*   Add a "Magic Summarizer" card to the Study Grid.
*   Include a drag-and-drop file zone.
*   Add JavaScript/AJAX to send the file to `summrize_note.php` and display the loading state/result.

## User Actions Required
After I implement the code, you must:
1.  Open `api/summarize_note.php`.
2.  Replace `'YOUR_GEMINI_API_KEY'` with your actual API key.

## Step-by-Step Implementation Guide

### Step 1: Create the Backend API
We will create a handling script that communicates with the LLM.

### Step 2: Add the UI to Study Space
We will insert a modern, glass-morphic card into the `study.php` layout.

### Step 3: Add Frontend Logic
We will add the necessary JavaScript to handle file uploads smoothly without refreshing the page.
