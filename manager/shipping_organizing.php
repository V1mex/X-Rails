<?php
require_once '../functions.php';
check_role('manager');
$conn = db_connect();

// Ініціалізація змінних
$error = '';
$success = '';
$contracts = [];
$wagon_types = [
    'closed' => 'Критий',
    'open' => 'Платформа',
    'cistern' => 'Цистерна'
];
$form_data = [
    'contract_id' => '',
    'wagon_type' => 'closed',
    'track_number' => '',
    'departure_datetime' => ''
];

if (!$conn) {
    $error = 'Помилка підключення до бази даних';
} else {
    // Завантаження контрактів зі статусом 'new'
    $result = $conn->query("SELECT id FROM contract WHERE status = 'new'");
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $contracts[] = $row['id'];
        }
    } else {
        $error = 'Нові контракти для організації транспортування відсутні';
    }
    if ($result) {
        $result->free();
    }

    // Обробка форми
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $form_data['contract_id'] = $_POST['contract_id'] ?? '';
        $form_data['wagon_type'] = $_POST['wagon_type'] ?? '';
        $form_data['track_number'] = $_POST['track_number'] ?? '';
        $form_data['departure_datetime'] = $_POST['departure_datetime'] ?? '';

        // Валідація
        if (empty($form_data['contract_id']) || !in_array($form_data['contract_id'], $contracts)) {
            $error = 'Вкажіть коректний ID контракту';
        } elseif (empty($form_data['wagon_type']) || !array_key_exists($form_data['wagon_type'], $wagon_types)) {
            $error = 'Виберіть коректний тип вагона';
        } elseif (empty($form_data['track_number']) || !is_numeric($form_data['track_number']) || $form_data['track_number'] < 1) {
            $error = 'Вкажіть коректний номер колії';
        } elseif (empty($form_data['departure_datetime']) || strtotime($form_data['departure_datetime']) < time()) {
            $error = 'Дата відправлення не може бути в минулому';
        } else {
            // Перевірка track_number
            $stmt = $conn->prepare('SELECT s.track_count FROM station s JOIN contract c ON s.id = c.start_station_id WHERE c.id = ?');
            $stmt->bind_param('i', $form_data['contract_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                if ($form_data['track_number'] > $row['track_count']) {
                    $error = 'Номер колії перевищує кількість доступних колій на станції';
                }
            } else {
                $error = 'Не вдалося перевірити станцію відправлення';
            }
            $stmt->close();
        }

        // Збереження транспортування
        if (!$error) {
            // Перевірка, чи контракт уже має транспортування
            $stmt = $conn->prepare('SELECT id FROM transportation WHERE contract_id = ?');
            $stmt->bind_param('i', $form_data['contract_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                $error = 'Цей контракт уже має організоване транспортування';
            }
            $stmt->close();

            if (!$error) {
                $stmt = $conn->prepare('INSERT INTO transportation (contract_id, wagon_type, track_number, departure_datetime) VALUES (?, ?, ?, ?)');
                $stmt->bind_param('isis', $form_data['contract_id'], $form_data['wagon_type'], $form_data['track_number'], $form_data['departure_datetime']);
                
                if ($stmt->execute()) {
                    // Оновлення статусу контракту
                    $stmt_update = $conn->prepare("UPDATE contract SET status = 'in_transit' WHERE id = ?");
                    $stmt_update->bind_param('i', $form_data['contract_id']);
                    $stmt_update->execute();
                    $stmt_update->close();
                    $success = 'Транспортування організовано успішно';
                } else {
                    $error = 'Помилка при організації транспортування';
                }
                $stmt->close();
            }
        }
    }
}

