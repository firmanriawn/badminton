<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class User_model extends CI_Model {

    public function create_user($data) {
        $this->db->insert('users', $data);
        return $this->db->insert_id();
    }

    public function get_user_by_email($email) {
        $query = $this->db->get_where('users', ['email' => $email]);
        return $query->row_array();
    }

    public function get_user_by_id($id) {
        $this->db->select('id, email, full_name, role, is_active, created_at');
        $query = $this->db->get_where('users', ['id' => $id]);
        return $query->row_array();
    }
    public function get_all_users($limit = null, $offset = null) {
        $this->db->select('id, email, full_name, role, is_active, created_at');
        if ($limit !== null && $offset !== null) {
            $this->db->limit($limit, $offset);
        }
        $query = $this->db->get('users');
        return $query->result_array();
    }

    public function count_all_users() {
        return $this->db->count_all('users');
    }

    public function update_user($id, $data) {
        $this->db->where('id', $id);
        return $this->db->update('users', $data);
    }

    public function delete_user($id) {
        $this->db->where('id', $id);
        return $this->db->delete('users');
    }
}