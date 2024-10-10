<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class JwtMiddleware {
    protected $CI;

    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->helper('jwt');
    }

    public function verify_token($params = array()) {
        $currentRoute = $this->CI->uri->uri_string();
        
        // Cek apakah rute saat ini dikecualikan
        if (isset($params['exclude']) && in_array($currentRoute, $params['exclude'])) {
            return;
        }

        $token = $this->CI->input->get_request_header('Authorization', TRUE);

        if ($token) {
            $token = str_replace('Bearer ', '', $token);
            $verified = verify_jwt($token);

            if ($verified && !is_token_revoked($token)) {
                // Token valid dan tidak dicabut, lanjutkan ke controller
                return;
            }
        }

        // Token tidak valid atau tidak ada, kirim respons error
        $response = [
            'code' => 401,
            'message' => 'UNAUTHORIZED',
            'error' => ['message' => 'Token tidak valid atau tidak ada']
        ];
        $this->CI->output
            ->set_content_type('application/json')
            ->set_status_header(401)
            ->set_output(json_encode($response));
        exit;
    }
}
