<?php
require_once '../functions.php';
check_role('manager');
$conn = db_connect();

// Ініціалізація змінних
$error = '';
$success = '';
$contracts = [];
$form_data = [
    'contract_id' => '',
    'storage_cell_number' => ''
];

if (!$conn) {
    $error = 'Помилка підключення до бази даних';
} else {
    // Завантаження контрактів зі статусом 'in_transit'
    $result = $conn->query("SELECT id FROM contract WHERE status = 'in_transit'");
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $contracts[] = $row['id'];
        }
    } else {
        $error = 'Доступні контракти для реєстрації прибуття відсутні';
    }
    if ($result) {
        $result->free();
    }

    // Обробка форми
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $form_data['contract_id'] = $_POST['contract_id'] ?? '';
        $form_data['storage_cell_number'] = $_POST['storage_cell_number'] ?? '';

        // Валідація
        if (empty($form_data['contract_id']) || !in_array($form_data['contract_id'], $contracts)) {
            $error = 'Вкажіть коректний ID контракту';
        } elseif (empty($form_data['storage_cell_number']) || strlen($form_data['storage_cell_number']) > 20) {
            $error = 'Вкажіть коректний номер комірки зберігання (до 20 символів)';
        }

        // Перевірка, чи контракт уже має прибуття
        if (!$error) {
            $stmt = $conn->prepare('SELECT id FROM arrival WHERE contract_id = ?');
            $stmt->bind_param('i', $form_data['contract_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $error = 'Цей контракт уже має зареєстроване прибуття';
            }
            $stmt->close();
        }

        // Збереження прибуття
        if (!$error) {
            $arrival_datetime = date('Y-m-d H:i:s'); // Поточний системний час
            $stmt = $conn->prepare('INSERT INTO arrival (contract_id, storage_cell_number, arrival_datetime) VALUES (?, ?, ?)');
            $stmt->bind_param('iss', $form_data['contract_id'], $form_data['storage_cell_number'], $arrival_datetime);
            if ($stmt->execute()) {
                // Оновлення статусу контракту
                $stmt_update = $conn->prepare("UPDATE contract SET status = 'arrived' WHERE id = ?");
                $stmt_update->bind_param('i', $form_data['contract_id']);
                $stmt_update->execute();
                $stmt_update->close();
                $success = 'Прибуття зареєстровано успішно';
            } else {
                $error = 'Помилка при реєстрації прибуття';
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
    <title>X-Rails - Реєстрація прибуття</title>
</head>
<body>
    <header>
        <h1>Реєстрація прибуття</h1>
    </header>
    
    <main>
        <div id="root">
            <div class="contract-container">
                <nav class="nav-links">
                    <a href="index.php" class="nav-btn">На головну</a>
                    <a href="/logout.php" class="nav-btn logout-btn">Вийти</a>
                </nav>
                <form class="contract-form" method="post">
                    <div class="form-group">
                        <label for="contract_id">ID контракту:</label>
                        <select id="contract_id" name="contract_id" required>
                            <option value="" disabled selected>Оберіть контракт</option>
                            <?php foreach ($contracts as $id): ?>
                                <option value="<?php echo e($id); ?>" <?php echo $form_data['contract_id'] === $id ? 'selected' : ''; ?>>
                                    <?php echo e($id); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="storage_cell_number">Номер комірки зберігання:</label>
                        <input
                            type="text"
                            id="storage_cell_number"
                            name="storage_cell_number"
                            value="<?php echo e($form_data['storage_cell_number']); ?>"
                            maxlength="20"
                            required
                        />
                    </div>
                    <div class="form-group submit-group">
                        <button type="submit" class="submit-btn">Зареєструвати прибуття</button>
                    </div>
                </form>
            </div>
            <?php if ($success): ?>
                <div class="modal-overlay" id="success-modal">
<div class="modal-content">
            <h2>Успіх!</h2>
            <p><?php echo e($success); ?></p>
            <button class="modal-close-btn" onclick="window.location.href='index.php'">Закрити</button>
        </div>
                    </div>
            <?php elseif ($error): ?>
                <div class="modal-overlay" id="error-modal">
                    <div class="modal-content">
                        <h2 class="error">Помилка!</h2>
                        <p><?php echo e($error); ?></p>
                        <button class="modal-close-btn" onclick="<?php echo $error === 'Доступні контракти для реєстрації прибуття відсутні' ? 'window.location.href=\'index.php\'' : 'document.getElementById(\'error-modal\').style.display=\'none\''; ?>">Гаразд</button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <footer>
        <p>© 2025 X-Rails. Для внутрішнього використання.</p>
    </footer>
</body>
</html>
