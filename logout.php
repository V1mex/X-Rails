<?php
require_once 'functions.php'; // Підключаємо для запуску сесії

session_destroy();
header('Location: /index.php'); // Повертаємо на сторінку входу
exit;
?>
