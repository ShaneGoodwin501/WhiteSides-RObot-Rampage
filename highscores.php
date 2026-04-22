<?php
declare(strict_types=1);
// highscores.php
// Simple global high score API: GET returns list, POST adds a score

header('Content-Type: application/json');

// === CONFIG ===
$file = __DIR__ . '/highscores.json'; // where scores are stored
$maxEntries = 10;
const MAX_SCORE_VALUE = 2147483647;

// Load existing scores from file
function load_scores($file) {
    if (!file_exists($file)) return [];
    $json = file_get_contents($file);
    $data = json_decode($json, true);
    if (!is_array($data)) return [];
    return $data;
}

function normalize_scores($scores, $maxEntries) {
    if (!is_array($scores)) return [];

    $clean = [];
    foreach ($scores as $entry) {
        if (!is_array($entry)) continue;
        $name = isset($entry['name']) ? strtoupper(trim((string)$entry['name'])) : '';
        $score = isset($entry['score']) ? intval($entry['score']) : 0;
        if ($name === '' || $score <= 0) continue;

        // keep letters, numbers, spaces, apostrophes and dashes only
        $name = preg_replace("/[^A-Z0-9\\s'\\-]/", '', $name);
        $name = trim(substr($name, 0, 8));
        if ($name === '') continue;

        $clean[] = ['name' => $name, 'score' => min($score, MAX_SCORE_VALUE)];
    }

    usort($clean, function($a, $b) {
        return ($b['score'] ?? 0) <=> ($a['score'] ?? 0);
    });

    return array_slice($clean, 0, $maxEntries);
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
    $scores = normalize_scores(load_scores($file), $maxEntries);

    echo json_encode($scores);
    exit;
}

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON payload']);
        exit;
    }

    $rawName = $input['name'] ?? null;
    if (!is_string($rawName)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid name or score']);
        exit;
    }

    $name  = strtoupper(trim($rawName));
    $score = isset($input['score']) ? intval($input['score']) : 0;
    $name = preg_replace("/[^A-Z0-9\\s'\\-]/", '', $name);

    if ($score <= 0 || $name === '' || $score > MAX_SCORE_VALUE) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid name or score']);
        exit;
    }

    // Hard cap name length to 8 chars
    $name = substr($name, 0, 8);

    $scores = normalize_scores(load_scores($file), $maxEntries);
    $scores[] = ['name' => $name, 'score' => $score];
    $scores = normalize_scores($scores, $maxEntries);

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
