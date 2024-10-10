<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$hook['post_controller_constructor'][] = array(
    array(
        'class'    => 'JwtMiddleware',
        'function' => 'verify_token',
        'filename' => 'JwtMiddleware.php',
        'filepath' => 'hooks',
        'params'   => array('exclude' => array('api/auth/signup','api/auth/signin'))
    ),
    array(
        'class'    => 'ActivityLogger',
        'function' => 'log_activity',
        'filename' => 'ActivityLogger.php',
        'filepath' => 'hooks'
    )
);
$hook['pre_system'][] = array(
    'class'    => 'Cors',
    'function' => 'handle',
    'filename' => 'Cors.php',
    'filepath' => 'hooks'
);