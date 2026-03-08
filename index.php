<?php
// public/index.php — Front controller / router
declare(strict_types=1);

 

$uri = strtok(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '?');



error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');

define('BASE_DIR', __DIR__);

require_once BASE_DIR . '/src/Database.php';
require_once BASE_DIR . '/src/Crypto.php';
require_once BASE_DIR . '/src/TOTP.php';
require_once BASE_DIR . '/src/Auth.php';
require_once BASE_DIR . '/src/OAuthP.php';
require_once BASE_DIR . '/src/Profile.php';
require_once BASE_DIR . '/src/MagicLink.php';

// Start session early
session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax','secure'=>isset($_SERVER['HTTPS'])]);
session_start();


 


// Helpers
function jsonResponse(mixed $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function redirect(string $url): never {
    $base = rtrim((require __DIR__ . '/config/config.php')['app_url'], '/');
    if (str_starts_with($url, '/')) {
        $url = $base . $url;
    }
    header('Location: ' . $url);
    exit;
}

function view(string $tpl, array $vars = []): never {
    extract($vars);
    require BASE_DIR . '/templates/' . $tpl . '.php';
    exit;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function verify_csrf(): void {
    $token = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
        jsonResponse(['error' => 'Invalid CSRF token'], 403);
    }
}

// ── Routing ────────────────────────────────────────────────────────────────

$method = $_SERVER['REQUEST_METHOD'];
$uri    = strtok(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '?');
$auth   = new Auth();
$user   = $auth->currentUser();

// ── Public routes ──────────────────────────────────────────────────────────

if ($uri === '/' && $method === 'GET') {
    if ($user) redirect('/dashboard');
    view('landing', ['user' => null]);
}


// ── Magic link: request ────────────────────────────────────────────────────

if ($uri === '/auth/magic-request' && $method === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        redirect('/?error=' . urlencode('Please enter a valid email address.'));
    }
    try {
        $magicLink = new MagicLink();
        $url       = $magicLink->create($email);
        $subject   = 'Your sign-in link for OTP Vault';
        $body      = "Sign in to OTP Vault\n\nClick this link (expires in 15 minutes):\n{$url}\n\nIf you didn't request this, ignore this email.";
  
 
        sendMail($email, $subject, $body);
    } catch (RuntimeException $e) {
        redirect('/?error=' . urlencode($e->getMessage()));
    }
    redirect('/?magic=sent&email=' . urlencode($email));
}

// ── Magic link: verify ─────────────────────────────────────────────────────

if ($uri === '/auth/magic' && $method === 'GET') {
    $token = $_GET['token'] ?? '';
    $email = $_GET['email'] ?? '';
    try {
        $magicLink     = new MagicLink();
        $verifiedEmail = $magicLink->verify($token, $email);
        $dbUser        = $magicLink->findOrCreateUser($verifiedEmail, $auth);
        $auth->loginUser($dbUser['id']);
        redirect('/dashboard');
    } catch (RuntimeException $e) {
        redirect('/?error=' . urlencode($e->getMessage()));
    }
}


// ── OAuth ──────────────────────────────────────────────────────────────────

if (preg_match('#^/auth/login/(\w+)$#', $uri, $m) && $method === 'GET') {
    $provider = $m[1];
    try {
        $oauth = new OAuthP($provider);
        redirect($oauth->getAuthUrl());
    } catch (Exception $e) {
        redirect('/?error=' . urlencode($e->getMessage()));
    }
}

if (preg_match('#^/auth/callback/(\w+)$#', $uri, $m) && $method === 'GET') {
    $provider = $m[1];
    try {
        if (!empty($_GET['error'])) throw new RuntimeException($_GET['error_description'] ?? $_GET['error']);
        $oauth    = new OAuthP($provider);
        $userData = $oauth->handleCallback($_GET['code'] ?? '', $_GET['state'] ?? '');
        $dbUser   = $auth->findOrCreateUser($userData);
        $auth->loginUser($dbUser['id']);
        redirect('/dashboard');
    } catch (Exception $e) {
        redirect('/?error=' . urlencode($e->getMessage()));
    }
}


