<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Configuration
$to_email = "info@strangeskies.app"; // Change this to your email
$subject = "New Strange Skies Beta Signup";

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Get form data
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';
    
    // Basic validation
    $errors = array();
    
    if (empty($name)) {
        $errors[] = "Name is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    // If no errors, send email
    if (empty($errors)) {
        
        // Email content
        $email_body = "New beta signup for Strange Skies:\n\n";
        $email_body .= "Name: " . $name . "\n";
        $email_body .= "Email: " . $email . "\n";
        $email_body .= "Message: " . ($message ? $message : "No message provided") . "\n\n";
        $email_body .= "Submitted on: " . date('Y-m-d H:i:s') . "\n";
        
        // Email headers
        $headers = "From: info@strangeskies.app\r\n"; // Change this to your domain
        $headers .= "Reply-To: " . $email . "\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        
        // Send email
        if (mail($to_email, $subject, $email_body, $headers)) {
            
            // Optional: Save to a file for backup
            $log_entry = date('Y-m-d H:i:s') . " - " . $name . " (" . $email . ")\n";
            file_put_contents('signups.txt', $log_entry, FILE_APPEND | LOCK_EX);
            
            // Return success response
            echo json_encode([
                'success' => true,
                'message' => 'We\'ve got your message!'
            ]);
            
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Sorry, there was an error sending your message. Please try again.'
            ]);
        }
        
    } else {
        // Return validation errors
        echo json_encode([
            'success' => false,
            'message' => implode('. ', $errors)
        ]);
    }
    
} else {
    // Not a POST request
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
?>
