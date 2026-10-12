<?php
require dirname(__DIR__) . '/shoot-request.php';

function check(bool $condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
}

$valid = ['name' => 'Test Bezoeker', 'email' => 'visitor@example.test', 'phone' => '+31 6 12345678', 'date' => '2099-06-20', 'time' => '14:30', 'description' => 'Een familieshoot met vier personen.'];
check(validateShootRequest($valid) === '', 'Valid request rejected');
foreach (['24:00', '12:60', '9:30', 'noon'] as $time) {
    check(validateShootRequest(array_replace($valid, ['time' => $time])) !== '', 'Invalid time accepted');
}
foreach (array_keys($valid) as $field) {
    foreach (['', '   ', []] as $emptyValue) {
        check(validateShootRequest(array_replace($valid, [$field => $emptyValue])) !== '', 'Empty ' . $field . ' accepted');
    }
    $missing = $valid;
    unset($missing[$field]);
    check(validateShootRequest($missing) !== '', 'Missing ' . $field . ' accepted');
}
foreach (['abc', '123', "0612345678\nExtra", str_repeat('1', 41)] as $phone) {
    check(validateShootRequest(array_replace($valid, ['phone' => $phone])) !== '', 'Invalid phone accepted');
}
foreach ([['name', ''], ['name', "Naam\r\nBcc: evil@example.test"], ['email', 'invalid'], ['email', "test@example.test\r\nBcc: evil@example.test"], ['date', '2099-02-30'], ['date', '2000-01-01'], ['date', "2099-01-01\0"], ['description', ''], ['description', str_repeat('x', 4001)]] as [$field, $value]) {
    check(validateShootRequest(array_replace($valid, [$field => $value])) !== '', 'Invalid ' . $field . ' accepted');
}
check(shootRequestInput(['name' => ['bad']])['name'] === '', 'Array input accepted');

$capture = tempnam(sys_get_temp_dir(), 'shoot-mail-');
$process = proc_open([PHP_BINARY, __DIR__ . '/smtp-server.php', $capture], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
try {
    $address = trim(fgets($pipes[1]));
    check($address !== '', 'SMTP fixture failed to start');
    putenv('SMTP_HOST=127.0.0.1');
    putenv('SMTP_PORT=' . explode(':', $address)[1]);
    putenv('SMTP_ENCRYPTION=none');
    putenv('SMTP_FROM=site@example.test');
    putenv('SMTP_USERNAME=');
    sendShootRequest($valid, 'owner@example.test');
    foreach ($pipes as $pipe) { fclose($pipe); }
    check(proc_close($process) === 0, 'SMTP fixture failed');
    $process = null;
    $mail = file_get_contents($capture);
    check(str_contains($mail, 'Gewenste tijd: 14:30'), 'Time missing in mail');
    foreach (['RCPT TO:<owner@example.test>', 'Reply-To: Test Bezoeker <visitor@example.test>', '20-06-2099', $valid['description'], 'Naam: Test Bezoeker', 'Telefoonnummer: +31 6 12345678'] as $expected) {
        check(str_contains($mail, $expected), 'Missing mail content: ' . $expected);
    }
    putenv('SMTP_HOST=');
    $failed = false;
    try { sendShootRequest($valid, 'owner@example.test'); } catch (RuntimeException $e) { $failed = true; }
    check($failed, 'Missing SMTP configuration did not fail');
    echo "PASS: validation, malformed input, SMTP delivery, recipient, reply address, summary, missing configuration\n";
} finally {
    if (is_resource($process)) { proc_terminate($process); proc_close($process); }
    unlink($capture);
}
