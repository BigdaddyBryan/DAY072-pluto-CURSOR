<?php

session_start();
if (isset($_COOKIE['token'])) {
	setcookie('token', '', time() - 3600, '/', '', false, true);
}
session_destroy();
header("Location: /home");
exit;