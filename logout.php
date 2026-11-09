<?php
require_once __DIR__ . '/includes/bootstrap.php';
unset($_SESSION['user']);
header('Location: ' . BASE_URL . '/login.php');
exit;
