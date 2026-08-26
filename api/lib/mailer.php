<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

function notifyUser(PDO $pdo, int $userId, string $title, string $content, ?string $link = null): void {
	$stmt = $pdo->prepare('INSERT INTO notifications (user_id, title, content, link_url) VALUES (?, ?, ?, ?)');
	$stmt->execute([$userId, $title, $content, $link]);
	if (!config('mail.enabled', false)) return;
	$stmt = $pdo->prepare('SELECT email FROM users WHERE id = ?');
	$stmt->execute([$userId]);
	$email = $stmt->fetchColumn();
	if ($email) @mail($email, $title, $content, 'From: ' . config('mail.from'));
}
