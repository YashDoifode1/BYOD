<?php
require_once __DIR__.'/vendor/autoload.php';
use PragmaRX\Google2FA\Google2FA;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class TwoFactorAuth {
    private $google2fa;
    private $qrBackend;
    
    public function __construct() {
        $this->google2fa = new Google2FA();
        $this->google2fa->setWindow(4); // Allow 4 codes for time sync
        $this->determineBackend();
    }
    
    private function determineBackend() {
        $this->qrBackend = 'text'; // Default fallback
        
        if (extension_loaded('imagick')) {
            $this->qrBackend = 'imagick';
        } elseif (extension_loaded('gd')) {
            $this->qrBackend = 'gd';
        }
    }
    
    public function generateSecret() {
        return $this->google2fa->generateSecretKey(32);
    }
    
    public function getQRCodeUrl($issuer, $username, $secret) {
        return $this->google2fa->getQRCodeUrl(
            rawurlencode($issuer),
            rawurlencode($username),
            $secret
        );
    }
    
    public function generateQRCode($issuer, $username, $secret) {
        try {
            $qrCodeUrl = $this->getQRCodeUrl($issuer, $username, $secret);
            
            switch ($this->qrBackend) {
                case 'imagick':
                    return $this->generateQRCodeImagick($qrCodeUrl);
                case 'gd':
                    return $this->generateQRCodeGD($qrCodeUrl);
                default:
                    return $this->generateQRCodeText($issuer, $username, $secret, $qrCodeUrl);
            }
        } catch (Exception $e) {
            error_log("QR Generation Error: " . $e->getMessage());
            return $this->generateQRCodeText($issuer, $username, $secret, $qrCodeUrl);
        }
    }
    
    private function generateQRCodeImagick($qrCodeUrl) {
        $renderer = new ImageRenderer(
            new RendererStyle(300),
            new \BaconQrCode\Renderer\Image\ImagickImageBackEnd()
        );
        $writer = new Writer($renderer);
        return 'data:image/png;base64,' . base64_encode($writer->writeString($qrCodeUrl));
    }
    
    private function generateQRCodeGD($qrCodeUrl) {
        $renderer = new ImageRenderer(
            new RendererStyle(300),
            new \BaconQrCode\Renderer\Image\ImageBackEndGD()
        );
        $writer = new Writer($renderer);
        ob_start();
        $writer->writeString($qrCodeUrl);
        $image = ob_get_clean();
        return 'data:image/png;base64,' . base64_encode($image);
    }
    
    private function generateQRCodeText($issuer, $username, $secret, $qrCodeUrl) {
        return [
            'type' => 'text',
            'secret' => $secret,
            'url' => $qrCodeUrl,
            'instructions' => "Enter this secret manually in your authenticator app:",
            'manual_entry' => "otpauth://totp/".rawurlencode($issuer).":".rawurlencode($username)."?secret=".$secret."&issuer=".rawurlencode($issuer)
        ];
    }
    
    public function verifyCode($secret, $code) {
        try {
            return $this->google2fa->verifyKey($secret, $code);
        } catch (Exception $e) {
            error_log("2FA Verification Error: " . $e->getMessage());
            return false;
        }
    }
    
    public function generateBackupCodes($count = 5) {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = substr(strtoupper(bin2hex(random_bytes(10))), 0, 10); // 10-char codes
        }
        return $codes;
    }
    
    public function getCurrentCode($secret) {
        return $this->google2fa->getCurrentOtp($secret);
    }
}