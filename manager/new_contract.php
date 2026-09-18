<?php
// --- ТИМЧАСОВИЙ КОД ДЛЯ НАЛАГОДЖЕННЯ ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// ---------------------------------------

require_once '../functions.php';
check_role('manager');
$conn = db_connect();

function send_json($data) {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $conn) {
    $action = $_POST['action'] ?? '';
    
    // Станції
    $station_ids = [];
    $result = $conn->query('SELECT id, name FROM station');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $station_ids[$row['name']] = $row['id'];
        }
        $result->free();
    }

    // Тарифи (для валідації ID)
    $tariff_ids = [];
    $result = $conn->query('SELECT id FROM tariff');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $tariff_ids[] = $row['id'];
        }
        $result->free();
    }

    $phone = $_POST['phone_number'] ?? '';
    $start_station_name = $_POST['start_station'] ?? '';
    $end_station_name = $_POST['end_station'] ?? '';
    $tariff_id = $_POST['tariff_id'] ?? ''; // ЗМІНЕНО: тепер отримуємо ID
    $cargo_volume = $_POST['cargo_volume'] ?? 0;

    // Валідація
    $error = '';
    if (empty($phone) || !preg_match('/^\+380[0-9]{9}$/', $phone)) {
        $error = 'Вкажіть коректний номер телефону (+380XXXXXXXXX)';
    } elseif (empty($start_station_name) || !isset($station_ids[$start_station_name])) {
        $error = 'Введена початкова станція не відповідає жодній із доступних';
    } elseif (empty($end_station_name) || !isset($station_ids[$end_station_name])) {
        $error = 'Введена кінцева станція не відповідає жодній із доступних';
    } elseif (empty($tariff_id) || !in_array($tariff_id, $tariff_ids)) { // ЗМІНЕНО: перевірка ID
        $error = 'Виберіть коректний тип вантажу';
    } elseif (empty($cargo_volume) || !is_numeric($cargo_volume) || $cargo_volume < 1) {
        $error = 'Обсяг вантажу має бути не менше 1 тонни';
    }

    if ($error) {
        send_json(['success' => false, 'message' => $error]);
    }

    $start_station_id = $station_ids[$start_station_name];
    $end_station_id = $station_ids[$end_station_name];

    if ($action === 'calculate') {
        $distance = 0;
        $price_per_km_ton = 0;

        // 1. Відстань
        $stmt = $conn->prepare('SELECT distance_km FROM StationDistances WHERE (station_A_id = ? AND station_B_id = ?) OR (station_A_id = ? AND station_B_id = ?)');
        $stmt->bind_param('iiii', $start_station_id, $end_station_id, $end_station_id, $start_station_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $distance = (float) $result->fetch_assoc()['distance_km'];
        } else {
            send_json(['success' => false, 'message' => 'Неможливо знайти відстань між вказаними станціями']);
        }
        $stmt->close();

        // 2. Тариф (ЗМІНЕНО: шукаємо за ID)
        $stmt = $conn->prepare('SELECT price_per_km_ton FROM tariff WHERE id = ?');
        $stmt->bind_param('i', $tariff_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $price_per_km_ton = (float) $result->fetch_assoc()['price_per_km_ton'];
        } else {
            send_json(['success' => false, 'message' => 'Неможливо знайти тариф']);
        }
        $stmt->close();

        // 3. Розрахунок (ФП функція з functions.php)
        $cost = calculate_contract_cost($distance, $price_per_km_ton, (int)$cargo_volume);
        send_json(['success' => true, 'cost' => number_format($cost, 2, '.', '')]);

    } elseif ($action === 'create') {
        try {
            // ЗМІНЕНО: вставляємо tariff_id замість cargo_type
            $stmt = $conn->prepare('INSERT INTO contract (tariff_id, weight, status, date_created, customer_phone, start_station_id, end_station_id) VALUES (?, ?, ?, NOW(), ?, ?, ?)');
            $status = 'new';
            $stmt->bind_param('idssii', $tariff_id, $cargo_volume, $status, $phone, $start_station_id, $end_station_id);
            
            if ($stmt->execute()) {
                $new_contract_id = $conn->insert_id;
                send_json(['success' => true, 'message' => 'Контракт успішно створено', 'contract_id' => $new_contract_id]);
            } else {
                throw new Exception($stmt->error);
            }
            $stmt->close();

        } catch (Exception $e) {
            send_json(['success' => false, 'message' => 'Помилка при створенні контракту: ' . $e->getMessage()]);
        }
    }
}

// GET: Завантаження даних
$cargo_types_list = []; // Масив об'єктів ['id' => 1, 'name' => 'Техніка']
$stations = [];
$form_data = [
    'phone_number' => '',
    'start_station' => '',
    'end_station' => '',
    'tariff_id' => '',
    'cargo_volume' => ''
];

