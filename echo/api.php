<?php
// Mauri Echo - Anonymous Classroom Feedback API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$dbDir = __DIR__ . '/data';
$dbFile = $dbDir . '/echo.db';

if (!is_dir($dbDir)) {
    mkdir($dbDir, 0755, true);
}

$pdo = new PDO('sqlite:' . $dbFile);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE IF NOT EXISTS sessions (
    id TEXT PRIMARY KEY,
    pin TEXT UNIQUE NOT NULL,
    question TEXT NOT NULL,
    created_at INTEGER NOT NULL,
    expires_at INTEGER NOT NULL,
    live BOOLEAN DEFAULT 1,
    active BOOLEAN DEFAULT 1
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS responses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    session_id TEXT NOT NULL,
    text TEXT NOT NULL,
    created_at INTEGER NOT NULL,
    FOREIGN KEY (session_id) REFERENCES sessions(id)
)");

$action = $_GET['action'] ?? '';

function generateId($length = 8) {
    return substr(str_shuffle(str_repeat('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ23456789', 10)), 0, $length);
}

function generatePin($pdo) {
    do {
        $pin = str_pad((string)random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare("SELECT 1 FROM sessions WHERE pin = ?");
        $stmt->execute([$pin]);
    } while ($stmt->fetch());
    return $pin;
}

function safeJson($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    if ($action === 'create') {
        $input = json_decode(file_get_contents('php://input'), true);
        $question = trim($input['question'] ?? '');
        if (empty($question)) {
            safeJson(['error' => 'Frage darf nicht leer sein.']);
        }
        $id = generateId();
        $pin = generatePin($pdo);
        $now = time();
        $expires = $now + 86400; // 24h
        $stmt = $pdo->prepare("INSERT INTO sessions (id, pin, question, created_at, expires_at, live, active) VALUES (?, ?, ?, ?, ?, 1, 1)");
        $stmt->execute([$id, $pin, $question, $now, $expires]);
        safeJson(['id' => $id, 'pin' => $pin, 'expires_at' => $expires]);
    }

    if ($action === 'get') {
        $id = $_GET['id'] ?? '';
        $pin = $_GET['pin'] ?? '';
        $session = null;
        if (!empty($id)) {
            $stmt = $pdo->prepare("SELECT * FROM sessions WHERE id = ? AND active = 1");
            $stmt->execute([$id]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
        } elseif (!empty($pin)) {
            $stmt = $pdo->prepare("SELECT * FROM sessions WHERE pin = ? AND active = 1");
            $stmt->execute([$pin]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        if (!$session) {
            safeJson(['error' => 'Session nicht gefunden.']);
        }
        $responses = [];
        $live = (bool)$session['live'];
        if ($live) {
            $stmt = $pdo->prepare("SELECT id, text, created_at FROM responses WHERE session_id = ? ORDER BY created_at DESC");
            $stmt->execute([$id]);
            $responses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        safeJson([
            'id' => $session['id'],
            'pin' => $session['pin'],
            'question' => $session['question'],
            'live' => $live,
            'active' => (bool)$session['active'],
            'expires_at' => (int)$session['expires_at'],
            'responses' => $responses
        ]);
    }

    if ($action === 'toggle') {
        $id = $_GET['id'] ?? '';
        $input = json_decode(file_get_contents('php://input'), true);
        $live = isset($input['live']) ? (int)$input['live'] : 1;
        $stmt = $pdo->prepare("UPDATE sessions SET live = ? WHERE id = ?");
        $stmt->execute([$live, $id]);
        safeJson(['live' => (bool)$live]);
    }

    if ($action === 'close') {
        $id = $_GET['id'] ?? '';
        $stmt = $pdo->prepare("UPDATE sessions SET active = 0 WHERE id = ?");
        $stmt->execute([$id]);
        safeJson(['active' => false]);
    }

    if ($action === 'submit') {
        $input = json_decode(file_get_contents('php://input'), true);
        $id = trim($input['id'] ?? '');
        $pin = trim($input['pin'] ?? '');
        $text = trim($input['text'] ?? '');
        if ((empty($id) && empty($pin)) || empty($text)) {
            safeJson(['error' => 'Session/Pin und Antwort erforderlich.']);
        }
        if (!empty($id)) {
            $stmt = $pdo->prepare("SELECT id FROM sessions WHERE id = ? AND active = 1 AND expires_at > ?");
            $stmt->execute([$id, time()]);
            $sessionId = $stmt->fetchColumn();
        } else {
            $stmt = $pdo->prepare("SELECT id FROM sessions WHERE pin = ? AND active = 1 AND expires_at > ?");
            $stmt->execute([$pin, time()]);
            $sessionId = $stmt->fetchColumn();
        }
        if (!$sessionId) {
            safeJson(['error' => 'Session nicht mehr aktiv.']);
        }
        $id = $sessionId;
        $stmt = $pdo->prepare("INSERT INTO responses (session_id, text, created_at) VALUES (?, ?, ?)");
        $stmt->execute([$id, $text, time()]);
        safeJson(['ok' => true]);
    }

    if ($action === 'export') {
        $id = $_GET['id'] ?? '';
        $stmt = $pdo->prepare("SELECT question FROM sessions WHERE id = ?");
        $stmt->execute([$id]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$session) {
            safeJson(['error' => 'Session nicht gefunden.']);
        }
        $stmt = $pdo->prepare("SELECT text FROM responses WHERE session_id = ? ORDER BY created_at ASC");
        $stmt->execute([$id]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="echo-' . $id . '.txt"');
        echo "Frage: " . $session['question'] . "\n\n";
        foreach ($rows as $i => $text) {
            echo ($i + 1) . ". " . $text . "\n";
        }
        exit;
    }

    safeJson(['error' => 'Unbekannte Aktion.']);
} catch (Exception $e) {
    http_response_code(500);
    safeJson(['error' => $e->getMessage()]);
}
