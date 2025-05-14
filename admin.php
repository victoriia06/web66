<?php
header('Content-Type: text/html; charset=UTF-8');

// HTTP-авторизация
if (empty($_SERVER['PHP_AUTH_USER']) || empty($_SERVER['PHP_AUTH_PW'])) {
    header('HTTP/1.1 401 Unauthorized');
    header('WWW-Authenticate: Basic realm="Admin Area"');
    echo '<h1>401 Требуется авторизация</h1>';
    exit();
}

// Подключение к БД
$user = 'u70422';
$pass = '4545635';
$dbname = 'u70422';

try {
    $db = new PDO("mysql:host=localhost;dbname=$dbname", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    
    // Проверка учетных данных администратора
    $stmt = $db->prepare("SELECT * FROM admins WHERE login = ?");
    $stmt->execute([$_SERVER['PHP_AUTH_USER']]);
    $admin = $stmt->fetch();
    
    if (!$admin || !password_verify($_SERVER['PHP_AUTH_PW'], $admin['password_hash'])) {
        header('HTTP/1.1 401 Unauthorized');
        header('WWW-Authenticate: Basic realm="Admin Area"');
        echo '<h1>401 Неверные учетные данные</h1>';
        exit();
    }
    
    // Обработка действий администратора
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        if (!empty($_POST['delete'])) {
            // Удаление пользователя
            $stmt = $db->prepare("DELETE FROM applications WHERE id = ?");
            $stmt->execute([$_POST['delete']]);
            header("Location: admin.php");
            exit();
        } elseif (!empty($_POST['update'])) {
            // Обновление данных пользователя
            $appId = $_POST['update'];
            $stmt = $db->prepare("UPDATE applications SET fio=?, tel=?, email=?, birth_date=?, gender=?, bio=? WHERE id=?");
            $stmt->execute([
                $_POST['fio'],
                $_POST['tel'],
                $_POST['email'],
                $_POST['date'],
                $_POST['gender'],
                $_POST['bio'],
                $appId
            ]);
            
            // Обновление языков программирования
            $db->prepare("DELETE FROM application_languages WHERE application_id = ?")->execute([$appId]);
            if (!empty($_POST['plang'])) {
                $stmt = $db->prepare("INSERT INTO application_languages (application_id, language_id) 
                                     SELECT ?, id FROM programming_languages WHERE name = ?");
                foreach ($_POST['plang'] as $lang) {
                    $stmt->execute([$appId, $lang]);
                }
            }
            header("Location: admin.php");
            exit();
        }
    }
    
    // Получение статистики по языкам
    $stats = $db->query("
        SELECT pl.name, COUNT(al.application_id) as user_count 
        FROM programming_languages pl 
        LEFT JOIN application_languages al ON pl.id = al.language_id 
        GROUP BY pl.id 
        ORDER BY user_count DESC, pl.name
    ")->fetchAll();
    
    // Получение всех заявок с языками
    $applications = $db->query("
        SELECT a.*, GROUP_CONCAT(pl.name SEPARATOR ', ') as languages 
        FROM applications a 
        LEFT JOIN application_languages al ON a.id = al.application_id 
        LEFT JOIN programming_languages pl ON al.language_id = pl.id 
        GROUP BY a.id 
        ORDER BY a.id DESC
    ")->fetchAll();
    
} catch (PDOException $e) {
    die("Ошибка базы данных: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            line-height: 1.6;
        }
        h1, h2 {
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .stats {
            margin-bottom: 30px;
        }
        .actions {
            white-space: nowrap;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.4);
        }
        .modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 600px;
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
        }
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 8px;
            box-sizing: border-box;
        }
        .form-group select[multiple] {
            height: 120px;
        }
        button {
            padding: 8px 15px;
            background: #4CAF50;
            color: white;
            border: none;
            cursor: pointer;
        }
        button:hover {
            background: #45a049;
        }
        .btn-danger {
            background: #f44336;
        }
        .btn-danger:hover {
            background: #d32f2f;
        }
    </style>
</head>
<body>
    <h1>Админ-панель</h1>
    <p>Вы вошли как администратор: <strong><?php echo htmlspecialchars($_SERVER['PHP_AUTH_USER']); ?></strong></p>
    
    <div class="stats">
        <h2>Статистика по языкам программирования</h2>
        <table>
            <tr>
                <th>Язык программирования</th>
                <th>Количество пользователей</th>
            </tr>
            <?php foreach ($stats as $stat): ?>
            <tr>
                <td><?php echo htmlspecialchars($stat['name']); ?></td>
                <td><?php echo htmlspecialchars($stat['user_count']); ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    
    <h2>Все заявки</h2>
    <table>
        <tr>
            <th>ID</th>
            <th>ФИО</th>
            <th>Телефон</th>
            <th>Email</th>
            <th>Дата рождения</th>
            <th>Пол</th>
            <th>Языки программирования</th>
            <th>Действия</th>
        </tr>
        <?php foreach ($applications as $app): ?>
        <tr>
            <td><?php echo htmlspecialchars($app['id']); ?></td>
            <td><?php echo htmlspecialchars($app['fio']); ?></td>
            <td><?php echo htmlspecialchars($app['tel']); ?></td>
            <td><?php echo htmlspecialchars($app['email']); ?></td>
            <td><?php echo htmlspecialchars($app['birth_date']); ?></td>
            <td><?php echo $app['gender'] == 'male' ? 'Мужской' : 'Женский'; ?></td>
            <td><?php echo htmlspecialchars($app['languages'] ?? 'Нет данных'); ?></td>
            <td class="actions">
                <button onclick="openEditModal(
                    <?php echo htmlspecialchars($app['id']); ?>,
                    '<?php echo htmlspecialchars($app['fio']); ?>',
                    '<?php echo htmlspecialchars($app['tel']); ?>',
                    '<?php echo htmlspecialchars($app['email']); ?>',
                    '<?php echo htmlspecialchars($app['birth_date']); ?>',
                    '<?php echo htmlspecialchars($app['gender']); ?>',
                    '<?php echo htmlspecialchars($app['bio']); ?>',
                    '<?php echo htmlspecialchars($app['languages'] ?? ''); ?>'
                )">Редактировать</button>
                
                <form method="POST" style="display:inline;">
                    <input type="hidden" name="delete" value="<?php echo htmlspecialchars($app['id']); ?>">
                    <button type="submit" class="btn-danger" onclick="return confirm('Удалить эту заявку?')">Удалить</button>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>
    
    <!-- Модальное окно для редактирования -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeEditModal()">&times;</span>
            <h2>Редактирование заявки</h2>
            <form id="editForm" method="POST">
                <input type="hidden" name="update" id="editAppId">
                
                <div class="form-group">
                    <label for="editFio">ФИО:</label>
                    <input type="text" name="fio" id="editFio" required>
                </div>
                
                <div class="form-group">
                    <label for="editTel">Телефон:</label>
                    <input type="tel" name="tel" id="editTel" required>
                </div>
                
                <div class="form-group">
                    <label for="editEmail">Email:</label>
                    <input type="email" name="email" id="editEmail" required>
                </div>
                
                <div class="form-group">
                    <label for="editDate">Дата рождения:</label>
                    <input type="date" name="date" id="editDate" required>
                </div>
                
                <div class="form-group">
                    <label>Пол:</label>
                    <div>
                        <input type="radio" name="gender" id="editGenderMale" value="male" required>
                        <label for="editGenderMale">Мужской</label>
                    </div>
                    <div>
                        <input type="radio" name="gender" id="editGenderFemale" value="female" required>
                        <label for="editGenderFemale">Женский</label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="editPlang">Языки программирования:</label>
                    <select name="plang[]" id="editPlang" multiple required>
                        <?php 
                        $allLangs = $db->query("SELECT name FROM programming_languages ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
                        foreach ($allLangs as $lang): ?>
                            <option value="<?php echo htmlspecialchars($lang); ?>"><?php echo htmlspecialchars($lang); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="editBio">Биография:</label>
                    <textarea name="bio" id="editBio" rows="4" required></textarea>
                </div>
                
                <button type="submit">Сохранить изменения</button>
            </form>
        </div>
    </div>
    
    <script>
        function openEditModal(id, fio, tel, email, date, gender, bio, languages) {
            document.getElementById('editAppId').value = id;
            document.getElementById('editFio').value = fio;
            document.getElementById('editTel').value = tel;
            document.getElementById('editEmail').value = email;
            document.getElementById('editDate').value = date;
            document.getElementById('editGender' + (gender === 'male' ? 'Male' : 'Female')).checked = true;
            document.getElementById('editBio').value = bio;
            
            // Очистка и выбор языков
            const langSelect = document.getElementById('editPlang');
            for (let i = 0; i < langSelect.options.length; i++) {
                langSelect.options[i].selected = false;
            }
            
            if (languages) {
                const selectedLangs = languages.split(', ');
                for (let i = 0; i < langSelect.options.length; i++) {
                    if (selectedLangs.includes(langSelect.options[i].value)) {
                        langSelect.options[i].selected = true;
                    }
                }
            }
            
            document.getElementById('editModal').style.display = 'block';
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        // Закрытие модального окна при клике вне его
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target == modal) {
                closeEditModal();
            }
        }
    </script>
</body>
</html>
