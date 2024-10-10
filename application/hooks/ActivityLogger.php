<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class ActivityLogger {
    public function log_activity() {
        $CI =& get_instance();
        $CI->load->library('user_agent');
        $CI->load->helper('jwt');
        
        $token = $CI->input->get_request_header('Authorization');
        $user_id = null;
        
        if ($token) {
            $token = str_replace('Bearer ', '', $token);
            $decoded = verify_jwt($token);
            if ($decoded && isset($decoded->user_data->id)) {
                $user_id = $decoded->user_data->id;
            }
        }
        
        $data = array(
            'user_id' => $user_id,
            'activity' => $CI->router->fetch_class() . '/' . $CI->router->fetch_method(),
            'ip_address' => $CI->input->ip_address(),
            'user_agent' => $CI->agent->agent_string(),
            'created_at' => date('Y-m-d H:i:s')
        );
        
        $CI->db->insert('activity_logs', $data);
    }
}