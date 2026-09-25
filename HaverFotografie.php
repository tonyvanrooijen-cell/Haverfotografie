<?php
// HaverFotografie.php
// Verbind met de database CustomerDB en toon standaard de titels uit tabel HaverFotografie.

session_start();

// Gebruik expliciet de externe database-instellingen om lokale Docker-overrides te negeren.
$host = '128.140.127.8';
$port = '3308';
$db   = 'CustomerDB';
$user = 'dad_admin';
$pass = 'x51`!fAa3DSs?B%YXv><rg{;22Q8';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: ($_SERVER['PHP_SELF'] ?? '');
$requestPath = str_replace('\\', '/', $requestPath);
$adminPathPattern = '#/admin(?:/index\.php)?/?$#';
$isAdminPath = (defined('HAVER_FORCE_ADMIN') && HAVER_FORCE_ADMIN) || preg_match($adminPathPattern, $requestPath) === 1;
$publicPath = $isAdminPath ? preg_replace($adminPathPattern, '', $requestPath) : $requestPath;
$publicPath = $publicPath === '' ? '/' : $publicPath;
$publicPath = $publicPath !== '/' ? rtrim($publicPath, '/') : $publicPath;
$adminPath = ($publicPath === '/' ? '' : $publicPath) . '/admin/';
$selfPath = $isAdminPath ? $adminPath : $publicPath;
$assetBasePath = $publicPath === '/' ? '' : $publicPath;
$logoPath = ($assetBasePath !== '' ? rtrim($assetBasePath, '/') : '') . '/logo.png';
$faviconPath = ($assetBasePath !== '' ? rtrim($assetBasePath, '/') : '') . '/favicon.svg';
$adminDashboardUrl = $adminPath;
$adminConfigUrl = $adminDashboardUrl . '?config=1';
$adminNewUrl = $adminDashboardUrl . '?new=1';
$adminVisitsUrl = $adminDashboardUrl . '?visits=1';
$queryString = $_SERVER['QUERY_STRING'] ?? '';
$selfUrl = $selfPath . ($queryString !== '' ? '?' . $queryString : '');
$adminPassword = 'geheim';
$isAdminLoggedIn = (bool) ($_SESSION['haverfotografie_admin'] ?? false);
$photoFieldCount = 9;

$message = '';
$errorMessage = '';
$edit = false;
$admin = $isAdminPath || isset($_GET['admin']) || isset($_GET['new']) || isset($_GET['edit']) || isset($_GET['photos']) || isset($_GET['config']);
$create = isset($_GET['new']);
$photos = false;
$display = !$admin || isset($_GET['view']);
$configScreen = isset($_GET['config']);
$visitsScreen = isset($_GET['visits']);
$portfolioView = isset($_GET['portfolio']);
$welcomeView = isset($_GET['welcome']);
$categoriesView = isset($_GET['categories']);
$collageView = isset($_GET['collage']);
$shootsView = isset($_GET['shoots']);
$contactView = isset($_GET['contact']);
$portfolioCategoryFilter = trim((string) ($_GET['categorie'] ?? ''));
$welcomeView = $welcomeView || (!$admin && !isset($_GET['shoot']) && !$portfolioView && !$categoriesView && !$collageView && !$shootsView && !$contactView && !isset($_GET['arthur']) && $portfolioCategoryFilter === '');
$aboutView = isset($_GET['arthur']);
$editId = 0;
$photoId = 0;
$photoData = [];
$selectedShoot = null;
$selectedShootPhotos = [];
$heroPhoto = '';
$shootGroups = [];
$hiddenShoot = null;
$hiddenShootDetails = null;
$hiddenLogoSrc = '';
$contactPhoto = '';
$portfolioCategoryTiles = [];
$shootOverviewTiles = [];
$authError = '';
$visitSummaryRows = [];
$recentVisits = [];
$todayVisitCount = 0;
$homepageCollageTiles = [];
$isHomepageCollage = false;
$welcomeShoot = null;
$welcomePhoto = '';

if (!$isAdminPath && (isset($_GET['admin']) || isset($_GET['new']) || isset($_GET['edit']) || isset($_GET['photos']) || isset($_GET['config']) || isset($_GET['visits'])) && !isset($_GET['view'])) {
    $redirectParams = $_GET;
    unset($redirectParams['admin']);
    $redirectQuery = http_build_query($redirectParams);

    header('Location: ' . $adminPath . ($redirectQuery !== '' ? '?' . $redirectQuery : ''));
    exit;
}

$configValues = [
    'font_family' => 'Arial, sans-serif',
    'background_color' => '#ffffff',
    'header_color' => '#111111',
    'text_color' => '#111111',
    'link_color' => '#1f5fbf',
    'overlay_text_color' => '#ffffff',
    'overlay_background' => 'rgba(0, 0, 0, 0.45)',
];
$formData = [
    'titel' => '',
    'categorie' => '',
    'lokatie' => '',
    'foto1' => '',
    'oms1' => '',
];

for ($i = 2; $i <= $photoFieldCount; $i++) {
    $formData['foto' . $i] = '';
}

for ($i = 1; $i <= 5; $i++) {
    $formData['object' . $i] = '';
    $formData['naam' . $i] = '';
    $formData['url' . $i] = '';
}

$detailFields = ['titel', 'categorie', 'lokatie', 'oms1'];
for ($i = 1; $i <= $photoFieldCount; $i++) {
    $detailFields[] = 'foto' . $i;
}
for ($i = 1; $i <= 5; $i++) {
    $detailFields[] = 'object' . $i;
    $detailFields[] = 'naam' . $i;
    $detailFields[] = 'url' . $i;
}

$detailSelectColumns = 'id, titel, categorie, lokatie';
for ($i = 1; $i <= $photoFieldCount; $i++) {
    $detailSelectColumns .= ', foto' . $i;
}
$detailSelectColumns .= ', oms1';
for ($i = 1; $i <= 5; $i++) {
    $detailSelectColumns .= ', object' . $i . ', naam' . $i . ', url' . $i;
}

$requiresAuth = $admin || $_SERVER['REQUEST_METHOD'] === 'POST';

if ($requiresAuth && !($_SESSION['haverfotografie_admin'] ?? false)) {
    if (($_POST['action'] ?? '') === 'admin-login') {
        $enteredPassword = (string) ($_POST['admin_password'] ?? '');
        $redirectTarget = (string) ($_POST['redirect_to'] ?? $selfUrl);

        if (hash_equals($adminPassword, $enteredPassword)) {
            $_SESSION['haverfotografie_admin'] = true;
            header('Location: ' . $redirectTarget);
            exit;
        }

        $authError = 'Onjuist wachtwoord.';
    }

    echo '<!DOCTYPE html><html lang="nl"><head><meta charset="UTF-8"><title>HaverFotografie</title><link rel="icon" type="image/svg+xml" href="' . h($faviconPath) . '"><style>';
    echo 'body{font-family:Arial,sans-serif;margin:2rem;}form{max-width:26rem;}label{display:block;font-weight:700;margin-bottom:0.5rem;}input{box-sizing:border-box;width:100%;padding:0.6rem;margin-bottom:1rem;}button{background:#1f5fbf;border:0;color:#fff;padding:0.65rem 1rem;cursor:pointer;}';
    echo '</style></head><body>';
    echo '<h1>Wachtwoord vereist</h1>';
    echo '<p>Voer het wachtwoord in om de beheerpagina te openen.</p>';
    if ($authError !== '') {
        echo '<p style="color:#b00020;"><strong>' . h($authError) . '</strong></p>';
    }
    echo '<form method="post" action="' . h($selfUrl) . '">';
    echo '<input type="hidden" name="action" value="admin-login">';
    echo '<input type="hidden" name="redirect_to" value="' . h($selfUrl) . '">';
    echo '<label for="admin_password">Wachtwoord</label>';
    echo '<input type="password" id="admin_password" name="admin_password" required autofocus>';
    echo '<button type="submit">Inloggen</button>';
    echo '</form></body></html>';
    exit;
}

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function colorWithOpacity(string $color, float $opacity, string $fallback = 'rgba(10, 10, 10, 0.3)'): string
{
    $color = trim($color);
    $opacity = max(0, min(1, $opacity));

    if (preg_match('/^#([0-9a-f]{6})$/i', $color, $matches) === 1) {
        $hex = $matches[1];
        $red = hexdec(substr($hex, 0, 2));
        $green = hexdec(substr($hex, 2, 2));
        $blue = hexdec(substr($hex, 4, 2));

        return sprintf('rgba(%d, %d, %d, %.3F)', $red, $green, $blue, $opacity);
    }

    if (preg_match('/^#([0-9a-f]{3})$/i', $color, $matches) === 1) {
        $hex = $matches[1];
        $red = hexdec(str_repeat($hex[0], 2));
        $green = hexdec(str_repeat($hex[1], 2));
        $blue = hexdec(str_repeat($hex[2], 2));

        return sprintf('rgba(%d, %d, %d, %.3F)', $red, $green, $blue, $opacity);
    }

    if (preg_match('/^rgb\s*\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*\)$/i', $color, $matches) === 1) {
        $red = min(255, (int) $matches[1]);
        $green = min(255, (int) $matches[2]);
        $blue = min(255, (int) $matches[3]);

        return sprintf('rgba(%d, %d, %d, %.3F)', $red, $green, $blue, $opacity);
    }

    return $fallback;
}

function buildDisplayUrl(string $publicPath, ?int $shootId = null, bool $portfolio = false): string
{
    $params = [];

    if ($shootId !== null) {
        $params['shoot'] = $shootId;
    }

    if ($portfolio) {
        $params['portfolio'] = '1';
    }

    $query = http_build_query($params);

    return $publicPath . ($query !== '' ? '?' . $query : '');
}

function buildCollageUrl(string $publicPath): string
{
    return $publicPath . '?collage=1';
}

function buildSectionUrl(string $publicPath, string $section): string
{
    return match ($section) {
        'portfolio' => $publicPath . '?portfolio=1',
        'welcome' => $publicPath . '?welcome=1',
        'categories' => $publicPath . '?categories=1',
        'shoots' => $publicPath . '?shoots=1',
        'arthur' => $publicPath . '?arthur=1',
        'contact' => $publicPath . '?contact=1',
        default => $publicPath,
    };
}

function buildCategoryUrl(string $publicPath, string $category): string
{
    return $publicPath . '?' . http_build_query([
        'shoots' => '1',
        'categorie' => $category,
    ]);
}

function buildPhotoUrl(string $publicPath, int $shootId, int $photoNumber = 0): string
{
    return $publicPath . '?' . http_build_query([
        'image' => '1',
        'shoot' => $shootId,
        'photo' => $photoNumber,
    ]);
}

function renderConfigSelect(string $name, string $currentValue, array $options, bool $previewFont = false): void
{
    echo '<select id="' . h($name) . '" name="' . h($name) . '" class="field">';

    if ($currentValue !== '' && !array_key_exists($currentValue, $options)) {
        $style = $previewFont ? ' style="font-family:' . h($currentValue) . '"' : '';
        echo '<option value="' . h($currentValue) . '"' . $style . ' selected>' . h($currentValue) . '</option>';
    }

    foreach ($options as $value => $label) {
        $selected = $currentValue === $value ? ' selected' : '';
        $style = $previewFont ? ' style="font-family:' . h($value) . '"' : '';
        echo '<option value="' . h($value) . '"' . $style . $selected . '>' . h($label) . '</option>';
    }

    echo '</select>';
}

function cleanRichText($value): string
{
    $value = (string) $value;
    $value = preg_replace('#<(script|style)[^>]*>.*?</\1>#is', '', $value);

    return strip_tags($value, '<p><div><br><b><strong><i><em><u><ul><ol><li><a>');
}

function firstRichTextParagraph($value): string
{
    $content = cleanRichText($value);

    if (preg_match('#<p\b[^>]*>.*?</p>#is', $content, $match)) {
        return $match[0];
    }

    $paragraphs = preg_split('#(?:<br\s*/?>\s*){2,}|\R\s*\R#i', $content);

    return trim((string) ($paragraphs[0] ?? ''));
}

function socialLinksFromShoot(array $shoot): array
{
    $platformDomains = [
        'instagram' => ['instagram.com', 'instagr.am'],
        'linkedin' => ['linkedin.com'],
        'facebook' => ['facebook.com', 'fb.com'],
    ];
    $socialLinks = [];

    for ($i = 1; $i <= 5; $i++) {
        $url = trim((string) ($shoot['url' . $i] ?? ''));

        if ($url === '') {
            continue;
        }

        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host) ?? '';

        foreach ($platformDomains as $platform => $domains) {
            foreach ($domains as $domain) {
                if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                    $socialLinks[$platform] = $url;
                    break 2;
                }
            }
        }
    }

    return $socialLinks;
}

function isDisplayableImage($value): bool
{
    $value = trim((string) $value);

    if ($value === '') {
        return false;
    }

    if (preg_match('#^data:image/(png|jpe?g|gif|webp);base64,#i', $value)) {
        return true;
    }

    if (preg_match('#^https?://#i', $value) && preg_match('#\.(png|jpe?g|gif|webp)(\?.*)?$#i', $value)) {
        return true;
    }

    return preg_match('#^[A-Za-z0-9/_ .-]+\.(png|jpe?g|gif|webp)$#i', $value) === 1;
}

function collectDisplayablePhotos(array $record, int $photoFieldCount, ?string $skipPhoto = null): array
{
    $photos = [];

    for ($i = 1; $i <= $photoFieldCount; $i++) {
        $photoValue = $record['foto' . $i] ?? '';

        if (!isDisplayableImage($photoValue)) {
            continue;
        }

        if ($skipPhoto !== null && $photoValue === $skipPhoto) {
            continue;
        }

        $photos[] = $photoValue;
    }

    return $photos;
}

function uploadedImageToDataUrl(string $field, ?string &$errorText = null): ?string
{
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
        $errorText = uploadErrorText((int) $_FILES[$field]['error']);
        return null;
    }

    $mime = mime_content_type($_FILES[$field]['tmp_name']);
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

    if (!in_array($mime, $allowedTypes, true)) {
        $errorText = 'bestandstype wordt niet ondersteund';
        return null;
    }

    $imageData = file_get_contents($_FILES[$field]['tmp_name']);

    if ($imageData === false) {
        $errorText = 'bestand kon niet worden gelezen';
        return null;
    }

    $imageSize = @getimagesize($_FILES[$field]['tmp_name']);
    $maxImageDimension = 2560;
    $shouldResize = $imageSize !== false && max((int) $imageSize[0], (int) $imageSize[1]) > $maxImageDimension;
    $shouldCompress = $mime === 'image/jpeg' && strlen($imageData) > 2 * 1024 * 1024;

    if (($shouldResize || $shouldCompress) && function_exists('imagecreatefromstring')) {
        $source = @imagecreatefromstring($imageData);

        if ($source !== false) {
            $sourceWidth = imagesx($source);
            $sourceHeight = imagesy($source);
            $scale = min(1, $maxImageDimension / max($sourceWidth, $sourceHeight));
            $targetWidth = max(1, (int) round($sourceWidth * $scale));
            $targetHeight = max(1, (int) round($sourceHeight * $scale));
            $target = imagecreatetruecolor($targetWidth, $targetHeight);

            if ($mime === 'image/png' || $mime === 'image/webp') {
                imagealphablending($target, false);
                imagesavealpha($target, true);
                imagefill($target, 0, 0, imagecolorallocatealpha($target, 0, 0, 0, 127));
            }

            imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);
            ob_start();
            $encoded = match ($mime) {
                'image/jpeg' => imagejpeg($target, null, 85),
                'image/png' => imagepng($target, null, 6),
                'image/webp' => imagewebp($target, null, 85),
                default => false,
            };
            $resizedImageData = ob_get_clean();
            imagedestroy($target);
            imagedestroy($source);

            if ($encoded && $resizedImageData !== false) {
                $imageData = $resizedImageData;
            }
        }
    }

    return 'data:' . $mime . ';base64,' . base64_encode($imageData);
}

