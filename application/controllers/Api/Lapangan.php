<?php
defined('BASEPATH') or exit('No direct script access allowed');

use chriskacerguis\RestServer\RestController;

class Lapangan extends RestController
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('lapangan_model');
        $this->load->model('user_model');
        $this->load->library('form_validation');
        $this->load->helper('jwt');
        $this->load->config('jwt');
        $this->load->driver('cache', array('adapter' => 'file'));
        $this->load->helper('encryption');
        $this->load->library('rate_limiter');
        $this->verify_and_set_user();
    }

    private function verify_and_set_user()
    {
        $token = $this->input->get_request_header('Authorization');
        $this->is_admin = false;

        if ($token) {
            $token = str_replace('Bearer ', '', $token);
            $decoded = verify_jwt($token);
            if ($decoded && isset($decoded->sub)) {
                $this->current_user = $this->user_model->get_user_by_id(decryption($decoded->sub));
                $this->is_admin = ($this->current_user && isset($this->current_user['role']) && $this->current_user['role'] === 'admin');
            }
        }
    }

    public function index_get()
    {
        $page = $this->get('page');
        $size = $this->get('size');

        $page = $page !== null ? $this->security->xss_clean($page) : 1;
        $size = $size !== null ? $this->security->xss_clean($size) : 10;

        $cache_key = "database/lapangan/page_{$page}_size_{$size}";

        if (!$response = $this->cache->get($cache_key)) {
            $page = (int)$page;
            $size = (int)$size;
            $offset = ($page - 1) * $size;

            $lapangan = $this->lapangan_model->get_all_lapangan($size, $offset);
            $total_lapangan = $this->lapangan_model->count_all_lapangan();

            $response = [
                'code' => 200,
                'message' => 'OK',
                'data' => [
                    'lapangan' => $lapangan,
                    'pagination' => [
                        'current_page' => $page,
                        'total_pages' => ceil($total_lapangan / $size),
                        'total_items' => $total_lapangan,
                        'items_per_page' => $size
                    ]
                ]
            ];

            $this->cache->save($cache_key, $response, 300); // Cache for 5 minutes
        }

        $this->response($response, 200);
    }

    public function create_post()
    {
        if (!$this->is_admin) {
            $this->response([
                'code' => 403,
                'message' => 'FORBIDDEN',
                'error' => ['message' => 'Access denied. Admin privileges required.']
            ], 403);
            return;
        }

        $this->form_validation->set_rules('nama', 'Nama Lapangan', 'required|max_length[100]');
        $this->form_validation->set_rules('deskripsi', 'Deskripsi', 'required');
        $this->form_validation->set_rules('jenis', 'Jenis Lapangan', 'required|in_list[futsal,badminton,basket,tenis]');
        $this->form_validation->set_rules('harga_per_jam', 'Harga per Jam', 'required|numeric');
        $this->form_validation->set_rules('kapasitas', 'Kapasitas', 'required|integer');
        $this->form_validation->set_rules('fasilitas', 'Fasilitas', 'required');

        if ($this->form_validation->run() == FALSE) {
            $this->response([
                'code' => 400,
                'message' => 'BAD_REQUEST',
                'error' => $this->form_validation->error_array()
            ], 400);
            return;
        }

        $data = [
            'nama' => $this->input->post('nama'),
            'deskripsi' => $this->input->post('deskripsi'),
            'jenis' => $this->input->post('jenis'),
            'harga_per_jam' => $this->input->post('harga_per_jam'),
            'kapasitas' => $this->input->post('kapasitas'),
            'fasilitas' => $this->input->post('fasilitas'),
            'status' => 'tersedia'
        ];

        $lapangan_id = $this->lapangan_model->create_lapangan($data);

        if ($lapangan_id) {
            $this->response([
                'code' => 201,
                'message' => 'CREATED',
                'data' => ['id' => $lapangan_id]
            ], 201);
        } else {
            $this->response([
                'code' => 500,
                'message' => 'INTERNAL_SERVER_ERROR',
                'error' => ['message' => 'Failed to create lapangan']
            ], 500);
        }
    }

    public function detail_get($id)
    {
        $lapangan = $this->lapangan_model->get_lapangan_by_id($id);

        if ($lapangan) {
            $this->response([
                'code' => 200,
                'message' => 'OK',
                'data' => $lapangan
            ], 200);
        } else {
            $this->response([
                'code' => 404,
                'message' => 'NOT_FOUND',
                'error' => ['message' => 'Lapangan not found']
            ], 404);
        }
    }

    public function update_put($id)
    {
        if (!$this->is_admin) {
            $this->response([
                'code' => 403,
                'message' => 'FORBIDDEN',
                'error' => ['message' => 'Access denied. Admin privileges required.']
            ], 403);
            return;
        }

        $lapangan = $this->lapangan_model->get_lapangan_by_id($id);

        if (!$lapangan) {
            $this->response([
                'code' => 404,
                'message' => 'NOT_FOUND',
                'error' => ['message' => 'Lapangan not found']
            ], 404);
            return;
        }

        $data = [];
        if ($this->put('nama')) $data['nama'] = $this->put('nama');
        if ($this->put('deskripsi')) $data['deskripsi'] = $this->put('deskripsi');
        if ($this->put('jenis')) $data['jenis'] = $this->put('jenis');
        if ($this->put('harga_per_jam')) $data['harga_per_jam'] = $this->put('harga_per_jam');
        if ($this->put('kapasitas')) $data['kapasitas'] = $this->put('kapasitas');
        if ($this->put('fasilitas')) $data['fasilitas'] = $this->put('fasilitas');
        if ($this->put('status')) $data['status'] = $this->put('status');

        if (empty($data)) {
            $this->response([
                'code' => 400,
                'message' => 'BAD_REQUEST',
                'error' => ['message' => 'No data to update']
            ], 400);
            return;
        }

        $updated = $this->lapangan_model->update_lapangan($id, $data);

        if ($updated) {
            $this->response([
                'code' => 200,
                'message' => 'OK',
                'data' => ['message' => 'Lapangan updated successfully']
            ], 200);
        } else {
            $this->response([
                'code' => 500,
                'message' => 'INTERNAL_SERVER_ERROR',
                'error' => ['message' => 'Failed to update lapangan']
            ], 500);
        }
    }

    public function delete_delete($id)
    {
        if (!$this->is_admin) {
            $this->response([
                'code' => 403,
                'message' => 'FORBIDDEN',
                'error' => ['message' => 'Access denied. Admin privileges required.']
            ], 403);
            return;
        }

        $lapangan = $this->lapangan_model->get_lapangan_by_id($id);

        if (!$lapangan) {
            $this->response([
                'code' => 404,
                'message' => 'NOT_FOUND',
                'error' => ['message' => 'Lapangan not found']
            ], 404);
            return;
        }

        $deleted = $this->lapangan_model->delete_lapangan($id);

        if ($deleted) {
            $this->response([
                'code' => 200,
                'message' => 'OK',
                'data' => ['message' => 'Lapangan deleted successfully']
            ], 200);
        } else {
            $this->response([
                'code' => 500,
                'message' => 'INTERNAL_SERVER_ERROR',
                'error' => ['message' => 'Failed to delete lapangan']
            ], 500);
        }
    }
}