<?php
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/razorpay_config.php';

header('Content-Type: application/json');

// Verify Razorpay payment signature
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $action = $data['action'] ?? '';
    
    if ($action === 'create_order') {
        // Create Razorpay Order
        $amount = floatval($data['amount']) * 100; // Convert to paise
        $book_id = intval($data['book_id']);
        $user_id = $_SESSION['user_id'];
        
        // Get book details
        $stmt = $pdo->prepare("SELECT b.*, u.full_name as seller_name FROM books b JOIN users u ON b.user_id = u.id WHERE b.id = ?");
        $stmt->execute([$book_id]);
        $book = $stmt->fetch();
        
        if (!$book) {
            echo json_encode(['success' => false, 'error' => 'Book not found']);
            exit;
        }
        
        // Create Razorpay Order via API
        $orderData = [
            'amount' => $amount,
            'currency' => 'INR',
            'receipt' => 'book_' . $book_id . '_' . time(),
            'notes' => [
                'book_id' => $book_id,
                'buyer_id' => $user_id,
                'book_title' => $book['title']
            ]
        ];
        
        $ch = curl_init('https://api.razorpay.com/v1/orders');
        curl_setopt($ch, CURLOPT_USERPWD, RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($orderData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            $order = json_decode($response, true);
            echo json_encode([
                'success' => true,
                'order_id' => $order['id'],
                'amount' => $order['amount'],
                'currency' => $order['currency'],
                'key' => RAZORPAY_KEY_ID,
                'book_title' => $book['title'],
                'seller_name' => $book['seller_name']
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to create order', 'details' => $response]);
        }
        exit;
    }
    
    if ($action === 'verify_payment') {
        $razorpay_order_id = $data['razorpay_order_id'] ?? '';
        $razorpay_payment_id = $data['razorpay_payment_id'] ?? '';
        $razorpay_signature = $data['razorpay_signature'] ?? '';
        $book_id = intval($data['book_id']);
        $user_id = $_SESSION['user_id'];
        
        // Verify signature
        $generated_signature = hash_hmac('sha256', $razorpay_order_id . '|' . $razorpay_payment_id, RAZORPAY_KEY_SECRET);
        
        if ($generated_signature === $razorpay_signature) {
            // Payment verified - Process the book purchase
            
            // Get book and seller info
            $stmt = $pdo->prepare("SELECT * FROM books WHERE id = ?");
            $stmt->execute([$book_id]);
            $book = $stmt->fetch();
            
            if ($book) {
                $seller_id = $book['user_id'];
                $price = $book['price'];
                
                // Record Transaction
                $stmt = $pdo->prepare("INSERT INTO book_transactions (book_id, seller_id, buyer_id, transaction_type, price, payment_id, payment_status, transaction_date) VALUES (?, ?, ?, 'buy', ?, ?, 'completed', NOW())");
                $stmt->execute([$book_id, $seller_id, $user_id, $price, $razorpay_payment_id]);
                
                // Update book status to Sold
                $stmt = $pdo->prepare("UPDATE books SET status = 'Sold' WHERE id = ?");
                $stmt->execute([$book_id]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Payment successful! Book purchased.'
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Book not found']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Payment verification failed']);
        }
        exit;
    }
}

echo json_encode(['success' => false, 'error' => 'Invalid request']);
?>
