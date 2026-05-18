<?php

define('ENCRYPT_KEY', $_ENV['ENCRYPT_KEY'] ?? 'your-secret-key-here-32chars!!'); // store in .env ideally
define('ENCRYPT_CIPHER', 'AES-256-CBC');

function encrypt(string $value): string
{
    $iv = random_bytes(openssl_cipher_iv_length(ENCRYPT_CIPHER));
    $encrypted = openssl_encrypt($value, ENCRYPT_CIPHER, ENCRYPT_KEY, 0, $iv);
    return base64_encode($encrypted . '::' . base64_encode($iv));
}

function decrypt(string $value): string|false
{
    $decoded = base64_decode($value);
    [$encrypted, $iv] = explode('::', $decoded, 2);
    return openssl_decrypt($encrypted, ENCRYPT_CIPHER, ENCRYPT_KEY, 0, base64_decode($iv));
}