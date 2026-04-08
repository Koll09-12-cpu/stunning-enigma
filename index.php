<?php
// ========== PHP ОБРАБОТЧИКИ ==========
session_start();

// Подключение к БД
$host = 'localhost';
$dbname = 'oran_restaurant';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Ошибка подключения к БД: " . $e->getMessage());
}

// Обработка подписки
if (isset($_POST['action']) && $_POST['action'] === 'subscribe') {
    header('Content-Type: application/json');
    
    $email = $_POST['email'] ?? '';
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Неверный формат email']);
        exit;
    }
    
    try {
        // Проверяем существование
        $stmt = $pdo->prepare("SELECT id FROM subscribers WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Этот email уже подписан']);
            exit;
        }
        
        // Добавляем подписчика
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $stmt = $pdo->prepare("INSERT INTO subscribers (email, ip_address) VALUES (?, ?)");
        $stmt->execute([$email, $ip]);
        
        echo json_encode(['success' => true, 'message' => 'Спасибо за подписку!']);
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Ошибка базы данных']);
    }
    exit;
}

// Обработка заказа
if (isset($_POST['action']) && $_POST['action'] === 'order') {
    header('Content-Type: application/json');
    
    $data = json_decode($_POST['order_data'], true);
    
    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'Нет данных заказа']);
        exit;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Генерируем номер заказа
        $order_number = 'ORD-' . date('Ymd') . '-' . rand(1000, 9999);
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        
        // Сохраняем заказ
        $stmt = $pdo->prepare("
            INSERT INTO orders (
                order_number, customer_name, customer_phone, customer_email, 
                delivery_address, total_amount, comment, ip_address
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $order_number,
            $data['customer_name'],
            $data['customer_phone'],
            $data['customer_email'] ?? '',
            $data['delivery_address'],
            $data['total_amount'],
            $data['comment'] ?? '',
            $ip
        ]);
        
        $order_id = $pdo->lastInsertId();
        
        // Сохраняем товары
        $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_name, price, quantity) VALUES (?, ?, ?, ?)");
        
        foreach ($data['items'] as $item) {
            $stmt->execute([
                $order_id,
                $item['name'],
                $item['price'],
                $item['quantity']
            ]);
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Заказ оформлен!',
            'order_number' => $order_number
        ]);
        
    } catch(PDOException $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Ошибка сохранения заказа']);
    }
    exit;
}

