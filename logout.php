<?php
require_once __DIR__ . '/src/bootstrap.php';
session_unset();
session_destroy();
header("Location: " . BASE_URL . "/login");
exit();
