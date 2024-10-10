<?php
defined('BASEPATH') or exit('No direct script access allowed');

use chriskacerguis\RestServer\RestController;

class Users extends RestController
{

    protected $is_admin = false;

    public function __construct()
    {
        parent::__construct();
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
        $this->is_admin = false; // Inisialisasi dengan false

        if ($token) {
            $token = str_replace('Bearer ', '', $token);
            $decoded = verify_jwt($token);
            if ($decoded && isset($decoded->sub)) {
                $this->current_user = $this->user_model->get_user_by_id(decryption($decoded->sub));
                $this->is_admin = ($this->current_user && isset($this->current_user['role']) && $this->current_user['role'] === 'admin');
            }
        }

        if (!$this->is_admin) {
            $this->response([
                'code' => 403,
                'message' => 'FORBIDDEN',
                'error' => ['message' => 'Access denied. Admin privileges required.']
            ], 403);
            exit; // Tambahkan exit untuk menghentikan eksekusi lebih lanjut
        }
    }

    public function index_get()
    {
        if (!$this->is_admin) {
            return;
        }

        $page = $this->get('page');
        $size = $this->get('size');

        $page = $page !== null ? $this->security->xss_clean($page) : 1;
        $size = $size !== null ? $this->security->xss_clean($size) : 10;

        $cache_key = "database/user/users_page_{$page}_size_{$size}";

        if (!$response = $this->cache->get($cache_key)) {
            $page = (int)$page;
            $size = (int)$size;
            $offset = ($page - 1) * $size;

            $users = $this->user_model->get_all_users($size, $offset);
            $total = $this->user_model->count_all_users();

            $response = [
                'code' => 200,
                'message' => 'SUCCESS',
                'data' => $users,
                'page' => [
                    'size' => (int)$size,
                    'total' => (int)$total,
                    'total_pages' => ceil($total / $size),
                    'current' => (int)$page
                ]
            ];

            $this->cache->save($cache_key, $response, 900); // Cache for 15 minutes
        }

        if (!$this->rate_limiter->limit('get_users', 100, 3600)) {
            $this->response([
                'code' => 429,
                'message' => 'TOO_MANY_REQUESTS',
                'error' => ['message' => 'Rate limit exceeded. Please try again later.']
            ], 429);
            return;
        }

        $this->response($response, 200);
    }

    public function user_get($id, $encrypt = false)
    {
        if (!$this->rate_limiter->limit('get_user', 200, 3600)) {
            $this->response([
                'code' => 429,
                'message' => 'TOO_MANY_REQUESTS',
                'error' => ['message' => 'Rate limit exceeded. Please try again later.']
            ], 429);
            return;
        }
        if ($encrypt) {
            $id = encryption(string: $id);
        } else {
            $id = $this->security->xss_clean($id);
        }
        $cache_key = "database/user/user_{$id}";

        if (!$user = $this->cache->get($cache_key)) {
            $user = $this->user_model->get_user_by_id($id);
            if ($user) {
                $this->cache->save($cache_key, $user, 900); // Cache for 15 minutes
            }
        }

        if (!$this->rate_limiter->limit('get_user', 200, 3600)) {
            $this->response([
                'code' => 429,
                'message' => 'TOO_MANY_REQUESTS',
                'error' => ['message' => 'Rate limit exceeded. Please try again later.']
            ], 429);
            return;
        }

        if ($user) {
            $this->response([
                'code' => 200,
                'message' => 'SUCCESS',
                'data' => $user
            ], 200);
        } else {
            $this->response([
                'code' => 404,
                'message' => 'NOT_FOUND',
                'error' => ['message' => 'User not found']
            ], 404);
        }
    }

    public function user_put($id)
    {
        if (!$this->rate_limiter->limit('put_user', 50, 3600)) {
            $this->response([
                'code' => 429,
                'message' => 'TOO_MANY_REQUESTS',
                'error' => ['message' => 'Rate limit exceeded. Please try again later.']
            ], 429);
            return;
        }
        $id = $this->security->xss_clean($id);
        $this->form_validation->set_data($this->put());
        $this->form_validation->set_rules('full_name', 'Full Name', 'required|max_length[100]');
        $this->form_validation->set_rules('email', 'Email', 'required|valid_email');

        if ($this->form_validation->run() == FALSE) {
            $this->response([
                'code' => 400,
                'message' => 'BAD_REQUEST',
                'error' => $this->form_validation->error_array()
            ], 400);
            return;
        }

        $update_data = [
            'full_name' => $this->put('full_name'),
            'email' => $this->put('email')
        ];

        if ($this->user_model->update_user($id, $update_data)) {
            $this->response([
                'code' => 200,
                'message' => 'SUCCESS',
                'data' => ['message' => 'User updated successfully']
            ], 200);
            $user_cache_file = glob(APPPATH . 'cache/database/user/user_' . $id);
            foreach ($user_cache_file as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }

            $users_page_cache_files = glob(APPPATH . 'cache/database/user/users_page_*');
            foreach ($users_page_cache_files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        } else {
            $this->response([
                'code' => 404,
                'message' => 'NOT_FOUND',
                'error' => ['message' => 'User not found or no changes made']
            ], 404);
        }
    }

    public function user_delete($id)
    {
        if (!$this->rate_limiter->limit('delete_user', 20, 3600)) {
            $this->response([
                'code' => 429,
                'message' => 'TOO_MANY_REQUESTS',
                'error' => ['message' => 'Rate limit exceeded. Please try again later.']
            ], 429);
            return;
        }
        $id = $this->security->xss_clean($id);
        if ($this->user_model->delete_user($id)) {
            $user_cache_file = glob(APPPATH . 'cache/database/user/user_' . $id);
            foreach ($user_cache_file as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }

            $users_page_cache_files = glob(APPPATH . 'cache/database/user/users_page_*');
            foreach ($users_page_cache_files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }

            $this->response([
                'code' => 200,
                'message' => 'SUCCESS',
                'data' => ['message' => 'User deleted successfully']
            ], 200);
        } else {
            // Response jika user tidak ditemukan
            $this->response([
                'code' => 404,
                'message' => 'NOT_FOUND',
                'error' => ['message' => 'User not found']
            ], 404);
        }
    }
}
