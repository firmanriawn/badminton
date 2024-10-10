<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$route['default_controller'] = 'welcome';
$route['404_override'] = '';
$route['translate_uri_dashes'] = FALSE;

//Api Routes

//Auth Service
$route['api/auth/signup']['post'] = 'api/auth/signup';
$route['api/auth/signin']['post'] = 'api/auth/signin';
$route['api/auth/logout']['post'] = 'api/auth/logout';

// Updated routes for Users
$route['api/users']['get'] = 'api/users/index';
$route['api/user/(:num)']['get'] = 'api/users/user/$1';
$route['api/user/(:num)']['put'] = 'api/users/user/$1';
$route['api/user/(:num)']['delete'] = 'api/users/user/$1';
