<?php
session_start();
session_start();
session_destroy();
setcookie("remember_token", "", time() - 3600, "/"); // ลบ token
header("Location: index.php");
exit;
?>