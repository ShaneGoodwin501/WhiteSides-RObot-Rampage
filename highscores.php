<?php
// highscores.php
// Simple global high score API: GET returns list, POST adds a score

header('Content-Type: application/json');

// === CONFIG ===
$file = __DIR__ . '/highscores.json'; // where scores are stored
$maxEntries = 10;

// Load existing scores from file
function load_scores($file) {
    if (!file_exists($file)) return [];
    $json = file_get_contents($file);
    $data = json_decode($json, true);
    if (!is_array($data)) return [];
    return $data;
}

// Save scores to file with basic locking
function save_scores($file, $scores) {
    $fp = fopen($file, 'c+');
    if (!$fp) return false;

    if (flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($scores, JSON_PRETTY_PRINT));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        return true;
    } else {
        fclose($fp);
        return false;
    }
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $scores = load_scores($file);

    // Sort descending by score
    usort($scores, function($a, $b) {
        return ($b['score'] ?? 0) <=> ($a['score'] ?? 0);
    });

    echo json_encode($scores);
    exit;
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    $name  = isset($input['name'])  ? strtoupper(trim($input['name'])) : '';
    $score = isset($input['score']) ? intval($input['score']) : 0;

    if ($score <= 0 || $name === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid name or score']);
        exit;
    }

    // Hard cap name length to 8 chars
    $name = substr($name, 0, 8);

    $scores = load_scores($file);
    $scores[] = ['name' => $name, 'score' => $score];

    // Sort and trim to maxEntries
    usort($scores, function($a, $b) {
        return ($b['score'] ?? 0) <=> ($a['score'] ?? 0);
    });
    $scores = array_slice($scores, 0, $maxEntries);

    if (!save_scores($file, $scores)) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save']);
        exit;
    }

    echo json_encode($scores);
    exit;
}

// Fallback for other methods
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);

