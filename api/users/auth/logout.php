<?php

session_start();

if (function_exists('revokeCurrentDeviceSession')) {
	revokeCurrentDeviceSession();
}

setcookie('device_session', '', time() - 3600, '/', '', false, true);
session_unset();

if (isset($_COOKIE['token'])) {
	setcookie('token', '', time() - 3600, '/', '', false, true);
}

echo json_encode(['success' => true, 'message' => 'Logout successful']);
