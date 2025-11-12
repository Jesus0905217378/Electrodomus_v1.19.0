<?php
// public/logout.php
require_once __DIR__.'/../app/auth.php';
auth_logout();
header('Location: index.php');
