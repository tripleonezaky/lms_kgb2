<?php
// siswa/resubmit_handler.php
session_start();
header('Content-Type: application/json');
require_once '../includes/check_session.php';
require_once '../includes/check_role.php';
check_role(['siswa']);
require_once '../config/database.php';
require_once '../includes/functions.php';

$uid = (int)$_SESSION['user_id'];
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false,'message'=>'Invalid method']);
    exit;
}
$action = $_POST['action'] ?? '';
if ($action !== 'resubmit_text') {
    echo json_encode(['success'=>false,'message'=>'Action not found']);
    exit;
}
$pengumpulan_id = isset($_POST['pengumpulan_id']) ? (int)$_POST['pengumpulan_id'] : 0;
$isi = trim($_POST['isi_jawaban'] ?? '');
if ($pengumpulan_id <= 0) {
    echo json_encode(['success'=>false,'message'=>'pengumpulan_id invalid']);
    exit;
}
// pastikan pengumpulan milik siswa dan boleh diubah
$sql = "SELECT pt.id, pt.siswa_id, pt.status, pt.allow_resubmit FROM pengumpulan_tugas pt WHERE pt.id={$pengumpulan_id} LIMIT 1";
$res = query($sql); $row = $res? fetch_assoc($res): null;
if (!$row || (int)$row['siswa_id'] !== $uid) {
    echo json_encode(['success'=>false,'message'=>'Forbidden']);
    exit;
}
$allowed = ($row['status'] !== 'graded') || ((int)$row['allow_resubmit'] === 1);
if (!$allowed) {
    echo json_encode(['success'=>false,'message'=>'Pengumpulan sudah dinilai dan tidak diizinkan resubmit']);
    exit;
}
$isi_esc = escape_string($isi);
$ok = query("UPDATE pengumpulan_tugas SET isi_jawaban='{$isi_esc}', edited_at='".date('Y-m-d H:i:s')."' WHERE id={$pengumpulan_id}");
if (!$ok) {
    echo json_encode(['success'=>false,'message'=>'Gagal menyimpan pembaruan']);
    exit;
}
echo json_encode(['success'=>true,'message'=>'Pengumpulan diperbarui']);
