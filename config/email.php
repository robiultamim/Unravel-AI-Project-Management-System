<?php
// config/email.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require 'vendor/autoload.php'; // Install via: composer require phpmailer/phpmailer

class EmailSender {
    private $mail;
    
    public function __construct() {
        $this->mail = new PHPMailer(true);
        
        // Server settings
        $this->mail->isSMTP();
        $this->mail->Host       = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
        $this->mail->SMTPAuth   = true;
        $this->mail->Username   = getenv('SMTP_USERNAME') ?: 'your_email@gmail.com';
        $this->mail->Password   = getenv('SMTP_PASSWORD') ?: 'your_app_password';
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mail->Port       = getenv('SMTP_PORT') ?: 587;
        
        // Sender
        $fromEmail = getenv('SMTP_FROM_EMAIL') ?: (getenv('SMTP_USERNAME') ?: 'no-reply@unravel.local');
        $this->mail->setFrom($fromEmail, 'Unravel System');
        $this->mail->isHTML(true);
    }
    
    public function sendInvitation($toEmail, $toName, $projectName, $role, $tempPassword, $username) {
        try {
            // Recipient
            $this->mail->addAddress($toEmail, $toName);
            
            // Content
            $this->mail->Subject = 'Invitation to join ' . $projectName . ' on Unravel';
            
            $message = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: #4361ee; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                        .content { background: #f9f9f9; padding: 20px; }
                        .footer { background: #f5f7fb; padding: 15px; text-align: center; font-size: 12px; color: #666; }
                        .btn { display: inline-block; padding: 10px 20px; background: #4361ee; color: white; text-decoration: none; border-radius: 4px; color: #fff; }
                        .credentials { background: #e6f7ff; padding: 15px; border-radius: 4px; margin: 15px 0; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h2>Unravel Invitation</h2>
                        </div>
                        <div class='content'>
                            <p>Hello $toName,</p>
                            <p>You have been invited to join the project <strong>$projectName</strong> as a <strong>$role</strong>.</p>
                            
                            <div class='credentials'>
                                <h4>Your Login Credentials:</h4>
                                <p><strong>Email:</strong> $toEmail</p>
                                <p><strong>Username:</strong> $username</p>
                                <p><strong>Temporary Password:</strong> $tempPassword</p>
                                <p><em>Please change your password after first login.</em></p>
                            </div>
                            
                            <p>Click the button below to login to Unravel:</p>
                            <p>
                                <a href='http://localhost/unravel/login.php' class='btn'>Login to Unravel</a>
                            </p>
                            
                            <p>If you have any questions, please contact your administrator.</p>
                        </div>
                        <div class='footer'>
                            <p>This is an automated message from Unravel. Please do not reply to this email.</p>
                        </div>
                    </div>
                </body>
                </html>
            ";
            
            $this->mail->Body = $message;
            
            // Send email
            return $this->mail->send();
        } catch (Exception $e) {
            error_log("Email could not be sent. Error: {$this->mail->ErrorInfo}");
            return false;
        }
    }
    
    public function sendNotification($toEmail, $toName, $subject, $message) {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($toEmail, $toName);
            $this->mail->Subject = $subject;
            $this->mail->Body = $message;
            
            return $this->mail->send();
        } catch (Exception $e) {
            error_log("Notification email failed: {$this->mail->ErrorInfo}");
            return false;
        }
    }
}
?>