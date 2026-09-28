<?php
// Project Root Redirection to public/
require_once __DIR__ . '/config/config.php';
header('Location: ' . BASE_URL);
exit;
