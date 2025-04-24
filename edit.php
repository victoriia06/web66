<?php
// HTTP-аутентификация (как в admin.php)
if (empty($_SERVER['PHP_AUTH_USER']) ||
    empty($_SERVER['PHP_AUTH_PW']) ||
    $_SERVER['PHP_AUTH_USER'] != 'admin' ||
    md5($_SERVER['PHP_AUTH_PW']) != md5('123')) {
  header('HTTP/1.1 401 Не авторизован');
  header('WWW-Authenticate: Basic realm="Панель администратора"');
  print('<h1>401 Требуется авторизация</h1>');
  exit();
}

// Подключение к базе данных
$user = 'u70422';
$pass = '4545635';
$dbname = 'u70422';

try {
    $db = new PDO("mysql:host=localhost;dbname=$dbname", $user, $pass, [
        PDO::ATTR_PERSISTENT => true,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die('Ошибка подключения к базе данных: ' . $e->getMessage());
}

// Получаем ID пользователя из URL
$userId = $_GET['id'] ?? null;
if (!$userId) {
    die('ID пользователя не указан');
}

// Получаем данные пользователя
try {
    // Получаем данные пользователя и заявки
    $stmt = $db->prepare("
        SELECT u.id, u.login, a.* 
        FROM users u 
        JOIN applications a ON u.application_id = a.id
        WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch();
    
    if (!$userData) {
        die('Пользователь не найден');
    }
    
    // Получаем языки программирования
    $stmt = $db->prepare("
        SELECT pl.name 
        FROM application_languages al 
        JOIN programming_languages pl ON al.language_id = pl.id 
        WHERE al.application_id = ?
    ");
    $stmt->execute([$userData['id']]);
    $userLanguages = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    die('Ошибка при получении данных пользователя: ' . $e->getMessage());
}

// Обработка отправки формы
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $errors = [];
    
    // Валидация ввода (аналогично index.php)
    $requiredFields = ['fio', 'tel', 'email', 'date', 'gender', 'bio'];
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            $errors[$field] = "Это поле обязательно для заполнения";
        }
    }
    
    if (empty($_POST['plang']) || !is_array($_POST['plang'])) {
        $errors['plang'] = "Необходимо выбрать хотя бы один язык";
    }
    
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Обновляем данные заявки
            $stmt = $db->prepare("
                UPDATE applications SET 
                    fio = ?, 
                    tel = ?, 
                    email = ?, 
                    birth_date = ?, 
                    gender = ?, 
                    bio = ? 
                WHERE id = ?
            ");
            $stmt->execute([
                $_POST['fio'],
                $_POST['tel'],
                $_POST['email'],
                $_POST['date'],
                $_POST['gender'],
                $_POST['bio'],
                $userData['id']
            ]);
            
            // Удаляем старые языки
            $stmt = $db->prepare("DELETE FROM application_languages WHERE application_id = ?");
            $stmt->execute([$userData['id']]);
            
            // Добавляем новые языки
            $stmt = $db->prepare("INSERT INTO application_languages (application_id, language_id) VALUES (?, ?)");
            foreach ($_POST['plang'] as $language) {
                // Получаем или создаем ID языка
                $langStmt = $db->prepare("SELECT id FROM programming_languages WHERE name = ?");
                $langStmt->execute([$language]);
                $langId = $langStmt->fetchColumn();
                
                if (!$langId) {
                    $langStmt = $db->prepare("INSERT INTO programming_languages (name) VALUES (?)");
                    $langStmt->execute([$language]);
                    $langId = $db->lastInsertId();
                }
                
                $stmt->execute([$userData['id'], $langId]);
            }
            
            $db->commit();
            
            // Перенаправляем обратно в панель администратора
            header('Location: admin.php');
            exit();
            
        } catch (PDOException $e) {
            $db->rollBack();
            $errors[] = 'Ошибка базы данных: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактирование пользователя</title>
    <style>
  /* ОСНОВНЫЕ СТИЛИ */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f5f5f5;
            margin: 0;
            padding: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        h1, h2 {
            color: #2c3e50;
        }
    </style>
</head>
<body>
    <h1>Редактирование пользователя: <?= htmlspecialchars($userData['fio']) ?></h1>
    
    <?php if (!empty($errors)): ?>
        <div style="color: red; margin-bottom: 15px;">
            <p>Пожалуйста, исправьте следующие ошибки:</p>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <form method="POST">
        <div class="form-group">
            <label for="fio">ФИО:</label>
            <input type="text" id="fio" name="fio" value="<?= htmlspecialchars($userData['fio']) ?>" required>
        </div>
        
        <div class="form-group">
            <label for="tel">Телефон:</label>
            <input type="tel" id="tel" name="tel" value="<?= htmlspecialchars($userData['tel']) ?>" required>
        </div>
        
        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($userData['email']) ?>" required>
        </div>
        
        <div class="form-group">
            <label for="date">Дата рождения:</label>
            <input type="date" id="date" name="date" value="<?= htmlspecialchars($userData['birth_date']) ?>" required>
        </div>
        
        <div class="form-group">
            <label>Пол:</label>
            <div class="gender-options">
                <label>
                    <input type="radio" name="gender" value="male" <?= $userData['gender'] == 'male' ? 'checked' : '' ?> required> Мужской
                </label>
                <label>
                    <input type="radio" name="gender" value="female" <?= $userData['gender'] == 'female' ? 'checked' : '' ?> required> Женский
                </label>
            </div>
        </div>
        
        <div class="form-group">
            <label for="plang">Языки программирования:</label>
            <select id="plang" name="plang[]" multiple required style="height: 100px;">
                <?php
                $allLanguages = ['Pascal', 'C', 'C++', 'JavaScript', 'PHP', 'Haskell', 'Clojure', 'Prolog', 'Scala'];
                foreach ($allLanguages as $lang) {
                    $selected = in_array($lang, $userLanguages) ? 'selected' : '';
                    echo "<option value=\"$lang\" $selected>$lang</option>";
                }
                ?>
            </select>
            <small>Удерживайте Ctrl для выбора нескольких языков</small>
        </div>
        
        <div class="form-group">
            <label for="bio">Биография:</label>
            <textarea id="bio" name="bio" rows="5" required><?= htmlspecialchars($userData['bio']) ?></textarea>
        </div>
        
        <button type="submit">Сохранить изменения</button>
        <a href="admin.php" style="margin-left: 10px;">Отмена</a>
    </form>
</body>
</html>
