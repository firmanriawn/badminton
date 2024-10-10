<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Token_model extends CI_Model {
    
    public function is_token_revoked($jti) {
        $query = $this->db->get_where('revoked_tokens', array('jti' => $jti));
        return $query->num_rows() > 0;
    }
    
    public function revoke_token($jti) {
        return $this->db->insert('revoked_tokens', array('jti' => $jti));
    }
}
