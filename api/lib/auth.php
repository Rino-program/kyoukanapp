<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

const ROLE_RANK = ['student' => 1, 'leader' => 2, 'teacher' => 3, 'admin' => 4, 'developer' => 5];

function base64UrlEncode(string $value): string { return rtrim(strtr(base64_encode($value), '+/', '-_'), '='); }
function base64UrlDecode(string $value): string { return base64_decode(strtr($value, '-_', '+/')) ?: ''; }
function createToken(array $claims): string {
	$header = base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
	$payload = base64UrlEncode(json_encode($claims));
	$signature = base64UrlEncode(hash_hmac('sha256', "$header.$payload", (string) config('jwt_secret'), true));
	return "$header.$payload.$signature";
}
function verifyToken(string $token): ?array {
	$parts = explode('.', $token);
	if (count($parts) !== 3) return null;
	$expected = base64UrlEncode(hash_hmac('sha256', "$parts[0].$parts[1]", (string) config('jwt_secret'), true));
	if (!hash_equals($expected, $parts[2])) return null;
	$claims = json_decode(base64UrlDecode($parts[1]), true);
	return is_array($claims) && (($claims['exp'] ?? 0) > time()) ? $claims : null;
}
function currentUser(bool $required = true): ?array {
	$header = $_SERVER['HTTP_AUTHORIZATION']
		?? $_SERVER['Authorization']
		?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
		?? $_SERVER['HTTP_X_AUTHORIZATION']
		?? '';
	if ($header === '' && function_exists('apache_request_headers')) {
		$headers = apache_request_headers();
		$header = (string) ($headers['Authorization'] ?? $headers['authorization'] ?? $headers['X-Authorization'] ?? $headers['x-authorization'] ?? '');
	}
	$token = preg_match('/^Bearer\s+/i', $header) ? trim(substr($header, 7)) : '';
	$claims = $token !== '' ? verifyToken($token) : null;
	if (!$claims && $required) fail('認証が必要です', 401);
	return $claims;
}
function requireRole(string $role): array {
	$user = currentUser();
	if ((ROLE_RANK[$user['role']] ?? 0) < (ROLE_RANK[$role] ?? 99)) fail('権限がありません', 403);
	return $user;
}
function googleLogin(string $idToken): array {
	$url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . rawurlencode($idToken);
	$response = @file_get_contents($url);
	$claims = $response ? json_decode($response, true) : null;
	if (!is_array($claims) || ($claims['aud'] ?? '') !== config('google_client_id') || empty($claims['sub'])) fail('Googleトークンを検証できません', 401);
	$pdo = Database::connection();
	$stmt = $pdo->prepare('SELECT id, google_id, email, name, role FROM users WHERE google_id = ? OR email = ?');
	$stmt->execute([$claims['sub'], $claims['email'] ?? '']);
	$user = $stmt->fetch();
	if (!$user) {
		$stmt = $pdo->prepare('INSERT INTO users (google_id, email, name) VALUES (?, ?, ?)');
		$stmt->execute([$claims['sub'], $claims['email'], $claims['name'] ?? 'ユーザー']);
		$user = ['id' => (int) $pdo->lastInsertId(), 'google_id' => $claims['sub'], 'email' => $claims['email'], 'name' => $claims['name'] ?? 'ユーザー', 'role' => 'student'];
	}
	return ['user' => $user, 'token' => createToken($user + ['iat' => time(), 'exp' => time() + 86400 * 7])];
}
