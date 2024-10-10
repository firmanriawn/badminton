<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Rate_limiter {
    protected $CI;
    protected $cache_path;

    public function __construct() {
        $this->CI =& get_instance();
        $this->cache_path = APPPATH . 'cache/rate_limiting/';
        
        if (!is_dir($this->cache_path)) {
            mkdir($this->cache_path, 0755, true);
        }
    }

    public function limit($action, $max_attempts, $period) {
        $ip = $this->CI->input->ip_address();
        
        $key = md5($action . $ip);
        $file = $this->cache_path . $key;
        
        $current_time = time();
        
        if (file_exists($file)) {
            $data = unserialize(file_get_contents($file));
            
            if ($current_time - $data['start_time'] > $period) {
                // Periode telah berlalu, atur ulang penghitung
                $data = [
                    'count' => 1,
                    'start_time' => $current_time
                ];
            } else {
                // Periode masih berlangsung
                $data['count']++;
                
                if ($data['count'] > $max_attempts) {
                    return false; // Batas laju terlampaui
                }
            }
        } else {
            // File tidak ada, buat baru
            $data = [
                'count' => 1,
                'start_time' => $current_time
            ];
        }
        
        file_put_contents($file, serialize($data));
        return true; // Permintaan diizinkan
    }
}