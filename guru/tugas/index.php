<?php
/**
 * File: guru/tugas/index.php
 * Fitur minimal: daftar tugas milik guru dan form tambah sederhana (judul + deadline + deskripsi)
 */
session_start();
require_once '../../includes/check_session.php';
require_once '../../includes/check_role.php';
check_role(['guru']);
require_once '../../config/database.php';
require_once '../../includes/functions.php';

$guru_id = (int)$_SESSION['user_id'];

// Ambil assignment guru untuk dropdown
$sql_assign = "
    SELECT ag.id AS assignment_id, k.nama_kelas, mp.nama_mapel, ta.nama_tahun_ajaran
    FROM assignment_guru ag
    JOIN kelas k ON ag.kelas_id = k.id
    JOIN mata_pelajaran mp ON ag.mapel_id = mp.id
    JOIN tahun_ajaran ta ON ag.tahun_ajaran_id = ta.id
    WHERE ag.guru_id = {$guru_id}
    ORDER BY ta.id DESC, k.nama_kelas, mp.nama_mapel
";
$assignments = fetch_all(query($sql_assign));

// Handle create tugas (mendukung multi-assignment)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create'])) {
    $assign_ids = isset($_POST['assignment_ids']) && is_array($_POST['assignment_ids']) ? array_map('intval', $_POST['assignment_ids']) : [];
    $judul = escape_string($_POST['judul_tugas'] ?? '');
    $isi_tugas = escape_string($_POST['isi_tugas'] ?? '');
    $jenis_tugas = isset($_POST['jenis_tugas']) && in_array($_POST['jenis_tugas'], ['Tugas','Quiz','UH']) ? $_POST['jenis_tugas'] : 'Tugas';
    $deadline = escape_string($_POST['deadline'] ?? '');

    if (!empty($assign_ids) && $judul !== '' && $deadline !== '') {
        @query("ALTER TABLE tugas ADD COLUMN isi_tugas TEXT NULL AFTER deskripsi");
        @query("ALTER TABLE tugas ADD COLUMN jenis_tugas ENUM('Tugas','Quiz','UH') NOT NULL DEFAULT 'Tugas' AFTER isi_tugas");
        @query("ALTER TABLE tugas ADD COLUMN file_path VARCHAR(255) NULL AFTER jenis_tugas");
        $file_path = '';
        if (!empty($_FILES['lampiran']['name']) && $_FILES['lampiran']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['pdf','doc','docx','ppt','pptx','xls','xlsx','zip'];
            $ext = strtolower(pathinfo($_FILES['lampiran']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                $dir = __DIR__ . '/../../assets/uploads/materi/';
                if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
                $newName = 'tugas-'.time().'-'.bin2hex(random_bytes(3)).'.'.$ext;
                $dest = $dir.$newName;
                if (move_uploaded_file($_FILES['lampiran']['tmp_name'], $dest)) {
                    $file_path = 'materi/'.$newName;
                }
            }
        }
        $created = 0; $invalid = 0;
        foreach ($assign_ids as $assignment_id) {
            $check = query("SELECT id FROM assignment_guru WHERE id = {$assignment_id} AND guru_id = {$guru_id} LIMIT 1");
            if ($check && fetch_assoc($check)) {
                $sql_ins = "INSERT INTO tugas (assignment_id, judul_tugas, isi_tugas, jenis_tugas, file_path, deadline) VALUES ({$assignment_id}, '{$judul}', '{$isi_tugas}', '".escape_string($jenis_tugas)."', '".escape_string($file_path)."', '{$deadline}')";
                if (query($sql_ins)) $created++;
            } else { $invalid++; }
        }
        if ($created>0) set_flash('success', 'Tugas berhasil dibuat untuk '.(int)$created.' assignment.'); else set_flash('error','Gagal membuat tugas.');
    } else {
        set_flash('error', 'Lengkapi data tugas dan pilih assignment.');
    }
    redirect('index.php');
}

// Ambil list tugas milik guru
$sql_list = "
    SELECT t.id, t.judul_tugas, t.jenis_tugas, t.deadline, t.created_at,
           k.nama_kelas, mp.nama_mapel
    FROM tugas t
    JOIN assignment_guru ag ON t.assignment_id = ag.id
    JOIN kelas k ON ag.kelas_id = k.id
    JOIN mata_pelajaran mp ON ag.mapel_id = mp.id
    WHERE ag.guru_id = {$guru_id}
    ORDER BY t.created_at DESC