// Підготовка значень для розділених полів дати і часу
$val_date = '';
$val_time = '12:00'; // Значення за замовчуванням для часу
if (!empty($form_data['departure_datetime'])) {
    $ts = strtotime($form_data['departure_datetime']);
    $val_date = date('Y-m-d', $ts);
    $val_time = date('H:i', $ts);
}
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <link rel="stylesheet" href="/styles.css">
    <meta charset="UTF-8">
    <title>X-Rails - Організація транспортування</title>
    <style>
        .datetime-container { position: relative; width: 100%; }
        
        .time-picker-icon { 
            background: #ccc; 
            border: none; 
            width: 40px;          /* Трохи збільшив ширину для пропорційності */
            /* height: 100%;      <-- ВИДАЛЕНО: це викликало сплющення */
            align-self: stretch;  /* ДОДАНО: кнопка розтягується під висоту інпутів */
            border-radius: 4px;   /* Трохи округліші краї, щоб пасувало до полів */
            cursor: pointer; 
            font-size: 18px;      /* Трохи більший годинник */
            display: flex; 
            align-items: center; 
            justify-content: center; 
            padding: 0; 
            margin-left: 0;       /* Відступ контролюється через gap у батьківському div */
        }
        .time-picker-icon:hover { background: #bbb; }
        
        .time-picker-container { position: absolute; background: white; border: 1px solid #ccc; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); z-index: 1000; padding: 0.5rem; right: 0; top: 100%; margin-top: 5px; display: none; width: 220px; }
        .time-picker-container .time-section { flex: 1; margin-right: 0.5rem; }
        .time-picker-container .time-section:last-child { margin-right: 0; }
        .time-picker-container select { width: 100%; }
        .time-picker-container button { background: linear-gradient(45deg, #ff8c00, #ffa500); color: white; border: none; padding: 0.5rem; border-radius: 2px; cursor: pointer; width: 90%; }
        .time-picker-container button:hover { background: linear-gradient(45deg, #e07b00, #ff8c00); }
    </style>
</head>
<body>
    <header>
        <h1>Організація транспортування</h1>
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
                            <?php 
                            echo render_options(
                                $contracts, 
                                fn($id) => $id, 
                                fn($id) => $id, 
                                $form_data['contract_id']
                            ); 
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="wagon_type">Тип вагона:</label>
                        <select id="wagon_type" name="wagon_type" required>
                            <?php 
                            echo render_options(
                                array_keys($wagon_types), 
                                fn($key) => $key, 
                                fn($key) => $wagon_types[$key], 
                                $form_data['wagon_type']
                            ); 
                            ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="track_number">Номер колії:</label>
                        <input type="number" id="track_number" name="track_number" min="1" value="<?php echo e($form_data['track_number']); ?>" required />
                    </div>
                    
                    <div class="form-group">
                        <label>Дата та час відправлення:</label>
                        <div class="datetime-container" style="display: flex; gap: 10px;">
                            <input type="date" id="date_part" value="<?php echo $val_date; ?>" required onchange="updateFullDateTime()" style="flex: 2;" />
                            
                            <input type="text" id="time_part" value="<?php echo $val_time; ?>" readonly onclick="toggleTimePicker()" style="flex: 1; text-align: center; cursor: pointer;" />
                            
                            <input type="hidden" id="departure_datetime" name="departure_datetime" value="<?php echo e($form_data['departure_datetime']); ?>" />
                            
                            <button type="button" class="time-picker-icon" onclick="toggleTimePicker()">🕓</button>
                            
                            <div id="time-picker" class="time-picker-container">
                                <div style="display: flex; justify-content: space-between;">
                                    <div class="time-section">
                                        <label for="time-hours">Години</label>
                                        <select id="time-hours">
                                            <?php for ($i = 0; $i < 24; $i++): ?>
                                                <option value="<?php echo str_pad((string)$i, 2, '0', STR_PAD_LEFT); ?>"><?php echo str_pad((string)$i, 2, '0', STR_PAD_LEFT); ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                    <div class="time-section">
                                        <label for="time-minutes">Хвилини</label>
                                        <select id="time-minutes">
                                            <?php for ($i = 0; $i < 60; $i += 5): ?>
                                                <option value="<?php echo str_pad((string)$i, 2, '0', STR_PAD_LEFT); ?>"><?php echo str_pad((string)$i, 2, '0', STR_PAD_LEFT); ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </div>
                                </div>
                                <hr style="margin: 0.5rem 0;">
                                <button type="button" onclick="applyTime()">Застосувати</button>
                            </div>
                        </div>
                    </div>

                    <div class="form-group submit-group">
                        <button type="submit" class="submit-btn">Організувати транспортування</button>
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
                        <button class="modal-close-btn" onclick="<?php echo $error === 'Нові контракти для організації транспортування відсутні' ? 'window.location.href=\'index.php\'' : 'document.getElementById(\'error-modal\').style.display=\'none\''; ?>">Гаразд</button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <footer><p>© 2025 X-Rails. Для внутрішнього використання.</p></footer>

    <script>
        function updateFullDateTime() {
            const dateVal = document.getElementById('date_part').value;
            const timeVal = document.getElementById('time_part').value;
            const hiddenInput = document.getElementById('departure_datetime');

            // Якщо дата вибрана, склеюємо її з часом
            if (dateVal && timeVal) {
                hiddenInput.value = `${dateVal}T${timeVal}`;
            } else {
                hiddenInput.value = '';
            }
        }

        function toggleTimePicker() {
            const timePicker = document.getElementById('time-picker');
            const isVisible = timePicker.style.display === 'block';
            timePicker.style.display = isVisible ? 'none' : 'block';

            // Синхронізуємо select-и з поточним значенням поля часу
            if (!isVisible) {
                const currentVals = document.getElementById('time_part').value.split(':');
                if (currentVals.length === 2) {
                    document.getElementById('time-hours').value = currentVals[0];
                    document.getElementById('time-minutes').value = currentVals[1];
                }
            }
        }

        function applyTime() {
            const hours = document.getElementById('time-hours').value;
            const minutes = document.getElementById('time-minutes').value;
            
            // Встановлюємо значення в поле відображення часу
            document.getElementById('time_part').value = `${hours}:${minutes}`;
            
            // Оновлюємо головне приховане поле
            updateFullDateTime();
            
            document.getElementById('time-picker').style.display = 'none';
        }

        // Закриття пікера при кліку поза ним
        document.addEventListener('click', function(event) {
            const timePicker = document.getElementById('time-picker');
            const timePickerIcon = document.querySelector('.time-picker-icon');
            const timeInput = document.getElementById('time_part');
            
            if (timePicker && timePicker.style.display === 'block') {
                if (!timePicker.contains(event.target) && 
                    !timePickerIcon.contains(event.target) && 
                    !timeInput.contains(event.target)) {
                    timePicker.style.display = 'none';
                }
            }
        });
    </script>
</body>
</html>