if ($conn) {
    // ЗМІНЕНО: Завантажуємо ID та назву
    $result = $conn->query('SELECT id, cargo_type FROM tariff');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $cargo_types_list[] = $row;
        }
        $result->free();
    }

    $result = $conn->query('SELECT id, name FROM station');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $stations[] = $row['name'];
        }
        $result->free();
    }
}
?>

<!DOCTYPE html>
<html lang="uk">
<head>
    <link rel="stylesheet" href="/styles.css"> <meta charset="UTF-8">
    <title>X-Rails - Укладання контракту</title>
    <style>
        #cost-section { display: none; margin-top: 20px; padding: 15px; background: #f4f4f4; border-radius: 8px; text-align: center; }
        #cost-value { font-size: 1.5em; font-weight: bold; color: #333; }
        #cost-buttons { margin-top: 15px;
		display: flex;
		justify-content: center;
            	gap: 20px;
	 }
        .modal-overlay { display: flex; justify-content: center; align-items: center; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; }
        .modal-content { background: white; padding: 20px; border-radius: 8px; text-align: center; }
    </style>
</head>
<body>
    <header><h1>Укладання контракту</h1></header>
    <main>
        <div id="root">
            <div class="contract-container">
                <nav class="nav-links"><a href="index.php" class="nav-btn">На головну</a> <a href="/logout.php" class="nav-btn logout-btn">Вийти</a></nav>
                
                <form class="contract-form" id="contract-form">
                    <div class="form-group">
                        <label for="phone_number">Номер телефону клієнта:</label>
                        <input type="text" id="phone_number" name="phone_number" placeholder="+380XXXXXXXXX" value="<?php echo e($form_data['phone_number']); ?>" required />
                    </div>
                    <div class="form-group">
                        <label for="start_station">Початкова станція:</label>
                        <input type="text" id="start_station" name="start_station" value="<?php echo e($form_data['start_station']); ?>" oninput="handleStationInput(this, 'start-suggestions')" onkeydown="handleStationKeyDown(event, 'start_station', 'start-suggestions')" onblur="setTimeout(() => document.getElementById('start-suggestions').style.display = 'none', 200)" required />
                        <div id="start-suggestions" class="autocomplete-suggestions" style="display: none;"></div>
                    </div>
                    <div class="form-group">
                        <label for="end_station">Кінцева станція:</label>
                        <input type="text" id="end_station" name="end_station" value="<?php echo e($form_data['end_station']); ?>" oninput="handleStationInput(this, 'end-suggestions')" onkeydown="handleStationKeyDown(event, 'end_station', 'end-suggestions')" onblur="setTimeout(() => document.getElementById('end-suggestions').style.display = 'none', 200)" required />
                        <div id="end-suggestions" class="autocomplete-suggestions" style="display: none;"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="tariff_id">Тип вантажу:</label>
                        <select id="tariff_id" name="tariff_id" required>
                            <?php 
                            echo render_options(
                                $cargo_types_list, 
                                fn($item) => $item['id'],          // Value = ID
                                fn($item) => $item['cargo_type'],  // Label = Назва
                                $form_data['tariff_id']
                            ); 
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="cargo_volume">Обсяг вантажу (тонн):</label>
                        <input type="number" id="cargo_volume" name="cargo_volume" min="1" value="<?php echo e($form_data['cargo_volume']); ?>" required />
                    </div>
                    <div class="form-group submit-group">
                        <button type="submit" class="submit-btn" id="calculate-btn">Розрахувати вартість</button>
                    </div>
                </form>

                <div id="cost-section">
                    <h3>Розрахована вартість:</h3>
                    <div id="cost-value">...</div>
                    <div id="cost-buttons">
                        <button type="button" class="submit-btn" id="create-btn">Укласти контракт</button>
                        <button type="button" class="nav-btn logout-btn" id="cancel-btn">Відхилити</button>
                    </div>
                </div>
            </div>
            
            <div class="modal-overlay" id="success-modal" style="display: none;">
                <div class="modal-content">
                    <h2>Успіх!</h2>
                    <p id="success-message"></p>
                    <button class="modal-close-btn" onclick="window.location.href='index.php'">Закрити</button>
                </div>
            </div>
            <div class="modal-overlay" id="error-modal" style="display: none;">
                <div class="modal-content">
                    <h2 class="error">Помилка!</h2>
                    <p id="error-message"></p>
                    <button class="modal-close-btn" onclick="document.getElementById('error-modal').style.display='none'">Закрити</button>
                </div>
            </div>
        </div>
    </main>
    <footer><p>© 2025 X-Rails. Для внутрішнього використання.</p></footer>
    
    <script>
        const stations = <?php echo json_encode($stations); ?>;
        // ... (решта JS коду для автодоповнення і fetch ідентична попередньому файлу) ...
        // (Я не дублюю весь JS код тут, щоб заощадити місце, він не змінився логічно, 
        // тільки назва поля cargo_type -> tariff_id в FormData відбудеться автоматично завдяки name="" в HTML)
        
        function handleStationInput(input, suggestionsId) {
             const value = input.value.trim().toLowerCase();
             const suggestionsDiv = document.getElementById(suggestionsId);
             suggestionsDiv.innerHTML = '';
             suggestionsDiv.style.display = 'none';
             if (value) {
                 const filtered = stations.filter(station => station.toLowerCase().startsWith(value));
                 if (filtered.length > 0) {
                     filtered.forEach((station, index) => {
                         const div = document.createElement('div');
                         div.className = 'autocomplete-suggestion';
                         div.textContent = station;
                         div.onclick = () => {
                             input.value = station;
                             suggestionsDiv.style.display = 'none';
                         };
                         suggestionsDiv.appendChild(div);
                     });
                     suggestionsDiv.style.display = 'block';
                 }
             }
         }
         function handleStationKeyDown(event, fieldName, suggestionsId) {
             if (event.key === 'Tab' && !event.shiftKey) {
                 const suggestionsDiv = document.getElementById(suggestionsId);
                 const suggestions = suggestionsDiv.getElementsByClassName('autocomplete-suggestion');
                 if (suggestions.length > 0) {
                     event.preventDefault();
                     document.getElementById(fieldName).value = suggestions[0].textContent;
                     suggestionsDiv.style.display = 'none';
                 }
             }
         }
 
         document.addEventListener('DOMContentLoaded', () => {
             const form = document.getElementById('contract-form');
             const calculateBtn = document.getElementById('calculate-btn');
             const createBtn = document.getElementById('create-btn');
             const cancelBtn = document.getElementById('cancel-btn');
             const costSection = document.getElementById('cost-section');
             const costValue = document.getElementById('cost-value');
 
             function showSuccessModal(message) {
                 document.getElementById('success-message').textContent = message;
                 document.getElementById('success-modal').style.display = 'flex';
             }
             function showErrorModal(message) {
                 document.getElementById('error-message').innerHTML = message;
                 document.getElementById('error-modal').style.display = 'flex';
             }
 
             form.addEventListener('submit', async (e) => {
                 e.preventDefault();
                 calculateBtn.disabled = true;
                 calculateBtn.textContent = 'Розрахунок...';
                 const formData = new FormData(form);
                 formData.append('action', 'calculate');
 
                 try {
                     const response = await fetch('new_contract.php', { method: 'POST', body: formData });
                     const responseText = await response.text();
                     if (!response.ok) throw new Error(`Помилка HTTP ${response.status}: <br><pre>${responseText}</pre>`);
                     let result;
                     try { result = JSON.parse(responseText); } 
                     catch (e) { throw new Error(`Сервер повернув не JSON: <br><pre>${responseText}</pre>`); }
 
                     if (result.success) {
                         costValue.textContent = `${result.cost} грн.`;
                         costSection.style.display = 'block';
                         calculateBtn.style.display = 'none';
                     } else {
                         showErrorModal(result.message || 'Невідома помилка');
                     }
                 } catch (error) { showErrorModal(error.message); } 
                 finally { calculateBtn.disabled = false; calculateBtn.textContent = 'Розрахувати вартість'; }
             });
 
             createBtn.addEventListener('click', async () => {
                 createBtn.disabled = true;
                 createBtn.textContent = 'Створення...';
                 const formData = new FormData(form);
                 formData.append('action', 'create');
 
                 try {
                     const response = await fetch('new_contract.php', { method: 'POST', body: formData });
                     const responseText = await response.text();
                     if (!response.ok) throw new Error(`Помилка HTTP ${response.status}: <br><pre>${responseText}</pre>`);
                     let result;
                     try { result = JSON.parse(responseText); } 
                     catch (e) { throw new Error(`Сервер повернув не JSON: <br><pre>${responseText}</pre>`); }
 
                     if (result.success) { showSuccessModal(result.message); } 
                     else { showErrorModal(result.message); }
                 } catch (error) { showErrorModal(error.message); } 
                 finally { createBtn.disabled = false; createBtn.textContent = 'Укласти контракт'; }
             });
 
             cancelBtn.addEventListener('click', () => {
                 costSection.style.display = 'none';
                 calculateBtn.style.display = 'block';
                 costValue.textContent = '...';
             });
         });
    </script>
</body>
</html>