";
$tugas_list = fetch_all(query($sql_list));
$flash = get_flash();

// Handle delete tugas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $tid = (int)$_POST['delete_id'];
    $cek = query("SELECT t.id FROM tugas t JOIN assignment_guru ag ON t.assignment_id=ag.id WHERE t.id={$tid} AND ag.guru_id={$guru_id} LIMIT 1");
    if ($cek && fetch_assoc($cek)) {
        if (query("DELETE FROM tugas WHERE id={$tid}")) set_flash('success','Tugas dihapus.'); else set_flash('error','Gagal menghapus tugas.');
    } else {
        set_flash('error','Tugas tidak valid.');
    }
    redirect('index.php');
}

// Handle update tugas
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $tid = (int)($_POST['tugas_id'] ?? 0);
    $judul = escape_string($_POST['judul_tugas'] ?? '');
    $isi_tugas = escape_string($_POST['isi_tugas'] ?? '');
    $jenis_tugas = isset($_POST['jenis_tugas']) && in_array($_POST['jenis_tugas'], ['Tugas','Quiz','UH']) ? $_POST['jenis_tugas'] : 'Tugas';
    $deadline = escape_string($_POST['deadline'] ?? '');
    $cek = query("SELECT t.id FROM tugas t JOIN assignment_guru ag ON t.assignment_id=ag.id WHERE t.id={$tid} AND ag.guru_id={$guru_id} LIMIT 1");
    if ($cek && fetch_assoc($cek)) {
        $file_snippet = '';
        if (!empty($_FILES['lampiran']['name']) && $_FILES['lampiran']['error'] === UPLOAD_ERR_OK) {
            @query("ALTER TABLE tugas ADD COLUMN file_path VARCHAR(255) NULL AFTER jenis_tugas");
            $allowed = ['pdf','doc','docx','ppt','pptx','xls','xlsx','zip'];
            $ext = strtolower(pathinfo($_FILES['lampiran']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                $dir = __DIR__ . '/../../assets/uploads/materi/';
                if (!is_dir($dir)) { @mkdir($dir, 0777, true); }
                $newName = 'tugas-'.time().'-'.bin2hex(random_bytes(3)).'.'.$ext;
                $dest = $dir.$newName;
                if (move_uploaded_file($_FILES['lampiran']['tmp_name'], $dest)) {
                    $fp = escape_string('materi/'.$newName);
                    $file_snippet = ", file_path='".$fp."'";
                }
            }
        }
        $ok = query("UPDATE tugas SET judul_tugas='{$judul}', isi_tugas='{$isi_tugas}', jenis_tugas='".escape_string($jenis_tugas)."', deadline='{$deadline}'".$file_snippet." WHERE id={$tid}");
        set_flash($ok? 'success':'error', $ok? 'Tugas diperbarui.':'Gagal memperbarui tugas.');
    } else {
        set_flash('error','Tugas tidak valid.');
    }
    redirect('index.php');
}

// Handle load data edit
$edit_row = null;
if (isset($_GET['edit_id'])) {
    $eid = (int)$_GET['edit_id'];
    $res = query("SELECT t.*, k.nama_kelas, mp.nama_mapel FROM tugas t JOIN assignment_guru ag ON t.assignment_id=ag.id JOIN kelas k ON ag.kelas_id=k.id JOIN mata_pelajaran mp ON ag.mapel_id=mp.id WHERE t.id={$eid} AND ag.guru_id={$guru_id} LIMIT 1");
    $edit_row = $res? fetch_assoc($res): null;
}

