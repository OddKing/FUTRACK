<?php
$_POST['name'] = 'Test User';
$_POST['email'] = 'test' . time() . '@example.com';
$_POST['password'] = 'password123';
$_GET['action'] = 'register';
require 'api/auth.php';
?>
