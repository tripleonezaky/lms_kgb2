<?php
/**
 * File: includes/chat_api.php
 * API Chat untuk ruang percakapan antara guru dan siswa
 *
 * Endpoints (action):
 * - get_or_create_thread: { context_type: 'tugas'|'assignment', context_id }
 * - list_messages: { thread_id, after_id? }
 * - send_message [POST]: { thread_id, message }
 * - delete_message [POST]: { message_id } (opsional, hanya pengirim; soft delete)
 *
 * Keamanan akses:
 * - context 'tugas':
 *   - guru pemilik assignment dari tugas
 *   - siswa yang kelas_id-nya sama dengan kelas assignment tugas
 * - context 'assignment':
 *   - guru pemilik assignment
 *   - siswa pada kelas dari assignment
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

// Buat tabel jika belum ada (idempotent)
@query("CREATE TABLE IF NOT EXISTS chat_threads (
  id INT(11) NOT NULL AUTO_INCREMENT,
  context_type ENUM('assignment','tugas') NOT NULL,
  context_id INT(11) NOT NULL,
  created_by INT(11) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ctx (context_type, context_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

@query("CREATE TABLE IF NOT EXISTS chat_messages (
  id INT(11) NOT NULL AUTO_INCREMENT,
  thread_id INT(11) NOT NULL,
  sender_id INT(11) NOT NULL,
  message TEXT NOT NULL,
  is_deleted TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY thread_idx (thread_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

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

function ensure_thread_access_by_id($thread_id, $uid, $role) {
    $thread_id = (int)$thread_id;
    $res = query("SELECT context_type, context_id FROM chat_threads WHERE id={$thread_id} LIMIT 1");
    $row = $res ? fetch_assoc($res) : null;
    if (!$row) return [false, null];
    $ok = can_access_context($row['context_type'], (int)$row['context_id'], $uid, $role);
    return [$ok, $row];
}

switch ($action) {
    case 'get_or_create_thread': {
        $context_type = isset($_REQUEST['context_type']) ? $_REQUEST['context_type'] : '';
        $context_id = isset($_REQUEST['context_id']) ? (int)$_REQUEST['context_id'] : 0;
        if (!in_array($context_type, ['assignment','tugas'], true) || $context_id <= 0) j_err('Parameter tidak valid');
        if (!can_access_context($context_type, $context_id, $uid, $role)) j_err('Forbidden', 403);
        // Cari thread
        $res = query("SELECT id FROM chat_threads WHERE context_type='".escape_string($context_type)."' AND context_id={$context_id} LIMIT 1");
        $row = $res ? fetch_assoc($res) : null;
        if ($row) j_ok(['thread_id' => (int)$row['id']]);
        // Buat thread
        $ok = query("INSERT INTO chat_threads (context_type, context_id, created_by) VALUES ('".escape_string($context_type)."', {$context_id}, {$uid})");
        if (!$ok) j_err('Gagal membuat thread', 500);
        $tid = last_insert_id();
        j_ok(['thread_id' => (int)$tid]);
    }
    case 'list_messages': {
        $thread_id = isset($_REQUEST['thread_id']) ? (int)$_REQUEST['thread_id'] : 0;
        $after_id = isset($_REQUEST['after_id']) ? (int)$_REQUEST['after_id'] : 0;
        if ($thread_id <= 0) j_err('thread_id invalid');
        list($allowed,) = ensure_thread_access_by_id($thread_id, $uid, $role);
        if (!$allowed) j_err('Forbidden', 403);
        $where = "WHERE cm.thread_id={$thread_id} AND cm.is_deleted=0";
        if ($after_id > 0) $where .= " AND cm.id > {$after_id}";
        $sql = "SELECT cm.id, cm.sender_id, cm.message, cm.created_at, u.nama_lengkap AS sender_name
                FROM chat_messages cm
                JOIN users u ON cm.sender_id = u.id
                {$where}
                ORDER BY cm.id ASC
                LIMIT 200";
        $rows = fetch_all(query($sql));
        j_ok(['messages' => $rows]);
    }
    case 'send_message': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') j_err('Invalid method', 405);
        $thread_id = isset($_POST['thread_id']) ? (int)$_POST['thread_id'] : 0;
        $message = isset($_POST['message']) ? trim($_POST['message']) : '';
        if ($thread_id <= 0 || $message === '') j_err('Data tidak lengkap');
        list($allowed,) = ensure_thread_access_by_id($thread_id, $uid, $role);
        if (!$allowed) j_err('Forbidden', 403);
        $msg_esc = escape_string($message);
        $ok = query("INSERT INTO chat_messages (thread_id, sender_id, message) VALUES ({$thread_id}, {$uid}, '{$msg_esc}')");
        if (!$ok) j_err('Gagal mengirim pesan', 500);
        $mid = last_insert_id();
        j_ok(['id' => (int)$mid]);
    }
    case 'delete_message': {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') j_err('Invalid method', 405);
        $message_id = isset($_POST['message_id']) ? (int)$_POST['message_id'] : 0;
        if ($message_id <= 0) j_err('message_id invalid');
        $res = query("SELECT thread_id, sender_id FROM chat_messages WHERE id={$message_id} LIMIT 1");
        $row = $res ? fetch_assoc($res) : null;
        if (!$row) j_err('Pesan tidak ditemukan', 404);
        // Hanya pengirim yang boleh menghapus
        if ((int)$row['sender_id'] !== (int)$uid) j_err('Forbidden', 403);
        list($allowed,) = ensure_thread_access_by_id((int)$row['thread_id'], $uid, $role);
        if (!$allowed) j_err('Forbidden', 403);
        $ok = query("UPDATE chat_messages SET is_deleted=1 WHERE id={$message_id}");
        if (!$ok) j_err('Gagal menghapus pesan', 500);
        j_ok(true);
    }
    default:
        j_err('Action not found', 404);
}
