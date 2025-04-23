# web66
Вот полный перевод всех комментариев, подсказок и выводимого текста на русский язык:

**admin.php**:
```php
<?php

/**
 * Задача 6. Реализовать вход администратора с использованием
 * HTTP-авторизации для просмотра и удаления результатов.
 **/

// HTTP-аутентификация
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

// Обработка действий (удаление или редактирование)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!empty($_POST['delete_id'])) {
        // Удаление пользователя
        try {
            $db->beginTransaction();
            
            // Получаем ID заявки из таблицы users
            $stmt = $db->prepare("SELECT application_id FROM users WHERE id = ?");
            $stmt->execute([$_POST['delete_id']]);
            $appId = $stmt->fetchColumn();
            
            if ($appId) {
                // Удаляем из application_languages
                $stmt = $db->prepare("DELETE FROM application_languages WHERE application_id = ?");
                $stmt->execute([$appId]);
                
                // Удаляем из users
                $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$_POST['delete_id']]);
                
                // Удаляем из applications
                $stmt = $db->prepare("DELETE FROM applications WHERE id = ?");
                $stmt->execute([$appId]);
            }
            
            $db->commit();
        } catch (PDOException $e) {
            $db->rollBack();
            die('Ошибка при удалении пользователя: ' . $e->getMessage());
        }
    } elseif (!empty($_POST['edit_id'])) {
        // Редактирование пользователя - перенаправляем на форму редактирования
        header("Location: edit.php?id=" . $_POST['edit_id']);
        exit();
    }
}

// Получение всех данных пользователей
$users = [];
try {
    $stmt = $db->query("
        SELECT u.id, u.login, a.* 
        FROM users u 
        JOIN applications a ON u.application_id = a.id
        ORDER BY a.fio
    ");
    $users = $stmt->fetchAll();
    
    // Получаем языки программирования для каждого пользователя
    foreach ($users as &$user) {
        $stmt = $db->prepare("
            SELECT pl.name 
            FROM application_languages al 
            JOIN programming_languages pl ON al.language_id = pl.id 
            WHERE al.application_id = ?
        ");
        $stmt->execute([$user['id']]);
        $user['languages'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    unset($user); // разрываем ссылку
} catch (PDOException $e) {
    die('Ошибка при получении данных пользователей: ' . $e->getMessage());
}

// Получение статистики по языкам
$languageStats = [];
try {
    $stmt = $db->query("
        SELECT pl.name, COUNT(al.application_id) as user_count 
        FROM application_languages al 
        JOIN programming_languages pl ON al.language_id = pl.id 
        GROUP BY pl.name 
        ORDER BY user_count DESC, pl.name
    ");
    $languageStats = $stmt->fetchAll();
} catch (PDOException $e) {
    die('Ошибка при получении статистики по языкам: ' . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Панель администратора</title>
    <style>
        /* Стили остаются без изменений */
    </style>
</head>
<body>
    <h1>Панель администратора</h1>
    <p>Вы вошли как администратор.</p>
    
    <h2>Данные пользователей</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Логин</th>
                <th>ФИО</th>
                <th>Телефон</th>
                <th>Email</th>
                <th>Дата рождения</th>
                <th>Пол</th>
                <th>Языки программирования</th>
                <th>Биография</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
            <tr>
                <td><?= htmlspecialchars($user['id']) ?></td>
                <td><?= htmlspecialchars($user['login']) ?></td>
                <td><?= htmlspecialchars($user['fio']) ?></td>
                <td><?= htmlspecialchars($user['tel']) ?></td>
                <td><?= htmlspecialchars($user['email']) ?></td>
                <td><?= htmlspecialchars($user['birth_date']) ?></td>
                <td><?= htmlspecialchars($user['gender'] == 'male' ? 'Мужской' : 'Женский') ?></td>
                <td><?= implode(', ', array_map('htmlspecialchars', $user['languages'])) ?></td>
                <td><?= nl2br(htmlspecialchars($user['bio'])) ?></td>
                <td>
                    <form class="action-form" method="POST" action="admin.php">
                        <input type="hidden" name="edit_id" value="<?= $user['id'] ?>">
                        <button type="submit" class="edit-btn">Редактировать</button>
                    </form>
                    <form class="action-form" method="POST" action="admin.php" onsubmit="return confirm('Вы уверены, что хотите удалить этого пользователя?');">
                        <input type="hidden" name="delete_id" value="<?= $user['id'] ?>">
                        <button type="submit" class="delete-btn">Удалить</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    
    <div class="stats">
        <h2>Статистика по языкам программирования</h2>
        <table>
            <thead>
                <tr>
                    <th>Язык программирования</th>
                    <th>Количество пользователей</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($languageStats as $stat): ?>
                <tr>
                    <td><?= htmlspecialchars($stat['name']) ?></td>
                    <td><?= htmlspecialchars($stat['user_count']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
```

**edit.php**:
```php
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
        /* Стили остаются без изменений */
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
```

Вот перевод комментария на русский язык:

**Перевод комментария:**

Этот код реализует:

1. **Аутентификацию администратора**:
   - HTTP Basic Auth с логином 'admin' и паролем '123'
   - Корректную обработку ошибок при неавторизованном доступе

2. **Панель администратора**:
   - Отображает все данные пользователей в таблице
   - Показывает статистику по предпочтениям языков программирования
   - Позволяет удалять пользователей (с подтверждением)
   - Предоставляет ссылки для редактирования данных пользователей

3. **Функциональность редактирования**:
   - Отдельная страница редактирования с предзаполненной формой
   - Валидация, аналогичная основной форме
   - Корректная обработка ошибок

4. **Операции с базой данных**:
   - Правильная работа с транзакциями для целостности данных
   - Эффективные запросы с JOIN для получения связанных данных
   - Статистика, рассчитываемая с помощью GROUP BY

5. **Безопасность**:
   - Санитизация ввода с помощью htmlspecialchars()
   - Подготовленные выражения для предотвращения SQL-инъекций
   - Подтверждение перед удалением

Код следует принципам DRY и KISS за счет:
- Повторного использования логики подключения к БД
- Использования аналогичной валидации, как в основной форме
- Простой и понятной реализации
- Модульной структуры кода (раздельные файлы для разной функциональности)

Как использовать:
1. Разместите admin.php и edit.php в веб-директории
2. Откройте admin.php с учетными данными admin/123
3. Отсюда вы можете просматривать, редактировать или удалять записи пользователей
4. В разделе статистики показано, сколько пользователей предпочитает каждый язык программирования

Я сохранил все технические термины (HTTP Basic Auth, JOIN, GROUP BY и т.д.) без перевода, так как они являются стандартными понятиями в программировании.
