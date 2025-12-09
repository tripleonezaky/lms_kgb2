<?php
/**
 * File: includes/meetings_api.php
 * API untuk manajemen tautan video conference (Zoom, Teams, Jitsi, Webex)
 *
 * Endpoints (action):
 * - list: { context_type: 'assignment'|'tugas', context_id }
 * - create [POST]: { context_type, context_id, provider, join_url?, meeting_title?, scheduled_at?, passcode? }
 *      - Jika provider=='jitsi' dan join_url kosong, sistem akan buat slug otomatis: https://meet.jit.si/{slug}
 * - delete [POST]: { meeting_id }
 *
 * Akses:
 * - list: guru pemilik assignment dan siswa pada kelas assignment.
 * - create/delete: hanya guru pemilik assignment.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

$uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$role = isset($_SESSION['role']) ? $_SESSION['role'] : null;
if (!$uid || !$role) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

function j_ok($data = null) { echo json_encode(['success' => true, 'data' => $data]); exit; }
function j_err($msg, $code = 400) { http_response_code($code); echo json_encode(['success' => false, 'message' => $msg]); exit; }

@query("CREATE TABLE IF NOT EXISTS meetings (
  id INT(11) NOT NULL AUTO_INCREMENT,
  context_type ENUM('assignment','tugas') NOT NULL,
  context_id INT(11) NOT NULL,
  provider ENUM('zoom','teams','jitsi','webex') NOT NULL,
  join_url VARCHAR(255) NOT NULL,
  meeting_title VARCHAR(200) DEFAULT NULL,
  scheduled_at DATETIME DEFAULT NULL,
  passcode VARCHAR(50) DEFAULT NULL,
  created_by INT(11) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ctx (context_type, context_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

function user_kelas_id($uid) {
    $res = query("SELECT kelas_id FROM users WHERE id=".(int)$uid." LIMIT 1");
    $row = $res ? fetch_assoc($res) : null;
    return $row && $row['kelas_id'] ? (int)$row['kelas_id'] : null;
}

function can_access_context($context_type, $context_id, $uid, $role) {
    $context_type = escape_string($context_type);
    $context_id = (int)$context_id;
    if ($context_type === 'tugas') {
        $sql = "SELECT ag.guru_id, ag.kelas_id FROM tugas t JOIN assignment_guru ag ON t.assignment_id = ag.id WHERE t.id={$context_id} LIMIT 1";
        $res = query($sql);
        $row = $res ? fetch_assoc($res) : null;
        if (!$row) return false;
        if ($role === 'guru') return ((int)$row['guru_id'] === (int)$uid);
        if ($role === 'siswa') {
            $kid = user_kelas_id($uid);
            return $kid && ((int)$row['kelas_id'] === (int)$kid);
        }
        return false;
    } elseif ($context_type === 'assignment') {
        $sql = "SELECT guru_id, kelas_id FROM assignment_guru WHERE id={$context_id} LIMIT 1";
        $res = query($sql);
        $row = $res ? fetch_assoc($res) : null;
        if (!$row) return false;
        if ($role === 'guru') return ((int)$row['guru_id'] === (int)$uid);
        if ($role === 'siswa') {
            $kid = user_kelas_id($uid);
            return $kid && ((int)$row['kelas_id'] === (int)$kid);
        }
        return false;
    }
    return false;
}

function ensure_guru_owner($context_type, $context_id, $uid) {
    $context_type = escape_string($context_type);
    $context_id = (int)$context_id;
    if ($context_type === 'tugas') {
        $sql = "SELECT ag.guru_id FROM tugas t JOIN assignment_guru ag ON t.assignment_id = ag.id WHERE t.id={$context_id} LIMIT 1";
    } else {
        $sql = "SELECT guru_id FROM assignment_guru WHERE id={$context_id} LIMIT 1";
    }
    $res = query($sql); $row = $res ? fetch_assoc($res) : null;
    return $row && ((int)$row['guru_id'] === (int)$uid);
}

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

switch ($action) {
    case 'list': {
        $context_type = isset($_REQUEST['context_type']) ? $_REQUEST['context_type'] : '';
        $context_id = isset($_REQUEST['context_id']) ? (int)$_REQUEST['context_id'] : 0;
        if (!in_array($context_type, ['assignment','tugas'], true) || $context_id <= 0) j_err('Parameter tidak valid');
        if (!can_access_context($context_type, $context_id, $uid, $role)) j_err('Forbidden', 403);
        $sql = "SELECT id, provider, join_url, meeting_title, scheduled_at, passcode, created_at
                FROM meetings
                WHERE context_type='".escape_string($context_type)."' AND context_id={$context_id}
                ORDER BY COALESCE(scheduled_at, created_at) DESC";
        $rows = fetch_all(query($sql));
        j_ok(['meetings' => $rows]);
    }
    case 'create': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') j_err('Invalid method', 405);
        if ($role !== 'guru') j_err('Hanya guru yang dapat membuat meeting', 403);
        $context_type = isset($_POST['context_type']) ? $_POST['context_type'] : '';
        $context_id = isset($_POST['context_id']) ? (int)$_POST['context_id'] : 0;
        $provider = isset($_POST['provider']) ? $_POST['provider'] : '';
        $join_url = isset($_POST['join_url']) ? trim($_POST['join_url']) : '';
        $title = isset($_POST['meeting_title']) ? trim($_POST['meeting_title']) : '';
        $scheduled_at = isset($_POST['scheduled_at']) ? trim($_POST['scheduled_at']) : null;
        $passcode = isset($_POST['passcode']) ? trim($_POST['passcode']) : null;
        if (!in_array($context_type, ['assignment','tugas'], true) || $context_id <= 0) j_err('Parameter context tidak valid');
        if (!in_array($provider, ['zoom','teams','jitsi','webex'], true)) j_err('Provider tidak valid');
        if (!ensure_guru_owner($context_type, $context_id, $uid)) j_err('Forbidden', 403);
        if ($provider === 'jitsi' && $join_url === '') {
            $slug = 'kgb2-'.bin2hex(random_bytes(4)).'-'.time();
            $join_url = 'https://meet.jit.si/' . $slug;
        }
        if ($join_url === '') j_err('join_url wajib diisi untuk provider ini');
        $ok = query("INSERT INTO meetings (context_type, context_id, provider, join_url, meeting_title, scheduled_at, passcode, created_by) VALUES ('".escape_string($context_type)."', {$context_id}, '".escape_string($provider)."', '".escape_string($join_url)."', "
            .($title!==''?"'".escape_string($title)."'":"NULL").", ".($scheduled_at?"'".escape_string($scheduled_at)."'":"NULL").", ".($passcode?"'".escape_string($passcode)."'":"NULL").", {$uid})");
        if (!$ok) j_err('Gagal membuat meeting', 500);
        j_ok(['id' => (int)last_insert_id()]);
    }
    case 'delete': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') j_err('Invalid method', 405);
        if ($role !== 'guru') j_err('Hanya guru yang dapat menghapus meeting', 403);
        $meeting_id = isset($_POST['meeting_id']) ? (int)$_POST['meeting_id'] : 0;
        if ($meeting_id <= 0) j_err('meeting_id invalid');
        $res = query("SELECT context_type, context_id FROM meetings WHERE id={$meeting_id} LIMIT 1");
        $row = $res ? fetch_assoc($res) : null;
        if (!$row) j_err('Meeting tidak ditemukan', 404);
        if (!ensure_guru_owner($row['context_type'], (int)$row['context_id'], $uid)) j_err('Forbidden', 403);
        $ok = query("DELETE FROM meetings WHERE id={$meeting_id}");
        if (!$ok) j_err('Gagal menghapus meeting', 500);
        j_ok(true);
    }
    default:
        j_err('Action not found', 404);
}