?><!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Tugas - Guru</title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        .container{max-width:1100px;margin:10px auto;padding:10px}
        .card{background:#fff;border:1px solid #ddd;border-radius:8px;margin-bottom:12px;overflow:hidden;box-shadow:0 2px 6px rgba(0,0,0,.04)}
        .card-header{padding:12px 16px;background:#f5f5f5;font-weight:600}
        .card-body{padding:16px}
        .row{display:flex;gap:12px;flex-wrap:wrap}
        .row .col{flex:1 1 280px}
        label{display:block;margin-bottom:10px;font-weight:600}
        input[type=text],input[type=datetime-local],select,textarea{width:100%;padding:10px;border:1px solid #ced4da;border-radius:6px;background:#fff;box-sizing:border-box}
        textarea{min-height:240px;padding:12px;line-height:1.5}
        textarea[name=isi_tugas]{margin-top:8px}
        .row + div{margin-top:12px}
        input:focus,select:focus,textarea:focus{outline:none;border-color:#2c7be5;box-shadow:0 0 0 3px rgba(44,123,229,.15)}
        .btn{display:inline-block;padding:10px 14px;border-radius:6px;border:1px solid #2c7be5;background:#2c7be5;color:#fff;cursor:pointer;text-decoration:none}
        .btn:hover{filter:brightness(0.95)}
        .table{width:100%;border-collapse:collapse}
        .table th,.table td{border:1px solid #e5e5e5;padding:10px}
        .table th{background:#fafafa;text-align:left}
        .alert{padding:10px;border-radius:6px;margin-bottom:10px}
        .alert-error{background:#fdecea;color:#b00020}
        .alert-success{background:#e6f4ea;color:#1e7e34}
        .muted{color:#64748b;font-size:12px}
    </style>
    <script>
      async function createMeeting(tugasId){
        const provider = prompt('Provider (zoom/teams/jitsi/webex):','jitsi');
        if(!provider) return; const p = provider.toLowerCase();
        let joinUrl = '';
        if(p !== 'jitsi'){
          joinUrl = prompt('Tempelkan Join URL (Zoom/Teams/Webex):','');
          if(!joinUrl) return;
        }
        const title = prompt('Judul meeting (opsional):','');
        const fd = new FormData(); fd.append('context_type','tugas'); fd.append('context_id', tugasId); fd.append('provider', p); if(joinUrl) fd.append('join_url', joinUrl); if(title) fd.append('meeting_title', title);
        const res = await fetch('../../includes/meetings_api.php?action=create', { method:'POST', body: fd });
        const js = await res.json(); if(!js.success){ alert(js.message||'Gagal membuat meeting'); } else { alert('Meeting dibuat'); loadMeetings(tugasId); }
      }
      async function loadMeetings(tugasId){
        const box = document.getElementById('meeting-items-'+tugasId);
        if(!box) return; box.innerHTML = 'Memuat...';
        const res = await fetch(`../../includes/meetings_api.php?action=list&context_type=tugas&context_id=${tugasId}`);
        const js = await res.json(); if(!js.success){ box.innerHTML = 'Gagal memuat'; return; }
        const arr = js.data.meetings||[];
        if(arr.length===0){ box.innerHTML = '<span style="color:#64748b">Belum ada meeting.</span>'; return; }
        box.innerHTML = arr.map(m=> `<div style=\"display:flex;justify-content:space-between;align-items:center;border-bottom:1px dashed #e5e7eb;padding:4px 0\"><div>${m.meeting_title?('<strong>'+m.meeting_title+'</strong> - '):''}<a href=\"${m.join_url}\" target=\"_blank\">${m.provider.toUpperCase()} - Join</a>${m.scheduled_at? ' <span style=\"color:#64748b\">('+m.scheduled_at+')</span>':''}</div><button onclick=\"deleteMeeting(${m.id},${tugasId})\" style=\"border:0;background:#fee2e2;color:#991b1b;border-radius:6px;padding:4px 8px\">Hapus</button></div>`).join('');
      }
      async function deleteMeeting(meetingId, tugasId){
        const fd = new FormData(); fd.append('meeting_id', meetingId);
        const res = await fetch('../../includes/meetings_api.php?action=delete', { method:'POST', body: fd });
        const js = await res.json(); if(!js.success){ alert(js.message||'Gagal menghapus'); } else { loadMeetings(tugasId); }
      }
      const chatState = {};
      async function getThread(contextType, contextId){
        const res = await fetch(`../../includes/chat_api.php?action=get_or_create_thread&context_type=${contextType}&context_id=${contextId}`);
        const js = await res.json(); if(!js.success) throw new Error(js.message||'gagal');
        return js.data.thread_id;
      }
      async function loadMessages(contextId){
        const st = chatState[contextId]; if(!st) return;
        const url = `../../includes/chat_api.php?action=list_messages&thread_id=${st.threadId}` + (st.lastId? `&after_id=${st.lastId}` : '');
        const res = await fetch(url); const js = await res.json(); if(!js.success) return;
        const list = js.data.messages || [];
        const box = document.getElementById('chat-messages-'+contextId);
        list.forEach(m=>{
          const div = document.createElement('div');
          div.style.borderBottom='1px dashed #e5e7eb'; div.style.padding='6px 0';
          div.innerHTML = `<div><strong>${m.sender_name||'User'}</strong> <span style=\"color:#64748b;font-size:12px\">${m.created_at}</span></div><div>${(m.message||'').replaceAll('\n','<br>')}</div>`;
          box.appendChild(div);
          st.lastId = Math.max(st.lastId||0, m.id);
        });
        if(list.length>0){ box.scrollTop = box.scrollHeight; }
      }
      async function openChat(contextType, contextId){
        const panel = document.getElementById('chat-'+contextId);
        if(!panel) return; panel.style.display='block';
        if(!chatState[contextId]){
          const threadId = await getThread(contextType, contextId);
          chatState[contextId] = { threadId, lastId: 0, interval: null };
          await loadMessages(contextId);
          chatState[contextId].interval = setInterval(()=> loadMessages(contextId), 4000);
        }
      }
      async function sendChatMessage(contextId){
        const st = chatState[contextId]; if(!st) return;
        const input = document.getElementById('chat-input-'+contextId);
        const msg = input.value.trim(); if(!msg) return;
        const fd = new FormData(); fd.append('thread_id', st.threadId); fd.append('message', msg);
        const res = await fetch('../../includes/chat_api.php?action=send_message', { method:'POST', body: fd });
        const js = await res.json(); if(js.success){ input.value=''; loadMessages(contextId); }
      }
      window.addEventListener('DOMContentLoaded', ()=>{
        document.querySelectorAll('[id^="meeting-items-"]').forEach(el=>{
          const id = parseInt(el.id.replace('meeting-items-','')); if(id) loadMeetings(id);
        });
      });
    </script>
</head>
<body>
<div class="container">
    <div class="card">
      <div class="card-header"><?php echo $edit_row? 'Edit Tugas' : 'Buat Tugas'; ?></div>
      <div class="card-body">
        <div style="margin-bottom:10px; display:flex; gap:8px; flex-wrap:wrap">
          <a href="../dashboard.php" class="btn back-btn" onclick="var href=this.getAttribute('href'); document.body.style.transition='opacity .22s'; document.body.style.opacity=0; setTimeout(function(){ if (history.length>1) { history.back(); } else { window.location=href; } },220); return false;"><i class="fas fa-arrow-left" aria-hidden="true"></i> Kembali</a>
          <?php if ($edit_row): ?><a href="index.php" class="btn">Buat Baru</a><?php endif; ?>
        </div>
        <?php if ($flash): ?>
          <div class="alert alert-<?php echo htmlspecialchars(($flash['type']) ?? ''); ?>"><?php echo htmlspecialchars(($flash['message']) ?? ''); ?></div>
        <?php endif; ?>
        <?php if ($edit_row): ?>
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="update" value="1">
          <input type="hidden" name="tugas_id" value="<?php echo (int)$edit_row['id']; ?>">
          <div class="row">
            <div class="col">
              <label>Assignment (Info)</label>
              <input type="text" value="<?php echo htmlspecialchars(($edit_row['nama_kelas'].' - '.$edit_row['nama_mapel']) ?? ''); ?>" disabled />
            </div>
            <div class="col">
              <label>Judul Tugas</label>
              <input type="text" name="judul_tugas" value="<?php echo htmlspecialchars(($edit_row['judul_tugas']) ?? ''); ?>" required />
            </div>
            <div class="col">
              <label>Jenis Tugas</label>
              <select name="jenis_tugas" required>
                <?php $j = $edit_row['jenis_tugas'] ?? 'Tugas'; ?>
                <option value="Tugas" <?php echo $j==='Tugas'?'selected':''; ?>>Tugas</option>
                <option value="Quiz" <?php echo $j==='Quiz'?'selected':''; ?>>Quiz</option>
                <option value="UH" <?php echo $j==='UH'?'selected':''; ?>>Ulangan Harian</option>
              </select>
            </div>
          </div>
                    <tr>
                      <td><?php echo $i+1; ?></td>
                      <td><?php echo htmlspecialchars(($t['judul_tugas']) ?? ''); ?></td>
                      <td><?php echo htmlspecialchars(($t['nama_mapel']) ?? ''); ?></td>
                      <td><?php echo htmlspecialchars(($t['nama_kelas']) ?? ''); ?></td>
                      <td>
                        <a class="btn icon-btn" href="detail.php?tugas_id=<?php echo (int)$t['id']; ?>" title="Detail" aria-label="Detail">Detail</a>
                        <a class="btn icon-btn" href="index.php?edit_id=<?php echo (int)$t['id']; ?>" title="Edit" aria-label="Edit">Edit</a>
                      </td>
            <div class="col">
              <label>Lampiran (opsional)</label>
              <input type="file" name="lampiran" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.zip" />
              <?php if (!empty($edit_row['file_path'])): ?>
                <div class="muted" style="margin-top:4px">File saat ini: <a href="../../assets/uploads/<?php echo htmlspecialchars(($edit_row['file_path']) ?? ''); ?>" target="_blank">Lihat</a></div>
              <?php endif; ?>
            </div>
          </div>
          <div style="margin-top:10px">
            <button class="btn" type="submit">Simpan Perubahan</button>
          </div>
        </form>
        <?php else: ?>
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="create" value="1">
          <div class="row">
            <div class="col">
              <label>Assignment (Kelas & Mapel)</label>
              <div style="border:1px solid #e5e7eb;border-radius:8px;padding:10px;max-height:220px;overflow:auto">
                <?php foreach ($assignments as $a): ?>
                  <label style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
                    <input type="checkbox" name="assignment_ids[]" value="<?php echo (int)$a['assignment_id']; ?>">
                    <span>TA <?php echo htmlspecialchars(($a['nama_tahun_ajaran']) ?? ''); ?> - <?php echo htmlspecialchars(($a['nama_kelas']) ?? ''); ?> - <?php echo htmlspecialchars(($a['nama_mapel']) ?? ''); ?></span>
                  </label>
                <?php endforeach; ?>
              </div>
              <div class="muted">Pilih satu atau lebih assignment sebagai target tugas.</div>
            </div>
            <div class="col">
              <label>Judul Tugas</label>
              <input type="text" name="judul_tugas" required />
            </div>
            <div class="col">
              <label>Jenis Tugas</label>
              <select name="jenis_tugas" required>
                <option value="Tugas">Tugas</option>
                <option value="Quiz">Quiz</option>
                <option value="UH">Ulangan Harian</option>
              </select>
            </div>
          </div>
                    <div>
            <label>Isi Tugas</label>
            <textarea name="isi_tugas" rows="8" placeholder="Instruksi/detail tugas yang harus dikerjakan siswa..."></textarea>
          </div>
          <div class="row">
            <div class="col">
              <label>Deadline</label>
              <input type="datetime-local" name="deadline" required />
            </div>
            <div class="col">
              <label>Lampiran (opsional)</label>
              <input type="file" name="lampiran" accept=".pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.zip" />
            </div>
          </div>
          <div style="margin-top:10px">
            <button class="btn" type="submit">Simpan</button>
          </div>
        </form>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-header">Daftar Tugas</div>
      <div class="card-body">
        <table class="table">
          <thead>
            <tr>
              <th>Judul</th>
              <th>Jenis</th>
              <th>Kelas</th>
              <th>Mapel</th>
              <th>Deadline</th>
              <th>Dibuat</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($tugas_list)): ?>
            <tr><td colspan="7">Belum ada tugas.</td></tr>
          <?php else: foreach ($tugas_list as $t): ?>
            <tr>
              <td><?php echo htmlspecialchars(($t['judul_tugas']) ?? ''); ?></td>
              <td><?php echo htmlspecialchars($t['jenis_tugas'] ?? 'Tugas'); ?></td>
              <td><?php echo htmlspecialchars(($t['nama_kelas']) ?? ''); ?></td>
              <td><?php echo htmlspecialchars(($t['nama_mapel']) ?? ''); ?></td>
              <td><?php echo htmlspecialchars(($t['deadline']) ?? ''); ?></td>
              <td><?php echo htmlspecialchars(($t['created_at']) ?? ''); ?></td>
              <td>
                <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                  <a class="btn" href="detail.php?tugas_id=<?php echo (int)$t['id']; ?>">Detail</a>
                  <a class="btn" href="index.php?edit_id=<?php echo (int)$t['id']; ?>">Edit</a>
                  <form method="post" onsubmit="return confirm('Hapus tugas ini? Pengumpulan terkait juga akan ikut terhapus.');" style="display:inline">
                    <input type="hidden" name="delete_id" value="<?php echo (int)$t['id']; ?>">
                    <button type="submit" class="btn" style="background:#dc2626;border-color:#dc2626" title="Delete"><i class="fas fa-trash" aria-hidden="true"></i></button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
</div>
</body>
</html>