function sendMail(string $to, string $subject, string $body): bool {
	$config = require __DIR__ . '/config/config.php';
	$mail   = $config['mail'];
	
	
    $apiKey = $mail['mailersend_key'];
    $from   = $mail['from_email'];
    $fromName = $mail['from_name'] ;

    $payload = json_encode([
        'from'     => ['email' => $from, 'name' => $fromName],
        'to'       => [['email' => $to]],
        'subject'  => $subject,
        'text'     => $body,
    ]);

    $ch = curl_init('https://api.mailersend.com/v1/email');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log("MailerSend cURL error: {$error}");
        return false;
    }
    if ($httpCode !== 202) {
        error_log("MailerSend failed: HTTP {$httpCode} — {$response}");
        return false;
    }
    return true;
}


if ($uri === '/auth/logout' && $method === 'GET') {
    $auth->logout();
    redirect('/');
}

// ── Require login from here ────────────────────────────────────────────────

if (!$user) {
    if ($method === 'GET') {
        redirect('/');
    }
    jsonResponse(['error' => 'Unauthorized'], 401);
}

// ── Dashboard ──────────────────────────────────────────────────────────────

if ($uri === '/dashboard' && $method === 'GET') {
    $profileSvc = new Profile();
    $profiles   = $profileSvc->listForUser($user['id']);
    view('dashboard', ['user' => $user, 'profiles' => $profiles]);
}

//Import Tools
if (preg_match('#^/tools/([\w-]+)$#', $uri, $m)) {
    $tool = $m[1];
    $file = BASE_DIR . '/tools/' . $tool . '.php';
    if (!file_exists($file)) {
        http_response_code(404);
        view('404', ['user' => $user]);
    }
    require $file;
    exit;
}


// ── API: OTP ───────────────────────────────────────────────────────────────

if ($uri === '/api/otp' && $method === 'GET') {
    $profileId = (int)($_GET['id'] ?? 0);
    try {
        $profileSvc = new Profile();
        jsonResponse($profileSvc->generateOTP($profileId, $user['id']));
    } catch (Exception $e) {
        jsonResponse(['error' => $e->getMessage()], 403);
    }
}

// ── API: Profiles ──────────────────────────────────────────────────────────

if ($uri === '/api/profiles' && $method === 'POST') {
    verify_csrf();
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    try {
        $profileSvc = new Profile();
        $id = $profileSvc->create($user['id'], $data);
        jsonResponse(['success' => true, 'id' => $id]);
    } catch (Exception $e) {
        jsonResponse(['error' => $e->getMessage()], 400);
    }
}

if (preg_match('#^/api/profiles/(\d+)$#', $uri, $m)) {
    $profileId  = (int)$m[1];
    $profileSvc = new Profile();

    if ($method === 'PUT') {
        verify_csrf();
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        $ok = $profileSvc->update($profileId, $user['id'], $data);
        jsonResponse(['success' => $ok]);
    }

    if ($method === 'DELETE') {
        verify_csrf();
        $ok = $profileSvc->delete($profileId, $user['id']);
        jsonResponse(['success' => $ok]);
    }

    if ($method === 'GET') {
        $profile = $profileSvc->get($profileId, $user['id']);
        if (!$profile) jsonResponse(['error' => 'Not found'], 404);
        unset($profile['secret_encrypted']);
        jsonResponse($profile);
    }
}

// ── API: Shares ────────────────────────────────────────────────────────────

if (preg_match('#^/api/profiles/(\d+)/shares$#', $uri, $m)) {
    $profileId  = (int)$m[1];
    $profileSvc = new Profile();

    if ($method === 'GET') {
        jsonResponse($profileSvc->getShares($profileId, $user['id']));
    }

    if ($method === 'POST') {
        verify_csrf();
        $data    = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $email   = trim(strtolower($data['email'] ?? ''));
        $canEdit = !empty($data['can_edit']);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['error' => 'Invalid email address'], 400);
        }
        if ($email === strtolower($user['email'])) {
            jsonResponse(['error' => 'You cannot share with yourself'], 400);
        }
        $ok = $profileSvc->share($profileId, $user['id'], $email, $canEdit);
        jsonResponse(['success' => $ok]);
    }

    if ($method === 'DELETE') {
        verify_csrf();
        $data  = json_decode(file_get_contents('php://input'), true) ?? [];
        $email = trim(strtolower($data['email'] ?? ''));
        $ok    = $profileSvc->unshare($profileId, $user['id'], $email);
        jsonResponse(['success' => $ok]);
    }
}

// ── API: Generate secret ───────────────────────────────────────────────────

if ($uri === '/api/generate-secret' && $method === 'GET') {
    jsonResponse(['secret' => TOTP::generateSecret()]);
}

// ── 404 ────────────────────────────────────────────────────────────────────

http_response_code(404);
view('404', ['user' => $user]);
