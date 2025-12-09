<?php
/**
 * File: includes/comments_api.php
 * API untuk komentar pada pengumpulan tugas dan toggle resubmit
 *
 * Endpoints (via POST/GET action):
 * - list_comments: { pengumpulan_id }
 * - add_comment: { pengumpulan_id, comment } [guru pemilik assignment]
 * - edit_comment: { comment_id, comment } [guru pembuat komentar]
 * - delete_comment: { comment_id } [guru pembuat komentar]
 * - toggle_allow_resubmit: { pengumpulan_id, allow (0|1) } [guru pemilik assignment]
 *
 * Response: JSON { success: bool, data?: any, message?: string }
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

// Bootstrap DB objects
function json_ok($data = null) { echo json_encode(['success' => true, 'data' => $data]); exit; }
function json_err($msg, $code = 400) { http_response_code($code); echo json_encode(['success' => false, 'message' => $msg]); exit; }

// Ensure tables/columns exist (idempotent)
@query("CREATE TABLE IF NOT EXISTS assignment_comments (
  id INT(11) NOT NULL AUTO_INCREMENT,
  pengumpulan_id INT(11) NOT NULL,
  guru_id INT(11) NOT NULL,
  comment TEXT NOT NULL,
  is_edited TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY pengumpulan_id (pengumpulan_id),
  KEY guru_id (guru_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

@query("ALTER TABLE pengumpulan_tugas ADD COLUMN allow_resubmit TINYINT(1) NOT NULL DEFAULT 0");
@query("ALTER TABLE pengumpulan_tugas ADD COLUMN edited_at DATETIME NULL AFTER tanggal_submit");

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

// Helper: cek akses ke pengumpulan untuk siswa/guru
function fetch_pengumpulan_detail($pengumpulan_id) {
    $pengumpulan_id = (int)$pengumpulan_id;
    $sql = "SELECT pt.id AS pengumpulan_id, pt.siswa_id, pt.tugas_id, pt.allow_resubmit,
                   t.assignment_id,
                   ag.guru_id, ag.kelas_id
            FROM pengumpulan_tugas pt
            JOIN tugas t ON pt.tugas_id = t.id
            JOIN assignment_guru ag ON t.assignment_id = ag.id
            WHERE pt.id = {$pengumpulan_id} LIMIT 1";
    $res = query($sql);
    return $res ? fetch_assoc($res) : null;
}

function ensure_guru_owner($pengumpulan_id, $uid) {
    $d = fetch_pengumpulan_detail($pengumpulan_id);
    return $d && (int)$d['guru_id'] === (int)$uid;
}

function ensure_access_list_comments($pengumpulan_id, $uid, $role) {
    $d = fetch_pengumpulan_detail($pengumpulan_id);
    if (!$d) return false;
    if ($role === 'guru' && (int)$d['guru_id'] === (int)$uid) return true;
    if ($role === 'siswa' && (int)$d['siswa_id'] === (int)$uid) return true;
    return false;
}

switch ($action) {
    case 'list_comments': {
        $pengumpulan_id = isset($_REQUEST['pengumpulan_id']) ? (int)$_REQUEST['pengumpulan_id'] : 0;
        if ($pengumpulan_id <= 0) json_err('pengumpulan_id invalid');
        if (!ensure_access_list_comments($pengumpulan_id, $uid, $role)) json_err('Forbidden', 403);
        $sql = "SELECT ac.id, ac.comment, ac.is_edited, ac.created_at, ac.updated_at,
                       u.nama_lengkap AS guru_nama
                FROM assignment_comments ac
                JOIN users u ON ac.guru_id = u.id
                WHERE ac.pengumpulan_id = {$pengumpulan_id}
                ORDER BY ac.created_at ASC";
        $rows = fetch_all(query($sql));
        json_ok(['comments' => $rows]);
    }
    case 'add_comment': {
        if ($role !== 'guru') json_err('Hanya guru yang dapat menambahkan komentar', 403);
        $pengumpulan_id = isset($_POST['pengumpulan_id']) ? (int)$_POST['pengumpulan_id'] : 0;
        $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';
        if ($pengumpulan_id <= 0 || $comment === '') json_err('Data tidak lengkap');
        if (!ensure_guru_owner($pengumpulan_id, $uid)) json_err('Forbidden', 403);
        $comment_esc = escape_string($comment);
        $sql = "INSERT INTO assignment_comments (pengumpulan_id, guru_id, comment) VALUES ({$pengumpulan_id}, {$uid}, '{$comment_esc}')";
        if (!query($sql)) json_err('Gagal menyimpan komentar', 500);
        $id = last_insert_id();
        json_ok(['id' => $id]);
    }
    case 'edit_comment': {
        if ($role !== 'guru') json_err('Hanya guru yang dapat mengedit komentar', 403);
        $comment_id = isset($_POST['comment_id']) ? (int)$_POST['comment_id'] : 0;
        $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';
        if ($comment_id <= 0 || $comment === '') json_err('Data tidak lengkap');
        $res = query("SELECT pengumpulan_id, guru_id FROM assignment_comments WHERE id={$comment_id} LIMIT 1");
        $row = $res ? fetch_assoc($res) : null;
        if (!$row) json_err('Komentar tidak ditemukan', 404);
        if ((int)$row['guru_id'] !== (int)$uid) json_err('Forbidden', 403);
        // validasi guru masih pemilik assignment
        if (!ensure_guru_owner((int)$row['pengumpulan_id'], $uid)) json_err('Forbidden', 403);
        $comment_esc = escape_string($comment);
        $ok = query("UPDATE assignment_comments SET comment='{$comment_esc}', is_edited=1 WHERE id={$comment_id}");
        if (!$ok) json_err('Gagal mengedit komentar', 500);
        json_ok(true);
    }
    case 'delete_comment': {
        if ($role !== 'guru') json_err('Hanya guru yang dapat menghapus komentar', 403);
        $comment_id = isset($_POST['comment_id']) ? (int)$_POST['comment_id'] : 0;
        if ($comment_id <= 0) json_err('comment_id invalid');
        $res = query("SELECT pengumpulan_id, guru_id FROM assignment_comments WHERE id={$comment_id} LIMIT 1");
        $row = $res ? fetch_assoc($res) : null;
        if (!$row) json_err('Komentar tidak ditemukan', 404);
        if ((int)$row['guru_id'] !== (int)$uid) json_err('Forbidden', 403);
        if (!ensure_guru_owner((int)$row['pengumpulan_id'], $uid)) json_err('Forbidden', 403);
        $ok = query("DELETE FROM assignment_comments WHERE id={$comment_id}");
        if (!$ok) json_err('Gagal menghapus komentar', 500);
        json_ok(true);
    }
    case 'toggle_allow_resubmit': {
        if ($role !== 'guru') json_err('Hanya guru yang dapat mengubah resubmit', 403);
        $pengumpulan_id = isset($_POST['pengumpulan_id']) ? (int)$_POST['pengumpulan_id'] : 0;
        $allow = isset($_POST['allow']) ? (int)$_POST['allow'] : 0;
        if ($pengumpulan_id <= 0) json_err('pengumpulan_id invalid');
        if (!ensure_guru_owner($pengumpulan_id, $uid)) json_err('Forbidden', 403);
        $ok = query("UPDATE pengumpulan_tugas SET allow_resubmit = ".$allow." WHERE id = ".$pengumpulan_id);
        if (!$ok) json_err('Gagal mengubah resubmit', 500);
        json_ok(['allow' => $allow]);
    }
    default:
        json_err('Action not found', 404);
}
