<?php

declare(strict_types=1);

// Обработчик формы, нейтральный к набору полей.
// Под новую форму правится только массив FORM_CONFIG: имена обязательных полей,
// поле с email, поле-ловушка и лимиты. Остальной код менять не нужно.
//
// ВАЖНО: имена в FORM_CONFIG должны совпадать с атрибутами name="..." в HTML.
// Меняешь поля формы — правь HTML и FORM_CONFIG одним коммитом.

header('X-Content-Type-Options: nosniff');

const FORM_CONFIG = [
    'required' => ['furniture', 'phone', 'consent'], // данные брифа и контакты обязательны
    'email' => '',                             // в форме нет email: обратная связь по телефону
    'honeypot' => 'website',                   // поле-ловушка для ботов; пустая строка — выключено
    'max_length' => 5000,                      // предел длины одного поля
    'max_body' => 65536,                       // предел размера всей заявки
];

/**
 * AJAX получает JSON; обычная отправка — редирект или страницу ошибки.
 */
function respond(int $status, array $payload): void
{
    header('Cache-Control: no-store');
    $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
    if (stripos($accept, 'application/json') !== false) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($status);
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($status === 200 && ($payload['ok'] ?? false) === true) {
        header('Location: ../thank-you.html', true, 303);
        exit;
    }

    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    $messages = $payload['errors'] ?? [$payload['error'] ?? 'Не удалось отправить заявку.'];
    $message = htmlspecialchars(implode(' ', $messages), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    echo '<!doctype html><html lang="ru"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta name="robots" content="noindex,nofollow"><title>Заявка не отправлена — Киви Мебель</title>'
        . '<link rel="stylesheet" href="../css/style.css"></head><body>'
        . '<main class="wrap section confirmation"><div class="eyebrow">Киви Мебель</div>'
        . '<h1>Заявка не отправлена</h1><p>' . $message . '</p>'
        . '<p>Вернитесь назад, чтобы проверить данные, или свяжитесь с нами по телефону '
        . '<a href="tel:+79869397372">+7 (986) 939-73-72</a>.</p>'
        . '<a class="button" href="../index.html">На главную ↗</a></main></body></html>';
    exit;
}

/**
 * Значение из окружения сайта: getenv(), затем $_ENV, затем $_SERVER.
 */
function env(string $key, string $default = ''): string
{
    $value = getenv($key);
    if ($value === false || $value === '') {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? $default;
    }

    return trim((string) $value);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, ['ok' => false, 'error' => 'Метод не поддерживается']);
}

$honeypot = FORM_CONFIG['honeypot'];
if ($honeypot !== '' && isset($_POST[$honeypot]) && (!is_string($_POST[$honeypot]) || trim($_POST[$honeypot]) !== '')) {
    respond(200, ['ok' => true]); // боту отвечаем как обычно, письмо не шлём
}

$fields = [];
$errors = [];
$bodySize = 0;

foreach ($_POST as $fieldName => $value) {
    $fieldName = (string) $fieldName;

    if ($honeypot !== '' && $fieldName === $honeypot) {
        continue;
    }
    if (preg_match('/^[a-z0-9_-]{1,40}$/i', $fieldName) !== 1) {
        continue; // имена с мусором в письмо не переносим
    }
    if (!is_string($value)) {
        $errors[] = 'Поле «' . $fieldName . '»: ожидается текст.';
        continue;
    }

    $value = trim($value);
    if (mb_strlen($value) > FORM_CONFIG['max_length']) {
        $errors[] = 'Поле «' . $fieldName . '»: не больше ' . FORM_CONFIG['max_length'] . ' символов.';
        continue;
    }
    if ($value === '') {
        continue;
    }

    $bodySize += mb_strlen($fieldName) + mb_strlen($value);
    $fields[$fieldName] = $value;
}

foreach (FORM_CONFIG['required'] as $requiredField) {
    if (!isset($fields[$requiredField])) {
        $errors[] = 'Поле «' . $requiredField . '»: заполните.';
    }
}

$emailField = FORM_CONFIG['email'];
$email = $emailField !== '' ? ($fields[$emailField] ?? '') : '';
if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    $errors[] = 'Поле «' . $emailField . '»: некорректный email.';
}

// Тип мебели проверяется независимо от JavaScript, чтобы не терять первый шаг.
if (isset($fields['furniture']) && !in_array($fields['furniture'], [
    'Кухня', 'Шкаф-купе', 'Гардеробная', 'Прихожая',
    'Детская мебель', 'Офисная мебель', 'Другая мебель',
], true)) {
    $errors[] = 'Выберите тип мебели из списка.';
}

// Проверяем контакты на сервере: браузерную валидацию можно обойти.
if (isset($fields['phone'])) {
    $phone = $fields['phone'];
    $digits = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) > 40 || preg_match('/^[+0-9()\s.\-]+$/D', $phone) !== 1 || strlen($digits) < 7 || strlen($digits) > 15) {
        $errors[] = 'Телефон: укажите корректный номер (от 7 до 15 цифр).';
    }
}
if (isset($fields['consent']) && $fields['consent'] !== '1') {
    $errors[] = 'Подтвердите согласие на обработку данных.';
}

if ($bodySize > FORM_CONFIG['max_body']) {
    respond(413, ['ok' => false, 'error' => 'Заявка слишком большая.']);
}

if ($errors !== []) {
    respond(422, ['ok' => false, 'errors' => $errors]);
}

if ($fields === []) {
    respond(422, ['ok' => false, 'error' => 'Пустая заявка.']);
}

$to = env('MAIL_TO');
if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
    respond(500, ['ok' => false, 'error' => 'Получатель не настроен.']);
}

$from = env('MAIL_FROM');
if ($from === '') {
    // Домен берём из конфигурации сервера, а не из заголовка Host от клиента.
    $host = preg_replace('/[^a-z0-9.\-]/i', '', (string) ($_SERVER['SERVER_NAME'] ?? ''));
    $from = $host !== '' ? 'no-reply@' . $host : $to;
}
if (filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
    $from = $to;
}

// mail() не кодирует заголовки сам: кириллица без RFC 2047 приходит пустой.
$prefix = env('MAIL_SUBJECT_PREFIX');
$subject = mb_encode_mimeheader(trim('Заявка с сайта ' . $prefix), 'UTF-8', 'B');

$body = "Дата заявки: " . date(DATE_ATOM) . "\n\n";
$labels = [
    'furniture' => 'Мебель',
    'dimensions' => 'Примерные размеры',
    'message' => 'Пожелания',
    'name' => 'Имя',
    'phone' => 'Телефон',
    'consent' => 'Согласие на обработку данных',
];
foreach ($fields as $fieldName => $value) {
    $body .= ($labels[$fieldName] ?? $fieldName) . ': ' . $value . "\n";
}

$headers = [
    'From: ' . $from,
    'Content-Type: text/plain; charset=utf-8',
    'Content-Transfer-Encoding: 8bit',
    'MIME-Version: 1.0',
];
if ($email !== '') {
    // в заголовок попадает только адрес, уже прошедший FILTER_VALIDATE_EMAIL
    $headers[] = 'Reply-To: ' . $email;
}

if (mail($to, $subject, $body, implode("\r\n", $headers)) === false) {
    respond(500, ['ok' => false, 'error' => 'Письмо не отправилось. Попробуйте позже.']);
}

respond(200, ['ok' => true]);
