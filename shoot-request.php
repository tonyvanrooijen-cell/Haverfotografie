<?php
declare(strict_types=1);

function shootRequestInput(array $input): array
{
    $data = [];
    foreach (['name', 'email', 'phone', 'date', 'time', 'description'] as $field) {
        $data[$field] = is_string($input[$field] ?? null) ? trim($input[$field], " \t\n\r\v") : '';
    }
    return $data;
}

function validateShootRequest(array $data): string
{
    $data = shootRequestInput($data);
    foreach ($data as $value) {
        if ($value === '') {
            return 'Vul alle velden in voordat je de aanvraag verstuurt.';
        }
    }
    if ($data['name'] === '' || strlen($data['name']) > 150 || preg_match('/[\r\n]/', $data['name'])) {
        return 'Vul je naam in (maximaal 150 tekens).';
    }
    if (strlen($data['email']) > 254 || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        return 'Vul een geldig e-mailadres in.';
    }
    if (strlen($data['phone']) > 40 || !preg_match('/^\+?[0-9 ()\.\-]+$/D', $data['phone']) || strlen(preg_replace('/\D/', '', $data['phone'])) < 7) {
        return 'Vul een geldig telefoonnummer in.';
    }
    if (!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $data['time'])) {
        return 'Vul een geldige tijd in (uren en minuten).';
    }
    $timezone = new DateTimeZone('Europe/Amsterdam');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $data['date'])) {
        return 'Kies een geldige datum vanaf vandaag.';
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $data['date'], $timezone);
    if (!$date || $date->format('Y-m-d') !== $data['date'] || $date < new DateTimeImmutable('today', $timezone)) {
        return 'Kies een geldige datum vanaf vandaag.';
    }
    if ($data['description'] === '' || strlen($data['description']) > 4000) {
        return 'Geef een korte omschrijving (maximaal 4000 tekens).';
    }
    return '';
}

function ensureShootRequestTable(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS HaverFotografieRequests (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        submission_token CHAR(64) NOT NULL UNIQUE,
        name VARCHAR(150) NOT NULL,
        email VARCHAR(254) NOT NULL,
        phone VARCHAR(40) NOT NULL,
        requested_date DATE NOT NULL,
        requested_time TIME NULL,
        description TEXT NOT NULL,
        created_at DATETIME NOT NULL,
        mail_status VARCHAR(16) NOT NULL DEFAULT 'pending'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    if (!$pdo->query("SHOW COLUMNS FROM HaverFotografieRequests LIKE 'requested_time'")->fetch()) {
        try {
            $pdo->exec('ALTER TABLE HaverFotografieRequests ADD COLUMN requested_time TIME NULL AFTER requested_date');
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? null) !== 1060) { throw $e; }
        }
    }
}

function saveShootRequest(PDO $pdo, array $data, string $token): int
{
    $data = shootRequestInput($data);
    if (validateShootRequest($data) !== '') {
        throw new InvalidArgumentException('Incomplete or invalid shoot request.');
    }
    $stmt = $pdo->prepare('INSERT INTO HaverFotografieRequests
        (submission_token, name, email, phone, requested_date, requested_time, description, created_at)
        VALUES (:token, :name, :email, :phone, :date, :time, :description, :created_at)');
    $stmt->execute($data + ['token' => $token, 'created_at' => gmdate('Y-m-d H:i:s')]);
    return (int) $pdo->lastInsertId();
}

function deleteShootRequest(PDO $pdo, int $id): bool
{
    $stmt = $pdo->prepare('DELETE FROM HaverFotografieRequests WHERE id = :id');
    $stmt->execute(['id' => $id]);
    return $stmt->rowCount() === 1;
}

function validateMailSettings(array $settings): string
{
    foreach (['smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption', 'smtp_from'] as $field) {
        if (!is_string($settings[$field] ?? null) || strlen($settings[$field]) > ($field === 'smtp_password' ? 1024 : 254)) {
            return 'Controleer de ingevulde mailinstellingen.';
        }
    }
    $host = trim($settings['smtp_host']);
    if ($host !== '' && !filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
        return 'Vul een geldige mailserver in, bijvoorbeeld smtp.voorbeeld.nl.';
    }
    $port = trim($settings['smtp_port']);
    if ($port !== '' && !filter_var($port, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 65535]])) {
        return 'Kies een poort tussen 1 en 65535.';
    }
    $from = trim($settings['smtp_from']);
    if ($from !== '' && !filter_var($from, FILTER_VALIDATE_EMAIL)) { return 'Vul een geldig afzenderadres in.'; }
    if (!in_array($settings['smtp_encryption'], ['tls', 'ssl', 'none'], true)) { return 'Kies een geldige beveiliging.'; }
    return '';
}

function sendShootRequest(array $data, string $recipient, array $settings = []): void
{
    require_once __DIR__ . '/vendor/autoload.php';
    $setting = static fn (string $key, string $default = ''): string => ($settings[strtolower($key)] ?? '') !== '' ? $settings[strtolower($key)] : (getenv($key) ?: $default);
    $host = $setting('SMTP_HOST');
    $from = $setting('SMTP_FROM');
    if ($host === '' || !filter_var($from, FILTER_VALIDATE_EMAIL) || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Shoot request mail is not configured.');
    }
    $encryption = $setting('SMTP_ENCRYPTION', 'tls');
    if (!in_array($encryption, ['tls', 'ssl', 'none'], true)) {
        throw new RuntimeException('Invalid SMTP encryption setting.');
    }
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $host;
    $mail->Port = (int) $setting('SMTP_PORT', '587');
    $mail->Username = $setting('SMTP_USERNAME');
    $mail->Password = $setting('SMTP_PASSWORD');
    $mail->SMTPAuth = $mail->Username !== '';
    $mail->SMTPSecure = $encryption === 'none' ? '' : $encryption;
    $mail->SMTPAutoTLS = $encryption !== 'none';
    $mail->Timeout = 15;
    $mail->CharSet = 'UTF-8';
    $mail->setFrom($from, 'Haver Fotografie');
    $mail->addAddress($recipient);
    $mail->addReplyTo($data['email'], $data['name']);
    $mail->Subject = 'Nieuwe shootaanvraag - ' . $data['date'];
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $data['date'])->format('d-m-Y');
    $mail->Body = "Nieuwe aanvraag via IK WIL OOK\n\n"
        . "Naam: {$data['name']}\nE-mailadres: {$data['email']}\nTelefoonnummer: {$data['phone']}\nGewenste datum: {$date}\n\n"
        . "Gewenste tijd: {$data['time']}\n\n"
        . "Omschrijving:\n{$data['description']}\n\n"
        . "Dit is een aanvraag; de datum is nog niet bevestigd.\n"
        . "Beantwoord deze mail om contact op te nemen met de aanvrager.\n";
    $mail->send();
}
