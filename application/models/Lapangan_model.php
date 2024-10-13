<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Lapangan_model extends CI_Model {

    public function create_lapangan($data) {
        $this->db->insert('lapangan', $data);
        return $this->db->insert_id();
    }

    public function get_lapangan_by_id($id) {
        $query = $this->db->get_where('lapangan', ['id' => $id]);
        return $query->row_array();
    }

    public function get_all_lapangan($limit = null, $offset = null) {
        if ($limit !== null && $offset !== null) {
            $this->db->limit($limit, $offset);
        }
        $query = $this->db->get('lapangan');
        return $query->result_array();
    }

    public function count_all_lapangan() {
        return $this->db->count_all('lapangan');
    }

    public function update_lapangan($id, $data) {
        $this->db->where('id', $id);
        return $this->db->update('lapangan', $data);
    }

    public function delete_lapangan($id) {
        $this->db->where('id', $id);
        return $this->db->delete('lapangan');
    }
}