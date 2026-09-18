<?php
require_once '../functions.php';
check_role('admin');
$conn = db_connect();

$success = '';
$error = '';
$tariff_list = []; // Масив ['id', 'cargo_type', 'price']

if (!$conn) {
    $error = 'Помилка підключення до бази даних';
} else {
    // Отримуємо всі можливі типи з ENUM (для випадаючого списку)
    // Але простіше: отримуємо всі записи з таблиці tariff, бо ми її наповнили всіма варіантами ENUM
    $result = $conn->query('SELECT id, cargo_type, price_per_km_ton FROM tariff');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $tariff_list[] = $row;
        }
        $result->free();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $tariff_id = $_POST['tariff_id'] ?? '';
        $new_price = $_POST['new_price'] ?? '';

        // Валідація: перевіряємо, чи існує такий ID у нашому списку
        $valid_id = false;
        foreach ($tariff_list as $item) {
            if ((string)$item['id'] === (string)$tariff_id) {
                $valid_id = true;
                break;
            }
        }

        if (!$valid_id) {
            $error = 'Невірний тариф';
        } elseif (!is_numeric($new_price) || $new_price < 0) {
            $error = 'Ціна має бути додатним числом';
        } else {
            // Оновлення ціни за ID
            $stmt = $conn->prepare('UPDATE tariff SET price_per_km_ton = ? WHERE id = ?');
            $stmt->bind_param('di', $new_price, $tariff_id);
            
            if ($stmt->execute()) {
                $success = 'Тариф успішно оновлено';
                // Оновлюємо ціну в локальному масиві для відображення
                foreach ($tariff_list as &$item) {
                    if ($item['id'] == $tariff_id) {
                        $item['price_per_km_ton'] = $new_price;
                    }
                }
            } else {
                $error = 'Помилка при оновленні тарифу';
            }
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <link rel="stylesheet" href="/styles.css">
    <meta charset="UTF-8">
    <title>X-Rails - Зміна тарифів</title>
</head>
<body>
    <header><h1>Зміна тарифів</h1></header>
    <main>
        <div class="tariff-edit-container">
            <nav class="nav-links"><a href="index.php" class="nav-btn">На головну</a> <a href="/logout.php" class="nav-btn logout-btn">Вийти</a></nav>
            <?php if ($success): ?><div class="toast-message visible" id="toast"><?php echo e($success); ?></div><?php endif; ?>
            <?php if ($error): ?><div class="error-message visible"><?php echo e($error); ?></div><?php endif; ?>
            
            <form action="admin_edit.php" method="post" class="tariff-edit-form">
                <div class="form-group">
                    <label for="tariff_id">Тип вантажу:</label>
                    <select id="tariff_id" name="tariff_id" required>
                        <?php 
                        echo render_options(
                            $tariff_list, 
                            fn($item) => $item['id'], 
                            // Показуємо Назву + Поточну ціну для зручності
                            fn($item) => $item['cargo_type'] . ' (поточна: ' . $item['price_per_km_ton'] . ' грн)', 
                            $_POST['tariff_id'] ?? ''
                        ); 
                        ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="new_price">Нова ціна за 1 км/тон (грн):</label>
                    <input type="number" id="new_price" name="new_price" min="0" step="0.1" required value="<?php echo e($_POST['new_price'] ?? ''); ?>">
                </div>
                <div class="form-group submit-group">
                    <button type="submit" class="submit-btn">Оновити тариф</button>
                </div>
            </form>
        </div>
    </main>
    <footer><p>© 2025 X-Rails. Для внутрішнього використання.</p></footer>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const toast = document.getElementById('toast');
            if (toast) { setTimeout(() => { toast.classList.remove('visible'); toast.style.opacity = '0'; }, 3000); }
        });
    </script>
</body>
</html>
