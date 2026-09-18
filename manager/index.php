<?php
require_once '../functions.php'; // Піднімаємось на 1 рівень до functions.php
check_role('manager'); // Перевірка ролі
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <link rel="stylesheet" href="/styles.css"> <meta charset="UTF-8">
    <title>X-Rails - Меню менеджера</title>
</head>
<body>
    <header>
        <h1>Меню менеджера</h1>
    </header>
    
    <main>
        <div class="menu-container">
            <a href="/logout.php" class="logout-link">Вийти</a> <ul class="menu-list">
                <li><a href="manager_view.php" class="menu-btn">Перегляд поточних тарифів</a></li>
                <li><a href="new_contract.php" class="menu-btn">Укладання контракту</a></li>
                <li><a href="shipping_organizing.php" class="menu-btn">Організація перевезення товару</a></li>
                <li><a href="register_arrival.php" class="menu-btn">Реєстрація прибуття товару на кінцеву станцію</a></li>
            </ul>
        </div>
    </main>
    
    <footer>
        <p>© 2025 X-Rails. Для внутрішнього використання.</p>
    </footer>
</body>
</html>