function ensureConfigTable(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS config (
            naam VARCHAR(100) NOT NULL PRIMARY KEY,
            waarde TEXT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

function ensureVisitLogTable(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS HaverFotografieVisits (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            shoot_id INT NOT NULL,
            visited_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            ip_address VARCHAR(45) NOT NULL DEFAULT \'\',
            user_agent VARCHAR(255) NOT NULL DEFAULT \'\',
            referer VARCHAR(255) NOT NULL DEFAULT \'\',
            INDEX idx_shoot_id (shoot_id),
            INDEX idx_visited_at (visited_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );
}

function ensurePhotoColumns(PDO $pdo, int $photoFieldCount): void
{
    $existingColumns = $pdo->query('SHOW COLUMNS FROM HaverFotografie')->fetchAll(PDO::FETCH_COLUMN, 0);

    for ($i = 1; $i <= $photoFieldCount; $i++) {
        $column = 'foto' . $i;

        if (in_array($column, $existingColumns, true)) {
            continue;
        }

        $pdo->exec('ALTER TABLE HaverFotografie ADD COLUMN ' . $column . ' LONGTEXT NULL');
    }
}

function ensureCategoryPhotoColumn(PDO $pdo): void
{
    $existingColumns = $pdo->query('SHOW COLUMNS FROM HaverFotografie')->fetchAll(PDO::FETCH_COLUMN, 0);

    if (!in_array('categorie_foto_nummer', $existingColumns, true)) {
        $pdo->exec('ALTER TABLE HaverFotografie ADD COLUMN categorie_foto_nummer TINYINT UNSIGNED NULL');
    }
}

function logShootVisit(PDO $pdo, int $shootId): void
{
    if ($shootId <= 0) {
        return;
    }

    $ipAddress = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    $userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    $referer = substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 255);

    $stmt = $pdo->prepare(
        'INSERT INTO HaverFotografieVisits (shoot_id, ip_address, user_agent, referer)
         VALUES (:shoot_id, :ip_address, :user_agent, :referer)'
    );
    $stmt->execute([
        'shoot_id' => $shootId,
        'ip_address' => $ipAddress,
        'user_agent' => $userAgent,
        'referer' => $referer,
    ]);
}

function loadConfig(PDO $pdo, array $defaults): array
{
    ensureConfigTable($pdo);

    foreach ($defaults as $name => $value) {
        $stmt = $pdo->prepare('INSERT IGNORE INTO config (naam, waarde) VALUES (:naam, :waarde)');
        $stmt->execute(['naam' => $name, 'waarde' => $value]);
    }

    $rows = $pdo->query('SELECT naam, waarde FROM config')->fetchAll();
    $config = $defaults;

    foreach ($rows as $row) {
        if (array_key_exists($row['naam'], $config)) {
            $config[$row['naam']] = $row['waarde'];
        }
    }

    return $config;
}

function uploadErrorText(int $error): string
{
    return match ($error) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'bestand is te groot',
        UPLOAD_ERR_PARTIAL => 'bestand is maar deels ontvangen',
        UPLOAD_ERR_NO_TMP_DIR => 'tijdelijke uploadmap ontbreekt',
        UPLOAD_ERR_CANT_WRITE => 'bestand kon niet worden geschreven',
        UPLOAD_ERR_EXTENSION => 'upload is door PHP gestopt',
        default => 'onbekende uploadfout',
    };
}

try {
    $pdo = new PDO($dsn, $user, $pass, $options);

    // Serve database-backed Base64 photos separately, so overview pages stay small.
    if (isset($_GET['image']) && ctype_digit((string) ($_GET['shoot'] ?? '')) && ctype_digit((string) ($_GET['photo'] ?? ''))) {
        $imageShootId = (int) $_GET['shoot'];
        $imagePhotoNumber = (int) $_GET['photo'];

        if ($imageShootId <= 0 || $imagePhotoNumber < 0 || $imagePhotoNumber > $photoFieldCount) {
            http_response_code(404);
            exit;
        }

        $imageColumns = [];
        if ($imagePhotoNumber === 0) {
            for ($i = $photoFieldCount; $i >= 1; $i--) {
                $imageColumns[] = 'foto' . $i;
            }
        } else {
            $imageColumns[] = 'foto' . $imagePhotoNumber;
        }

        $imageStmt = $pdo->prepare('SELECT ' . implode(', ', $imageColumns) . ' FROM HaverFotografie WHERE id = :id');
        $imageStmt->execute(['id' => $imageShootId]);
        $imageRecord = $imageStmt->fetch();
        $imageSource = '';

        foreach ($imageColumns as $imageColumn) {
            $candidate = (string) ($imageRecord[$imageColumn] ?? '');
            if (isDisplayableImage($candidate)) {
                $imageSource = $candidate;
                break;
            }
        }

        if ($imageSource === '') {
            http_response_code(404);
            exit;
        }

        if (preg_match('#^data:(image/(?:png|jpe?g|gif|webp));base64,(.*)$#is', $imageSource, $imageMatches)) {
            $imageData = base64_decode($imageMatches[2], true);
            if ($imageData === false) {
                http_response_code(404);
                exit;
            }

            header('Content-Type: ' . $imageMatches[1]);
            header('Content-Length: ' . strlen($imageData));
            header('Cache-Control: public, max-age=604800, immutable');
            echo $imageData;
            exit;
        }

        header('Location: ' . $imageSource, true, 302);
        exit;
    }

    ensurePhotoColumns($pdo, $photoFieldCount);
    ensureCategoryPhotoColumn($pdo);
    $configValues = loadConfig($pdo, $configValues);
    ensureVisitLogTable($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (($_POST['action'] ?? '') === 'logout') {
            unset($_SESSION['haverfotografie_admin']);
            header('Location: ' . $publicPath);
            exit;
        }

        if (($_POST['action'] ?? '') === 'config') {
            foreach ($configValues as $name => $currentValue) {
                $value = trim($_POST[$name] ?? $currentValue);
                $stmt = $pdo->prepare('UPDATE config SET waarde = :waarde WHERE naam = :naam');
                $stmt->execute(['naam' => $name, 'waarde' => $value]);
            }

            header('Location: ' . $adminDashboardUrl);
            exit;
        }

        if (($_POST['action'] ?? '') === 'update-prio') {
            header('Content-Type: application/json; charset=utf-8');

            $prioId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
            $prioValue = filter_var($_POST['prio'] ?? null, FILTER_VALIDATE_INT);

            if ($prioId <= 0 || $prioValue === false) {
                http_response_code(422);
                echo json_encode(['ok' => false, 'message' => 'Ongeldige prioriteit.']);
                exit;
            }

            $prioStmt = $pdo->prepare('UPDATE HaverFotografie SET prio = :prio WHERE id = :id');
            $prioStmt->execute(['prio' => $prioValue, 'id' => $prioId]);

            echo json_encode(['ok' => true]);
            exit;
        }

        if (($_POST['action'] ?? '') === 'delete-shoot') {
            $deleteId = isset($_POST['id']) ? (int) $_POST['id'] : 0;

            if ($deleteId > 0) {
                $deleteVisitsStmt = $pdo->prepare('DELETE FROM HaverFotografieVisits WHERE shoot_id = :id');
                $deleteVisitsStmt->execute(['id' => $deleteId]);
                $deleteStmt = $pdo->prepare('DELETE FROM HaverFotografie WHERE id = :id');
                $deleteStmt->execute(['id' => $deleteId]);
                header('Location: ' . $adminDashboardUrl . '?deleted=1');
                exit;
            }

            $errorMessage = 'Ongeldige shoot.';
        }

        if (($_POST['action'] ?? '') === 'photos') {
            $photoId = isset($_POST['id']) ? (int) $_POST['id'] : 0;

            if ($photoId > 0) {
                $photoColumns = [];
                for ($i = 1; $i <= $photoFieldCount; $i++) {
                    $photoColumns[] = 'foto' . $i;
                }
                $photoStmt = $pdo->prepare('SELECT ' . implode(', ', $photoColumns) . ', categorie_foto_nummer FROM HaverFotografie WHERE id = :id');
                $photoStmt->execute(['id' => $photoId]);
                $existingPhotos = $photoStmt->fetch();

                if ($existingPhotos) {
                    $categoryPhotoNumber = trim((string) ($_POST['categorie_foto_nummer'] ?? ''));
                    $photoParams = [
                        'id' => $photoId,
                        'categorie_foto_nummer' => $categoryPhotoNumber === '' ? 0 : max(0, min($photoFieldCount, (int) $categoryPhotoNumber)),
                    ];
                    for ($i = 1; $i <= $photoFieldCount; $i++) {
                        $fieldName = 'foto' . $i;
                        $uploadError = null;
                        $uploadedPhoto = uploadedImageToDataUrl($fieldName, $uploadError);

                        if ($uploadError !== null) {
                            $errorMessage .= 'Foto ' . $i . ': ' . $uploadError . '. ';
                        }

                        $photoParams['foto' . $i] = $uploadedPhoto ?? $existingPhotos['foto' . $i];
                    }

                    if ($errorMessage === '') {
                        $photoAssignments = [];
                        for ($i = 1; $i <= $photoFieldCount; $i++) {
                            $photoAssignments[] = 'foto' . $i . ' = :foto' . $i;
                        }
                        $updatePhotosStmt = $pdo->prepare(
                            'UPDATE HaverFotografie SET ' . implode(', ', $photoAssignments) . ', categorie_foto_nummer = :categorie_foto_nummer WHERE id = :id'
                        );
                        $updatePhotosStmt->execute($photoParams);
                        header('Location: ' . $publicPath . '?shoot=' . $photoId);
                        exit;
                    }

                    $photos = true;
                    $photoDataStmt = $pdo->prepare('SELECT id, titel, ' . implode(', ', $photoColumns) . ', oms1, categorie_foto_nummer FROM HaverFotografie WHERE id = :id');
                    $photoDataStmt->execute(['id' => $photoId]);
                    $photoData = $photoDataStmt->fetch() ?: [];
                }
            }
        } else {
            $editId = isset($_POST['id']) ? (int) $_POST['id'] : 0;
            $create = $editId === 0;
            foreach ($detailFields as $field) {
                $formData[$field] = $field === 'oms1' ? cleanRichText($_POST[$field] ?? '') : trim($_POST[$field] ?? '');
            }

            if ($formData['titel'] === '') {
                $message = 'Vul een titel in.';
            } else {
                if ($editId > 0) {
                    $updateStmt = $pdo->prepare(
                        'UPDATE HaverFotografie SET titel = :titel, categorie = :categorie, lokatie = :lokatie, oms1 = :oms1, object1 = :object1, naam1 = :naam1, url1 = :url1, object2 = :object2, naam2 = :naam2, url2 = :url2, object3 = :object3, naam3 = :naam3, url3 = :url3, object4 = :object4, naam4 = :naam4, url4 = :url4, object5 = :object5, naam5 = :naam5, url5 = :url5 WHERE id = :id'
                    );
                    $editableFields = array_filter($detailFields, static fn (string $field): bool => !str_starts_with($field, 'foto'));
                    $params = [];
                    foreach ($editableFields as $field) {
                        $params[$field] = $formData[$field];
                    }
                    $params['id'] = $editId;
                    $updateStmt->execute($params);
                    header('Location: ' . $publicPath . '?shoot=' . $editId);
                    exit;
                } else {
                    $insertStmt = $pdo->prepare(
                        'INSERT INTO HaverFotografie (titel, categorie, lokatie, foto1, foto2, foto3, foto4, foto5, foto6, foto7, foto8, foto9, oms1, object1, naam1, url1, object2, naam2, url2, object3, naam3, url3, object4, naam4, url4, object5, naam5, url5) VALUES (:titel, :categorie, :lokatie, :foto1, :foto2, :foto3, :foto4, :foto5, :foto6, :foto7, :foto8, :foto9, :oms1, :object1, :naam1, :url1, :object2, :naam2, :url2, :object3, :naam3, :url3, :object4, :naam4, :url4, :object5, :naam5, :url5)'
                    );
                    $params = [];
                    foreach ($detailFields as $field) {
                        $params[$field] = $formData[$field];
                    }
                    $insertStmt->execute($params);
                    $message = 'Shoot succesvol toegevoegd.';
                    foreach ($detailFields as $field) {
                        $formData[$field] = '';
                    }
                    $create = false;
                }
            }
        }
    }

    if (isset($_GET['edit']) && ctype_digit($_GET['edit'])) {
        $editId = (int) $_GET['edit'];
        $create = false;
        $editStmt = $pdo->prepare("SELECT $detailSelectColumns FROM HaverFotografie WHERE id = :id");
        $editStmt->execute(['id' => $editId]);
        $record = $editStmt->fetch();

        if ($record) {
            $edit = true;
            foreach ($detailFields as $field) {
                $formData[$field] = $record[$field];
            }
        }
    }

    if (isset($_GET['photos']) && ctype_digit($_GET['photos'])) {
        $photoId = (int) $_GET['photos'];
        $edit = false;
        $create = false;
        $display = false;
        $configScreen = false;
        $visitsScreen = false;
        $photoColumns = [];
        for ($i = 1; $i <= $photoFieldCount; $i++) {
            $photoColumns[] = 'foto' . $i;
        }
        $photoStmt = $pdo->prepare('SELECT id, titel, ' . implode(', ', $photoColumns) . ', oms1, categorie_foto_nummer FROM HaverFotografie WHERE id = :id');
        $photoStmt->execute(['id' => $photoId]);
        $photoData = $photoStmt->fetch();
        $photos = (bool) $photoData;
    }

    if (isset($_GET['deleted'])) {
        $message = 'Shoot verwijderd.';
    }

    if ($display) {
        $displayDetailStmt = $pdo->prepare("SELECT $detailSelectColumns, categorie_foto_nummer FROM HaverFotografie WHERE id = :id");
        $displayShootDetailsById = [];
        $loadShootDetails = static function (int $shootId) use (&$displayShootDetailsById, $displayDetailStmt): ?array {
            if ($shootId <= 0) {
                return null;
            }

            if (array_key_exists($shootId, $displayShootDetailsById)) {
                return $displayShootDetailsById[$shootId];
            }

            $displayDetailStmt->execute(['id' => $shootId]);
            $displayShootDetailsById[$shootId] = $displayDetailStmt->fetch() ?: null;

            return $displayShootDetailsById[$shootId];
        };

        $hiddenShootStmt = $pdo->query(
            'SELECT id, titel, categorie, lokatie, prio
             FROM HaverFotografie
             WHERE LOWER(TRIM(categorie)) = \'hidden\'
             ORDER BY COALESCE(prio, 2147483647), id ASC
             LIMIT 1'
        );
        $hiddenShoot = $hiddenShootStmt->fetch() ?: null;

        if ($hiddenShoot && ($contactView || $welcomeView)) {
            $hiddenShootDetails = $loadShootDetails((int) $hiddenShoot['id']);
            $hiddenShootPhotos = $hiddenShootDetails ?: [];

            for ($i = $photoFieldCount; $i >= 1; $i--) {
                $candidate = $hiddenShootPhotos['foto' . $i] ?? '';

                if (isDisplayableImage($candidate)) {
                    $hiddenLogoSrc = $candidate;
                    break;
                }
            }

            $hiddenContactPhotos = [];
            for ($i = 1; $i <= min(4, $photoFieldCount); $i++) {
                $candidate = $hiddenShootPhotos['foto' . $i] ?? '';

                if (isDisplayableImage($candidate)) {
                    $hiddenContactPhotos[] = $candidate;
                }
            }

            if ($hiddenContactPhotos) {
                $contactPhoto = $hiddenContactPhotos[random_int(0, count($hiddenContactPhotos) - 1)];
            }
        } elseif ($hiddenShoot) {
            $hiddenLogoSrc = buildPhotoUrl($publicPath, (int) $hiddenShoot['id']);
        }

        if (!$contactView) {
            $displayStmt = $pdo->query(
                'SELECT id, titel, categorie, lokatie, prio, categorie_foto_nummer
                 FROM HaverFotografie
                 ORDER BY COALESCE(prio, 2147483647), categorie, titel'
            );
            $displayShoots = $displayStmt->fetchAll();
            $visibleDisplayShoots = [];

            foreach ($displayShoots as $shoot) {
                if ($hiddenShoot && (int) $shoot['id'] === (int) $hiddenShoot['id']) {
                    continue;
                }

                if (strtolower(trim($shoot['categorie'])) === 'hidden') {
                    continue;
                }

                $category = $shoot['categorie'] !== '' ? $shoot['categorie'] : 'Zonder categorie';
                $shootGroups[$category][] = $shoot;
                $visibleDisplayShoots[] = $shoot;
            }

            if ($welcomeView) {
                $welcomeShoot = $hiddenShootDetails ?: $loadShootDetails(18);

                if ($welcomeShoot) {
                    $welcomePhotos = collectDisplayablePhotos($welcomeShoot, $photoFieldCount);
                    $welcomePhotos = array_values(array_filter(
                        $welcomePhotos,
                        static fn (string $photo): bool => $photo !== $hiddenLogoSrc
                    ));

                    if ($welcomePhotos) {
                        $welcomePhoto = $welcomePhotos[random_int(0, count($welcomePhotos) - 1)];
                    }
                }
            }

            if (!$welcomeView && !$categoriesView && !$shootsView && !$collageView && (!$portfolioView || isset($_GET['shoot']))) {
                if (isset($_GET['shoot']) && ctype_digit($_GET['shoot'])) {
                    $selectedShoot = $loadShootDetails((int) $_GET['shoot']);
                } elseif (count($visibleDisplayShoots) > 0) {
                    $selectedShoot = $loadShootDetails(18);

                    if (!$selectedShoot) {
                        $fallbackShoot = $visibleDisplayShoots[random_int(0, count($visibleDisplayShoots) - 1)];
                        $selectedShoot = $loadShootDetails((int) $fallbackShoot['id']);
                    }
                }
            }

            if ($collageView) {
                $collageCandidates = $visibleDisplayShoots;
                shuffle($collageCandidates);

                foreach ($collageCandidates as $collageCandidate) {
                    if (count($homepageCollageTiles) >= 5) {
                        break;
                    }

                    $collageShoot = $loadShootDetails((int) $collageCandidate['id']);

                    if (!$collageShoot) {
                        continue;
                    }

                    $shootPhotos = collectDisplayablePhotos($collageShoot, $photoFieldCount);

                    if (!$shootPhotos) {
                        continue;
                    }

                    shuffle($shootPhotos);
                    $homepageCollageTiles[] = [
                        'photo' => $shootPhotos[0],
                        'shoot_id' => (int) $collageShoot['id'],
                        'title' => (string) $collageShoot['titel'],
                        'url' => $publicPath . '?shoot=' . (int) $collageShoot['id'],
                    ];
                }

                $isHomepageCollage = count($homepageCollageTiles) === 5;
            }

            if ($categoriesView || $portfolioView) {
                $portfolioCategories = array_keys($shootGroups);
                shuffle($portfolioCategories);

                foreach ($portfolioCategories as $category) {
                    $shoots = $shootGroups[$category];
                    $categoryCandidates = $shoots;
                    shuffle($categoryCandidates);

                    foreach ($categoryCandidates as $categoryShoot) {
                        $categoryPhotoIndex = (int) ($categoryShoot['categorie_foto_nummer'] ?? 0);
                        if ($categoryPhotoIndex <= 0 || $categoryPhotoIndex > $photoFieldCount) {
                            continue;
                        }

                        $portfolioCategoryTiles[] = [
                            'category' => (string) $category,
                            'title' => (string) $category,
                            'photo' => buildPhotoUrl($publicPath, (int) $categoryShoot['id'], $categoryPhotoIndex),
                            'url' => buildCategoryUrl($publicPath, (string) $category),
                        ];
                        break;
                    }
                }
            }

            if ($shootsView) {
                foreach ($visibleDisplayShoots as $shoot) {
                    $shootCategory = (string) ($shoot['categorie'] !== '' ? $shoot['categorie'] : 'Zonder categorie');
                    if ($portfolioCategoryFilter !== '' && strcasecmp($shootCategory, $portfolioCategoryFilter) !== 0) {
                        continue;
                    }

                    if ($portfolioCategoryFilter === '' && count($shootOverviewTiles) >= 9) {
                        break;
                    }

                    $shootOverviewDetails = $loadShootDetails((int) $shoot['id']);

                    if (!$shootOverviewDetails) {
                        continue;
                    }

                    $shootOverviewPhotos = collectDisplayablePhotos($shootOverviewDetails, $photoFieldCount);

                    if (!$shootOverviewPhotos) {
                        continue;
                    }

                    shuffle($shootOverviewPhotos);
                    $shootOverviewTiles[] = [
                        'category' => $shootCategory,
                        'title' => (string) $shoot['titel'],
                        'photo' => $shootOverviewPhotos[0],
                        'url' => $publicPath . '?shoot=' . (int) $shoot['id'],
                    ];
                }
            }

            if ($selectedShoot) {
                $selectedShootPhotos = collectDisplayablePhotos(
                    $selectedShoot,
                    $photoFieldCount,
                    $hiddenShoot && (int) $selectedShoot['id'] === (int) $hiddenShoot['id'] && $hiddenLogoSrc !== '' ? $hiddenLogoSrc : null
                );

                $heroPhoto = $selectedShootPhotos[0] ?? '';

                logShootVisit($pdo, (int) $selectedShoot['id']);
            }
        }
    }

    if ($visitsScreen) {
        $display = false;
        $configScreen = false;
        $photos = false;
        $edit = false;
        $create = false;

        $visitSummaryStmt = $pdo->query(
            'SELECT h.id, h.titel, h.categorie, h.lokatie,
                    COUNT(v.id) AS visit_count,
                    SUM(CASE WHEN DATE(v.visited_at) = CURDATE() THEN 1 ELSE 0 END) AS today_visit_count,
                    MAX(v.visited_at) AS last_visited_at
             FROM HaverFotografie h
             LEFT JOIN HaverFotografieVisits v ON v.shoot_id = h.id
             GROUP BY h.id, h.titel, h.categorie, h.lokatie
             ORDER BY visit_count DESC, h.titel ASC'
        );
        $visitSummaryRows = $visitSummaryStmt->fetchAll();

        $todayVisitCount = (int) $pdo->query(
            'SELECT COUNT(*) FROM HaverFotografieVisits WHERE DATE(visited_at) = CURDATE()'
        )->fetchColumn();

        $recentVisitsStmt = $pdo->query(
            'SELECT v.visited_at, v.ip_address, v.user_agent, v.referer, h.titel
             FROM HaverFotografieVisits v
             INNER JOIN HaverFotografie h ON h.id = v.shoot_id
             ORDER BY v.visited_at DESC, v.id DESC
             LIMIT 100'
        );
        $recentVisits = $recentVisitsStmt->fetchAll();
    }

    $stmt = $pdo->query('SELECT id, titel, categorie, lokatie, prio FROM HaverFotografie');
    $rows = $stmt->fetchAll();
    $needsRichTextEditor = $edit || $create || $photos;
} catch (PDOException $e) {
    echo '<!DOCTYPE html><html lang="nl"><head><meta charset="UTF-8"><title>HaverFotografie</title><link rel="icon" type="image/svg+xml" href="' . h($faviconPath) . '"></head><body>';
    echo '<h1>Databasefout</h1>';
    echo '<p>' . h($e->getMessage()) . '</p>';
    echo '</body></html>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>HaverFotografie</title>
    <link rel="icon" type="image/svg+xml" href="<?php echo h($faviconPath); ?>">
    <?php if ($needsRichTextEditor): ?>
    <script src="https://cdn.jsdelivr.net/npm/tinymce@7/tinymce.min.js" referrerpolicy="origin"></script>
    <?php endif; ?>
    <style>
        body { font-family: Arial, sans-serif; margin: 2rem; }
        body.display-body {
            height: 100vh;
            height: 100dvh;
            margin: 0;
            overflow: hidden;
        }
        body.admin-body {
            height: 100vh;
            height: 100dvh;
            margin: 0;
            overflow: hidden;
            padding: 0;
        }
        .site-footer {
            color: #666;
            margin-top: 2rem;
            padding: 1rem 0;
        }
        .display-body .site-footer {
            background: var(--display-footer-overlay);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            color: var(--display-overlay-text);
            flex-shrink: 0;
            margin-top: 0;
            padding: 1rem 1.5rem max(1rem, env(safe-area-inset-bottom));
        }
        .site-footer a {
            color: inherit;
        }
        table { border-collapse: collapse; width: 100%; table-layout: fixed; }
        table + .admin-actions {
            margin-top: 1rem;
        }
        th, td { border: 1px solid #ccc; font-size: 0.92rem; padding: 0.55rem 0.65rem; text-align: left; }
        th { background: #f4f4f4; }
        .field { box-sizing: border-box; width: 100%; padding: 0.45rem; }
        .photo-paste-field {
            align-items: center;
            border: 2px dashed #9aa7b3;
            display: flex;
            justify-content: center;
            margin: 0.25rem 0 0.75rem 0;
            max-width: 400px;
            min-height: 160px;
            padding: 0.75rem;
            text-align: center;
        }
        .photo-paste-field:focus { border-color: #3366cc; outline: 2px solid #c9d8ff; }
        .photo-paste-field img { max-height: 220px; max-width: 100%; object-fit: contain; }
        .photo-hint { color: #59636e; margin: 0; }
        .photo-preview { max-height: 90px; max-width: 120px; object-fit: contain; }
        .photo-actions { margin-top: -0.25rem; margin-bottom: 0.75rem; }
        .form-panel { max-width: 1520px; }
        .wide-field-row {
            align-items: center;
            display: grid;
            gap: 1.5rem;
            grid-template-columns: 130px minmax(240px, 1fr);
            margin-bottom: 1.55rem;
        }
        .wide-field-row label {
            font-weight: 700;
        }
        .object-grid {
            display: grid;
            gap: 1.55rem 4.5rem;
            grid-template-columns: repeat(3, minmax(340px, 1fr));
            margin-left: 130px;
            max-width: 1580px;
        }
        .photo-upload-grid {
            display: grid;
            gap: 1.25rem;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            max-width: 1520px;
            background: rgba(255, 255, 255, 0.6);
            border: 1px solid #d8dee6;
            border-radius: 1rem;
            padding: 1rem;
        }
        .photo-upload-item {
            background: #fff;
            border: 1px solid #ccc;
            border-radius: 0.85rem;
            padding: 0.75rem;
        }
        .photo-upload-item label {
            display: block;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .photo-upload-item img {
            display: block;
            max-height: 150px;
            max-width: 100%;
            object-fit: contain;
            margin-bottom: 0.75rem;
        }
        .photo-page-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-top: 1.5rem;
        }
        .photo-page-meta {
            display: grid;
            gap: 0.5rem;
            margin-top: 1.25rem;
            max-width: 520px;
        }
        .photo-page-meta label {
            font-weight: 700;
        }
        .button-link,
        .primary-button {
            background: #1f5fbf;
            border: 0;
            color: #fff;
            display: inline-block;
            font: inherit;
            margin-bottom: 1rem;
            padding: 0.6rem 1rem;
            text-decoration: none;
            cursor: pointer;
            white-space: nowrap;
        }
        .admin-shell {
            display: flex;
            flex-direction: column;
            height: 100vh;
            height: 100dvh;
            margin: 0 auto;
            max-width: 1660px;
            overflow: hidden;
            padding: 2rem;
            box-sizing: border-box;
        }
        .admin-header {
            align-items: center;
            background: <?php echo h(colorWithOpacity($configValues['header_color'], 0.34)); ?>;
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border-radius: 1rem;
            display: flex;
            flex-shrink: 0;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding: 1rem 1.5rem;
        }
        .admin-header-copy {
            color: #ffffff;
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
        }
        .admin-header-copy .admin-brand {
            color: #ffffff;
        }
        .admin-header-note {
            color: #ffffff;
            font-size: 2rem;
            font-weight: 700;
            line-height: 1.2;
            margin: 0;
        }
        .admin-content {
            flex: 1;
            overflow-y: auto;
            padding-bottom: 1rem;
        }
        .admin-brand {
            display: inline-flex;
            align-items: center;
            text-decoration: none;
        }
        .admin-brand img {
            display: block;
            height: auto;
            max-height: 52px;
            width: auto;
        }
        .danger-button {
            background: #b00020;
            color: #fff;
        }
        .secondary-button {
            background: #4b5563;
            color: #fff;
        }
        .form-actions { margin-top: 2rem; }
        .rich-editor {
            max-width: 680px;
        }
        .rich-toolbar {
            display: flex;
            gap: 0.35rem;
            margin: 0.5rem 0 0 0;
        }
        .rich-toolbar button {
            background: #f4f4f4;
            border: 1px solid #bbb;
            cursor: pointer;
            font: inherit;
            min-width: 2.25rem;
            padding: 0.35rem 0.55rem;
        }
        .rich-editor-area {
            border: 1px solid #888;
            box-sizing: border-box;
            min-height: 130px;
            padding: 0.65rem;
            width: 100%;
        }
        .rich-editor-area:focus {
            outline: 2px solid #c9d8ff;
        }
        .display-page {
            background: var(--display-background);
            color: var(--display-text);
            display: flex;
            flex-direction: column;
            font-family: var(--display-font);
            height: 100vh;
            height: 100dvh;
            overflow: hidden;
            position: relative;
        }
        .display-content {
            box-sizing: border-box;
            flex: 1;
            overflow-y: auto;
            padding: 0 1.25rem 5.5rem;
        }
        .display-header {
            align-items: center;
            background: var(--display-header-overlay);
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            color: var(--display-overlay-text);
            display: flex;
            flex-shrink: 0;
            flex-wrap: wrap;
            gap: 1rem;
            left: 0;
            padding: 1rem 1.5rem;
            position: absolute;
            right: 0;
            top: 0;
            z-index: 30;
        }
        .display-menu-toggle {
            display: none;
        }
        .display-brand {
            color: inherit;
            display: inline-flex;
            align-items: center;
            margin-right: 1rem;
            padding: 0.2rem 0;
            text-decoration: none;
            white-space: nowrap;
        }
        .display-brand img {
            display: block;
            height: auto;
            max-height: 44px;
            width: auto;
        }
        .display-main-nav {
            align-items: center;
            display: flex;
            flex: 1;
            flex-wrap: wrap;
            gap: 0.75rem;
            justify-content: center;
        }
        .display-menu-panel {
            align-items: center;
            display: flex;
            flex: 1;
            gap: 1rem;
            justify-content: flex-end;
            min-width: 0;
        }
        .display-main-link {
            color: #c8beb2;
            font-family: "Montserrat", Arial, sans-serif;
            font-size: 0.82rem;
            font-weight: 400;
            letter-spacing: 0.03em;
            padding: 0.45rem 0.2rem;
            text-decoration: none;
            text-transform: uppercase;
            white-space: nowrap;
        }
        .display-main-link:hover,
        .display-main-link:focus-visible,
        .display-main-link.is-active {
            color: #e0d7cc;
            opacity: 1;
            outline: none;
        }
        .display-contact-link {
            align-items: center;
            background: transparent;
            border: 1px solid #c59655;
            border-radius: 0;
            color: #d4a15c;
            display: inline-flex;
            font-family: "Montserrat", Arial, sans-serif;
            font-size: 0.7rem;
            font-weight: 600;
            height: 2.1rem;
            justify-content: center;
            margin-left: 1rem;
            padding: 0.35rem 0.8rem;
            text-decoration: none;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            transition: filter 160ms ease, transform 160ms ease;
            white-space: nowrap;
            width: 6.2rem;
        }
        .display-contact-link:hover,
        .display-contact-link:focus-visible {
            background: rgba(201, 162, 75, 0.12);
            outline: none;
            transform: translateY(-1px);
        }
        .portfolio-category-grid {
            column-count: 3;
            column-gap: 0;
            margin: 0 auto;
            max-width: 1120px;
            padding: 1.5rem 0 1.5rem;
        }
        .portfolio-divider-stack {
            display: grid;
            gap: 0.7rem;
            margin: 1.5rem auto 0;
            max-width: 1120px;
            width: 100%;
        }
        .portfolio-divider-stack span {
            background: rgba(255, 234, 182, 0.18);
            display: block;
            height: 1px;
            width: 100%;
        }
        .portfolio-secondary-grid {
            padding-top: 1.5rem;
        }
        .portfolio-intro-wrap {
            padding: 6.75rem 0 0;
            width: 100%;
        }
        .portfolio-intro-wrap.is-shoots-overview-wrap {
            padding-top: 0;
        }
        .portfolio-intro-wrap.is-portfolio-overview-wrap {
            padding-top: 0;
        }
        .portfolio-intro {
            border-bottom: 1px solid rgba(255, 234, 182, 0.18);
            box-sizing: border-box;
            display: block;
            margin: 0 auto;
            max-width: 1120px;
            padding: 1.9rem 0 2rem;
            width: 100%;
        }
        .portfolio-overview-intro,
        .shoots-overview {
            max-width: none;
            width: 90%;
        }
        .portfolio-intro-copy {
            max-width: 36rem;
        }
        .portfolio-intro-kicker {
            color: #d7b15f;
            display: block;
            font-size: 0.72rem;
            letter-spacing: 0.42em;
            margin-bottom: 1rem;
            text-transform: uppercase;
        }
        .portfolio-intro h1 {
            font-family: "Cormorant Garamond", "Times New Roman", serif;
            font-size: clamp(3rem, 5vw, 4.4rem);
            font-weight: 400;
            letter-spacing: -0.03em;
            line-height: 0.98;
            margin: 0;
        }
        .portfolio-intro-note {
            color: rgba(255, 234, 182, 0.82);
            font-size: 0.98rem;
            line-height: 1.65;
            margin: 1rem 0 0;
            max-width: 36rem;
        }
        .portfolio-category-grid {
            width: 100%;
        }
        .portfolio-category-card {
            color: #fff;
            display: block;
            break-inside: avoid;
            margin: 0;
            overflow: hidden;
            position: relative;
            text-decoration: none;
            width: 100%;
        }
        .portfolio-category-card::after {
            background: linear-gradient(180deg, rgba(0, 0, 0, 0) 38%, rgba(0, 0, 0, 0.38) 58%, rgba(0, 0, 0, 0.82) 78%, rgba(0, 0, 0, 0.98) 100%);
            content: "";
            inset: 0;
            position: absolute;
        }
        .portfolio-category-card img {
            display: block;
            height: auto;
            object-fit: contain;
            transition: transform 180ms ease;
            width: 100%;
        }
        .portfolio-category-card:hover img,
        .portfolio-category-card:focus-visible img {
            transform: scale(1.04);
        }
        .portfolio-category-card:focus-visible {
            outline: 2px solid #fff;
            outline-offset: 2px;
        }
        .portfolio-category-label {
            left: 1rem;
            position: absolute;
            z-index: 1;
        }
        .portfolio-category-label {
            background: rgba(0, 0, 0, 0.32);
            border-radius: 999px;
            bottom: 1rem;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            opacity: 0.92;
            padding: 0.22rem 0.55rem;
            text-transform: uppercase;
        }
        .collage-grid {
            display: grid;
            gap: 0.55rem;
            grid-template-areas:
                "large side-top side-middle"
                "large bottom-left bottom-right";
            grid-template-columns: minmax(0, 1.7fr) repeat(2, minmax(0, 1fr));
            grid-template-rows: minmax(220px, 1fr) minmax(220px, 1fr);
            height: 100%;
            margin: 0 auto;
            max-width: 1120px;
            padding: 6.25rem 0 1.5rem;
            width: 100%;
            box-sizing: border-box;
        }
        .display-page.is-collage .collage-grid {
            padding-bottom: 1.5rem;
            padding-top: 6.5rem;
        }
        .collage-item {
            border-radius: 1rem;
            color: #fff;
            display: block;
            min-height: 0;
            overflow: hidden;
            position: relative;
            text-decoration: none;
        }
        .collage-item:nth-child(1) {
            grid-area: large;
        }
        .collage-item:nth-child(2) {
            grid-area: side-top;
        }
        .collage-item:nth-child(3) {
            grid-area: side-middle;
        }
        .collage-item:nth-child(4) {
            grid-area: bottom-left;
        }
        .collage-item:nth-child(5) {
            grid-area: bottom-right;
        }
        .collage-item::after {
            background: linear-gradient(180deg, rgba(0, 0, 0, 0) 55%, rgba(0, 0, 0, 0.96) 100%);
            content: "";
            inset: 0;
            position: absolute;
        }
        .collage-item img {
            display: block;
            height: 100%;
            object-fit: cover;
            transition: transform 180ms ease;
            width: 100%;
        }
        .collage-item:hover img,
        .collage-item:focus-visible img {
            transform: scale(1.04);
        }
        .collage-item:focus-visible {
            outline: 2px solid #fff;
            outline-offset: 2px;
        }
        .collage-label {
            bottom: 0.75rem;
            left: 0.75rem;
            max-width: calc(100% - 1.5rem);
            overflow: hidden;
            position: absolute;
            text-overflow: ellipsis;
            white-space: nowrap;
            z-index: 1;
            font-size: 0.9rem;
            font-weight: 700;
        }
        .collage-empty {
            display: grid;
            margin: 0 auto;
            max-width: 880px;
            place-items: center;
            padding: 6.25rem 0 1.5rem;
            text-align: center;
        }
        .shoots-overview {
            column-count: 3;
            column-gap: 1.5rem;
            margin: 0 auto;
            padding: 6.75rem 0 1.5rem;
        }
        .portfolio-photo-overview {
            padding-top: 1.5rem;
        }
        .portfolio-overview-intro.is-portfolio-intro h1,
        .portfolio-overview-intro.is-shoots-intro h1 {
            color: #d7b15f;
            font-family: var(--display-font);
            font-size: clamp(1rem, 1.45vw, 1.35rem);
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }
        .portfolio-overview-intro .portfolio-subtitle {
            font-family: "Cormorant Garamond", "Times New Roman", serif;
            font-size: clamp(2.35rem, 4.1vw, 3.7rem);
            font-weight: 400;
            line-height: 1;
            margin: 1.25rem 0 0;
        }
        .shoot-card {
            color: #fff;
            display: block;
            break-inside: avoid;
            margin: 0 0 1.5rem;
            overflow: hidden;
            position: relative;
            text-decoration: none;
            width: 100%;
        }
        .shoot-card::after {
            background: linear-gradient(180deg, rgba(0, 0, 0, 0.12) 25%, rgba(0, 0, 0, 0.46) 60%, rgba(0, 0, 0, 1) 100%);
            content: "";
            inset: 0;
            position: absolute;
        }
        .shoot-card img {
            display: block;
            height: auto;
            object-fit: contain;
            width: 100%;
        }
        .shoot-card-copy {
            bottom: 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.28rem;
            left: 1rem;
            padding: 0;
            position: absolute;
            right: 1rem;
            z-index: 1;
        }
        .shoot-card-title {
            color: rgba(255, 255, 255, 0.84);
            display: block;
            font-size: 1.05rem;
            font-weight: 500;
            letter-spacing: 0.01em;
            line-height: 1.3;
            margin: 0;
            text-shadow: 0 1px 10px rgba(0, 0, 0, 0.45);
        }
        .shoot-card-meta {
            color: rgba(255, 234, 182, 0.9);
            display: inline-block;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.12em;
            margin: 0;
            opacity: 0.9;
            text-shadow: 0 1px 10px rgba(0, 0, 0, 0.45);
            text-transform: uppercase;
            width: fit-content;
        }
        .contact-panel {
            align-items: stretch;
            display: grid;
            gap: 2rem;
            grid-template-columns: minmax(0, 1.1fr) minmax(320px, 0.9fr);
            margin: 0 auto;
            max-width: 1120px;
            padding: 7rem 0 1.5rem;
        }
        .contact-visual {
            align-items: stretch;
            border-radius: 1.25rem;
            display: flex;
            overflow: hidden;
            position: relative;
        }
        .contact-visual::after {
            background: linear-gradient(180deg, rgba(0, 0, 0, 0.12) 25%, rgba(0, 0, 0, 0.46) 60%, rgba(0, 0, 0, 1) 100%);
            content: "";
            inset: 0;
            position: absolute;
        }
        .contact-photo {
            border-radius: 1.25rem;
            display: block;
            height: 100%;
            object-fit: contain;
            width: 100%;
        }
        .contact-copy {
            color: var(--display-text);
            padding: 0;
        }
        .contact-copy-kicker {
            color: rgba(215, 177, 95, 0.82);
            display: block;
            font-size: 0.72rem;
            letter-spacing: 0.38em;
            margin-bottom: 0.9rem;
            text-transform: uppercase;
        }
        .contact-copy h1 {
            font-size: clamp(2rem, 4vw, 3.4rem);
            line-height: 1.05;
            margin: 0 0 1rem;
        }
        .contact-description {
            font-size: 1.05rem;
            line-height: 1.7;
        }
        .contact-more {
            background: none;
            border: 0;
            color: #d7b15f;
            cursor: pointer;
            font: inherit;
            margin-top: 0.5rem;
            padding: 0;
            text-decoration: underline;
            text-underline-offset: 0.2em;
        }
        .contact-dialog {
            background: #17130e;
            border: 1px solid rgba(215, 177, 95, 0.62);
            box-shadow: 0 1.5rem 5rem rgba(0, 0, 0, 0.55);
            color: var(--display-text);
            max-width: min(42rem, calc(100vw - 2rem));
            padding: 2rem;
        }
        .contact-dialog::backdrop {
            background: rgba(0, 0, 0, 0.76);
        }
        .contact-dialog-close {
            background: transparent;
            border: 0;
            color: #d7b15f;
            cursor: pointer;
            float: right;
            font-size: 1.7rem;
            line-height: 1;
            padding: 0 0 0.75rem 1rem;
        }
        .contact-dialog h2 {
            font-size: clamp(1.8rem, 4vw, 2.8rem);
            line-height: 1.1;
            margin: 0 0 1.25rem;
        }
        .contact-dialog-content {
            font-size: 1.05rem;
            line-height: 1.7;
        }
        .contact-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.85rem;
            margin-top: 1.5rem;
        }
        .contact-socials {
            display: flex;
            gap: 0.7rem;
            margin-top: 1.25rem;
        }
        .contact-social-link {
            align-items: center;
            border: 1px solid rgba(215, 177, 95, 0.62);
            border-radius: 50%;
            color: #d7b15f;
            display: inline-flex;
            height: 2.6rem;
            justify-content: center;
            transition: background-color 160ms ease, color 160ms ease;
            width: 2.6rem;
        }
        .contact-social-link:hover,
        .contact-social-link:focus-visible {
            background: #d7b15f;
            color: #17130e;
        }
        .contact-social-link svg {
            height: 1.2rem;
            width: 1.2rem;
        }
        .contact-action {
            align-items: center;
            background: transparent;
            border: 1px solid #c59655;
            border-radius: 0;
            color: #d4a15c;
            display: inline-flex;
            gap: 0.6rem;
            font-family: "Montserrat", Arial, sans-serif;
            font-size: 0.7rem;
            font-weight: 600;
            letter-spacing: 0.12em;
            min-height: 2.1rem;
            padding: 0.35rem 0.8rem;
            text-decoration: none;
            text-transform: uppercase;
            transition: filter 160ms ease, transform 160ms ease, background 160ms ease;
        }
        .contact-action:hover,
        .contact-action:focus-visible {
            background: rgba(201, 162, 75, 0.12);
            outline: none;
            transform: translateY(-1px);
        }
        .contact-action svg {
            height: 1.15rem;
            width: 1.15rem;
        }
        .hero-photo {
            margin: 0;
            position: relative;
            width: 100%;
        }
        .hero-photo img {
            display: block;
            height: auto;
            margin: 0 auto;
            max-width: 100%;
            opacity: 1;
            object-fit: cover;
            transition: opacity 1800ms ease-in-out;
            width: 100%;
        }
        .hero-photo img.is-fading {
            opacity: 0;
        }
        .portfolio-stage {
            background: #000;
            flex: 1;
            min-height: 0;
            overflow: hidden;
            position: relative;
            width: 100%;
        }
        .portfolio-stage img {
            display: block;
            height: 100%;
            object-fit: cover;
            transition: opacity 900ms ease-in-out;
            width: 100%;
        }
        .portfolio-stage img.is-fading {
            opacity: 0;
        }
        .portfolio-overlay {
            align-items: flex-end;
            background: linear-gradient(180deg, rgba(0, 0, 0, 0.05), rgba(0, 0, 0, 0.78));
            color: #fff;
            display: flex;
            inset: 0;
            padding: 1.5rem;
            pointer-events: none;
            position: absolute;
        }
        .portfolio-meta {
            max-width: min(760px, 100%);
        }
        .portfolio-counter {
            background: rgba(0, 0, 0, 0.55);
            border-radius: 999px;
            bottom: 1.5rem;
            color: #fff;
            left: 50%;
            padding: 0.45rem 0.85rem;
            position: absolute;
            transform: translateX(-50%);
            z-index: 2;
        }
        .welcome-stage .portfolio-overlay {
            align-items: flex-start;
            background: linear-gradient(180deg, rgba(0, 0, 0, 0.08) 12%, rgba(0, 0, 0, 0.4) 48%, rgba(0, 0, 0, 0.88) 74%, rgba(0, 0, 0, 1) 100%);
            padding: clamp(6rem, 34vw, 28rem) 1.5rem 1.5rem;
            z-index: 1;
        }
        .welcome-stage {
            align-self: flex-start;
            display: grid;
            flex: 0 0 auto;
        }
        .welcome-stage img {
            grid-area: 1 / 1;
            height: auto;
            width: 100%;
        }
        .welcome-stage .portfolio-overlay {
            grid-area: 1 / 1;
            position: relative;
        }
        .welcome-stage .portfolio-meta {
            max-width: min(44rem, 100%);
        }
        .welcome-stage .hero-title {
            font-size: clamp(2.5rem, 5vw, 4.8rem);
            line-height: 0.95;
            margin: 0;
            white-space: nowrap;
        }
        .welcome-kicker {
            color: #d7b15f;
            font-family: var(--display-font);
            font-size: clamp(1rem, 1.45vw, 1.35rem);
            font-weight: 700;
            letter-spacing: 0.12em;
            margin: 0 0 1.1rem;
            text-transform: uppercase;
        }
        .welcome-description {
            color: rgba(255, 255, 255, 0.84);
            font-size: clamp(0.95rem, 1.3vw, 1.1rem);
            line-height: 1.65;
            margin-top: 1.25rem;
        }
        .welcome-description > :first-child {
            margin-top: 0;
        }
        .welcome-description > :last-child {
            margin-bottom: 0;
        }
        .portfolio-nav {
            align-items: center;
            background: rgba(0, 0, 0, 0.38);
            border: 1px solid rgba(255, 255, 255, 0.65);
            border-radius: 50%;
            color: #fff;
            cursor: pointer;
            display: flex;
            font-family: Arial, sans-serif;
            font-size: 2.6rem;
            height: 4rem;
            justify-content: center;
            line-height: 1;
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 4rem;
            z-index: 2;
        }
        .portfolio-nav:hover,
        .portfolio-nav:focus-visible {
            background: rgba(255, 255, 255, 0.2);
            outline: 2px solid #fff;
            outline-offset: 2px;
        }
        .portfolio-prev {
            left: 1rem;
        }
        .portfolio-next {
            right: 1rem;
        }
        .hero-panel {
            background: var(--display-overlay-background);
            border-radius: 1rem;
            color: var(--display-overlay-text);
            left: 1.25rem;
            max-width: min(720px, calc(100% - 2.5rem));
            padding: 1rem 1.25rem;
            position: absolute;
            top: 1.25rem;
        }
        .hero-title {
            font-size: clamp(1.8rem, 4vw, 3.6rem);
            font-weight: 700;
            line-height: 1.05;
            margin: 0;
        }
        .hero-title-row {
            align-items: center;
            display: flex;
            gap: 0.8rem;
        }
        .gallery-open {
            align-items: center;
            background: rgba(255, 255, 255, 0.16);
            border: 1px solid rgba(255, 255, 255, 0.55);
            border-radius: 50%;
            color: inherit;
            cursor: pointer;
            display: inline-flex;
            flex: 0 0 auto;
            height: 2.8rem;
            justify-content: center;
            padding: 0;
            transition: background 160ms ease, transform 160ms ease;
            width: 2.8rem;
        }
        .gallery-open:hover,
        .gallery-open:focus-visible {
            background: rgba(255, 255, 255, 0.3);
            outline: 2px solid currentColor;
            outline-offset: 2px;
            transform: scale(1.05);
        }
        .gallery-open svg {
            height: 1.45rem;
            width: 1.45rem;
        }
        .photo-viewer[hidden] {
            display: none;
        }
        .photo-viewer-nav[hidden] {
            display: none;
        }
        .photo-viewer {
            align-items: center;
            background: rgba(0, 0, 0, 0.96);
            display: flex;
            inset: 0;
            justify-content: center;
            position: fixed;
            z-index: 1000;
        }
        .photo-viewer-image {
            display: block;
            height: 100%;
            max-height: 100vh;
            max-width: 100vw;
            object-fit: contain;
            width: 100%;
        }
        .photo-viewer-close,
        .photo-viewer-nav {
            align-items: center;
            background: rgba(0, 0, 0, 0.48);
            border: 1px solid rgba(255, 255, 255, 0.65);
            border-radius: 50%;
            color: #fff;
            cursor: pointer;
            display: flex;
            font-family: Arial, sans-serif;
            justify-content: center;
            position: absolute;
            z-index: 2;
        }
        .photo-viewer-close {
            font-size: 2rem;
            height: 3rem;
            right: 1rem;
            top: 1rem;
            width: 3rem;
        }
        .photo-viewer-nav {
            font-size: 2.6rem;
            height: 4rem;
            line-height: 1;
            top: 50%;
            transform: translateY(-50%);
            width: 4rem;
        }
        .photo-viewer-nav:hover,
        .photo-viewer-nav:focus-visible,
        .photo-viewer-close:hover,
        .photo-viewer-close:focus-visible {
            background: rgba(255, 255, 255, 0.2);
            outline: 2px solid #fff;
            outline-offset: 2px;
        }
        .photo-viewer-prev {
            left: 1rem;
        }
        .photo-viewer-next {
            right: 1rem;
        }
        .photo-viewer-counter {
            background: rgba(0, 0, 0, 0.55);
            border-radius: 999px;
            bottom: 1rem;
            color: #fff;
            left: 50%;
            padding: 0.4rem 0.8rem;
            position: absolute;
            transform: translateX(-50%);
            z-index: 2;
        }
        .hero-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 0.6rem;
            margin-top: 0.85rem;
        }
        .hero-pill {
            background: rgba(255, 255, 255, 0.14);
            border-radius: 999px;
            padding: 0.4rem 0.75rem;
        }
        .hero-details {
            margin: 1.5rem 0 0;
            padding: 0 1.25rem 2rem;
        }
        .hero-description {
            color: var(--display-text);
            font-size: 1.05rem;
            line-height: 1.7;
            margin: 0;
            max-width: min(720px, calc(100% - 2.5rem));
            text-align: left;
        }
        .hero-description-preview {
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
            overflow: hidden;
        }
        .shoot-gallery {
            display: grid;
            gap: 0.85rem;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            margin: 0;
            padding: 0 1.25rem 2rem;
        }
        .shoot-gallery-item {
            background: rgba(0, 0, 0, 0.04);
            border: 0;
            border-radius: 0.85rem;
            cursor: pointer;
            display: block;
            overflow: hidden;
            padding: 0;
            text-decoration: none;
            width: 100%;
        }
        .shoot-gallery-item img {
            display: block;
            height: 100%;
            min-height: 220px;
            object-fit: cover;
            transition: transform 180ms ease;
            width: 100%;
        }
        .shoot-gallery-item:hover img,
        .shoot-gallery-item:focus-visible img {
            transform: scale(1.03);
        }
        .shoot-gallery-item:focus-visible {
            outline: 2px solid currentColor;
            outline-offset: 3px;
        }
        .display-empty {
            flex: 1;
            padding: 3rem 1.5rem;
        }
        .display-content {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
            padding-bottom: 5.5rem;
            padding-top: 5.5rem;
        }
        .display-page.is-portfolio .display-content {
            display: flex;
            overflow: hidden;
            padding-bottom: 0;
            padding-top: 0;
        }
        .display-page.is-portfolio-overview .display-content {
            flex-direction: column;
            overflow-y: auto;
            padding-bottom: 5.5rem;
            padding-top: 5.5rem;
        }
        .display-page.is-welcome .display-content {
            display: flex;
            overflow-y: auto;
            padding: 0;
        }
        .config-grid {
            display: grid;
            gap: 1rem;
            max-width: 1180px;
        }
        .config-grid label {
            font-weight: 700;
        }
        .config-color-row {
            display: grid;
            gap: 1rem;
            grid-template-columns: 1fr;
            max-width: 620px;
        }
        .config-field {
            display: grid;
            gap: 0.35rem;
        }
        .inline-form {
            display: inline;
        }
        .admin-actions {
            align-items: center;
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
        }
        .table-actions {
            font-size: 0.88rem;
            white-space: normal;
        }
        .table-actions a,
        .table-actions .inline-form,
        .table-actions .delete-button {
            display: inline-block;
            vertical-align: middle;
        }
        .table-actions .inline-form {
            margin-top: 0.2rem;
        }
        .table-actions .delete-button {
            white-space: nowrap;
        }
        .muted-cell {
            color: #6b7280;
        }
        .prio-editor {
            align-items: center;
            display: flex;
            gap: 0.45rem;
        }
        .prio-input {
            box-sizing: border-box;
            max-width: 5.5rem;
            padding: 0.35rem 0.45rem;
            width: 100%;
        }
        .prio-status {
            display: inline-block;
            font-size: 1.1rem;
            font-weight: 700;
            min-width: 1.2rem;
        }
        .prio-status.is-saving {
            color: #6b7280;
        }
        .prio-status.is-saved {
            color: #16803a;
        }
        .prio-status.is-error {
            color: #b00020;
        }
        .delete-button {
            background: none;
            border: 0;
            color: #b00020;
            cursor: pointer;
            font: inherit;
            padding: 0;
            text-decoration: underline;
        }
        .admin-body .site-footer {
            background: <?php echo h(colorWithOpacity($configValues['header_color'], 0.28)); ?>;
            backdrop-filter: blur(14px);
            -webkit-backdrop-filter: blur(14px);
            border-radius: 1rem;
            color: #ffffff;
            flex-shrink: 0;
            margin-top: 0;
            padding: 1rem 1.5rem;
        }
        .display-page > .site-footer {
            bottom: 0;
            left: 0;
            position: absolute;
            right: 0;
            z-index: 30;
        }
        @media (max-width: 720px) {
            .display-header {
                align-items: flex-start;
                flex-wrap: nowrap;
                padding: 0.75rem 1rem;
            }
            .display-brand {
                margin-right: 0;
            }
            .display-brand img {
                max-height: 34px;
            }
            .display-menu-toggle {
                align-items: center;
                align-self: center;
                background: transparent;
                border: 1px solid rgba(255, 234, 182, 0.28);
                border-radius: 999px;
                color: inherit;
                cursor: pointer;
                display: inline-flex;
                flex-direction: column;
                gap: 0.3rem;
                height: 2.85rem;
                justify-content: center;
                margin-left: auto;
                padding: 0;
                width: 2.85rem;
            }
            .display-menu-toggle span {
                background: currentColor;
                border-radius: 999px;
                display: block;
                height: 2px;
                transition: transform 160ms ease, opacity 160ms ease;
                width: 1.15rem;
            }
            .display-header.is-menu-open .display-menu-toggle span:nth-child(1) {
                transform: translateY(0.38rem) rotate(45deg);
            }
            .display-header.is-menu-open .display-menu-toggle span:nth-child(2) {
                opacity: 0;
            }
            .display-header.is-menu-open .display-menu-toggle span:nth-child(3) {
                transform: translateY(-0.38rem) rotate(-45deg);
            }
            .display-menu-panel {
                background: rgba(9, 7, 5, 0.96);
                border: 1px solid rgba(255, 234, 182, 0.14);
                border-radius: 1rem;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.34);
                display: none;
                left: 1rem;
                padding: 1rem;
                position: absolute;
                right: 1rem;
                top: calc(100% + 0.5rem);
            }
            .display-header.is-menu-open .display-menu-panel {
                align-items: stretch;
                display: flex;
                flex-direction: column;
                gap: 0.9rem;
            }
            .display-main-nav {
                align-items: stretch;
                flex-direction: column;
                gap: 0.25rem;
                margin-left: 0;
                width: 100%;
            }
            .display-main-link {
                border-bottom: 1px solid rgba(255, 234, 182, 0.08);
                font-size: 1rem;
                padding: 0.7rem 0;
            }
            .display-contact-link {
                align-self: stretch;
                height: 2.75rem;
                margin-left: 0;
                width: 100%;
            }
            .hero-photo img {
                width: 100%;
            }
            .hero-panel {
                left: 0.75rem;
                max-width: calc(100% - 1.5rem);
                padding: 0.85rem 1rem;
                top: 0.75rem;
            }
            .hero-title {
                font-size: clamp(1.6rem, 7vw, 2.6rem);
            }
            .collage-grid {
                grid-template-areas:
                    "large"
                    "side-top"
                    "side-middle"
                    "bottom-left"
                    "bottom-right";
                grid-template-columns: 1fr;
                grid-template-rows: repeat(5, minmax(140px, 1fr));
                padding: 5.5rem 0 1.5rem;
            }
            .portfolio-intro-wrap {
                padding: 8.5rem 0 0;
            }
            .portfolio-intro-wrap.is-shoots-overview-wrap {
                padding-top: 0;
            }
            .portfolio-intro-wrap.is-portfolio-overview-wrap {
                padding-top: 0;
            }
            .portfolio-intro {
                padding: 1.25rem 0 1.5rem;
            }
            .portfolio-intro h1 {
                font-size: clamp(2.55rem, 14vw, 3.8rem);
            }
            .portfolio-intro-note {
                max-width: 100%;
            }
            .portfolio-category-grid {
                column-count: 1;
                padding: 8.5rem 0 1.5rem;
            }
            .portfolio-divider-stack {
                gap: 0.55rem;
                margin-top: 1rem;
            }
            .portfolio-secondary-grid {
                padding-top: 1rem;
            }
            .shoots-overview {
                column-count: 1;
                padding: 8.5rem 0 1.5rem;
            }
            .portfolio-photo-overview {
                padding-top: 1.5rem;
            }
            .contact-panel {
                grid-template-columns: 1fr;
                padding: 8.5rem 0 1.5rem;
            }
            .contact-photo {
                height: auto;
                max-height: 58vh;
            }
            .display-content {
                padding: 0 1rem 5rem;
            }
            .photo-upload-grid {
                grid-template-columns: 1fr;
            }
            .gallery-open {
                height: 2.5rem;
                width: 2.5rem;
            }
            .portfolio-overlay {
                padding: 1rem;
            }
            .portfolio-nav {
                font-size: 2rem;
                height: 3rem;
                width: 3rem;
            }
            .portfolio-prev {
                left: 0.5rem;
            }
            .portfolio-next {
                right: 0.5rem;
            }
            .portfolio-counter {
                bottom: 1rem;
            }
            .photo-viewer-nav {
                font-size: 2rem;
                height: 3rem;
                width: 3rem;
            }
            .photo-viewer-prev {
                left: 0.5rem;
            }
            .photo-viewer-next {
                right: 0.5rem;
            }
            .hero-details {
                padding: 0 1rem 1.5rem;
            }
            .shoot-gallery {
                grid-template-columns: 1fr;
                padding: 0 1rem 1.5rem;
            }
            .shoot-gallery-item img {
                min-height: 180px;
            }
            .display-content {
                padding-bottom: 4.75rem;
                padding-top: 4.75rem;
            }
            .display-page.is-portfolio .display-content {
                padding-bottom: 0;
                padding-top: 0;
            }
            .display-page.is-portfolio-overview .display-content {
                padding-bottom: 4.75rem;
                padding-top: 4.75rem;
            }
            .display-page.is-welcome .display-content {
                -webkit-overflow-scrolling: touch;
                overflow-x: hidden;
                overflow-y: auto;
                padding: 0;
            }
            .welcome-stage .portfolio-meta {
                min-width: 0;
            }
            .welcome-stage .hero-title {
                overflow-wrap: anywhere;
                white-space: normal;
            }
            .welcome-stage img {
                -webkit-mask-image: linear-gradient(to bottom, #000 48%, transparent 100%);
                mask-image: linear-gradient(to bottom, #000 48%, transparent 100%);
            }
        }
    </style>
</head>
<body class="<?php echo $display ? 'display-body' : 'admin-body'; ?>">
    <?php if (!$display): ?>
    <div class="admin-shell">
        <header class="admin-header">
            <div class="admin-header-copy">
                <a href="<?php echo h($publicPath); ?>" class="admin-brand" aria-label="Ga naar weergave">
                    <img src="<?php echo h($logoPath); ?>" alt="HaverFotografie">
                </a>
                <?php if ($photos && $photoData): ?>
                <h1 class="admin-header-note">Foto's uploaden - <?php echo h($photoData['titel']); ?></h1>
                <?php elseif ($edit): ?>
                <?php endif; ?>
            </div>
        </header>
        <div class="admin-content">
    <?php endif; ?>
    <?php if ($configScreen): ?>
        <h1>Configuratie</h1>
        <p><a href="<?php echo h($adminDashboardUrl); ?>">Terug naar de lijst</a></p>

        <form method="post" action="" class="config-grid">
            <input type="hidden" name="action" value="config">

            <label for="font_family">Lettertype</label>
            <?php renderConfigSelect('font_family', $configValues['font_family'], [
                'Arial, sans-serif' => 'Arial',
                '"Montserrat", Arial, sans-serif' => 'Montserrat',
                'Verdana, sans-serif' => 'Verdana',
                'Georgia, serif' => 'Georgia',
                '"Times New Roman", serif' => 'Times New Roman',
                '"Trebuchet MS", sans-serif' => 'Trebuchet MS',
                '"Courier New", monospace' => 'Courier New',
            ], true); ?>

            <div class="config-color-row">
                <div class="config-field">
                    <label for="background_color">Achtergrondkleur</label>
                    <?php renderConfigSelect('background_color', $configValues['background_color'], [
                        '#ffffff' => 'Wit',
                        '#f7f7f7' => 'Lichtgrijs',
                        '#f5f1ea' => 'Warm licht',
                        '#111111' => 'Zwart',
                        '#1f2933' => 'Donker blauwgrijs',
                    ]); ?>
                </div>

                <div class="config-field">
                    <label for="header_color">Headerkleur</label>
                    <?php renderConfigSelect('header_color', $configValues['header_color'], [
                        '#111111' => 'Zwart',
                        '#1f2933' => 'Donker blauwgrijs',
                        '#ffffff' => 'Wit',
                        '#6b4f3f' => 'Warm bruin',
                        '#1f5fbf' => 'Blauw',
                    ]); ?>
                </div>

                <div class="config-field">
                    <label for="text_color">Tekstkleur</label>
                    <?php renderConfigSelect('text_color', $configValues['text_color'], [
                        '#111111' => 'Zwart',
                        '#333333' => 'Donkergrijs',
                        '#ffffff' => 'Wit',
                        '#f7f7f7' => 'Lichtgrijs',
                        '#6b4f3f' => 'Warm bruin',
                    ]); ?>
                </div>

                <div class="config-field">
                    <label for="link_color">Linkkleur</label>
                    <?php renderConfigSelect('link_color', $configValues['link_color'], [
                        '#1f5fbf' => 'Blauw',
                        '#0f766e' => 'Groenblauw',
                        '#7c3aed' => 'Paars',
                        '#b45309' => 'Amber',
                        '#111111' => 'Zwart',
                    ]); ?>
                </div>

                <div class="config-field">
                    <label for="overlay_text_color">Naamkleur op foto</label>
                    <?php renderConfigSelect('overlay_text_color', $configValues['overlay_text_color'], [
                        '#ffffff' => 'Wit',
                        '#f7f7f7' => 'Lichtgrijs',
                        '#111111' => 'Zwart',
                        '#facc15' => 'Goud',
                        '#f5f1ea' => 'Warm licht',
                    ]); ?>
                </div>

                <div class="config-field">
                    <label for="overlay_background">Transparante achtergrond naam</label>
                    <?php renderConfigSelect('overlay_background', $configValues['overlay_background'], [
                        'rgba(0, 0, 0, 0.25)' => 'Zwart 25%',
                        'rgba(0, 0, 0, 0.45)' => 'Zwart 45%',
                        'rgba(0, 0, 0, 0.65)' => 'Zwart 65%',
                        'rgba(255, 255, 255, 0.35)' => 'Wit 35%',
                        'rgba(255, 255, 255, 0.6)' => 'Wit 60%',
                    ]); ?>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="primary-button">Configuratie opslaan</button>
            </div>
        </form>
    <?php elseif ($visitsScreen): ?>
        <h2>Bezoeken per shoot — <?php echo h($todayVisitCount); ?> bezoeken vandaag</h2>
        <?php if (count($visitSummaryRows) === 0): ?>
            <p>Geen shoots gevonden.</p>
        <?php else: ?>
            <table>
                <colgroup>
                    <col style="width: 27%;">
                    <col style="width: 13%;">
                    <col style="width: 18%;">
                    <col style="width: 10%;">
                    <col style="width: 10%;">
                    <col style="width: 22%;">
                </colgroup>
                <thead>
                    <tr>
                        <th>Titel</th>
                        <th>Categorie</th>
                        <th>Locatie</th>
                        <th>Bezoeken</th>
                        <th>Vandaag</th>
                        <th>Laatst bezocht</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($visitSummaryRows as $visitRow): ?>
                        <tr>
                            <td><?php echo h($visitRow['titel']); ?></td>
                            <td><?php echo h($visitRow['categorie']); ?></td>
                            <td><?php echo h($visitRow['lokatie']); ?></td>
                            <td><?php echo h($visitRow['visit_count']); ?></td>
                            <td><?php echo h($visitRow['today_visit_count']); ?></td>
                            <td class="<?php echo $visitRow['last_visited_at'] ? '' : 'muted-cell'; ?>">
                                <?php echo h($visitRow['last_visited_at'] ?: 'Nog niet bezocht'); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <h2>Recente bezoeken</h2>
        <?php if (count($recentVisits) === 0): ?>
            <p>Nog geen bezoeken gelogd.</p>
        <?php else: ?>
            <table>
                <colgroup>
                    <col style="width: 16%;">
                    <col style="width: 22%;">
                    <col style="width: 14%;">
                    <col style="width: 24%;">
                    <col style="width: 24%;">
                </colgroup>
                <thead>
                    <tr>
                        <th>Moment</th>
                        <th>Shoot</th>
                        <th>IP-adres</th>
                        <th>Referer</th>
                        <th>User agent</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentVisits as $visit): ?>
                        <tr>
                            <td><?php echo h($visit['visited_at']); ?></td>
                            <td><?php echo h($visit['titel']); ?></td>
                            <td><?php echo h($visit['ip_address'] !== '' ? $visit['ip_address'] : '-'); ?></td>
                            <td><?php echo h($visit['referer'] !== '' ? $visit['referer'] : '-'); ?></td>
                            <td><?php echo h($visit['user_agent'] !== '' ? $visit['user_agent'] : '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php elseif ($display): ?>
        <div class="display-page<?php echo $portfolioView ? ' is-portfolio' : ''; ?><?php echo $portfolioView && !isset($_GET['shoot']) ? ' is-portfolio-overview' : ''; ?><?php echo $welcomeView ? ' is-welcome' : ''; ?><?php echo $collageView ? ' is-collage' : ''; ?>" style="--display-font: <?php echo h($configValues['font_family']); ?>; --display-background: <?php echo h($configValues['background_color']); ?>; --display-header: <?php echo h($configValues['header_color']); ?>; --display-header-overlay: <?php echo h(colorWithOpacity($configValues['header_color'], 0.34)); ?>; --display-footer-overlay: <?php echo h(colorWithOpacity($configValues['header_color'], 0.28)); ?>; --display-text: <?php echo h($configValues['text_color']); ?>; --display-link: <?php echo h($configValues['link_color']); ?>; --display-overlay-text: <?php echo h($configValues['overlay_text_color']); ?>; --display-overlay-background: <?php echo h($configValues['overlay_background']); ?>;">
            <header class="display-header">
                <a href="<?php echo h($isAdminLoggedIn ? $adminDashboardUrl : buildSectionUrl($publicPath, 'welcome')); ?>" class="display-brand" aria-label="<?php echo h($isAdminLoggedIn ? 'Ga naar beheer' : 'Ga naar start'); ?>">
                    <img src="<?php echo h($hiddenLogoSrc !== '' ? $hiddenLogoSrc : $logoPath); ?>" alt="HaverFotografie">
                </a>
                <button type="button" class="display-menu-toggle" id="displayMenuToggle" aria-expanded="false" aria-controls="displayMenuPanel" aria-label="Open navigatiemenu">
                    <span aria-hidden="true"></span>
                    <span aria-hidden="true"></span>
                    <span aria-hidden="true"></span>
                </button>
                <div class="display-menu-panel" id="displayMenuPanel">
                    <nav class="display-main-nav" aria-label="Hoofdnavigatie">
                        <a href="<?php echo h(buildSectionUrl($publicPath, 'welcome')); ?>" class="display-main-link<?php echo $welcomeView ? ' is-active' : ''; ?>">Welkom</a>
                        <a href="<?php echo h(buildSectionUrl($publicPath, 'portfolio')); ?>" class="display-main-link<?php echo $portfolioView ? ' is-active' : ''; ?>">Portfolio</a>
                        <a href="<?php echo h(buildSectionUrl($publicPath, 'shoots')); ?>" class="display-main-link<?php echo ($shootsView || isset($_GET['shoot'])) ? ' is-active' : ''; ?>">Shoots</a>
                        <a href="<?php echo h(buildSectionUrl($publicPath, 'contact')); ?>" class="display-main-link<?php echo $contactView ? ' is-active' : ''; ?>">Over Arthur</a>
                    </nav>
                    <a href="<?php echo h(buildSectionUrl($publicPath, 'contact')); ?>" class="display-contact-link<?php echo $contactView ? ' is-active' : ''; ?>">Contact</a>
                </div>
            </header>
            <div class="display-content">
                <?php if ($contactView): ?>
                    <section class="contact-panel">
                        <?php if ($contactPhoto !== ''): ?>
                            <div class="contact-visual">
                                <img class="contact-photo" src="<?php echo h($contactPhoto); ?>" alt="<?php echo h($hiddenShootDetails['titel'] ?? 'Contactfoto'); ?>" loading="lazy" decoding="async">
                            </div>
                        <?php endif; ?>
                        <div class="contact-copy">
                            <span class="contact-copy-kicker">Over mij</span>
                            <h1><?php echo h($hiddenShootDetails['titel'] ?? 'Contact'); ?></h1>
                            <?php
                                $contactDescription = cleanRichText($hiddenShootDetails['oms1'] ?? '');
                                $contactExcerpt = firstRichTextParagraph($hiddenShootDetails['oms1'] ?? '');
                                $contactHasMore = trim(strip_tags($contactDescription)) !== trim(strip_tags($contactExcerpt));
                                $contactSocialLinks = socialLinksFromShoot($hiddenShootDetails ?? []);
                            ?>
                            <div class="contact-description"><?php echo $contactExcerpt; ?></div>
                            <?php if ($contactHasMore): ?>
                                <button type="button" class="contact-more" id="contactMoreButton" aria-haspopup="dialog" aria-controls="contactDescriptionDialog">meer...</button>
                                <dialog class="contact-dialog" id="contactDescriptionDialog" aria-labelledby="contactDescriptionTitle">
                                    <button type="button" class="contact-dialog-close" id="contactDialogClose" aria-label="Sluiten">&times;</button>
                                    <h2 id="contactDescriptionTitle"><?php echo h($hiddenShootDetails['titel'] ?? 'Over Arthur'); ?></h2>
                                    <div class="contact-dialog-content"><?php echo $contactDescription; ?></div>
                                </dialog>
                            <?php endif; ?>
                            <div class="contact-actions">
                                <a class="contact-action" href="tel:+31643273002">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path fill="currentColor" d="M6.6 10.8a15.5 15.5 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.24c1.08.36 2.24.56 3.4.56a1 1 0 0 1 1 1V20a1 1 0 0 1-1 1C10.3 21 3 13.7 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.16.2 2.32.56 3.4a1 1 0 0 1-.24 1l-2.22 2.4Z"/>
                                    </svg>
                                    <span>+31 6 43273002</span>
                                </a>
                                <a class="contact-action" href="mailto:info@haverfotografie.nl">
                                    <svg viewBox="0 0 24 24" aria-hidden="true">
                                        <path fill="currentColor" d="M4 5h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Zm0 2v.2l8 5.34 8-5.34V7H4Zm16 10V9.6l-7.45 4.97a1 1 0 0 1-1.1 0L4 9.6V17h16Z"/>
                                    </svg>
                                    <span>info@haverfotografie.nl</span>
                                </a>
                            </div>
                            <?php if ($contactSocialLinks): ?>
                                <div class="contact-socials" aria-label="Volg Haver Fotografie op sociale media">
                                    <?php if (isset($contactSocialLinks['instagram'])): ?>
                                        <a class="contact-social-link" href="<?php echo h($contactSocialLinks['instagram']); ?>" target="_blank" rel="noopener noreferrer" aria-label="Instagram">
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M7.2 2h9.6A5.2 5.2 0 0 1 22 7.2v9.6a5.2 5.2 0 0 1-5.2 5.2H7.2A5.2 5.2 0 0 1 2 16.8V7.2A5.2 5.2 0 0 1 7.2 2Zm-.16 2A3.04 3.04 0 0 0 4 7.04v9.92A3.04 3.04 0 0 0 7.04 20h9.92A3.04 3.04 0 0 0 20 16.96V7.04A3.04 3.04 0 0 0 16.96 4H7.04ZM17.5 5.5a1.25 1.25 0 1 1 0 2.5 1.25 1.25 0 0 1 0-2.5ZM12 7a5 5 0 1 1 0 10 5 5 0 0 1 0-10Zm0 2a3 3 0 1 0 0 6 3 3 0 0 0 0-6Z"/></svg>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (isset($contactSocialLinks['linkedin'])): ?>
                                        <a class="contact-social-link" href="<?php echo h($contactSocialLinks['linkedin']); ?>" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn">
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M6.5 8.25H3.25V20.5H6.5V8.25ZM4.88 3.5a1.88 1.88 0 1 0 0 3.75 1.88 1.88 0 0 0 0-3.75ZM20.75 13.48c0-3.7-1.97-5.42-4.6-5.42-2.12 0-3.07 1.17-3.6 1.99V8.25H9.3V20.5h3.25v-6.06c0-1.6.3-3.15 2.29-3.15 1.96 0 1.99 1.84 1.99 3.25v5.96h3.25v-7.02h.67Z"/></svg>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (isset($contactSocialLinks['facebook'])): ?>
                                        <a class="contact-social-link" href="<?php echo h($contactSocialLinks['facebook']); ?>" target="_blank" rel="noopener noreferrer" aria-label="Facebook">
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M13.5 21v-8h2.75l.41-3.12H13.5V7.89c0-.9.25-1.52 1.55-1.52h1.65V3.58A22.2 22.2 0 0 0 14.3 3C11.92 3 10.3 4.45 10.3 7.12v2.76H7.5V13h2.8v8h3.2Z"/></svg>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php elseif ($shootsView || $portfolioView): ?>
                    <div class="portfolio-intro-wrap<?php echo $shootsView ? ' is-shoots-overview-wrap' : ''; ?><?php echo $portfolioView ? ' is-portfolio-overview-wrap' : ''; ?>">
                        <section class="portfolio-intro portfolio-overview-intro<?php echo $portfolioView ? ' is-portfolio-intro' : ' is-shoots-intro'; ?>">
                            <h1><?php echo $portfolioView ? 'PORTFOLIO' : 'SHOOTS'; ?></h1>
                            <?php if ($portfolioView || $shootsView): ?>
                                <h2 class="portfolio-subtitle">De foto's</h2>
                            <?php endif; ?>
                            <p><?php echo $portfolioView ? "De fotoshoots zijn gegroepeerd per categorie. Bekijk hieronder welke categorieën er zijn en selecteer de categorie die je wilt bekijken." : ($portfolioCategoryFilter !== '' ? 'Hieronder staan de shoots in de categorie: ' . h($portfolioCategoryFilter) : 'Hieronder vind je 9 willekeurig geselecteerde fotoshoots uit mijn portfolio. Klik op een foto om de volledige shoot te bekijken..'); ?></p>
                        </section>
                    </div>
                    <section class="shoots-overview<?php echo $portfolioView ? ' portfolio-photo-overview' : ''; ?>" aria-label="Overzicht van shoots">
                        <?php if ($portfolioView ? $portfolioCategoryTiles : $shootOverviewTiles): ?>
                            <?php foreach ($portfolioView ? $portfolioCategoryTiles : $shootOverviewTiles as $tile): ?>
                                <a class="shoot-card" href="<?php echo h($tile['url']); ?>" aria-label="Bekijk <?php echo $portfolioView ? 'categorie' : 'shoot'; ?> <?php echo h($tile['title']); ?>">
                                    <img src="<?php echo h($tile['photo']); ?>" alt="<?php echo h($tile['title']); ?>" loading="lazy" decoding="async">
                                    <div class="shoot-card-copy">
                                        <p class="shoot-card-meta"><?php echo h($tile['category']); ?></p>
                                        <p class="shoot-card-title"><?php echo h($tile['title']); ?></p>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p><?php echo $portfolioView ? 'Er zijn nog geen categorieen met een foto ingesteld.' : 'Er zijn geen shoots gevonden voor deze categorie.'; ?></p>
                        <?php endif; ?>
                    </section>
                <?php elseif ($categoriesView): ?>
                    <div class="portfolio-intro-wrap">
                        <section class="portfolio-intro portfolio-overview-intro">
                            <h1>CATEGORIE</h1>
                            <p>Klik op een foto om de shoots in deze categorie te openen</p>
                        </section>
                    </div>
                    <section class="shoots-overview" aria-label="Overzicht van categorieen">
                        <?php if ($portfolioCategoryTiles): ?>
                            <?php foreach ($portfolioCategoryTiles as $tile): ?>
                                <a class="shoot-card" href="<?php echo h($tile['url']); ?>" aria-label="Bekijk categorie <?php echo h($tile['category']); ?>">
                                    <img src="<?php echo h($tile['photo']); ?>" alt="<?php echo h($tile['category']); ?>" loading="lazy" decoding="async">
                                    <div class="shoot-card-copy">
                                        <p class="shoot-card-meta">Categorie</p>
                                        <p class="shoot-card-title"><?php echo h($tile['category']); ?></p>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>Er zijn geen categorieen gevonden.</p>
                        <?php endif; ?>
                    </section>
                <?php elseif ($welcomeView): ?>
                    <?php if ($welcomeShoot && $welcomePhoto !== ''): ?>
                        <section class="portfolio-stage welcome-stage">
                            <img src="<?php echo h($welcomePhoto); ?>" alt="<?php echo h($welcomeShoot['titel']); ?>" decoding="async">
                            <div class="portfolio-overlay">
                                <div class="portfolio-meta">
                                    <p class="welcome-kicker">Fotografie uit Helmond</p>
                                    <h1 class="hero-title"><?php echo h($hiddenShootDetails['titel'] ?? $welcomeShoot['titel']); ?></h1>
                                    <?php if (trim(strip_tags($hiddenShootDetails['oms1'] ?? '')) !== ''): ?>
                                        <div class="welcome-description"><?php echo cleanRichText($hiddenShootDetails['oms1']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </section>
                    <?php else: ?>
                        <main class="collage-empty">
                            <p>Shoot 18 heeft nog geen foto beschikbaar voor Welkom.</p>
                        </main>
                    <?php endif; ?>
                <?php elseif ($collageView): ?>
                    <?php if ($isHomepageCollage): ?>
                        <section class="collage-grid" aria-label="Collage van willekeurige shoots">
                            <?php foreach ($homepageCollageTiles as $tile): ?>
                                <a class="collage-item" href="<?php echo h($tile['url']); ?>" aria-label="Bekijk shoot <?php echo h($tile['title']); ?>">
                                    <img src="<?php echo h($tile['photo']); ?>" alt="<?php echo h($tile['title']); ?>" loading="lazy" decoding="async">
                                    <span class="collage-label"><?php echo h($tile['title']); ?></span>
                                </a>
                            <?php endforeach; ?>
                        </section>
                    <?php else: ?>
                        <main class="collage-empty">
                            <p>Er zijn nog niet genoeg shoots met foto's voor een collage.</p>
                        </main>
                    <?php endif; ?>
                <?php elseif ($selectedShoot): ?>
                    <?php if ($portfolioView && $heroPhoto !== ''): ?>
                        <section class="portfolio-stage">
                            <img id="displayHeroPhoto" src="<?php echo h($heroPhoto); ?>" alt="<?php echo h($selectedShoot['titel']); ?>" decoding="async">
                            <button type="button" class="portfolio-nav portfolio-prev" id="portfolioPrevious" aria-label="Vorige foto" <?php echo count($selectedShootPhotos) <= 1 ? 'hidden' : ''; ?>>&#8249;</button>
                            <button type="button" class="portfolio-nav portfolio-next" id="portfolioNext" aria-label="Volgende foto" <?php echo count($selectedShootPhotos) <= 1 ? 'hidden' : ''; ?>>&#8250;</button>
                            <div class="portfolio-overlay">
                                <div class="portfolio-meta">
                                    <h1 class="hero-title"><?php echo h($selectedShoot['titel']); ?></h1>
                                    <?php if ($selectedShoot['categorie'] !== '' || $selectedShoot['lokatie'] !== ''): ?>
                                        <div class="hero-meta">
                                            <?php if ($selectedShoot['categorie'] !== ''): ?>
                                                <div class="hero-pill"><?php echo h($selectedShoot['categorie']); ?></div>
                                            <?php endif; ?>
                                            <?php if ($selectedShoot['lokatie'] !== ''): ?>
                                                <div class="hero-pill"><?php echo h($selectedShoot['lokatie']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="portfolio-counter" id="heroPhotoCounter" aria-live="polite"></div>
                        </section>
                    <?php else: ?>
                        <section class="hero-photo">
                            <?php if ($heroPhoto !== ''): ?>
                                <img src="<?php echo h($heroPhoto); ?>" alt="<?php echo h($selectedShoot['titel']); ?>" decoding="async">
                            <?php endif; ?>
                            <div class="hero-panel">
                                <div class="hero-title-row">
                                    <h1 class="hero-title"><?php echo h($selectedShoot['titel']); ?></h1>
                                    <?php if ($heroPhoto !== ''): ?>
                                        <button type="button" class="gallery-open" id="galleryOpen" aria-label="Open foto's van deze shoot" title="Open foto's">
                                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                                <path fill="currentColor" d="M9 4 7.2 6H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-3.2L15 4H9Zm3 13a4.5 4.5 0 1 1 0-9 4.5 4.5 0 0 1 0 9Zm0-2a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z"/>
                                            </svg>
                                        </button>
                                    <?php endif; ?>
                                </div>
                                <?php if ($selectedShoot['categorie'] !== '' || $selectedShoot['lokatie'] !== ''): ?>
                                    <div class="hero-meta">
                                        <?php if ($selectedShoot['categorie'] !== ''): ?>
                                            <div class="hero-pill"><?php echo h($selectedShoot['categorie']); ?></div>
                                        <?php endif; ?>
                                        <?php if ($selectedShoot['lokatie'] !== ''): ?>
                                            <div class="hero-pill"><?php echo h($selectedShoot['lokatie']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </section>
                        <?php if (trim(strip_tags($selectedShoot['oms1'])) !== ''): ?>
                            <?php
                                $shootDescription = cleanRichText($selectedShoot['oms1']);
                                $shootDescriptionPreview = trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode($shootDescription, ENT_QUOTES | ENT_HTML5, 'UTF-8'))) ?? '');
                            ?>
                            <div class="hero-details">
                                <div class="hero-description hero-description-preview"><?php echo h($shootDescriptionPreview); ?></div>
                                <button type="button" class="contact-more" id="shootMoreButton" aria-haspopup="dialog" aria-controls="shootDescriptionDialog">lees meer...</button>
                                <dialog class="contact-dialog" id="shootDescriptionDialog" aria-labelledby="shootDescriptionTitle">
                                    <button type="button" class="contact-dialog-close" id="shootDialogClose" aria-label="Sluiten">&times;</button>
                                    <h2 id="shootDescriptionTitle"><?php echo h($selectedShoot['titel']); ?></h2>
                                    <div class="contact-dialog-content"><?php echo $shootDescription; ?></div>
                                </dialog>
                            </div>
                        <?php endif; ?>
                        <?php if (count($selectedShootPhotos) > 0): ?>
                            <section class="shoot-gallery" aria-label="Foto's van <?php echo h($selectedShoot['titel']); ?>">
                                <?php foreach ($selectedShootPhotos as $photoIndex => $photoSrc): ?>
                                    <button type="button" class="shoot-gallery-item" data-photo-index="<?php echo h((string) $photoIndex); ?>" aria-label="Open foto <?php echo h((string) ($photoIndex + 1)); ?>">
                                        <img src="<?php echo h($photoSrc); ?>" alt="<?php echo h($selectedShoot['titel']); ?> foto <?php echo h((string) ($photoIndex + 1)); ?>" loading="lazy" decoding="async">
                                    </button>
                                <?php endforeach; ?>
                            </section>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($heroPhoto !== ''): ?>
                        <div class="photo-viewer" id="photoViewer" role="dialog" aria-modal="true" aria-label="Foto's van <?php echo h($selectedShoot['titel']); ?>" hidden>
                            <img class="photo-viewer-image" id="photoViewerImage" src="<?php echo h($heroPhoto); ?>" alt="<?php echo h($selectedShoot['titel']); ?>">
                            <button type="button" class="photo-viewer-close" id="photoViewerClose" aria-label="Sluit fotoviewer">&times;</button>
                            <button type="button" class="photo-viewer-nav photo-viewer-prev" id="photoViewerPrevious" aria-label="Vorige foto" <?php echo count($selectedShootPhotos) <= 1 ? 'hidden' : ''; ?>>&#8249;</button>
                            <button type="button" class="photo-viewer-nav photo-viewer-next" id="photoViewerNext" aria-label="Volgende foto" <?php echo count($selectedShootPhotos) <= 1 ? 'hidden' : ''; ?>>&#8250;</button>
                            <div class="photo-viewer-counter" id="photoViewerCounter" aria-live="polite"></div>
                        </div>
                    <?php endif; ?>
                    <script>
                        window.displayPhotos = <?php echo json_encode($selectedShootPhotos, JSON_UNESCAPED_SLASHES); ?>;
                        window.isPortfolioView = <?php echo $portfolioView ? 'true' : 'false'; ?>;
                    </script>
                <?php else: ?>
                    <main class="display-empty">
                        <p>Kies bovenin een shoot om de foto te tonen.</p>
                    </main>
                <?php endif; ?>
            </div>
            <footer class="site-footer">
                &copy; 2026 Haver Fotografie
            </footer>
        </div>
    <?php elseif ($photos): ?>
        <form method="post" action="" enctype="multipart/form-data" class="form-panel">
            <input type="hidden" name="action" value="photos">
            <input type="hidden" name="id" value="<?php echo h($photoId); ?>">
            <input type="hidden" name="MAX_FILE_SIZE" value="20971520">

            <div class="photo-upload-grid">
                <?php for ($i = 1; $i <= $photoFieldCount; $i++): ?>
                    <div class="photo-upload-item">
                        <label for="foto<?php echo $i; ?>">Foto <?php echo $i; ?></label>
                        <?php if (isDisplayableImage($photoData['foto' . $i])): ?>
                            <img src="<?php echo h($photoData['foto' . $i]); ?>" alt="Foto <?php echo $i; ?>" loading="lazy" decoding="async">
                        <?php endif; ?>
                        <input type="file" id="foto<?php echo $i; ?>" name="foto<?php echo $i; ?>" accept="image/png,image/jpeg,image/gif,image/webp">
                    </div>
                <?php endfor; ?>
            </div>

            <div class="photo-page-meta">
                <label for="categorie_foto_nummer">Welke foto zou in aanmerking komen als categorie foto? </label>
                <select id="categorie_foto_nummer" name="categorie_foto_nummer" class="field">
                    <?php for ($i = 0; $i <= $photoFieldCount; $i++): ?>
                        <option value="<?php echo h((string) $i); ?>" <?php echo (int) ($photoData['categorie_foto_nummer'] ?? 0) === $i ? 'selected' : ''; ?>>
                            <?php echo h((string) $i); ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="photo-page-actions">
                <button type="submit" class="primary-button">Foto's opslaan</button>
                <a class="button-link secondary-button" href="?edit=<?php echo h($photoId); ?>">Naar details</a>
                <a class="button-link secondary-button" href="<?php echo h($adminDashboardUrl); ?>">Terug naar lijst</a>
            </div>
        </form>
        <form method="post" action="" class="inline-form" onsubmit="return confirm('Weet je zeker dat je deze shoot wilt verwijderen?');">
            <input type="hidden" name="action" value="delete-shoot">
            <input type="hidden" name="id" value="<?php echo h($photoId); ?>">
            <button type="submit" class="primary-button danger-button">Verwijder shoot</button>
        </form>
    <?php elseif ($edit || $create): ?>
        <?php if ($create): ?>
        <h1>Nieuwe shoot</h1>
        <?php endif; ?>

        <form method="post" action="" class="form-panel">
            <input type="hidden" name="id" value="<?php echo $editId > 0 ? $editId : ''; ?>">

            <div class="wide-field-row">
                <label for="titel">Titel</label>
                <input type="text" id="titel" name="titel" required placeholder="Titel" value="<?php echo h($formData['titel']); ?>" class="field" />
            </div>

            <div class="wide-field-row">
                <label for="categorie">Categorie</label>
                <input type="text" id="categorie" name="categorie" placeholder="Categorie" value="<?php echo h($formData['categorie']); ?>" class="field" />
            </div>

            <div class="wide-field-row">
                <label for="lokatie">Locatie</label>
                <input type="text" id="lokatie" name="lokatie" placeholder="Locatie" value="<?php echo h($formData['lokatie']); ?>" class="field" />
            </div>

            <div class="wide-field-row form-actions">
                <label for="oms1">Omschrijving</label>
                <textarea id="oms1" name="oms1" class="rich-text" rows="8"><?php echo h(cleanRichText($formData['oms1'])); ?></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="primary-button"><?php echo $edit ? 'Opslaan' : 'Opslaan'; ?></button>
                <?php if ($edit && $editId > 0): ?>
                    <a class="button-link secondary-button" href="?photos=<?php echo h($editId); ?>">Naar foto's</a>
                <?php endif; ?>
                <a class="button-link secondary-button" href="<?php echo h($adminDashboardUrl); ?>">Terug naar lijst</a>
            </div>

            <div class="object-grid">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <input type="text" id="object<?php echo $i; ?>" name="object<?php echo $i; ?>" placeholder="Object<?php echo $i; ?>" value="<?php echo h($formData['object' . $i]); ?>" class="field" />
                    <input type="text" id="naam<?php echo $i; ?>" name="naam<?php echo $i; ?>" placeholder="Naam<?php echo $i; ?>" value="<?php echo h($formData['naam' . $i]); ?>" class="field" />
                    <input type="url" id="url<?php echo $i; ?>" name="url<?php echo $i; ?>" placeholder="URL<?php echo $i; ?>" value="<?php echo h($formData['url' . $i]); ?>" class="field" />
                <?php endfor; ?>
            </div>
        </form>
        <?php if ($edit): ?>
        <form method="post" action="" class="inline-form" onsubmit="return confirm('Weet je zeker dat je deze shoot wilt verwijderen?');">
            <input type="hidden" name="action" value="delete-shoot">
            <input type="hidden" name="id" value="<?php echo h($editId); ?>">
            <button type="submit" class="primary-button danger-button">Verwijder shoot</button>
        </form>
        <?php endif; ?>
    <?php else: ?>
    <?php endif; ?>

    <?php if ($message): ?>
        <p style="color: green;"><strong><?php echo h($message); ?></strong></p>
    <?php endif; ?>
    <?php if ($errorMessage): ?>
        <p style="color: #b00020;"><strong><?php echo h($errorMessage); ?></strong></p>
    <?php endif; ?>

    <?php if (!$edit && !$create && !$photos && !$display && !$configScreen && !$visitsScreen): ?>
        <?php if (count($rows) === 0): ?>
            <p>Geen shoots gevonden.</p>
        <?php else: ?>
            <table>
                <colgroup>
                    <col style="width: 34%;">
                    <col style="width: 12%;">
                    <col style="width: 22%;">
                    <col style="width: 12%;">
                    <col style="width: 20%;">
                </colgroup>
                <thead>
                    <tr>
                        <th>Titel</th>
                        <th>Categorie</th>
                        <th>Locatie</th>
                        <th>Prio</th>
                        <th>Acties</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?php echo h($row['titel']); ?></td>
                            <td><?php echo h($row['categorie']); ?></td>
                            <td><?php echo h($row['lokatie']); ?></td>
                            <td>
                                <div class="prio-editor">
                                    <input
                                        class="prio-input"
                                        type="number"
                                        value="<?php echo h($row['prio']); ?>"
                                        data-shoot-id="<?php echo h($row['id']); ?>"
                                        aria-label="Prioriteit van <?php echo h($row['titel']); ?>"
                                    >
                                    <span class="prio-status" aria-live="polite"></span>
                                </div>
                            </td>
                            <td class="table-actions">
                                <a href="?edit=<?php echo $row['id']; ?>">Wijzig</a>
                                |
                                <a href="?photos=<?php echo $row['id']; ?>">Foto's</a>
                                |
                                <form method="post" action="" class="inline-form" onsubmit="return confirm('Weet je zeker dat je deze shoot wilt verwijderen?');">
                                    <input type="hidden" name="action" value="delete-shoot">
                                    <input type="hidden" name="id" value="<?php echo h($row['id']); ?>">
                                    <button type="submit" class="delete-button">Verwijder</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <div class="admin-actions">
            <a class="button-link" href="<?php echo h($adminNewUrl); ?>">Nieuwe shoot</a>
            <a class="button-link secondary-button" href="<?php echo h($adminVisitsUrl); ?>">Bezoeken</a>
            <a class="button-link" href="<?php echo h($publicPath); ?>">Weergave</a>
            <a class="button-link" href="<?php echo h($adminConfigUrl); ?>">Configuratie</a>
            <?php if ($isAdminLoggedIn): ?>
            <form method="post" action="" class="inline-form">
                <input type="hidden" name="action" value="logout">
                <button type="submit" class="primary-button secondary-button">Uitloggen</button>
            </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php if (!$display): ?>
    </div>
    <footer class="site-footer">&copy; 2026 Haver Fotografie</footer>
    <?php endif; ?>
    <?php if (!$display): ?>
    </div>
    <?php endif; ?>
    <script>
        const foto1Input = document.getElementById('foto1');
        const pasteField = document.getElementById('foto1PasteField');
        const clearButton = document.getElementById('clearFoto1');

        function showPhoto(value) {
            pasteField.innerHTML = '';

            if (!value) {
                const hint = document.createElement('p');
                hint.className = 'photo-hint';
                hint.textContent = 'Klik hier en plak een foto met Ctrl+V';
                pasteField.appendChild(hint);
                return;
            }

            const image = document.createElement('img');
            image.src = value;
            image.alt = 'Voorbeeld foto 1';
            pasteField.appendChild(image);
        }

        pasteField?.addEventListener('click', () => pasteField.focus());
        pasteField?.addEventListener('paste', (event) => {
            const item = Array.from(event.clipboardData.items).find((clipboardItem) => clipboardItem.type.startsWith('image/'));

            if (!item) {
                return;
            }

            event.preventDefault();
            const file = item.getAsFile();
            const reader = new FileReader();

            reader.addEventListener('load', () => {
                foto1Input.value = reader.result;
                showPhoto(reader.result);
            });

            reader.readAsDataURL(file);
        });

        clearButton?.addEventListener('click', () => {
            foto1Input.value = '';
            showPhoto('');
            pasteField.focus();
        });

        document.querySelectorAll('.prio-input').forEach((input) => {
            let saveTimer = null;
            const status = input.parentElement.querySelector('.prio-status');

            input.addEventListener('input', () => {
                window.clearTimeout(saveTimer);
                status.className = 'prio-status is-saving';
                status.textContent = '…';
                status.title = 'Bezig met opslaan';

                saveTimer = window.setTimeout(async () => {
                    const formData = new FormData();
                    formData.set('action', 'update-prio');
                    formData.set('id', input.dataset.shootId);
                    formData.set('prio', input.value);

                    try {
                        const response = await fetch(<?php echo json_encode($adminDashboardUrl); ?>, {
                            method: 'POST',
                            body: formData,
                            credentials: 'same-origin'
                        });
                        const result = await response.json();

                        if (!response.ok || !result.ok) {
                            throw new Error(result.message || 'Opslaan mislukt');
                        }

                        status.className = 'prio-status is-saved';
                        status.textContent = '✓';
                        status.title = 'Opgeslagen';
                    } catch (error) {
                        status.className = 'prio-status is-error';
                        status.textContent = '!';
                        status.title = error.message || 'Opslaan mislukt';
                    }
                }, 500);
            });
        });

        if (window.tinymce) {
            tinymce.init({
                selector: 'textarea.rich-text',
                menubar: false,
                plugins: 'lists link',
                toolbar: 'undo redo | bold italic underline | bullist numlist | link removeformat',
                branding: false,
                height: 260,
                setup: (editor) => {
                    editor.on('change keyup', () => editor.save());
                }
            });
        }

        const heroPhoto = document.getElementById('displayHeroPhoto');
        const heroPhotoCounter = document.getElementById('heroPhotoCounter');
        const portfolioPrevious = document.getElementById('portfolioPrevious');
        const portfolioNext = document.getElementById('portfolioNext');
        const displayHeader = document.querySelector('.display-header');
        const displayMenuToggle = document.getElementById('displayMenuToggle');
        const displayMenuPanel = document.getElementById('displayMenuPanel');
        const contactMoreButton = document.getElementById('contactMoreButton');
        const contactDescriptionDialog = document.getElementById('contactDescriptionDialog');
        const contactDialogClose = document.getElementById('contactDialogClose');
        const shootMoreButton = document.getElementById('shootMoreButton');
        const shootDescriptionDialog = document.getElementById('shootDescriptionDialog');
        const shootDialogClose = document.getElementById('shootDialogClose');
        const galleryOpen = document.getElementById('galleryOpen');
        const photoViewer = document.getElementById('photoViewer');
        const photoViewerImage = document.getElementById('photoViewerImage');
        const photoViewerClose = document.getElementById('photoViewerClose');
        const photoViewerPrevious = document.getElementById('photoViewerPrevious');
        const photoViewerNext = document.getElementById('photoViewerNext');
        const photoViewerCounter = document.getElementById('photoViewerCounter');
        const galleryItems = document.querySelectorAll('.shoot-gallery-item');
        const displayPhotos = Array.isArray(window.displayPhotos) ? window.displayPhotos : [];
        let slideshowTimer = null;
        let slideshowIndex = 0;
        let photoViewerIndex = 0;
        let photoViewerTrigger = galleryOpen;

        function setDisplayMenuState(isOpen) {
            if (!displayHeader || !displayMenuToggle || !displayMenuPanel) {
                return;
            }

            displayHeader.classList.toggle('is-menu-open', isOpen);
            displayMenuToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            displayMenuToggle.setAttribute('aria-label', isOpen ? 'Sluit navigatiemenu' : 'Open navigatiemenu');
        }

        displayMenuToggle?.addEventListener('click', () => {
            setDisplayMenuState(!displayHeader?.classList.contains('is-menu-open'));
        });

        displayMenuPanel?.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 720) {
                    setDisplayMenuState(false);
                }
            });
        });

        contactMoreButton?.addEventListener('click', () => {
            contactDescriptionDialog?.showModal();
        });

        contactDialogClose?.addEventListener('click', () => {
            contactDescriptionDialog?.close();
        });

        contactDescriptionDialog?.addEventListener('click', (event) => {
            if (event.target === contactDescriptionDialog) {
                contactDescriptionDialog.close();
            }
        });

        shootMoreButton?.addEventListener('click', () => {
            shootDescriptionDialog?.showModal();
        });

        shootDialogClose?.addEventListener('click', () => {
            shootDescriptionDialog?.close();
        });

        shootDescriptionDialog?.addEventListener('click', (event) => {
            if (event.target === shootDescriptionDialog) {
                shootDescriptionDialog.close();
            }
        });

        function setPhotoViewerPhoto(index) {
            if (!photoViewerImage || !displayPhotos.length) {
                return;
            }

            photoViewerIndex = (index + displayPhotos.length) % displayPhotos.length;
            photoViewerImage.src = displayPhotos[photoViewerIndex];
            photoViewerImage.alt = `Foto ${photoViewerIndex + 1} van ${displayPhotos.length}`;

            if (photoViewerCounter) {
                photoViewerCounter.textContent = `${photoViewerIndex + 1} / ${displayPhotos.length}`;
            }
        }

        function closePhotoViewer() {
            if (!photoViewer) {
                return;
            }

            photoViewer.hidden = true;
            photoViewerTrigger?.focus();
        }

        function openPhotoViewer(index, trigger) {
            if (!photoViewer || !displayPhotos.length) {
                return;
            }

            photoViewerTrigger = trigger;
            setPhotoViewerPhoto(index);
            photoViewer.hidden = false;
            photoViewerClose?.focus();
        }

        galleryOpen?.addEventListener('click', () => {
            openPhotoViewer(slideshowIndex, galleryOpen);
        });

        galleryItems.forEach((item) => {
            item.addEventListener('click', () => {
                openPhotoViewer(Number(item.dataset.photoIndex), item);
            });
        });

        photoViewerClose?.addEventListener('click', closePhotoViewer);
        photoViewerPrevious?.addEventListener('click', () => setPhotoViewerPhoto(photoViewerIndex - 1));
        photoViewerNext?.addEventListener('click', () => setPhotoViewerPhoto(photoViewerIndex + 1));

        window.addEventListener('resize', () => {
            if (window.innerWidth > 720) {
                setDisplayMenuState(false);
            }
        });

        function updateHeroCounter() {
            if (!heroPhotoCounter || !displayPhotos.length) {
                return;
            }

            heroPhotoCounter.textContent = `${slideshowIndex + 1} / ${displayPhotos.length}`;
        }

        function setHeroPhoto(index) {
            if (!heroPhoto || !displayPhotos.length) {
                return;
            }

            slideshowIndex = (index + displayPhotos.length) % displayPhotos.length;
            heroPhoto.classList.add('is-fading');

            window.setTimeout(() => {
                heroPhoto.src = displayPhotos[slideshowIndex];
                heroPhoto.alt = `Foto ${slideshowIndex + 1} van ${displayPhotos.length}`;
                heroPhoto.addEventListener('load', () => {
                    heroPhoto.classList.remove('is-fading');
                }, { once: true });
                updateHeroCounter();
            }, 150);
        }

        function stopHeroSlideshow() {
            if (slideshowTimer === null) {
                return;
            }

            window.clearInterval(slideshowTimer);
            slideshowTimer = null;
        }

        function startHeroSlideshow() {
            updateHeroCounter();

            if (!heroPhoto || !window.isPortfolioView || displayPhotos.length <= 1 || slideshowTimer !== null) {
                return;
            }

            slideshowTimer = window.setInterval(() => {
                setHeroPhoto(slideshowIndex + 1);
            }, 2800);
        }

        function restartHeroSlideshow() {
            stopHeroSlideshow();
            startHeroSlideshow();
        }

        startHeroSlideshow();

        portfolioPrevious?.addEventListener('click', () => {
            if (!window.isPortfolioView || displayPhotos.length <= 1) {
                return;
            }

            setHeroPhoto(slideshowIndex - 1);
            restartHeroSlideshow();
        });

        portfolioNext?.addEventListener('click', () => {
            if (!window.isPortfolioView || displayPhotos.length <= 1) {
                return;
            }

            setHeroPhoto(slideshowIndex + 1);
            restartHeroSlideshow();
        });

        document.addEventListener('keydown', (event) => {
            if (photoViewer && !photoViewer.hidden) {
                if (event.key === 'Escape') {
                    closePhotoViewer();
                } else if (event.key === 'ArrowLeft' && displayPhotos.length > 1) {
                    setPhotoViewerPhoto(photoViewerIndex - 1);
                } else if (event.key === 'ArrowRight' && displayPhotos.length > 1) {
                    setPhotoViewerPhoto(photoViewerIndex + 1);
                }

                return;
            }

            if (event.key === 'Escape' && displayHeader?.classList.contains('is-menu-open')) {
                setDisplayMenuState(false);
                displayMenuToggle?.focus();
                return;
            }

            if (!window.isPortfolioView || !heroPhoto || displayPhotos.length <= 1) {
                return;
            }

            if (event.key === 'ArrowLeft') {
                setHeroPhoto(slideshowIndex - 1);
                restartHeroSlideshow();
            } else if (event.key === 'ArrowRight') {
                setHeroPhoto(slideshowIndex + 1);
                restartHeroSlideshow();
            }
        });

    </script>
</body>
</html>
