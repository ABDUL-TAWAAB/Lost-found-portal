<?php
function encryptMessage($message){
    $cipher = "AES-256-CBC";
    $iv = random_bytes(openssl_cipher_iv_length($cipher));
    $encrypted = openssl_encrypt(
        $message,
        $cipher,
        ENCRYPTION_KEY,
        OPENSSL_RAW_DATA,
        $iv
    );
    return base64_encode($iv . $encrypted);
}

function decryptMessage($encryptedMessage){
    $cipher = "AES-256-CBC";
    $data = base64_decode($encryptedMessage);
    $ivLength = openssl_cipher_iv_length($cipher);
    $iv = substr($data, 0, $ivLength);
    $encrypted = substr($data, $ivLength);
    return openssl_decrypt(
        $encrypted,
        $cipher,
        ENCRYPTION_KEY,
        OPENSSL_RAW_DATA,
        $iv
    );
}
?>