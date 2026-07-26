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

$message = '';
$errorMessage = '';
$edit = false;
$admin = $isAdminPath || isset($_GET['admin']) || isset($_GET['new']) || isset($_GET['edit']) || isset($_GET['photos']) || isset($_GET['config']);
$create = isset($_GET['new']);
$photos = false;
$display = !$admin || isset($_GET['view']);
$configScreen = isset($_GET['config']);
$visitsScreen = isset($_GET['visits']);
$editId = 0;
$photoId = 0;
$photoData = [];
$selectedShoot = null;
$shootGroups = [];
$hiddenShoot = null;
$hiddenLogoSrc = '';
$authError = '';
$visitSummaryRows = [];
$recentVisits = [];
$todayVisitCount = 0;

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
    'object1' => '',
    'naam1' => '',
    'url1' => '',
    'object2' => '',
    'naam2' => '',
    'url2' => '',
    'object3' => '',
    'naam3' => '',
    'url3' => '',
    'object4' => '',
    'naam4' => '',
    'url4' => '',
    'object5' => '',
    'naam5' => '',
    'url5' => ''
];

$detailFields = [
    'titel',
    'categorie',
    'lokatie',
    'foto1',
    'oms1',
    'object1',
    'naam1',
    'url1',
    'object2',
    'naam2',
    'url2',
    'object3',
    'naam3',
    'url3',
    'object4',
    'naam4',
    'url4',
    'object5',
    'naam5',
    'url5',
];

