<?php
namespace App\Helpers;
class EncryptionHelper {
    private static $encryptionMethod = 'AES-256-CBC'; 
    private static $secretKey = 'ABCDEFGHIJK'; 

    public static function encrypt($plainText) {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::$encryptionMethod));
        $encryptedText = openssl_encrypt($plainText, self::$encryptionMethod, self::$secretKey, 0, $iv);
        return base64_encode($encryptedText . '::' . base64_encode($iv)); 
    }

    public static function decrypt($encryptedText) {
        $decoded = base64_decode($encryptedText);
        if (strpos($decoded, '::') === false) {
            error_log('Invalid encrypted format: Missing "::" separator.');
            return false;
        }
        list($encryptedData, $iv) = explode('::', $decoded, 2);
        $iv = base64_decode($iv);  
        return openssl_decrypt($encryptedData, self::$encryptionMethod, self::$secretKey, 0, $iv);
    }
}
