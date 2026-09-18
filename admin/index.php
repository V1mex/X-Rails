<?php
require_once '../functions.php';
check_role('admin');
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <link rel="stylesheet" href="/styles.css">
    <meta charset="UTF-8">
    <title>X-Rails - Меню адміністратора</title>
</head>
<body>
    <header>
        <h1>Меню адміністратора</h1>
    </header>

    <main>
        <div class="menu-container">
            <nav class="nav-links">
                <a href="/logout.php" class="logout-link">Вийти</a>
            </nav>
            <ul class="menu-list">
                <li><a href="admin_view.php" class="menu-btn">Перегляд поточних тарифів</a></li>
                <li><a href="admin_edit.php" class="menu-btn">Зміна тарифів</a></li>
                <li><a href="extra_statistics_info.php" class="menu-btn">Перегляд додаткової статистичної інформації</a></li>
            </ul>
        </div>
    </main>

    <footer>
        <p>© 2025 X-Rails. Для внутрішнього використання.</p>
    </footer>
</body>
</html>