$detailSelectColumns = 'id, titel, categorie, lokatie, foto1, foto2, foto3, foto4, foto5, oms1, object1, naam1, url1, object2, naam2, url2, object3, naam3, url3, object4, naam4, url4, object5, naam5, url5';

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

    return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($_FILES[$field]['tmp_name']));
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
                $photoStmt = $pdo->prepare('SELECT foto1, foto2, foto3, foto4, foto5 FROM HaverFotografie WHERE id = :id');
                $photoStmt->execute(['id' => $photoId]);
                $existingPhotos = $photoStmt->fetch();

                if ($existingPhotos) {
                    $photoParams = [
                        'id' => $photoId,
                        'oms1' => cleanRichText($_POST['oms1'] ?? ''),
                    ];
                    for ($i = 1; $i <= 5; $i++) {
                        $fieldName = 'foto' . $i;
                        $uploadError = null;
                        $uploadedPhoto = uploadedImageToDataUrl($fieldName, $uploadError);

                        if ($uploadError !== null) {
                            $errorMessage .= 'Foto ' . $i . ': ' . $uploadError . '. ';
                        }

                        $photoParams['foto' . $i] = $uploadedPhoto ?? $existingPhotos['foto' . $i];
                    }

                    if ($errorMessage === '') {
                        $updatePhotosStmt = $pdo->prepare(
                            'UPDATE HaverFotografie SET foto1 = :foto1, foto2 = :foto2, foto3 = :foto3, foto4 = :foto4, foto5 = :foto5, oms1 = :oms1 WHERE id = :id'
                        );
                        $updatePhotosStmt->execute($photoParams);
                        header('Location: ' . $publicPath . '?shoot=' . $photoId);
                        exit;
                    }

                    $photos = true;
                    $photoDataStmt = $pdo->prepare('SELECT id, titel, foto1, foto2, foto3, foto4, foto5, oms1 FROM HaverFotografie WHERE id = :id');
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
                        'UPDATE HaverFotografie SET titel = :titel, categorie = :categorie, lokatie = :lokatie, foto1 = :foto1, oms1 = :oms1, object1 = :object1, naam1 = :naam1, url1 = :url1, object2 = :object2, naam2 = :naam2, url2 = :url2, object3 = :object3, naam3 = :naam3, url3 = :url3, object4 = :object4, naam4 = :naam4, url4 = :url4, object5 = :object5, naam5 = :naam5, url5 = :url5 WHERE id = :id'
                    );
                    $params = [];
                    foreach ($detailFields as $field) {
                        $params[$field] = $formData[$field];
                    }
                    $params['id'] = $editId;
                    $updateStmt->execute($params);
                    header('Location: ' . $publicPath . '?shoot=' . $editId);
                    exit;
                } else {
                    $insertStmt = $pdo->prepare(
                        'INSERT INTO HaverFotografie (titel, categorie, lokatie, foto1, oms1, object1, naam1, url1, object2, naam2, url2, object3, naam3, url3, object4, naam4, url4, object5, naam5, url5) VALUES (:titel, :categorie, :lokatie, :foto1, :oms1, :object1, :naam1, :url1, :object2, :naam2, :url2, :object3, :naam3, :url3, :object4, :naam4, :url4, :object5, :naam5, :url5)'
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
        $editStmt = $pdo->prepare('SELECT id, titel, categorie, lokatie, foto1, oms1, object1, naam1, url1, object2, naam2, url2, object3, naam3, url3, object4, naam4, url4, object5, naam5, url5 FROM HaverFotografie WHERE id = :id');
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
        $photoStmt = $pdo->prepare('SELECT id, titel, foto1, foto2, foto3, foto4, foto5, oms1 FROM HaverFotografie WHERE id = :id');
        $photoStmt->execute(['id' => $photoId]);
        $photoData = $photoStmt->fetch();
        $photos = (bool) $photoData;
    }

    if (isset($_GET['deleted'])) {
        $message = 'Shoot verwijderd.';
    }

    if ($display) {
        $displayStmt = $pdo->query(
            'SELECT id, titel, categorie, prio
             FROM HaverFotografie
             ORDER BY COALESCE(prio, 2147483647), categorie, titel'
        );
        $displayShoots = $displayStmt->fetchAll();
        $visibleDisplayShoots = [];
        foreach ($displayShoots as $shoot) {
            if (strtolower(trim($shoot['categorie'])) === 'hidden') {
                $hiddenShoot ??= $shoot;
                continue;
            }

            $category = $shoot['categorie'] !== '' ? $shoot['categorie'] : 'Zonder categorie';
            $shootGroups[$category][] = $shoot;
            $visibleDisplayShoots[] = $shoot;
        }

        if ($hiddenShoot) {
            $hiddenShootStmt = $pdo->prepare('SELECT foto1, foto2, foto3, foto4, foto5 FROM HaverFotografie WHERE id = :id');
            $hiddenShootStmt->execute(['id' => (int) $hiddenShoot['id']]);
            $hiddenShootPhotos = $hiddenShootStmt->fetch() ?: [];

            for ($i = 5; $i >= 1; $i--) {
                $candidate = $hiddenShootPhotos['foto' . $i] ?? '';

                if (isDisplayableImage($candidate)) {
                    $hiddenLogoSrc = $candidate;
                    break;
                }
            }
        }

        if (isset($_GET['shoot']) && ctype_digit($_GET['shoot'])) {
            $selectedStmt = $pdo->prepare("SELECT $detailSelectColumns FROM HaverFotografie WHERE id = :id");
            $selectedStmt->execute(['id' => (int) $_GET['shoot']]);
            $selectedShoot = $selectedStmt->fetch() ?: null;
        } elseif (count($visibleDisplayShoots) > 0) {
            $randomShoot = $visibleDisplayShoots[random_int(0, count($visibleDisplayShoots) - 1)];
            $selectedStmt = $pdo->prepare("SELECT $detailSelectColumns FROM HaverFotografie WHERE id = :id");
            $selectedStmt->execute(['id' => (int) $randomShoot['id']]);
            $selectedShoot = $selectedStmt->fetch() ?: null;
        }

        if ($selectedShoot) {
            logShootVisit($pdo, (int) $selectedShoot['id']);
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
            background: var(--display-background);
            color: var(--display-text);
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
            grid-template-columns: 1fr;
            max-width: 520px;
        }
        .photo-upload-item {
            border: 1px solid #ccc;
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
            display: flex;
            flex-shrink: 0;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .admin-header-copy {
            color: #111111;
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
        }
        .admin-header-copy .admin-brand {
            color: #111111;
        }
        .admin-header-note {
            color: #111111;
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
        }
        .display-header {
            align-items: center;
            background: var(--display-header);
            color: var(--display-overlay-text);
            display: flex;
            flex-shrink: 0;
            flex-wrap: wrap;
            gap: 1rem;
            padding: 1rem 1.5rem;
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
        .category-menu {
            position: relative;
        }
        .category-menu summary {
            cursor: pointer;
            list-style: none;
            padding: 0.35rem 0.1rem;
        }
        .category-menu summary::-webkit-details-marker {
            display: none;
        }
        .category-menu summary::after {
            content: "▾";
            display: inline-block;
            font-size: 0.8rem;
            margin-left: 0.35rem;
        }
        .category-options {
            background: var(--display-background);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.18);
            color: var(--display-text);
            font-family: var(--display-font);
            min-width: 190px;
            padding: 0.35rem;
            position: absolute;
            top: 100%;
            z-index: 20;
        }
        .category-options a {
            color: var(--display-text);
            display: block;
            padding: 0.45rem 0.55rem;
            text-decoration: none;
        }
        .category-options a:hover {
            background: rgba(0, 0, 0, 0.08);
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
        .display-empty {
            flex: 1;
            padding: 3rem 1.5rem;
        }
        .display-content {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
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
            flex-shrink: 0;
            margin-top: 0;
            padding: 1rem 0 0;
        }
        @media (max-width: 720px) {
            .display-header {
                align-items: flex-start;
            }
            .display-brand {
                margin-right: 0;
            }
            .display-brand img {
                max-height: 34px;
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
            .gallery-open {
                height: 2.5rem;
                width: 2.5rem;
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
                <?php if ($edit): ?>
                <h1 class="admin-header-note">Pas hier de gegevens van de shoot aan.</h1>
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
        <div class="display-page" style="--display-font: <?php echo h($configValues['font_family']); ?>; --display-background: <?php echo h($configValues['background_color']); ?>; --display-header: <?php echo h($configValues['header_color']); ?>; --display-text: <?php echo h($configValues['text_color']); ?>; --display-link: <?php echo h($configValues['link_color']); ?>; --display-overlay-text: <?php echo h($configValues['overlay_text_color']); ?>; --display-overlay-background: <?php echo h($configValues['overlay_background']); ?>;">
            <header class="display-header">
                <a href="<?php echo h($isAdminLoggedIn ? $adminDashboardUrl : ($hiddenShoot ? $publicPath . '?shoot=' . $hiddenShoot['id'] : $publicPath)); ?>" class="display-brand" aria-label="<?php echo h($isAdminLoggedIn ? 'Ga naar beheer' : 'Toon hidden shoot'); ?>">
                    <img src="<?php echo h($hiddenLogoSrc !== '' ? $hiddenLogoSrc : $logoPath); ?>" alt="HaverFotografie">
                </a>
                <?php foreach ($shootGroups as $category => $shoots): ?>
                    <details class="category-menu">
                        <summary><?php echo h($category); ?></summary>
                        <div class="category-options">
                            <?php foreach ($shoots as $shoot): ?>
                                <a href="<?php echo h($publicPath . '?shoot=' . $shoot['id']); ?>">
                                    <?php echo h($shoot['titel']); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </details>
                <?php endforeach; ?>
            </header>
            <div class="display-content">
                <?php if ($selectedShoot): ?>
                    <?php
                        $displayPhotos = [];
                        $hideLogoInShoot = $hiddenShoot && (int) $selectedShoot['id'] === (int) $hiddenShoot['id'] && $hiddenLogoSrc !== '';
                        for ($i = 1; $i <= 5; $i++) {
                            $photoValue = $selectedShoot['foto' . $i];

                            if (!isDisplayableImage($photoValue)) {
                                continue;
                            }

                            if ($hideLogoInShoot && $photoValue === $hiddenLogoSrc) {
                                continue;
                            }

                            $displayPhotos[] = $photoValue;
                        }
                        $heroPhoto = $displayPhotos[0] ?? '';
                    ?>
                    <section class="hero-photo">
                        <?php if ($heroPhoto !== ''): ?>
                            <img id="displayHeroPhoto" src="<?php echo h($heroPhoto); ?>" alt="<?php echo h($selectedShoot['titel']); ?>">
                        <?php endif; ?>
                        <div class="hero-panel">
                            <div class="hero-title-row">
                                <h1 class="hero-title"><?php echo h($selectedShoot['titel']); ?></h1>
                                <?php if ($heroPhoto !== ''): ?>
                                    <button type="button" class="gallery-open" id="openPhotoViewer" aria-label="Bekijk alle foto's van deze shoot op volledig scherm" title="Bekijk alle foto's">
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
                        <div class="hero-details">
                            <div class="hero-description"><?php echo cleanRichText($selectedShoot['oms1']); ?></div>
                        </div>
                    <?php endif; ?>
                    <script>
                        window.displayPhotos = <?php echo json_encode($displayPhotos, JSON_UNESCAPED_SLASHES); ?>;
                    </script>
                    <?php if ($heroPhoto !== ''): ?>
                        <div class="photo-viewer" id="photoViewer" role="dialog" aria-modal="true" aria-label="Foto's van <?php echo h($selectedShoot['titel']); ?>" hidden>
                            <button type="button" class="photo-viewer-close" id="closePhotoViewer" aria-label="Sluiten">&times;</button>
                            <button type="button" class="photo-viewer-nav photo-viewer-prev" id="previousPhoto" aria-label="Vorige foto">&#8249;</button>
                            <img class="photo-viewer-image" id="photoViewerImage" src="" alt="">
                            <button type="button" class="photo-viewer-nav photo-viewer-next" id="nextPhoto" aria-label="Volgende foto">&#8250;</button>
                            <div class="photo-viewer-counter" id="photoViewerCounter" aria-live="polite"></div>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <main class="display-empty">
                        <p>Kies bovenin een shoot om de foto te tonen.</p>
                    </main>
                <?php endif; ?>
            </div>
            <footer class="site-footer">
                &copy; 2026 Haver Fotografie<?php if ($hiddenShoot): ?>, neem contact met mij op, door <a href="<?php echo h($publicPath . '?shoot=' . $hiddenShoot['id']); ?>">hier te klikken</a>.<?php endif; ?>
            </footer>
        </div>
    <?php elseif ($photos): ?>
        <h1>Foto's uploaden</h1>
        <p><a href="<?php echo h($adminDashboardUrl); ?>">Terug naar de lijst</a></p>
        <h2><?php echo h($photoData['titel']); ?></h2>

        <form method="post" action="" enctype="multipart/form-data" class="form-panel">
            <input type="hidden" name="action" value="photos">
            <input type="hidden" name="id" value="<?php echo h($photoId); ?>">
            <input type="hidden" name="MAX_FILE_SIZE" value="20971520">

            <div class="photo-upload-grid">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <div class="photo-upload-item">
                        <label for="foto<?php echo $i; ?>">Foto <?php echo $i; ?></label>
                        <?php if (isDisplayableImage($photoData['foto' . $i])): ?>
                            <img src="<?php echo h($photoData['foto' . $i]); ?>" alt="Foto <?php echo $i; ?>">
                        <?php endif; ?>
                        <input type="file" id="foto<?php echo $i; ?>" name="foto<?php echo $i; ?>" accept="image/png,image/jpeg,image/gif,image/webp">
                    </div>
                <?php endfor; ?>
            </div>

            <div class="form-actions">
                <label for="oms1">Omschrijving</label><br>
                <textarea id="oms1" name="oms1" class="rich-text" rows="8"><?php echo h(cleanRichText($photoData['oms1'])); ?></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="primary-button">Foto's opslaan</button>
                <a href="<?php echo h($adminDashboardUrl); ?>" style="margin-left:1rem;">Annuleer</a>
            </div>
        </form>
        <form method="post" action="" class="inline-form" onsubmit="return confirm('Weet je zeker dat je deze shoot wilt verwijderen?');">
            <input type="hidden" name="action" value="delete-shoot">
            <input type="hidden" name="id" value="<?php echo h($photoId); ?>">
            <button type="submit" class="primary-button danger-button">Verwijder shoot</button>
        </form>
    <?php elseif ($edit || $create): ?>
        <h1><?php echo $edit ? 'Shoot wijzigen' : 'Nieuwe shoot'; ?></h1>
        <p><a href="<?php echo h($adminDashboardUrl); ?>">Terug naar de lijst</a></p>

        <form method="post" action="" class="form-panel">
            <input type="hidden" name="id" value="<?php echo $editId > 0 ? $editId : ''; ?>">
            <input type="hidden" name="foto1" value="<?php echo h($formData['foto1']); ?>">

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

            <div class="object-grid">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <input type="text" id="object<?php echo $i; ?>" name="object<?php echo $i; ?>" placeholder="Object<?php echo $i; ?>" value="<?php echo h($formData['object' . $i]); ?>" class="field" />
                    <input type="text" id="naam<?php echo $i; ?>" name="naam<?php echo $i; ?>" placeholder="Naam<?php echo $i; ?>" value="<?php echo h($formData['naam' . $i]); ?>" class="field" />
                    <input type="url" id="url<?php echo $i; ?>" name="url<?php echo $i; ?>" placeholder="URL<?php echo $i; ?>" value="<?php echo h($formData['url' . $i]); ?>" class="field" />
                <?php endfor; ?>
            </div>

            <div class="wide-field-row form-actions">
                <label for="oms1">Omschrijving</label>
                <textarea id="oms1" name="oms1" class="rich-text" rows="8"><?php echo h(cleanRichText($formData['oms1'])); ?></textarea>
            </div>

            <div class="form-actions">
                <button type="submit" class="primary-button"><?php echo $edit ? 'Wijzig shoot' : 'Voeg shoot toe'; ?></button>
                <a href="<?php echo h($adminDashboardUrl); ?>" style="margin-left:1rem;">Annuleer</a>
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

        document.querySelectorAll('.category-menu').forEach((menu) => {
            menu.addEventListener('toggle', () => {
                if (!menu.open) {
                    return;
                }

                document.querySelectorAll('.category-menu').forEach((otherMenu) => {
                    if (otherMenu !== menu) {
                        otherMenu.removeAttribute('open');
                    }
                });
            });
        });

        document.addEventListener('click', (event) => {
            if (event.target.closest('.category-menu')) {
                return;
            }

            document.querySelectorAll('.category-menu[open]').forEach((menu) => {
                menu.removeAttribute('open');
            });
        });

        const heroPhoto = document.getElementById('displayHeroPhoto');
        let slideshowTimer = null;
        let slideshowIndex = 0;

        function startHeroSlideshow() {
            if (!heroPhoto || !Array.isArray(window.displayPhotos) || window.displayPhotos.length <= 1 || slideshowTimer !== null) {
                return;
            }

            slideshowIndex = 0;
            slideshowTimer = window.setInterval(() => {
                slideshowIndex = (slideshowIndex + 1) % window.displayPhotos.length;
                heroPhoto.classList.add('is-fading');

                window.setTimeout(() => {
                    heroPhoto.src = window.displayPhotos[slideshowIndex];
                    heroPhoto.addEventListener('load', () => {
                        heroPhoto.classList.remove('is-fading');
                    }, { once: true });
                }, 900);
            }, 5000);
        }

        startHeroSlideshow();

        const photoViewer = document.getElementById('photoViewer');
        const openPhotoViewer = document.getElementById('openPhotoViewer');
        const closePhotoViewer = document.getElementById('closePhotoViewer');
        const previousPhoto = document.getElementById('previousPhoto');
        const nextPhoto = document.getElementById('nextPhoto');
        const photoViewerImage = document.getElementById('photoViewerImage');
        const photoViewerCounter = document.getElementById('photoViewerCounter');
        let viewerIndex = 0;

        function showViewerPhoto(index) {
            const photos = window.displayPhotos || [];
            if (!photos.length) {
                return;
            }

            viewerIndex = (index + photos.length) % photos.length;
            photoViewerImage.src = photos[viewerIndex];
            photoViewerImage.alt = `Foto ${viewerIndex + 1} van ${photos.length}`;
            photoViewerCounter.textContent = `${viewerIndex + 1} / ${photos.length}`;
            previousPhoto.hidden = photos.length <= 1;
            nextPhoto.hidden = photos.length <= 1;
        }

        function openViewer() {
            if (!photoViewer || !window.displayPhotos?.length) {
                return;
            }

            showViewerPhoto(slideshowIndex);
            photoViewer.hidden = false;
            document.body.style.overflow = 'hidden';
            closePhotoViewer.focus();
        }

        function closeViewer() {
            if (!photoViewer || photoViewer.hidden) {
                return;
            }

            photoViewer.hidden = true;
            photoViewerImage.src = '';
            document.body.style.overflow = '';
            openPhotoViewer.focus();
        }

        openPhotoViewer?.addEventListener('click', openViewer);
        closePhotoViewer?.addEventListener('click', closeViewer);
        previousPhoto?.addEventListener('click', () => showViewerPhoto(viewerIndex - 1));
        nextPhoto?.addEventListener('click', () => showViewerPhoto(viewerIndex + 1));
        photoViewer?.addEventListener('click', (event) => {
            if (event.target === photoViewer) {
                closeViewer();
            }
        });

        document.addEventListener('keydown', (event) => {
            if (!photoViewer || photoViewer.hidden) {
                return;
            }

            if (event.key === 'Escape') {
                closeViewer();
            } else if (event.key === 'ArrowLeft') {
                showViewerPhoto(viewerIndex - 1);
            } else if (event.key === 'ArrowRight') {
                showViewerPhoto(viewerIndex + 1);
            }
        });

    </script>
</body>
</html>