// Получение истории заказов
if (isset($_GET['action']) && $_GET['action'] === 'get_orders') {
    header('Content-Type: application/json');
    
    try {
        $stmt = $pdo->query("
            SELECT o.*, 
                   (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as items_count
            FROM orders o 
            ORDER BY o.order_date DESC 
            LIMIT 50
        ");
        
        $orders = $stmt->fetchAll();
        
        foreach ($orders as &$order) {
            $stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
            $stmt->execute([$order['id']]);
            $order['items'] = $stmt->fetchAll();
        }
        
        echo json_encode(['success' => true, 'orders' => $orders]);
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Ошибка получения заказов']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ОРАН - Искусство японской кухни</title>
<link rel="apple-touch-icon" sizes="180x180" href="favicon/android-chrome-192x192.png">
<link rel="icon" type="image/png" sizes="32x32" href="favicon/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="favicon/favicon-16x16.png">
<link rel="manifest" href="/site.webmanifest">
<script src="https://cdn.tailwindcss.com/3.4.16"></script>
<script>tailwind.config={theme:{extend:{colors:{primary:'#e53e3e',secondary:'#4a5568'},borderRadius:{'none':'0px','sm':'4px',DEFAULT:'8px','md':'12px','lg':'16px','xl':'20px','2xl':'24px','3xl':'32px','full':'9999px','button':'8px'}}}}</script>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.6.0/remixicon.min.css">

<style>
/*! tailwindcss v3.4.1 | MIT License | https://tailwindcss.com */
*,
::after,
::before {
    box-sizing: border-box;
    border-width: 0;
    border-style: solid;
    border-color: #e5e7eb;
}
::after,
::before {
    --tw-content: '';
}
html {
    line-height: 1.5;
    -webkit-text-size-adjust: 100%;
    -moz-tab-size: 4;
    tab-size: 4;
    font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji";
    font-feature-settings: normal;
    font-variation-settings: normal;
}
body {
    margin: 0;
    line-height: inherit;
}
hr {
    height: 0;
    color: inherit;
    border-top-width: 1px;
}
abbr:where([title]) {
    -webkit-text-decoration: underline dotted;
    text-decoration: underline dotted;
}
h1,
h2,
h3,
h4,
h5,
h6 {
    font-size: inherit;
    font-weight: inherit;
}
a {
    color: inherit;
    text-decoration: inherit;
}
b,
strong {
    font-weight: bolder;
}
code,
kbd,
pre,
samp {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    font-size: 1em;
}
small {
    font-size: 80%;
}
sub,
sup {
    font-size: 75%;
    line-height: 0;
    position: relative;
    vertical-align: baseline;
}
sub {
    bottom: -0.25em;
}
sup {
    top: -0.5em;
}
table {
    text-indent: 0;
    border-color: inherit;
    border-collapse: collapse;
}
button,
input,
optgroup,
select,
textarea {
    font-family: inherit;
    font-feature-settings: inherit;
    font-variation-settings: inherit;
    font-size: 100%;
    font-weight: inherit;
    line-height: inherit;
    color: inherit;
    margin: 0;
    padding: 0;
}
button,
select {
    text-transform: none;
}
[type=button],
[type=reset],
[type=submit],
button {
    -webkit-appearance: button;
    background-color: transparent;
    background-image: none;
}
:-moz-focusring {
    outline: auto;
}
:-moz-ui-invalid {
    box-shadow: none;
}
progress {
    vertical-align: baseline;
}
::-webkit-inner-spin-button,
::-webkit-outer-spin-button {
    height: auto;
}
[type=search] {
    -webkit-appearance: textfield;
    outline-offset: -2px;
}
::-webkit-search-decoration {
    -webkit-appearance: none;
}
::-webkit-file-upload-button {
    -webkit-appearance: button;
    font: inherit;
}
summary {
    display: list-item;
}
blockquote,
dd,
dl,
figure,
h1,
h2,
h3,
h4,
h5,
h6,
hr,
p,
pre {
    margin: 0;
}
fieldset {
    margin: 0;
    padding: 0;
}
legend {
    padding: 0;
}
menu,
ol,
ul {
    list-style: none;
    margin: 0;
    padding: 0;
}
dialog {
    padding: 0;
}
textarea {
    resize: vertical;
}
input::placeholder,
textarea::placeholder {
    opacity: 1;
    color: #9ca3af;
}
[role=button],
button {
    cursor: pointer;
}
:disabled {
    cursor: default;
}
audio,
canvas,
embed,
iframe,
img,
object,
svg,
video {
    display: block;
    vertical-align: middle;
}
img,
video {
    max-width: 100%;
    height: auto;
}
[hidden] {
    display: none;
}

/* Базовые утилиты */
.container {
    width: 100%;
    margin-right: auto;
    margin-left: auto;
    padding-right: 1rem;
    padding-left: 1rem;
}
@media (min-width: 640px) {
    .container {
        max-width: 640px;
    }
}
@media (min-width: 768px) {
    .container {
        max-width: 768px;
    }
}
@media (min-width: 1024px) {
    .container {
        max-width: 1024px;
    }
}
@media (min-width: 1280px) {
    .container {
        max-width: 1280px;
    }
}
@media (min-width: 1536px) {
    .container {
        max-width: 1536px;
    }
}

/* Кастомные цвета */
.text-primary { color: #e53e3e; }
.bg-primary { background-color: #e53e3e; }
.hover\:bg-primary:hover { background-color: #e53e3e; }
.hover\:text-primary:hover { color: #e53e3e; }
.border-primary { border-color: #e53e3e; }

/* Кастомные скругления */
.rounded-button { border-radius: 8px; }
.rounded { border-radius: 8px; }
.rounded-lg { border-radius: 16px; }
.rounded-full { border-radius: 9999px; }

/* Основные стили */
:where([class^="ri-"])::before { content: "\f3c2"; }
body {
    font-family: 'Montserrat', sans-serif;
}
.hero-section {
    background-image: linear-gradient(to right, rgba(0, 0, 0, 0.7) 0%, rgba(0, 0, 0, 0.5) 40%, rgba(0, 0, 0, 0.3) 100%), url('https://readdy.ai/api/search-image?query=A%20high-quality%20professional%20photograph%20of%20fresh%2C%20beautifully%20arranged%20sushi%20on%20a%20dark%20wooden%20table.%20The%20sushi%20appears%20fresh%20and%20appetizing%20with%20vibrant%20colors.%20The%20background%20is%20slightly%20blurred%20with%20soft%20lighting%2C%20creating%20an%20elegant%20and%20sophisticated%20atmosphere%20typical%20of%20a%20high-end%20Japanese%20restaurant.%20The%20image%20has%20a%20modern%20and%20clean%20aesthetic.&width=1920&height=800&seq=12345&orientation=landscape');
    background-size: cover;
    background-position: center;
}
.category-card {
    transition: transform 0.3s ease;
    cursor: pointer;
}
.category-card:hover {
    transform: translateY(-5px);
}
.menu-item-card {
    transition: transform 0.3s ease, box-shadow 0.3s ease;
}
.menu-item-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
}
.custom-checkbox {
    position: relative;
    padding-left: 30px;
    cursor: pointer;
}
.custom-checkbox input {
    position: absolute;
    opacity: 0;
    cursor: pointer;
}
.checkmark {
    position: absolute;
    top: 0;
    left: 0;
    height: 20px;
    width: 20px;
    background-color: #fff;
    border: 2px solid #e53e3e;
    border-radius: 4px;
}
.custom-checkbox input:checked ~ .checkmark {
    background-color: #e53e3e;
}
.checkmark:after {
    content: "";
    position: absolute;
    display: none;
}
.custom-checkbox input:checked ~ .checkmark:after {
    display: block;
}
.custom-checkbox .checkmark:after {
    left: 6px;
    top: 2px;
    width: 5px;
    height: 10px;
    border: solid white;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
}
/* Анимация для уведомлений */
@keyframes slideIn {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}
.notification {
    animation: slideIn 0.3s ease-out;
}

/* Стили для модалки истории */
.order-history-modal {
    max-width: 800px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
}
.order-item-history {
    border-left: 3px solid #e53e3e;
    transition: all 0.2s;
}
.order-item-history:hover {
    background-color: #fef2f2;
}

/* Tailwind-подобные утилиты */
.fixed { position: fixed; }
.absolute { position: absolute; }
.relative { position: relative; }
.inset-0 { top: 0; right: 0; bottom: 0; left: 0; }
.top-0 { top: 0; }
.-top-1 { top: -0.25rem; }
.-right-1 { right: -0.25rem; }
.z-50 { z-index: 50; }
.z-\[60\] { z-index: 60; }
.mx-auto { margin-left: auto; margin-right: auto; }
.mx-4 { margin-left: 1rem; margin-right: 1rem; }
.my-4 { margin-top: 1rem; margin-bottom: 1rem; }
.mb-2 { margin-bottom: 0.5rem; }
.mb-3 { margin-bottom: 0.75rem; }
.mb-4 { margin-bottom: 1rem; }
.mb-6 { margin-bottom: 1.5rem; }
.mb-8 { margin-bottom: 2rem; }
.mb-12 { margin-bottom: 3rem; }
.mt-1 { margin-top: 0.25rem; }
.mt-2 { margin-top: 0.5rem; }
.mr-1 { margin-right: 0.25rem; }
.mr-2 { margin-right: 0.5rem; }
.mr-3 { margin-right: 0.75rem; }
.ml-4 { margin-left: 1rem; }
.block { display: block; }
.flex { display: flex; }
.grid { display: grid; }
.hidden { display: none; }
.h-6 { height: 1.5rem; }
.h-8 { height: 2rem; }
.h-10 { height: 2.5rem; }
.h-16 { height: 4rem; }
.h-40 { height: 10rem; }
.h-48 { height: 12rem; }
.h-\[400px\] { height: 400px; }
.h-\[600px\] { height: 600px; }
.h-auto { height: auto; }
.h-full { height: 100%; }
.max-h-\[60vh\] { max-height: 60vh; }
.w-5 { width: 1.25rem; }
.w-6 { width: 1.5rem; }
.w-8 { width: 2rem; }
.w-10 { width: 2.5rem; }
.w-12 { width: 3rem; }
.w-16 { width: 4rem; }
.w-full { width: 100%; }
.max-w-2xl { max-width: 42rem; }
.max-w-md { max-width: 28rem; }
.max-w-sm { max-width: 24rem; }
.flex-1 { flex: 1 1 0%; }
.transform { transform: translate(var(--tw-translate-x), var(--tw-translate-y)) rotate(var(--tw-rotate)) skewX(var(--tw-skew-x)) skewY(var(--tw-skew-y)) scaleX(var(--tw-scale-x)) scaleY(var(--tw-scale-y)); }
.cursor-pointer { cursor: pointer; }
.grid-cols-1 { grid-template-columns: repeat(1, minmax(0, 1fr)); }
.flex-col { flex-direction: column; }
.flex-wrap { flex-wrap: wrap; }
.items-start { align-items: flex-start; }
.items-center { align-items: center; }
.items-end { align-items: flex-end; }
.justify-end { justify-content: flex-end; }
.justify-center { justify-content: center; }
.justify-between { justify-content: space-between; }
.gap-6 { gap: 1.5rem; }
.gap-8 { gap: 2rem; }
.gap-10 { gap: 2.5rem; }
.gap-12 { gap: 3rem; }
.space-x-3 > :not([hidden]) ~ :not([hidden]) { --tw-space-x-reverse: 0; margin-right: calc(0.75rem * var(--tw-space-x-reverse)); margin-left: calc(0.75rem * calc(1 - var(--tw-space-x-reverse))); }
.space-x-4 > :not([hidden]) ~ :not([hidden]) { --tw-space-x-reverse: 0; margin-right: calc(1rem * var(--tw-space-x-reverse)); margin-left: calc(1rem * calc(1 - var(--tw-space-x-reverse))); }
.space-x-8 > :not([hidden]) ~ :not([hidden]) { --tw-space-x-reverse: 0; margin-right: calc(2rem * var(--tw-space-x-reverse)); margin-left: calc(2rem * calc(1 - var(--tw-space-x-reverse))); }
.space-y-2 > :not([hidden]) ~ :not([hidden]) { --tw-space-y-reverse: 0; margin-top: calc(0.5rem * calc(1 - var(--tw-space-y-reverse))); margin-bottom: calc(0.5rem * var(--tw-space-y-reverse)); }
.space-y-3 > :not([hidden]) ~ :not([hidden]) { --tw-space-y-reverse: 0; margin-top: calc(0.75rem * calc(1 - var(--tw-space-y-reverse))); margin-bottom: calc(0.75rem * var(--tw-space-y-reverse)); }
.space-y-4 > :not([hidden]) ~ :not([hidden]) { --tw-space-y-reverse: 0; margin-top: calc(1rem * calc(1 - var(--tw-space-y-reverse))); margin-bottom: calc(1rem * var(--tw-space-y-reverse)); }
.overflow-hidden { overflow: hidden; }
.overflow-y-auto { overflow-y: auto; }
.whitespace-nowrap { white-space: nowrap; }
.rounded { border-radius: 8px; }
.rounded-l { border-top-left-radius: 8px; border-bottom-left-radius: 8px; }
.rounded-r { border-top-right-radius: 8px; border-bottom-right-radius: 8px; }
.border { border-width: 1px; }
.border-y { border-top-width: 1px; border-bottom-width: 1px; }
.border-t { border-top-width: 1px; }
.border-none { border-style: none; }
.border-gray-200 { border-color: #e5e7eb; }
.border-gray-800 { border-color: #1f2937; }
.bg-black { background-color: #000; }
.bg-white { background-color: #fff; }
.bg-gray-50 { background-color: #f9fafb; }
.bg-gray-200 { background-color: #e5e7eb; }
.bg-gray-300 { background-color: #d1d5db; }
.bg-gray-400 { background-color: #9ca3af; }
.bg-gray-800 { background-color: #1f2937; }
.bg-gray-900 { background-color: #111827; }
.bg-green-500 { background-color: #10b981; }
.bg-red-500 { background-color: #ef4444; }
.bg-opacity-50 { --tw-bg-opacity: 0.5; }
.bg-gradient-to-t { background-image: linear-gradient(to top, var(--tw-gradient-stops)); }
.from-black\/70 { --tw-gradient-from: rgb(0 0 0 / 0.7) var(--tw-gradient-from-position); --tw-gradient-to: rgb(0 0 0 / 0) var(--tw-gradient-to-position); --tw-gradient-stops: var(--tw-gradient-from), var(--tw-gradient-to); }
.to-transparent { --tw-gradient-to: transparent var(--tw-gradient-to-position); }
.object-cover { object-fit: cover; }
.object-top { object-position: top; }
.p-3 { padding: 0.75rem; }
.p-4 { padding: 1rem; }
.p-6 { padding: 1.5rem; }
.px-4 { padding-left: 1rem; padding-right: 1rem; }
.px-6 { padding-left: 1.5rem; padding-right: 1.5rem; }
.px-8 { padding-left: 2rem; padding-right: 2rem; }
.py-1 { padding-top: 0.25rem; padding-bottom: 0.25rem; }
.py-2 { padding-top: 0.5rem; padding-bottom: 0.5rem; }
.py-3 { padding-top: 0.75rem; padding-bottom: 0.75rem; }
.py-6 { padding-top: 1.5rem; padding-bottom: 1.5rem; }
.py-8 { padding-top: 2rem; padding-bottom: 2rem; }
.py-12 { padding-top: 3rem; padding-bottom: 3rem; }
.py-16 { padding-top: 4rem; padding-bottom: 4rem; }
.pt-6 { padding-top: 1.5rem; }
.pt-12 { padding-top: 3rem; }
.pb-6 { padding-bottom: 1.5rem; }
.text-left { text-align: left; }
.text-center { text-align: center; }
.text-right { text-align: right; }
.font-\[\'Pacifico\'\] { font-family: 'Pacifico', cursive; }
.text-xs { font-size: 0.75rem; line-height: 1rem; }
.text-sm { font-size: 0.875rem; line-height: 1.25rem; }
.text-lg { font-size: 1.125rem; line-height: 1.75rem; }
.text-xl { font-size: 1.25rem; line-height: 1.75rem; }
.text-2xl { font-size: 1.5rem; line-height: 2rem; }
.text-3xl { font-size: 1.875rem; line-height: 2.25rem; }
.text-5xl { font-size: 3rem; line-height: 1; }
.font-medium { font-weight: 500; }
.font-bold { font-weight: 700; }
.text-white { color: #fff; }
.text-gray-400 { color: #9ca3af; }
.text-gray-500 { color: #6b7280; }
.text-gray-600 { color: #4b5563; }
.text-gray-700 { color: #374151; }
.text-gray-800 { color: #1f2937; }
.text-red-500 { color: #ef4444; }
.text-red-700 { color: #b91c1c; }
.shadow-md { box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1); }
.shadow-lg { box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1); }
.transition-colors { transition-property: color, background-color, border-color, fill, stroke, -webkit-text-decoration-color; transition-property: color, background-color, border-color, text-decoration-color, fill, stroke; transition-property: color, background-color, border-color, text-decoration-color, fill, stroke, -webkit-text-decoration-color; transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1); transition-duration: 150ms; }
.sticky { position: sticky; }
</style>
</head>

<body class="bg-gray-50">
<!-- Header -->
<header class="bg-white shadow-md sticky top-0 z-50">
<div class="container mx-auto px-4 py-3 flex items-center justify-between">
<div class="flex items-center">
<a href="#" class="text-3xl font-['Pacifico'] text-primary">ОРАН</a>
</div>
<nav class="hidden md:flex items-center space-x-8">
<a href="#menu" class="text-gray-700 hover:text-primary font-medium transition-colors">Меню</a>
<a href="#about" class="text-gray-700 hover:text-primary font-medium transition-colors">О нас</a>
<a href="#delivery" class="text-gray-700 hover:text-primary font-medium transition-colors">Доставка</a>
<a href="#contacts" class="text-gray-700 hover:text-primary font-medium transition-colors">Контакты</a>
</nav>
<div class="flex items-center space-x-4">
<button class="text-gray-700 hover:text-primary transition-colors mr-2" id="history-btn" title="История заказов">
<i class="ri-history-line ri-lg"></i>
</button>
<div class="relative cursor-pointer" id="cart-icon">
<div class="w-10 h-10 flex items-center justify-center text-gray-700 hover:text-primary">
<i class="ri-shopping-cart-2-line ri-lg"></i>
</div>
<span class="absolute -top-1 -right-1 bg-primary text-white text-xs w-5 h-5 flex items-center justify-center rounded-full" id="cart-counter">0</span>
</div>
<button class="bg-primary text-white px-6 py-2 !rounded-button font-medium hover:bg-red-600 transition-colors whitespace-nowrap" id="order-button">Заказать</button>
</div>
</div>
</header>

<!-- Hero Section -->
<section class="hero-section w-full h-[600px] flex items-center">
<div class="container mx-auto px-4">
<div class="max-w-2xl text-white">
<h1 class="text-5xl font-bold mb-4">ОРАН</h1>
<h2 class="text-3xl font-medium mb-6">Искусство японской кухни</h2>
<p class="text-xl mb-8">Свежие и вкусные суши с доставкой по всему городу</p>
<button class="bg-primary text-white px-8 py-3 !rounded-button font-medium hover:bg-red-600 transition-colors whitespace-nowrap" id="view-menu-btn">Посмотреть меню</button>
</div>
</div>
</section>

<!-- Popular Items Section -->
<section id="menu" class="py-16 bg-white">
<div class="container mx-auto px-4">
<div class="flex justify-between items-center mb-8">
<h2 class="text-3xl font-bold" id="menu-title">Популярные роллы</h2>
<button class="bg-gray-200 text-gray-700 px-4 py-2 rounded-button font-medium hover:bg-gray-300 transition-colors hidden" id="reset-filter-btn">← Все товары</button>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8" id="products-grid">
<!-- Популярные роллы (по умолчанию) -->
<div class="menu-item-card bg-white rounded shadow-lg overflow-hidden" data-category="роллы" data-name="Филадельфия" data-price="450">
<img src="https://readdy.ai/api/search-image?query=A%20professional%20close-up%20photograph%20of%20a%20Philadelphia%20roll%20sushi%20with%20cream%20cheese%2C%20fresh%20salmon%2C%20and%20avocado.%20The%20sushi%20is%20beautifully%20arranged%20on%20a%20dark%20plate%20with%20a%20minimalist%20presentation.%20The%20lighting%20is%20soft%20and%20highlights%20the%20fresh%20ingredients.%20The%20background%20is%20slightly%20blurred%20with%20a%20dark%20tone%20to%20make%20the%20sushi%20stand%20out.&width=400&height=300&seq=1001&orientation=landscape" alt="Филадельфия" class="w-full h-48 object-cover object-top">
<div class="p-4">
<h3 class="text-xl font-bold mb-2">Филадельфия</h3>
<p class="text-gray-600 text-sm mb-3">Лосось, сливочный сыр, авокадо, огурец, рис, нори</p>
<div class="flex justify-between items-center">
<span class="text-lg font-bold text-primary price">450 ₽</span>
<button class="add-to-cart-btn bg-primary text-white px-4 py-2 !rounded-button text-sm font-medium hover:bg-red-600 transition-colors whitespace-nowrap">В корзину</button>
</div>
</div>
</div>

<div class="menu-item-card bg-white rounded shadow-lg overflow-hidden" data-category="роллы" data-name="Калифорния" data-price="420">
<img src="https://readdy.ai/api/search-image?query=A%20professional%20close-up%20photograph%20of%20a%20California%20roll%20sushi%20with%20crab%20meat%2C%20avocado%2C%20and%20cucumber.%20The%20sushi%20is%20beautifully%20arranged%20on%20a%20dark%20plate%20with%20a%20minimalist%20presentation.%20The%20lighting%20is%20soft%20and%20highlights%20the%20fresh%20ingredients.%20The%20background%20is%20slightly%20blurred%20with%20a%20dark%20tone%20to%20make%20the%20sushi%20stand%20out.&width=400&height=300&seq=1002&orientation=landscape" alt="Калифорния" class="w-full h-48 object-cover object-top">
<div class="p-4">
<h3 class="text-xl font-bold mb-2">Калифорния</h3>
<p class="text-gray-600 text-sm mb-3">Краб, авокадо, огурец, тобико, майонез, рис, нори</p>
<div class="flex justify-between items-center">
<span class="text-lg font-bold text-primary price">420 ₽</span>
<button class="add-to-cart-btn bg-primary text-white px-4 py-2 !rounded-button text-sm font-medium hover:bg-red-600 transition-colors whitespace-nowrap">В корзину</button>
</div>
</div>
</div>

<div class="menu-item-card bg-white rounded shadow-lg overflow-hidden" data-category="роллы" data-name="Дракон" data-price="520">
<img src="https://readdy.ai/api/search-image?query=A%20professional%20close-up%20photograph%20of%20a%20Dragon%20roll%20sushi%20with%20eel%2C%20avocado%2C%20and%20cucumber.%20The%20sushi%20is%20beautifully%20arranged%20on%20a%20dark%20plate%20with%20a%20minimalist%20presentation.%20The%20lighting%20is%20soft%20and%20highlights%20the%20fresh%20ingredients.%20The%20background%20is%20slightly%20blurred%20with%20a%20dark%20tone%20to%20make%20the%20sushi%20stand%20out.&width=400&height=300&seq=1003&orientation=landscape" alt="Дракон" class="w-full h-48 object-cover object-top">
<div class="p-4">
<h3 class="text-xl font-bold mb-2">Дракон</h3>
<p class="text-gray-600 text-sm mb-3">Угорь, авокадо, огурец, соус унаги, кунжут, рис, нори</p>
<div class="flex justify-between items-center">
<span class="text-lg font-bold text-primary price">520 ₽</span>
<button class="add-to-cart-btn bg-primary text-white px-4 py-2 !rounded-button text-sm font-medium hover:bg-red-600 transition-colors whitespace-nowrap">В корзину</button>
</div>
</div>
</div>

<div class="menu-item-card bg-white rounded shadow-lg overflow-hidden" data-category="роллы" data-name="Темпура" data-price="480">
<img src="https://readdy.ai/api/search-image?query=A%20professional%20close-up%20photograph%20of%20a%20Tempura%20roll%20sushi%20with%20shrimp%20tempura%2C%20avocado%2C%20and%20cucumber.%20The%20sushi%20is%20beautifully%20arranged%20on%20a%20dark%20plate%20with%20a%20minimalist%20presentation.%20The%20lighting%20is%20soft%20and%20highlights%20the%20fresh%20ingredients.%20The%20background%20is%20slightly%20blurred%20with%20a%20dark%20tone%20to%20make%20the%20sushi%20stand%20out.&width=400&height=300&seq=1004&orientation=landscape" alt="Темпура" class="w-full h-48 object-cover object-top">
<div class="p-4">
<h3 class="text-xl font-bold mb-2">Темпура</h3>
<p class="text-gray-600 text-sm mb-3">Креветка в темпуре, авокадо, огурец, соус спайси, рис, нори</p>
<div class="flex justify-between items-center">
<span class="text-lg font-bold text-primary price">480 ₽</span>
<button class="add-to-cart-btn bg-primary text-white px-4 py-2 !rounded-button text-sm font-medium hover:bg-red-600 transition-colors whitespace-nowrap">В корзину</button>
</div>
</div>
</div>

<!-- Суши (скрыты по умолчанию) -->
<div class="menu-item-card bg-white rounded shadow-lg overflow-hidden hidden" data-category="суши" data-name="Лосось" data-price="350">
<img src="https://readdy.ai/api/search-image?query=A%20professional%20close-up%20photograph%20of%20fresh%20salmon%20nigiri%20sushi%20on%20a%20dark%20plate.%20The%20rice%20is%20perfectly%20shaped%20with%20a%20slice%20of%20fresh%20salmon%20on%20top.%20The%20lighting%20is%20soft%20and%20highlights%20the%20fresh%20ingredients.&width=400&height=300&seq=2001&orientation=landscape" alt="Лосось" class="w-full h-48 object-cover object-top">
<div class="p-4">
<h3 class="text-xl font-bold mb-2">Лосось</h3>
<p class="text-gray-600 text-sm mb-3">Свежий лосось, рис, васаби</p>
<div class="flex justify-between items-center">
<span class="text-lg font-bold text-primary price">350 ₽</span>
<button class="add-to-cart-btn bg-primary text-white px-4 py-2 !rounded-button text-sm font-medium hover:bg-red-600 transition-colors whitespace-nowrap">В корзину</button>
</div>
</div>
</div>

<div class="menu-item-card bg-white rounded shadow-lg overflow-hidden hidden" data-category="суши" data-name="Тунец" data-price="380">
<img src="https://readdy.ai/api/search-image?query=A%20professional%20close-up%20photograph%20of%20fresh%20tuna%20nigiri%20sushi%20on%20a%20dark%20plate.%20The%20rice%20is%20perfectly%20shaped%20with%20a%20slice%20of%20fresh%20tuna%20on%20top.%20The%20lighting%20is%20soft%20and%20highlights%20the%20fresh%20ingredients.&width=400&height=300&seq=2002&orientation=landscape" alt="Тунец" class="w-full h-48 object-cover object-top">
<div class="p-4">
<h3 class="text-xl font-bold mb-2">Тунец</h3>
<p class="text-gray-600 text-sm mb-3">Свежий тунец, рис, васаби</p>
<div class="flex justify-between items-center">
<span class="text-lg font-bold text-primary price">380 ₽</span>
<button class="add-to-cart-btn bg-primary text-white px-4 py-2 !rounded-button text-sm font-medium hover:bg-red-600 transition-colors whitespace-nowrap">В корзину</button>
</div>
</div>
</div>

<div class="menu-item-card bg-white rounded shadow-lg overflow-hidden hidden" data-category="суши" data-name="Угорь" data-price="420">
<img src="https://readdy.ai/api/search-image?query=A%20professional%20close-up%20photograph%20of%20fresh%20eel%20nigiri%20sushi%20with%20unagi%20sauce%20on%20a%20dark%20plate.%20The%20rice%20is%20perfectly%20shaped%20with%20a%20slice%20of%20grilled%20eel%20on%20top.%20The%20lighting%20is%20soft%20and%20highlights%20the%20fresh%20ingredients.&width=400&height=300&seq=2003&orientation=landscape" alt="Угорь" class="w-full h-48 object-cover object-top">
<div class="p-4">
<h3 class="text-xl font-bold mb-2">Угорь</h3>
<p class="text-gray-600 text-sm mb-3">Копченый угорь, рис, соус унаги</p>
<div class="flex justify-between items-center">
<span class="text-lg font-bold text-primary price">420 ₽</span>
<button class="add-to-cart-btn bg-primary text-white px-4 py-2 !rounded-button text-sm font-medium hover:bg-red-600 transition-colors whitespace-nowrap">В корзину</button>
</div>
</div>
</div>

<!-- Сеты (скрыты по умолчанию) -->
<div class="menu-item-card bg-white rounded shadow-lg overflow-hidden hidden" data-category="сеты" data-name="Сет Филадельфия" data-price="1250">
<img src="https://readdy.ai/api/search-image?query=A%20professional%20photograph%20of%20a%20large%20sushi%20set%20with%20various%20rolls%20including%20Philadelphia%2C%20California%2C%20and%20Dragon%20rolls%20arranged%20on%20a%20large%20wooden%20board.%20The%20presentation%20is%20elegant%20and%20colorful.&width=400&height=300&seq=3001&orientation=landscape" alt="Сет Филадельфия" class="w-full h-48 object-cover object-top">
<div class="p-4">
<h3 class="text-xl font-bold mb-2">Сет Филадельфия</h3>
<p class="text-gray-600 text-sm mb-3">24 ролла: Филадельфия, Калифорния, Дракон</p>
<div class="flex justify-between items-center">
<span class="text-lg font-bold text-primary price">1250 ₽</span>
<button class="add-to-cart-btn bg-primary text-white px-4 py-2 !rounded-button text-sm font-medium hover:bg-red-600 transition-colors whitespace-nowrap">В корзину</button>
</div>
</div>
</div>

<div class="menu-item-card bg-white rounded shadow-lg overflow-hidden hidden" data-category="сеты" data-name="Сет Для двоих" data-price="1890">
<img src="https://readdy.ai/api/search-image?query=A%20professional%20photograph%20of%20a%20romantic%20sushi%20set%20for%20two%20with%20assorted%20rolls%2C%20nigiri%2C%20and%20sashimi%20arranged%20beautifully%20on%20a%20dark%20platter.%20Perfect%20for%20a%20date%20night.&width=400&height=300&seq=3002&orientation=landscape" alt="Сет Для двоих" class="w-full h-48 object-cover object-top">
<div class="p-4">
<h3 class="text-xl font-bold mb-2">Сет Для двоих</h3>
<p class="text-gray-600 text-sm mb-3">32 ролла, 8 суши, 4 сашими</p>
<div class="flex justify-between items-center">
<span class="text-lg font-bold text-primary price">1890 ₽</span>
<button class="add-to-cart-btn bg-primary text-white px-4 py-2 !rounded-button text-sm font-medium hover:bg-red-600 transition-colors whitespace-nowrap">В корзину</button>
</div>
</div>
</div>

<div class="menu-item-card bg-white rounded shadow-lg overflow-hidden hidden" data-category="сеты" data-name="Сет Император" data-price="2450">
<img src="https://readdy.ai/api/search-image?query=A%20luxurious%20professional%20photograph%20of%20a%20premium%20sushi%20set%20with%20the%20finest%20ingredients%20including%20tuna%2C%20salmon%2C%20eel%2C%20and%20special%20rolls%20on%20an%20elegant%20black%20platter%20with%20gold%20accents.&width=400&height=300&seq=3003&orientation=landscape" alt="Сет Император" class="w-full h-48 object-cover object-top">
<div class="p-4">
<h3 class="text-xl font-bold mb-2">Сет Император</h3>
<p class="text-gray-600 text-sm mb-3">48 премиальных роллов, 12 суши</p>
<div class="flex justify-between items-center">
<span class="text-lg font-bold text-primary price">2450 ₽</span>
<button class="add-to-cart-btn bg-primary text-white px-4 py-2 !rounded-button text-sm font-medium hover:bg-red-600 transition-colors whitespace-nowrap">В корзину</button>
</div>
</div>
</div>

<!-- Напитки (скрыты по умолчанию) -->
<div class="menu-item-card bg-white rounded shadow-lg overflow-hidden hidden" data-category="напитки" data-name="Саке" data-price="350">
<img src="https://readdy.ai/api/search-image?query=A%20professional%20photograph%20of%20traditional%20Japanese%20sake%20in%20a%20ceramic%20bottle%20and%20small%20cups%20on%20a%20dark%20wooden%20table.%20Elegant%20presentation%20with%20soft%20lighting.&width=400&height=300&seq=4001&orientation=landscape" alt="Саке" class="w-full h-48 object-cover object-top">
<div class="p-4">
<h3 class="text-xl font-bold mb-2">Саке</h3>
<p class="text-gray-600 text-sm mb-3">Традиционная японская рисовая водка</p>
<div class="flex justify-between items-center">
<span class="text-lg font-bold text-primary price">350 ₽</span>
<button class="add-to-cart-btn bg-primary text-white px-4 py-2 !rounded-button text-sm font-medium hover:bg-red-600 transition-colors whitespace-nowrap">В корзину</button>
</div>
</div>
</div>

<div class="menu-item-card bg-white rounded shadow-lg overflow-hidden hidden" data-category="напитки" data-name="Зеленый чай" data-price="150">
<img src="https://readdy.ai/api/search-image?query=A%20professional%20photograph%20of%20fresh%20Japanese%20green%20tea%20in%20a%20traditional%20cast%20iron%20teapot%20and%20cup%20on%20a%20bamboo%20mat.%20Steam%20rising%20from%20the%20cup%2C%20warm%20atmosphere.&width=400&height=300&seq=4002&orientation=landscape" alt="Зеленый чай" class="w-full h-48 object-cover object-top">
<div class="p-4">
<h3 class="text-xl font-bold mb-2">Зеленый чай</h3>
<p class="text-gray-600 text-sm mb-3">Японский зеленый чай высшего сорта</p>
<div class="flex justify-between items-center">
<span class="text-lg font-bold text-primary price">150 ₽</span>
<button class="add-to-cart-btn bg-primary text-white px-4 py-2 !rounded-button text-sm font-medium hover:bg-red-600 transition-colors whitespace-nowrap">В корзину</button>
</div>
</div>
</div>

<div class="menu-item-card bg-white rounded shadow-lg overflow-hidden hidden" data-category="напитки" data-name="Рамунэ" data-price="200">
<img src="https://readdy.ai/api/search-image?query=A%20professional%20photograph%20of%20Ramune%20Japanese%20soda%20in%20its%20distinctive%20Codd-neck%20bottle%20with%20a%20marble.%20Colorful%20and%20refreshing%20presentation%20on%20a%20bright%20background.&width=400&height=300&seq=4003&orientation=landscape" alt="Рамунэ" class="w-full h-48 object-cover object-top">
<div class="p-4">
<h3 class="text-xl font-bold mb-2">Рамунэ</h3>
<p class="text-gray-600 text-sm mb-3">Японская газировка со вкусом лимона</p>
<div class="flex justify-between items-center">
<span class="text-lg font-bold text-primary price">200 ₽</span>
<button class="add-to-cart-btn bg-primary text-white px-4 py-2 !rounded-button text-sm font-medium hover:bg-red-600 transition-colors whitespace-nowrap">В корзину</button>
</div>
</div>
</div>
</div>
</div>
</section>

<!-- Benefits Section -->
<section class="py-16 bg-gray-50">
<div class="container mx-auto px-4">
<h2 class="text-3xl font-bold text-center mb-12">Наши преимущества</h2>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
<div class="bg-white p-6 rounded shadow-md text-center">
<div class="w-16 h-16 mx-auto mb-4 flex items-center justify-center text-primary">
<i class="ri-leaf-line ri-3x"></i>
</div>
<h3 class="text-xl font-bold mb-2">Свежие ингредиенты</h3>
<p class="text-gray-600">Мы используем только свежие продукты высшего качества</p>
</div>
<div class="bg-white p-6 rounded shadow-md text-center">
<div class="w-16 h-16 mx-auto mb-4 flex items-center justify-center text-primary">
<i class="ri-time-line ri-3x"></i>
</div>
<h3 class="text-xl font-bold mb-2">Быстрая доставка</h3>
<p class="text-gray-600">Доставляем заказы в течение 60 минут или бесплатно</p>
</div>
<div class="bg-white p-6 rounded shadow-md text-center">
<div class="w-16 h-16 mx-auto mb-4 flex items-center justify-center text-primary">
<i class="ri-user-star-line ri-3x"></i>
</div>
<h3 class="text-xl font-bold mb-2">Опытные повара</h3>
<p class="text-gray-600">Наши шеф-повара обучались искусству суши в Японии</p>
</div>
<div class="bg-white p-6 rounded shadow-md text-center">
<div class="w-16 h-16 mx-auto mb-4 flex items-center justify-center text-primary">
<i class="ri-gift-line ri-3x"></i>
</div>
<h3 class="text-xl font-bold mb-2">Программа лояльности</h3>
<p class="text-gray-600">Скидки и бонусы для постоянных клиентов</p>
</div>
</div>
</div>
</section>

<!-- Menu Categories Section -->
<section class="py-16 bg-white">
<div class="container mx-auto px-4">
<h2 class="text-3xl font-bold text-center mb-12">Категории меню</h2>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
<div class="category-card bg-white rounded shadow-lg overflow-hidden cursor-pointer" onclick="filterByCategory('роллы')">
<div class="relative h-40">
<img src="https://readdy.ai/api/search-image?query=A%20professional%20overhead%20photograph%20of%20various%20sushi%20rolls%20arranged%20on%20a%20dark%20plate.%20The%20rolls%20include%20different%20types%20with%20vibrant%20ingredients%20like%20salmon%2C%20tuna%2C%20avocado%2C%20and%20cucumber.%20The%20lighting%20is%20soft%20and%20highlights%20the%20fresh%20ingredients.%20The%20background%20is%20slightly%20blurred%20with%20a%20dark%20tone%20to%20make%20the%20sushi%20stand%20out.&width=400&height=200&seq=2001&orientation=landscape" alt="Роллы" class="w-full h-full object-cover object-top">
<a href="#menu"><div class="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent flex items-end">
<h3 class="text-white text-xl font-bold p-4">Роллы</h3>
</div></a>
</div>
</div>
<div class="category-card bg-white rounded shadow-lg overflow-hidden cursor-pointer" onclick="filterByCategory('суши')">
<div class="relative h-40">
<img src="https://readdy.ai/api/search-image?query=A%20professional%20overhead%20photograph%20of%20various%20nigiri%20sushi%20arranged%20on%20a%20dark%20plate.%20The%20nigiri%20includes%20different%20types%20with%20fresh%20fish%20like%20salmon%2C%20tuna%2C%20and%20shrimp%20on%20top%20of%20rice.%20The%20lighting%20is%20soft%20and%20highlights%20the%20fresh%20ingredients.%20The%20background%20is%20slightly%20blurred%20with%20a%20dark%20tone%20to%20make%20the%20sushi%20stand%20out.&width=400&height=200&seq=2002&orientation=landscape" alt="Суши" class="w-full h-full object-cover object-top">
<a href="#menu"><div class="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent flex items-end">
<h3 class="text-white text-xl font-bold p-4">Суши</h3>
</div></a>
</div>
</div>
<div class="category-card bg-white rounded shadow-lg overflow-hidden cursor-pointer" onclick="filterByCategory('сеты')">
<div class="relative h-40">
<img src="https://readdy.ai/api/search-image?query=A%20professional%20overhead%20photograph%20of%20a%20large%20sushi%20set%20arranged%20on%20a%20dark%20wooden%20board.%20The%20set%20includes%20various%20rolls%2C%20nigiri%2C%20and%20sashimi%20with%20fresh%20ingredients.%20The%20lighting%20is%20soft%20and%20highlights%20the%20fresh%20ingredients.%20The%20background%20is%20slightly%20blurred%20with%20a%20dark%20tone%20to%20make%20the%20sushi%20stand%20out.&width=400&height=200&seq=2003&orientation=landscape" alt="Сеты" class="w-full h-full object-cover object-top">
<a href="#menu"><div class="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent flex items-end">
<h3 class="text-white text-xl font-bold p-4">Сеты</h3>
</div></a>
</div>
</div>
<div class="category-card bg-white rounded shadow-lg overflow-hidden cursor-pointer" onclick="filterByCategory('напитки')">
<div class="relative h-40">
<img src="https://readdy.ai/api/search-image?query=A%20professional%20photograph%20of%20Japanese%20drinks%20like%20green%20tea%2C%20sake%2C%20and%20other%20beverages%20arranged%20on%20a%20dark%20wooden%20table.%20The%20drinks%20are%20in%20traditional%20Japanese%20cups%20and%20bottles.%20The%20lighting%20is%20soft%20and%20creates%20an%20elegant%20atmosphere.%20The%20background%20is%20slightly%20blurred%20with%20a%20dark%20tone%20to%20make%20the%20drinks%20stand%20out.&width=400&height=200&seq=2004&orientation=landscape" alt="Напитки" class="w-full h-full object-cover object-top">
<a href="#menu"><div class="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent flex items-end">
<h3 class="text-white text-xl font-bold p-4">Напитки</h3>
</div></a>
</div>
</div>
</div>
</div>
</section>

<!-- Delivery Section -->
<section id="delivery" class="py-16 bg-gray-50">
<div class="container mx-auto px-4">
<h2 class="text-3xl font-bold text-center mb-12">Информация о доставке</h2>
<div class="grid grid-cols-1 lg:grid-cols-2 gap-10">
<div class="bg-white p-6 rounded shadow-md">
<h3 class="text-2xl font-bold mb-4">Зоны доставки</h3>
<div class="w-full h-[400px] bg-gray-200 rounded mb-6 overflow-hidden">
<div style="background-image: url('https://avatars.mds.yandex.net/i?id=c3cb6d7e75f25c7736697308a4cb7e72_sr-5904614-images-thumbs&n=13'); background-size: cover; background-position: center; width: 100%; height: 100%;"></div>
</div>
</div>
<div class="bg-white p-6 rounded shadow-md">
<h3 class="text-2xl font-bold mb-4">Условия доставки</h3>
<ul class="space-y-4">
<li class="flex items-start">
<div class="w-8 h-8 flex items-center justify-center text-primary mr-3 mt-1">
<i class="ri-time-line ri-lg"></i>
</div>
<div>
<h4 class="font-bold text-lg">Время доставки</h4>
<p class="text-gray-600">В среднем 45-60 минут. В часы пик может увеличиваться до 90 минут.</p>
</div>
</li>
<li class="flex items-start">
<div class="w-8 h-8 flex items-center justify-center text-primary mr-3 mt-1">
<i class="ri-money-dollar-circle-line ri-lg"></i>
</div>
<div>
<h4 class="font-bold text-lg">Стоимость доставки</h4>
<p class="text-gray-600">Бесплатно при заказе от 1500 ₽. При заказе меньше 1500 ₽ стоимость доставки составляет 250 ₽.</p>
</div>
</li>
<li class="flex items-start">
<div class="w-8 h-8 flex items-center justify-center text-primary mr-3 mt-1">
<i class="ri-shopping-basket-line ri-lg"></i>
</div>
<div>
<h4 class="font-bold text-lg">Минимальная сумма заказа</h4>
<p class="text-gray-600">Минимальная сумма заказа составляет 800 ₽.</p>
</div>
</li>
<li class="flex items-start">
<div class="w-8 h-8 flex items-center justify-center text-primary mr-3 mt-1">
<i class="ri-map-pin-line ri-lg"></i>
</div>
<div>
<h4 class="font-bold text-lg">Зоны доставки</h4>
<p class="text-gray-600">Мы доставляем по всему городу и в ближайшие пригороды. Подробности уточняйте у оператора.</p>
</div>
</li>
</ul>
</div>
</div>
</div>
</section>

<!-- About Us Section -->
<section id="about" class="py-16 bg-white">
<div class="container mx-auto px-4">
<div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
<div>
<h2 class="text-3xl font-bold mb-6">О ресторане ОРАН</h2>
<p class="text-gray-600 mb-4">Ресторан ОРАН — это место, где традиционная японская кухня встречается с современными кулинарными тенденциями. Мы создаем не просто блюда, а настоящие произведения искусства.</p>
<p class="text-gray-600 mb-4">Наша команда профессиональных поваров прошла обучение в Японии и владеет всеми секретами приготовления идеальных суши. Мы используем только свежие ингредиенты высшего качества, чтобы каждый ролл был не только вкусным, но и полезным.</p>
<p class="text-gray-600 mb-4">ОРАН — это не просто ресторан, это философия. Мы стремимся создать атмосферу, в которой каждый гость чувствует себя особенным. Наша цель — подарить вам незабываемые гастрономические впечатления.</p>
</div>
<div>
<img src="https://readdy.ai/api/search-image?query=A%20professional%20photograph%20of%20a%20modern%20Japanese%20restaurant%20interior%20with%20elegant%20design.%20The%20interior%20features%20a%20sushi%20bar%20with%20chefs%20preparing%20food%2C%20wooden%20tables%2C%20and%20subtle%20Japanese%20decor%20elements.%20The%20lighting%20is%20warm%20and%20creates%20an%20inviting%20atmosphere.%20The%20image%20conveys%20a%20sense%20of%20sophistication%20and%20authenticity.&width=600&height=400&seq=3001&orientation=landscape" alt="Ресторан ОРАН" class="w-full h-auto rounded shadow-lg">
</div>
</div>
</div>
</section>

<!-- Footer -->
<footer id="contacts" class="bg-gray-900 text-white pt-12 pb-6">
<div class="container mx-auto px-4">
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 mb-8">
<div>
<h3 class="text-xl font-bold mb-4">Контакты</h3>
<ul class="space-y-3">
<li class="flex items-start">
<div class="w-6 h-6 flex items-center justify-center mr-3 mt-1">
<i class="ri-map-pin-line"></i>
</div>
<span>ул. Пушкина, д. 10, Козьмодемьянск</span>
</li>
<li class="flex items-start">
<div class="w-6 h-6 flex items-center justify-center mr-3 mt-1">
<i class="ri-phone-line"></i>
</div>
<span>+7 (902) 326-02-42</span>
</li>
<li class="flex items-start">
<div class="w-6 h-6 flex items-center justify-center mr-3 mt-1">
<i class="ri-mail-line"></i>
</div>
<span>Koll09-12@yandex.ru</span>
</li>
</ul>
</div>
<div>
<h3 class="text-xl font-bold mb-4">Время работы</h3>
<ul class="space-y-2">
<li class="flex justify-between">
<span>Понедельник - Четверг:</span>
<span>10:00 - 22:00</span>
</li>
<li class="flex justify-between">
<span>Пятница - Суббота:</span>
<span>10:00 - 23:00</span>
</li>
<li class="flex justify-between">
<span>Воскресенье:</span>
<span>11:00 - 22:00</span>
</li>
</ul>
</div>
<div>
<h3 class="text-xl font-bold mb-4">Мы в соцсетях</h3>
<div class="flex space-x-4">
<a href="#" class="social-link w-10 h-10 flex items-center justify-center bg-gray-800 rounded-full hover:bg-primary transition-colors" data-social="instagram">
<i class="ri-instagram-line"></i>
</a>
<a href="#" class="social-link w-10 h-10 flex items-center justify-center bg-gray-800 rounded-full hover:bg-primary transition-colors" data-social="facebook">
<i class="ri-facebook-fill"></i>
</a>
<a href="#" class="social-link w-10 h-10 flex items-center justify-center bg-gray-800 rounded-full hover:bg-primary transition-colors" data-social="vk">
<i class="ri-vk-fill"></i>
</a>
<a href="#" class="social-link w-10 h-10 flex items-center justify-center bg-gray-800 rounded-full hover:bg-primary transition-colors" data-social="telegram">
<i class="ri-telegram-fill"></i>
</a>
</div>
</div>
<div>
<h3 class="text-xl font-bold mb-4">Подписка на новости</h3>
<p class="text-gray-400 mb-4">Подпишитесь на нашу рассылку, чтобы получать новости о скидках и акциях</p>
<form class="flex flex-col space-y-3" id="newsletter-form" method="POST">
<input type="hidden" name="action" value="subscribe">
<input type="email" name="email" placeholder="Ваш email" class="px-4 py-2 rounded border-none focus:outline-none focus:ring-2 focus:ring-primary text-gray-800" id="newsletter-email" required>
<label class="custom-checkbox text-gray-400 text-sm">
<input type="checkbox" id="privacy-checkbox" required>
<span class="checkmark"></span>
Я согласен с политикой конфиденциальности
</label>
<button type="submit" class="bg-primary text-white px-4 py-2 !rounded-button font-medium hover:bg-red-600 transition-colors whitespace-nowrap">Подписаться</button>
</form>
</div>
</div>
<div class="pt-6 border-t border-gray-800 text-center text-gray-400 text-sm">
<p>© 2025 ОРАН. Все права защищены. <a href="#" class="privacy-link text-gray-400 hover:text-primary">Политика конфиденциальности</a></p>
</div>
</div>
</footer>

<!-- Cart Modal -->
<div id="cart-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
<div class="bg-white rounded-lg w-full max-w-md mx-4 overflow-hidden">
<div class="p-4 bg-primary text-white flex justify-between items-center">
<h3 class="text-xl font-bold">Корзина</h3>
<button id="close-cart" class="text-white hover:text-gray-200">
<div class="w-8 h-8 flex items-center justify-center">
<i class="ri-close-line ri-lg"></i>
</div>
</button>
</div>
<div class="p-4 max-h-[60vh] overflow-y-auto" id="cart-items-container">
<div id="cart-items" class="space-y-4">
<div id="empty-cart-message" class="text-center text-gray-500 py-8">
<div class="w-16 h-16 mx-auto mb-4 flex items-center justify-center text-gray-400">
<i class="ri-shopping-cart-line ri-3x"></i>
</div>
<p>Ваша корзина пуста</p>
</div>
</div>
</div>

<!-- Поля для данных клиента -->
<div class="p-4 border-t" id="checkout-fields">
    <div class="mb-3">
        <input type="text" id="customer-name" placeholder="Ваше имя *" class="w-full px-4 py-2 rounded border focus:outline-none focus:ring-2 focus:ring-primary" required>
    </div>
    <div class="mb-3">
        <input type="tel" id="customer-phone" placeholder="Телефон *" class="w-full px-4 py-2 rounded border focus:outline-none focus:ring-2 focus:ring-primary" required>
    </div>
    <div class="mb-3">
        <input type="email" id="customer-email" placeholder="Email" class="w-full px-4 py-2 rounded border focus:outline-none focus:ring-2 focus:ring-primary">
    </div>
    <div class="mb-3">
        <textarea id="delivery-address" placeholder="Адрес доставки *" class="w-full px-4 py-2 rounded border focus:outline-none focus:ring-2 focus:ring-primary" rows="2" required></textarea>
    </div>
    <div class="mb-3">
        <textarea id="order-comment" placeholder="Комментарий к заказу" class="w-full px-4 py-2 rounded border focus:outline-none focus:ring-2 focus:ring-primary" rows="2"></textarea>
    </div>
</div>

<div class="p-4 border-t">
<div class="flex justify-between font-bold text-lg mb-4">
<span>Итого:</span>
<span id="cart-total">0 ₽</span>
</div>
<button class="w-full bg-primary text-white py-3 !rounded-button font-medium hover:bg-red-600 transition-colors whitespace-nowrap" id="checkout-btn">Оформить заказ</button>
</div>
</div>
</div>

<!-- Modal истории заказов -->
<div id="history-modal" class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden">
    <div class="bg-white rounded-lg order-history-modal mx-4 overflow-hidden">
        <div class="p-4 bg-primary text-white flex justify-between items-center sticky top-0">
            <h3 class="text-xl font-bold">История заказов</h3>
            <button id="close-history" class="text-white hover:text-gray-200">
                <div class="w-8 h-8 flex items-center justify-center">
                    <i class="ri-close-line ri-lg"></i>
                </div>
            </button>
        </div>
        <div class="p-4" id="orders-history-container">
            <div id="empty-orders" class="text-center text-gray-500 py-8">
                <i class="ri-shopping-bag-line ri-3x mb-2"></i>
                <p>У вас пока нет заказов</p>
            </div>
            <div id="orders-list" class="space-y-3"></div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ========== СОСТОЯНИЕ ==========
    let cart = [];
    
    // ========== DOM ЭЛЕМЕНТЫ ==========
    const cartIcon = document.getElementById('cart-icon');
    const cartModal = document.getElementById('cart-modal');
    const closeCart = document.getElementById('close-cart');
    const cartItemsContainer = document.getElementById('cart-items');
    const emptyCartMessage = document.getElementById('empty-cart-message');
    const cartTotal = document.getElementById('cart-total');
    const cartCounter = document.getElementById('cart-counter');
    const checkoutBtn = document.getElementById('checkout-btn');
    const orderButton = document.getElementById('order-button');
    const viewMenuBtn = document.getElementById('view-menu-btn');
    const menuTitle = document.getElementById('menu-title');
    const resetFilterBtn = document.getElementById('reset-filter-btn');
    
    // Элементы для истории
    const historyBtn = document.getElementById('history-btn');
    const historyModal = document.getElementById('history-modal');
    const closeHistory = document.getElementById('close-history');
    const ordersList = document.getElementById('orders-list');
    const emptyOrders = document.getElementById('empty-orders');
    
    // ========== ФУНКЦИИ УВЕДОМЛЕНИЙ ==========
    function showNotification(message, type = 'success') {
        const notification = document.createElement('div');
        notification.className = `fixed bottom-4 right-4 ${type === 'success' ? 'bg-green-500' : 'bg-red-500'} text-white px-4 py-2 rounded shadow-lg z-50 flex items-center notification`;
        notification.innerHTML = `
            <i class="${type === 'success' ? 'ri-checkbox-circle-line' : 'ri-error-warning-line'} mr-2"></i>
            ${message}
        `;
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 2000);
    }
    
    // ========== ФУНКЦИИ КОРЗИНЫ ==========
    function updateCartDisplay() {
        cartItemsContainer.innerHTML = '';
        
        let total = 0;
        let totalItems = 0;
        
        if (cart.length === 0) {
            cartItemsContainer.appendChild(emptyCartMessage.cloneNode(true));
        } else {
            emptyCartMessage.classList.add('hidden');
            
            cart.forEach((item, index) => {
                total += item.price * item.quantity;
                totalItems += item.quantity;
                
                const itemElement = document.createElement('div');
                itemElement.className = 'flex items-center justify-between bg-gray-50 p-3 rounded';
                itemElement.innerHTML = `
                    <div class="flex-1">
                        <h4 class="font-medium">${item.name}</h4>
                        <div class="flex items-center mt-1">
                            <button class="decrease-quantity w-8 h-8 flex items-center justify-center bg-gray-200 rounded-l hover:bg-gray-300 transition-colors" data-index="${index}">
                                <i class="ri-subtract-line"></i>
                            </button>
                            <span class="w-12 text-center bg-white py-1 border-y border-gray-200">${item.quantity}</span>
                            <button class="increase-quantity w-8 h-8 flex items-center justify-center bg-gray-200 rounded-r hover:bg-gray-300 transition-colors" data-index="${index}">
                                <i class="ri-add-line"></i>
                            </button>
                        </div>
                    </div>
                    <div class="text-right ml-4">
                        <div class="font-medium">${item.price * item.quantity} ₽</div>
                        <button class="remove-item text-red-500 hover:text-red-700 text-sm mt-1 flex items-center" data-index="${index}">
                            <i class="ri-delete-bin-line mr-1"></i>Удалить
                        </button>
                    </div>
                `;
                
                cartItemsContainer.appendChild(itemElement);
                
                const decreaseBtn = itemElement.querySelector('.decrease-quantity');
                const increaseBtn = itemElement.querySelector('.increase-quantity');
                const removeBtn = itemElement.querySelector('.remove-item');
                
                decreaseBtn.addEventListener('click', () => {
                    if (cart[index].quantity > 1) {
                        cart[index].quantity--;
                        updateCartDisplay();
                        showNotification(`Количество "${item.name}" уменьшено`);
                    }
                });
                
                increaseBtn.addEventListener('click', () => {
                    cart[index].quantity++;
                    updateCartDisplay();
                    showNotification(`Количество "${item.name}" увеличено`);
                });
                
                removeBtn.addEventListener('click', () => {
                    showDeleteConfirmation(item, index);
                });
            });
        }
        
        cartTotal.textContent = `${total} ₽`;
        cartCounter.textContent = totalItems;
    }
    
    function showDeleteConfirmation(item, index) {
        const confirmModal = document.createElement('div');
        confirmModal.className = 'fixed inset-0 bg-black bg-opacity-50 z-[60] flex items-center justify-center';
        confirmModal.innerHTML = `
            <div class="bg-white rounded-lg w-full max-w-sm mx-4 overflow-hidden">
                <div class="p-6">
                    <h3 class="text-xl font-bold mb-4">Подтверждение удаления</h3>
                    <p class="text-gray-600 mb-6">Вы уверены, что хотите удалить "${item.name}" из корзины?</p>
                    <div class="flex justify-end space-x-3">
                        <button class="px-4 py-2 bg-gray-200 text-gray-700 !rounded-button hover:bg-gray-300 transition-colors whitespace-nowrap" id="cancel-delete">Отмена</button>
                        <button class="px-4 py-2 bg-red-500 text-white !rounded-button hover:bg-red-600 transition-colors whitespace-nowrap" id="confirm-delete">Удалить</button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(confirmModal);
        
        document.getElementById('cancel-delete').addEventListener('click', () => {
            confirmModal.remove();
        });
        
        document.getElementById('confirm-delete').addEventListener('click', () => {
            cart.splice(index, 1);
            updateCartDisplay();
            showNotification(`"${item.name}" удален из корзины`);
            confirmModal.remove();
        });
        
        confirmModal.addEventListener('click', (e) => {
            if (e.target === confirmModal) {
                confirmModal.remove();
            }
        });
    }
    
    function addToCart(name, price) {
        const existingItem = cart.find(item => item.name === name);
        
        if (existingItem) {
            existingItem.quantity++;
            showNotification(`Количество "${name}" увеличено`);
        } else {
            cart.push({
                name: name,
                price: price,
                quantity: 1
            });
            showNotification(`"${name}" добавлен в корзину`);
        }
        
        updateCartDisplay();
    }
    
    // ========== ФИЛЬТРАЦИЯ КАТЕГОРИЙ ==========
    window.filterByCategory = function(category) {
        const products = document.querySelectorAll('.menu-item-card');
        let visibleCount = 0;
        
        products.forEach(product => {
            if (product.dataset.category === category) {
                product.classList.remove('hidden');
                visibleCount++;
            } else {
                product.classList.add('hidden');
            }
        });
        
        const categoryNames = {
            'роллы': 'Роллы',
            'суши': 'Суши',
            'сеты': 'Сеты',
            'напитки': 'Напитки'
        };
        
        menuTitle.textContent = categoryNames[category] || category;
        resetFilterBtn.classList.remove('hidden');
        
        setTimeout(() => {
            document.getElementById('products-grid').scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }, 100);
        
        showNotification(`Показаны товары категории: ${categoryNames[category]} (${visibleCount} шт.)`, 'success');
    };
    
    function resetFilter() {
        const products = document.querySelectorAll('.menu-item-card');
        
        products.forEach(product => {
            if (product.dataset.category === 'роллы') {
                product.classList.remove('hidden');
            } else {
                product.classList.add('hidden');
            }
        });
        
        menuTitle.textContent = 'Популярные роллы';
        resetFilterBtn.classList.add('hidden');
        
        showNotification('Показаны популярные роллы', 'success');
    }
    
    // ========== ЗАГРУЗКА ИСТОРИИ ЗАКАЗОВ ==========
    async function loadOrderHistory() {
        try {
            const response = await fetch('?action=get_orders');
            const data = await response.json();
            
            if (data.success && data.orders.length > 0) {
                ordersList.innerHTML = '';
                emptyOrders.classList.add('hidden');
                
                data.orders.forEach(order => {
                    const date = new Date(order.order_date).toLocaleString('ru-RU', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    
                    const itemsList = order.items.map(item => 
                        `${item.product_name} x${item.quantity}`
                    ).join(', ');
                    
                    const orderElement = document.createElement('div');
                    orderElement.className = 'order-item-history bg-gray-50 p-4 rounded';
                    orderElement.innerHTML = `
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <span class="font-bold text-primary">${order.order_number}</span>
                                <span class="text-sm text-gray-500 ml-2">${date}</span>
                            </div>
                            <span class="font-bold">${order.total_amount} ₽</span>
                        </div>
                        <div class="text-sm text-gray-600 mb-2">
                            <div>${order.customer_name} | ${order.customer_phone}</div>
                            <div class="truncate">${order.delivery_address}</div>
                            <div class="text-xs text-gray-500 mt-1">${itemsList}</div>
                        </div>
                    `;
                    
                    ordersList.appendChild(orderElement);
                });
            } else {
                emptyOrders.classList.remove('hidden');
                ordersList.innerHTML = '';
            }
        } catch (error) {
            console.error('Ошибка загрузки истории:', error);
        }
    }
    
    // ========== ОБРАБОТЧИКИ СОБЫТИЙ ==========
    
    // Корзина
    cartIcon.addEventListener('click', function() {
        cartModal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    });
    
    closeCart.addEventListener('click', function() {
        cartModal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    });
    
    cartModal.addEventListener('click', function(e) {
        if (e.target === cartModal) {
            cartModal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
    });
    
    // Кнопки "В корзину"
    document.querySelectorAll('.add-to-cart-btn').forEach(button => {
        button.addEventListener('click', function(e) {
            e.stopPropagation();
            const card = this.closest('.menu-item-card');
            const name = card.querySelector('h3').textContent;
            const priceText = card.querySelector('.price').textContent;
            const price = parseInt(priceText);
            
            addToCart(name, price);
        });
    });
    
    // Кнопка "Заказать"
    orderButton.addEventListener('click', function() {
        if (cart.length > 0) {
            cartModal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        } else {
            showNotification('Корзина пуста. Добавьте товары', 'error');
        }
    });
    
    // Кнопка "Посмотреть меню"
    viewMenuBtn.addEventListener('click', function() {
        document.getElementById('menu').scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    });
    
    // Кнопка оформления заказа
    checkoutBtn.addEventListener('click', async function() {
        if (cart.length === 0) {
            showNotification('Корзина пуста', 'error');
            return;
        }
        
        const name = document.getElementById('customer-name')?.value;
        const phone = document.getElementById('customer-phone')?.value;
        const address = document.getElementById('delivery-address')?.value;
        
        if (!name || !phone || !address) {
            showNotification('Заполните имя, телефон и адрес доставки', 'error');
            return;
        }
        
        const orderData = {
            customer_name: name,
            customer_phone: phone,
            customer_email: document.getElementById('customer-email')?.value || '',
            delivery_address: address,
            comment: document.getElementById('order-comment')?.value || '',
            total_amount: cart.reduce((sum, item) => sum + (item.price * item.quantity), 0),
            items: cart.map(item => ({
                name: item.name,
                price: item.price,
                quantity: item.quantity
            }))
        };
        
        // Отправляем заказ
        const formData = new FormData();
        formData.append('action', 'order');
        formData.append('order_data', JSON.stringify(orderData));
        
        try {
            const response = await fetch('', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                showNotification(`Заказ ${result.order_number} оформлен!`, 'success');
                cart = [];
                updateCartDisplay();
                cartModal.classList.add('hidden');
                document.body.style.overflow = 'auto';
                
                // Очищаем поля
                document.getElementById('customer-name').value = '';
                document.getElementById('customer-phone').value = '';
                document.getElementById('customer-email').value = '';
                document.getElementById('delivery-address').value = '';
                document.getElementById('order-comment').value = '';
                
                // Обновляем историю
                loadOrderHistory();
            } else {
                showNotification(result.message || 'Ошибка оформления заказа', 'error');
            }
        } catch (error) {
            showNotification('Ошибка соединения', 'error');
        }
    });
    
    // Кнопка сброса фильтра
    resetFilterBtn.addEventListener('click', resetFilter);
    
    // История заказов
    historyBtn.addEventListener('click', function() {
        loadOrderHistory();
        historyModal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    });
    
    closeHistory.addEventListener('click', function() {
        historyModal.classList.add('hidden');
        document.body.style.overflow = 'auto';
    });
    
    historyModal.addEventListener('click', function(e) {
        if (e.target === historyModal) {
            historyModal.classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
    });
    
    // Плавный скролл для навигации
    document.querySelectorAll('nav a').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href');
            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                window.scrollTo({
                    top: targetElement.offsetTop - 80,
                    behavior: 'smooth'
                });
            }
        });
    });
    
    // Форма подписки
    const newsletterForm = document.getElementById('newsletter-form');
    if (newsletterForm) {
        newsletterForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification(result.message, 'success');
                    this.reset();
                } else {
                    showNotification(result.message, 'error');
                }
            } catch (error) {
                showNotification('Ошибка соединения', 'error');
            }
        });
    }
    
    // Социальные ссылки
    document.querySelectorAll('.social-link').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const social = this.dataset.social;
            const socialNames = {
                'instagram': 'Instagram',
                'facebook': 'Facebook',
                'vk': 'VK',
                'telegram': 'Telegram'
            };
            showNotification(`Переход в ${socialNames[social]} (демо-режим)`, 'success');
        });
    });
    
    // Политика конфиденциальности
    const privacyLink = document.querySelector('.privacy-link');
    if (privacyLink) {
        privacyLink.addEventListener('click', function(e) {
            e.preventDefault();
            showNotification('Политика конфиденциальности (демо-режим)', 'success');
        });
    }
    
    // Инициализация
    updateCartDisplay();
});
</script>
</body>
</html>
