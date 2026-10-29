<?php
declare(strict_types=1);
// Завдання для самостійного виконання: форма зворотного зв’язку з необов’язковим вкладенням.
// Обробка — тут, розмітка — feedback-form.php. Лист не надсилається, дані й файл не зберігаються.
require __DIR__ . '/helpers.php';

const NAME_MAX_CHARS = 100;
const EMAIL_MAX_BYTES = 254;
const MESSAGE_MIN_CHARS = 10;
const MESSAGE_MAX_CHARS = 1000;
const ATTACHMENT_MAX_BYTES = 1024 * 1024;   // 1 MiB

const FIELD_LABELS = ['name' => 'Ім’я', 'email' => 'Електронна пошта', 'message' => 'Повідомлення'];

/** Текстове поле POST: [нормалізоване значення, помилка або null]. */
function textField(string $key): array
{
    $raw = $_POST[$key] ?? null;
    if ($raw === null) {
        return ['', 'Поле «' . FIELD_LABELS[$key] . '» не надіслано.'];
    }
    if (!is_string($raw)) {
        return ['', 'Поле «' . FIELD_LABELS[$key] . '» має бути рядком.'];
    }
    if (!mb_check_encoding($raw, 'UTF-8')) {
        return ['', 'Поле «' . FIELD_LABELS[$key] . '» містить некоректні символи (очікується UTF-8).'];
    }
    return [trim($raw), null];
}

/**
 * Перевірка вкладення: спершу структура й код error, далі типи ключів і розмір.
 * Повертає ['file' => null|['name', 'size', 'type'], 'error' => null|string]; file = null без помилки — вкладення немає.
 */
function checkAttachment(): array
{
    if (!array_key_exists('attachment', $_FILES)) {
        return ['file' => null, 'error' => null];
    }
    $file = $_FILES['attachment'];
    if (!is_array($file) || !array_key_exists('error', $file) || !is_int($file['error'])) {
        return ['file' => null, 'error' => 'Некоректні дані вкладення: очікується один файл.'];
    }
    $uploadError = match ($file['error']) {
        UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE => null,
        UPLOAD_ERR_INI_SIZE => 'Файл перевищує серверну межу upload_max_filesize (' . ini_get('upload_max_filesize') . ').',
        UPLOAD_ERR_FORM_SIZE => 'Файл перевищує межу, задану у формі.',
        UPLOAD_ERR_PARTIAL => 'Файл передано не повністю. Спробуйте надіслати ще раз.',
        UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'Сервер не зміг тимчасово зберегти файл. Зверніться до адміністратора.',
        UPLOAD_ERR_EXTENSION => 'Завантаження зупинено розширенням PHP на сервері.',
        default => 'Невідомий код помилки завантаження: ' . $file['error'] . '.',
    };
    if ($uploadError !== null) {
        return ['file' => null, 'error' => $uploadError];
    }
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['file' => null, 'error' => null];   // необов’язкове вкладення не вибрано — це не помилка
    }
    if (!is_string($file['name'] ?? null) || !is_string($file['type'] ?? null) || !is_int($file['size'] ?? null)
        || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) {
        return ['file' => null, 'error' => 'Некоректні дані вкладення.'];
    }
    if ($file['size'] < 1) {
        return ['file' => null, 'error' => 'Файл порожній (0 байт).'];
    }
    if ($file['size'] > ATTACHMENT_MAX_BYTES) {
        return ['file' => null, 'error' => 'Файл більший за 1 МіБ: ' . formatBytes($file['size']) . '.'];
    }
    // Тимчасовий файл не переміщуємо й не читаємо: PHP видалить його після запиту.
    return ['file' => ['name' => $file['name'], 'size' => $file['size'], 'type' => $file['type']], 'error' => null];
}

$values = ['name' => '', 'email' => '', 'message' => ''];
$fieldErrors = [];
$formError = null;
$summary = null;

if (postBodyLost()) {
    $formError = 'Дані форми не отримано: розмір запиту ' . formatBytes((int) $_SERVER['CONTENT_LENGTH'])
        . ' перевищує post_max_size (' . ini_get('post_max_size') . '). Зменште вкладення й надішліть форму ще раз.';
    http_response_code(413);
} elseif (isPost()) {
    foreach (array_keys($values) as $key) {
        [$values[$key], $error] = textField($key);
        if ($error !== null) {
            $fieldErrors[$key] = $error;
        }
    }
    if (!isset($fieldErrors['name'])) {
        $length = mb_strlen($values['name'], 'UTF-8');
        if ($length === 0) {
            $fieldErrors['name'] = 'Вкажіть ім’я.';
        } elseif ($length > NAME_MAX_CHARS) {
            $fieldErrors['name'] = 'Ім’я — до ' . NAME_MAX_CHARS . " символів (зараз $length).";
        }
    }
    if (!isset($fieldErrors['email'])) {
        if ($values['email'] === '') {
            $fieldErrors['email'] = 'Вкажіть електронну пошту.';
        } elseif (strlen($values['email']) > EMAIL_MAX_BYTES) {
            $fieldErrors['email'] = 'Адреса — до ' . EMAIL_MAX_BYTES . ' байтів.';
        } elseif (filter_var($values['email'], FILTER_VALIDATE_EMAIL) === false) {
            $fieldErrors['email'] = 'Некоректна адреса електронної пошти (приклад: name@example.com).';
        }
    }
    if (!isset($fieldErrors['message'])) {
        $length = mb_strlen($values['message'], 'UTF-8');
        if ($length < MESSAGE_MIN_CHARS || $length > MESSAGE_MAX_CHARS) {
            $fieldErrors['message'] = 'Повідомлення — від ' . MESSAGE_MIN_CHARS . ' до ' . MESSAGE_MAX_CHARS
                . " символів без крайніх пробілів (зараз $length).";
        }
    }
    $attachment = checkAttachment();
    if ($attachment['error'] !== null) {
        $fieldErrors['attachment'] = $attachment['error'];
    }
    if ($fieldErrors === []) {
        $summary = $values + ['attachment' => $attachment['file']];   // успіх — лише після всіх перевірок
    } else {
        http_response_code(422);
    }
} elseif (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET, POST');
    http_response_code(405);
    exit('Метод не підтримується.');
}

require __DIR__ . '/feedback-form.php';
