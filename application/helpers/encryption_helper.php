<?php
defined('BASEPATH') OR exit('Akses skrip langsung tidak diizinkan');

if (!function_exists('encryption')) {
    function encryption($string) {
        $CI =& get_instance();
        $kunci_enkripsi = $CI->config->item('encryption_key');
        
        $metode = 'AES-256-CBC';
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($metode));
        
        $enkripsi = openssl_encrypt($string, $metode, $kunci_enkripsi, 0, $iv);
        return base64_encode($enkripsi . '::' . $iv);
    }
}

if (!function_exists('decryption')) {
    function decryption($string) {
        $CI =& get_instance();
        $kunci_enkripsi = $CI->config->item('encryption_key');
        
        list($enkripsi, $iv) = explode('::', base64_decode($string), 2);
        $metode = 'AES-256-CBC';
        
        return openssl_decrypt($enkripsi, $metode, $kunci_enkripsi, 0, $iv);
    }
}