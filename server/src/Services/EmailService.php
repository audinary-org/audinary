<?php

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Email Service for sending emails using PHPMailer
 */
class EmailService
{
    /** @var array<string, mixed> */
    private array $config;
    private string $templatesDir;

    /** @param array<string, mixed> $smtpConfig */
    public function __construct(array $smtpConfig)
    {
        $this->config = $smtpConfig;
        $this->templatesDir = dirname(__DIR__) . '/templates/email';

        // Ensure templates directory exists
        if (!is_dir($this->templatesDir)) {
            mkdir($this->templatesDir, 0755, true);
        }
    }

    /**
     * Check if SMTP is enabled and configured
     */
    public function isEnabled(): bool
    {
        return !empty($this->config['enabled']) &&
            !empty($this->config['host']) &&
            !empty($this->config['username']) &&
            !empty($this->config['password']);
    }

    /**
     * Send password reset email
     */
    public function sendPasswordResetEmail(string $toEmail, string $toName, string $resetLink, string $username): bool
    {
        if (!$this->isEnabled()) {
            error_log("EmailService: SMTP not enabled or configured");
            return false;
        }

        try {
            $subject = 'Password Reset - ' . ($this->config['from_name'] ?? 'Audinary');
            $htmlBody = $this->getPasswordResetTemplate($resetLink, $username, $toName);
            $textBody = $this->getPasswordResetTextTemplate($resetLink, $username, $toName);

            return $this->sendEmail($toEmail, $toName, $subject, $htmlBody, $textBody);
        } catch (Exception $e) {
            error_log("EmailService: Failed to send password reset email: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send email using PHPMailer
     */
    private function sendEmail(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody = ''): bool
    {
        $mail = new PHPMailer(true);

        try {
            // Server settings
            if (!empty($this->config['debug'])) {
                $mail->SMTPDebug = SMTP::DEBUG_SERVER;
            }

            $mail->isSMTP();
            $mail->Host = $this->config['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->config['username'];
            $mail->Password = $this->config['password'];
            $mail->Port = $this->config['port'] ?? 587;

            // Encryption
            if (!empty($this->config['encryption'])) {
                if (strtolower($this->config['encryption']) === 'ssl') {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                } else {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                }
            }

            // Recipients
            $mail->setFrom($this->config['from_email'], $this->config['from_name'] ?? 'Audinary');
            $mail->addAddress($toEmail, $toName);
            $mail->addReplyTo($this->config['from_email'], $this->config['from_name'] ?? 'Audinary');

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;

            if ($textBody !== '' && $textBody !== '0') {
                $mail->AltBody = $textBody;
            }

            $mail->send();
            error_log("EmailService: Password reset email sent successfully to {$toEmail}");
            return true;
        } catch (Exception $e) {
            error_log("EmailService: Failed to send email to {$toEmail}: " . $mail->ErrorInfo);
            return false;
        }
    }

    /**
     * Get HTML template for password reset email
     */
    private function getPasswordResetTemplate(string $resetLink, string $username, string $displayName): string
    {
        $templateFile = $this->templatesDir . '/password_reset.html';

        if (file_exists($templateFile)) {
            $templateContent = file_get_contents($templateFile);
            $template = $templateContent === false ? $this->getDefaultPasswordResetTemplate() : $templateContent;
        } else {
            // Fallback template if file doesn't exist
            $template = $this->getDefaultPasswordResetTemplate();
        }

        // Replace placeholders
        $replacements = [
            '{{display_name}}' => htmlspecialchars($displayName !== '' && $displayName !== '0' ? $displayName : $username),
            '{{username}}' => htmlspecialchars($username),
            '{{reset_link}}' => $resetLink,
            '{{app_name}}' => htmlspecialchars($this->config['from_name'] ?? 'Audinary'),
            '{{current_year}}' => date('Y'),
            '{{validity_minutes}}' => '15'
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * Get plain text template for password reset email
     */
    private function getPasswordResetTextTemplate(string $resetLink, string $username, string $displayName): string
    {
        $appName = $this->config['from_name'] ?? 'Audinary';
        $name = $displayName !== '' && $displayName !== '0' ? $displayName : $username;

        return <<<TEXT
Hi {$name},

You have requested to reset your password for your {$appName} account.

To reset your password, please click on the following link or copy it into your browser:
{$resetLink}

This link is valid for 15 minutes and can only be used once.

If you did not request this password reset, please ignore this email.

Best regards,
{$appName} Team

---
This is an automated message, please do not reply to this email.
TEXT;
    }

    /**
     * Default HTML template for password reset email
     */
    private function getDefaultPasswordResetTemplate(): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset - {{app_name}}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #007bff;
            margin-bottom: 10px;
        }
        .title {
            color: #333;
            margin-bottom: 20px;
        }
        .content {
            margin-bottom: 30px;
        }
        .reset-button {
            display: inline-block;
            background-color: #007bff;
            color: white;
            padding: 12px 30px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin: 20px 0;
        }
        .reset-button:hover {
            background-color: #0056b3;
        }
        .warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .footer {
            border-top: 1px solid #eee;
            padding-top: 20px;
            margin-top: 30px;
            color: #666;
            font-size: 14px;
            text-align: center;
        }
        .link-fallback {
            word-break: break-all;
            color: #666;
            font-size: 14px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">{{app_name}}</div>
        </div>
        
        <h2 class="title">Password Reset Request</h2>
        
        <div class="content">
            <p>Hi {{display_name}},</p>
            
            <p>You have requested to reset your password for your {{app_name}} account (<strong>{{username}}</strong>).</p>
            
            <p>To reset your password, please click the button below:</p>
            
            <p style="text-align: center;">
                <a href="{{reset_link}}" class="reset-button">Reset Password</a>
            </p>
            
            <div class="warning">
                <strong>Important:</strong> This link is valid for {{validity_minutes}} minutes and can only be used once.
            </div>
            
            <p>If the button above doesn't work, copy and paste the following link into your browser:</p>
            <div class="link-fallback">{{reset_link}}</div>
            
            <p>If you did not request this password reset, please ignore this email. Your password will remain unchanged.</p>
        </div>
        
        <div class="footer">
            <p>Best regards,<br>{{app_name}} Team</p>
            <p>&copy; {{current_year}} {{app_name}}. This is an automated message, please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Test SMTP connection
     */
    /** @return array<string, mixed> */
    public function testConnection(): array
    {
        if (!$this->isEnabled()) {
            return [
                'success' => false,
                'message' => 'SMTP not enabled or not configured'
            ];
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $this->config['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->config['username'];
            $mail->Password = $this->config['password'];
            $mail->Port = $this->config['port'] ?? 587;

            if (!empty($this->config['encryption'])) {
                if (strtolower($this->config['encryption']) === 'ssl') {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                } else {
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                }
            }

            // Test connection
            $mail->smtpConnect();
            $mail->smtpClose();

            return [
                'success' => true,
                'message' => 'SMTP connection successful'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'SMTP connection failed: ' . $e->getMessage()
            ];
        }
    }
}
