<?php
function loadLanguage(): array {
    $locale = $_SESSION['locale'] ?? ($_COOKIE['locale'] ?? 'en');
    $allowed = ['en', 'fr', 'uk'];
    if (!in_array($locale, $allowed)) $locale = 'en';
    $_SESSION['locale'] = $locale;
    $file = __DIR__ . '/../lang/' . $locale . '.php';
    return file_exists($file) ? require $file : require __DIR__ . '/../lang/en.php';
}
$lang = loadLanguage();
function t(string $key): string {
    global $lang;
    return $lang[$key] ?? $key;
}
?>
