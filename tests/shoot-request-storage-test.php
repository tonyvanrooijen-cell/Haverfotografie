<?php
require dirname(__DIR__) . '/shoot-request.php';

// Uses only a newly created, isolated test database on the local Docker MySQL.
$pdo = new PDO('mysql:host=db;charset=utf8mb4', 'root', 'secret', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$database = 'shoot_request_test_' . bin2hex(random_bytes(6));
$pdo->exec('CREATE DATABASE `' . $database . '`');
try {
    $pdo->exec('USE `' . $database . '`');
    ensureShootRequestTable($pdo);
    $pdo->exec('ALTER TABLE HaverFotografieRequests DROP COLUMN requested_time');
    ensureShootRequestTable($pdo);
    $data = ['name' => 'Test Bezoeker', 'email' => 'visitor@example.test', 'phone' => '+31 6 12345678', 'date' => '2099-06-20', 'time' => '14:30', 'description' => "Familiefoto\nBuiten in het park <test>"];
    $token = bin2hex(random_bytes(32));
    $id = saveShootRequest($pdo, $data, $token);
    $row = $pdo->query('SELECT * FROM HaverFotografieRequests')->fetch(PDO::FETCH_ASSOC);
    if ($row['requested_time'] !== '14:30:00') { throw new RuntimeException('Time not saved'); }
    foreach (['name', 'email', 'phone', 'description'] as $key) {
        if ($row[$key] !== $data[$key]) { throw new RuntimeException('Lost field: ' . $key); }
    }
    if ($row['requested_date'] !== $data['date'] || $row['mail_status'] !== 'pending' || (int) $row['id'] !== $id) {
        throw new RuntimeException('Invalid stored request');
    }
    try {
        saveShootRequest($pdo, $data, $token);
        throw new RuntimeException('Duplicate accepted');
    } catch (PDOException $e) {
        if ($e->getCode() !== '23000') { throw $e; }
    }
    try {
        saveShootRequest($pdo, array_replace($data, ['phone' => '']), bin2hex(random_bytes(32)));
        throw new RuntimeException('Incomplete request accepted');
    } catch (InvalidArgumentException $e) {}
    $stmt = $pdo->prepare('UPDATE HaverFotografieRequests SET mail_status = :status WHERE id = :id');
    $stmt->execute(['status' => 'failed', 'id' => $id]);
    if ((int) $pdo->query('SELECT COUNT(*) FROM HaverFotografieRequests WHERE mail_status = \'failed\'')->fetchColumn() !== 1) {
        throw new RuntimeException('Failed mail request not retained');
    }
    $otherId = saveShootRequest($pdo, $data, bin2hex(random_bytes(32)));
    if (!deleteShootRequest($pdo, $id) || deleteShootRequest($pdo, $id)) {
        throw new RuntimeException('Delete result incorrect');
    }
    if ((int) $pdo->query('SELECT id FROM HaverFotografieRequests')->fetchColumn() !== $otherId) {
        throw new RuntimeException('Wrong request deleted');
    }
    echo "PASS: migration, time storage, duplicate protection, required fields, mail failure, targeted deletion\n";
} finally {
    $pdo->exec('DROP DATABASE `' . $database . '`');
}
