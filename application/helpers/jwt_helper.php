<?php
use \Firebase\JWT\JWT;
use \Firebase\JWT\Key;
use \Firebase\JWT\ExpiredException;

function generate_jwt($user_id) {
    $CI =& get_instance();
    $key = $CI->config->item('jwt_key');
    $algorithm = $CI->config->item('jwt_algorithm');
    $expiry = $CI->config->item('jwt_expiry');

    $time = time();
    $jti = bin2hex(random_bytes(16));
    $payload = array(
        'iat' => $time,
        'exp' => $time + $expiry,
        'jti' => $jti,
        'iss' => 'backend_api',
        'aud' => 'frontend_app',
        'sub' => $user_id
    );

    return JWT::encode($payload, $key, $algorithm);
}

function generate_refresh_token($user_id) {
    $CI =& get_instance();
    $key = $CI->config->item('jwt_key');
    $algorithm = $CI->config->item('jwt_algorithm');
    $expiry = $CI->config->item('refresh_token_expiry');

    $time = time();
    $payload = array(
        'iat' => $time,
        'exp' => $time + $expiry,
        'user_id' => $user_id,
        'type' => 'refresh'
    );

    return JWT::encode($payload, $key, $algorithm);
}

function verify_jwt($token) {
    $CI =& get_instance();
    $key = $CI->config->item('jwt_key');
    $algorithm = $CI->config->item('jwt_algorithm');

    if (!$key || !$algorithm) {
        log_message('error', 'JWT key or algorithm not set in config');
        return false;
    }

    // Cek apakah token sudah ada di cache
    $cached_data = get_cached_token($token);
    if ($cached_data !== FALSE) {
        return $cached_data;
    }

    try {
        $decoded = JWT::decode($token, new Key($key, $algorithm));
        
        if ($decoded->iss !== 'backend_api' || $decoded->aud !== 'frontend_app') {
            return false;
        }

        if (isset($decoded->exp) && $decoded->exp < time()) {
            return false;
        }
        
        $CI->load->model('Token_model');
        if ($CI->Token_model->is_token_revoked($decoded->jti)) {
            return false;
        }
        
        // Simpan token yang sudah diverifikasi ke dalam cache
        cache_verified_token($token, $decoded);
        
        return $decoded;
    } catch (ExpiredException $e) {
        log_message('error', 'JWT expired: ' . $e->getMessage());
        return false;
    } catch (Exception $e) {
        log_message('error', 'JWT verification failed: ' . $e->getMessage());
        return false;
    }
}

function revoke_token($token) {
    $CI =& get_instance();
    $CI->load->model('Token_model');
    $decoded = verify_jwt($token);
    if ($decoded && isset($decoded->jti)) {
        return $CI->Token_model->revoke_token($decoded->jti);
    }
    return false;
}

function is_token_revoked($token) {
    $CI =& get_instance();
    $CI->load->model('Token_model');
    $decoded = verify_jwt($token);
    if ($decoded && isset($decoded->jti)) {
        return $CI->Token_model->is_token_revoked($decoded->jti);
    }
    return true;
}

function cache_verified_token($token, $decoded_data, $expiry_time = 300) {
    $CI =& get_instance();
    $CI->load->driver('cache', array('adapter' => 'file'));
    $cache_key = 'verified_token_' . md5($token);
    $CI->cache->save($cache_key, $decoded_data, $expiry_time);
}

function get_cached_token($token) {
    $CI =& get_instance();
    $CI->load->driver('cache', array('adapter' => 'file'));
    $cache_key = 'verified_token_' . md5($token);
    return $CI->cache->get($cache_key);
}