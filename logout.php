<?php
session_start();
session_unset();
session_destroy();
header('Location: /tesoreria/login.php');
exit;