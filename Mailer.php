<?php
require_once 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer {
    private $mailer;

    public function __construct() {
        $this->mailer = new PHPMailer(true);
        
        // Server settings
        $this->mailer->isSMTP();
        $this->mailer->Host = SMTP_HOST;
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = SMTP_USERNAME;
        $this->mailer->Password = SMTP_PASSWORD;
        $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mailer->Port = SMTP_PORT;
        $this->mailer->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $this->mailer->isHTML(true);
    }

    private function sendEmail($to, $name, $subject, $body) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($to, $name);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $body;
            
            return $this->mailer->send();
        } catch (Exception $e) {
            error_log("Mailer Error: " . $this->mailer->ErrorInfo);
            return false;
        }
    }

    public function send2FACode($email, $name, $code) {
        $subject = 'Your Two-Factor Authentication Code';
        $body = "
            <h2>Your Two-Factor Authentication Code</h2>
            <p>Hello $name,</p>
            <p>Your verification code is: <strong>$code</strong></p>
            <p>This code will expire in 10 minutes.</p>
            <p>If you didn't request this code, please ignore this email.</p>
        ";
        
        return $this->sendEmail($email, $name, $subject, $body);
    }

    public function sendPasswordResetEmail($to, $name, $resetLink) {
        $subject = "Password Reset Request";
        $body = "
            <html>
            <head>
                <title>Password Reset</title>
            </head>
            <body>
                <h2>Password Reset Request</h2>
                <p>Hello $name,</p>
                <p>We received a request to reset your password. Click the link below to reset it:</p>
                <p><a href='$resetLink' style='padding: 10px 15px; background-color: #4e73df; color: white; text-decoration: none; border-radius: 5px;'>Reset Password</a></p>
                <p>If you didn't request this, please ignore this email.</p>
                <p>This link will expire in 1 hour.</p>
            </body>
            </html>
        ";
        
        return $this->sendEmail($to, $name, $subject, $body);
    }

    public function sendPasswordChangedConfirmation($to, $name) {
        $subject = "Your Password Has Been Changed";
        $body = "
            <html>
            <head>
                <title>Password Changed</title>
            </head>
            <body>
                <h2>Password Changed Successfully</h2>
                <p>Hello $name,</p>
                <p>This is a confirmation that the password for your account has been successfully changed.</p>
                <p>If you did not make this change, please contact our support team immediately.</p>
                <p>Thank you,<br>" . htmlspecialchars(SITE_NAME) . "</p>
            </body>
            </html>
        ";
        
        return $this->sendEmail($to, $name, $subject, $body);
    }
}