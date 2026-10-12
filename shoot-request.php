<?php
declare(strict_types=1);

function shootRequestInput(array $input): array
{
    $data = [];
    foreach (['name', 'email', 'phone', 'date', 'description'] as $field) {
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
        description TEXT NOT NULL,
        created_at DATETIME NOT NULL,
        mail_status VARCHAR(16) NOT NULL DEFAULT 'pending'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function saveShootRequest(PDO $pdo, array $data, string $token): int
{
    $data = shootRequestInput($data);
    if (validateShootRequest($data) !== '') {
        throw new InvalidArgumentException('Incomplete or invalid shoot request.');
    }
    $stmt = $pdo->prepare('INSERT INTO HaverFotografieRequests
        (submission_token, name, email, phone, requested_date, description, created_at)
        VALUES (:token, :name, :email, :phone, :date, :description, :created_at)');
    $stmt->execute($data + ['token' => $token, 'created_at' => gmdate('Y-m-d H:i:s')]);
    return (int) $pdo->lastInsertId();
}

function sendShootRequest(array $data, string $recipient): void
{
    require_once __DIR__ . '/vendor/autoload.php';
    $host = getenv('SMTP_HOST') ?: '';
    $from = getenv('SMTP_FROM') ?: '';
    if ($host === '' || !filter_var($from, FILTER_VALIDATE_EMAIL) || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Shoot request mail is not configured.');
    }
    $encryption = getenv('SMTP_ENCRYPTION') ?: 'tls';
    if (!in_array($encryption, ['tls', 'ssl', 'none'], true)) {
        throw new RuntimeException('Invalid SMTP encryption setting.');
    }
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = $host;
    $mail->Port = (int) (getenv('SMTP_PORT') ?: '587');
    $mail->Username = getenv('SMTP_USERNAME') ?: '';
    $mail->Password = getenv('SMTP_PASSWORD') ?: '';
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
        . "Omschrijving:\n{$data['description']}\n\n"
        . "Dit is een aanvraag; de datum is nog niet bevestigd.\n"
        . "Beantwoord deze mail om contact op te nemen met de aanvrager.\n";
    $mail->send();
}
