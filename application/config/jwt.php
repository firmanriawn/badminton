<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$config['jwt_key'] = 'rahasia';
$config['jwt_algorithm'] = 'HS256';
$config['jwt_expiry'] = 3600; // 1 hour for access token
$config['refresh_token_expiry'] = 604800; // 7 days for refresh token