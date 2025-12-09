README - Deployment & Rollback Notes
====================================

Purpose
-------
This file documents the recent fix for duplicated "Pilihan Ganda Kompleks (PGK)" answer keys in the teacher question builder and describes recommended steps to test, deploy, and rollback the change.

Summary of change
-----------------
- Centralized question-type UI behavior into `assets/js/script.js`.
- Removed dynamic fallback code that previously injected duplicate key blocks.
- Ensured the UI now relies solely on template (legacy) controls found in `guru/ujian/index.php`: `#kunci-pg`, `#kunci-pgk`, `#kunci-pgk-edit`, `#kunci-bs` and corresponding form inputs like `jawaban_pgk[]`.
- Removed temporary debug badge and `console.log` from `assets/js/script.js`.

Files modified
--------------
- `assets/js/script.js`  (primary changes: removed fallback injection, removed debug log, refined visibility logic)

Why this is safe
-----------------
- The change avoids injecting DOM elements at runtime and instead toggles visibility of existing template controls. This is more deterministic for production and avoids duplication caused by race or form-layout variations.
- No server-side logic was changed; only client-side UI behavior was adjusted.

Manual verification (quick)
--------------------------
1. Open browser and go to teacher question builder: `http://<your-server-or-ip>/lms_kgb2/guru/ujian/index.php`.
2. Hard-refresh to ensure latest assets are loaded: press `Ctrl+F5` (Windows) or clear cache and reload.
3. Create or open a question builder form (Add / Edit).
4. In `Tipe Soal`, choose `Pilihan Ganda Kompleks`.
   - Expected: only one block of checkboxes for the PGK key is visible and functional.
5. Switch to other types (Pilihan Ganda, Benar/Salah, Essay) to confirm respective fields show/hide correctly.

Git commands (PowerShell)
-------------------------
# from project root `C:\xampp\htdocs\lms_kgb2`

```powershell
# create branch (recommended)
git checkout -b fix/pgk-duplicate

# stage and commit changed file
git add assets/js/script.js
git commit -m "Fix: remove PGK fallback and debug log; rely on template controls"

# push branch to remote (if remote exists)
git remote get-url origin
git push -u origin fix/pgk-duplicate
```

If you prefer to commit directly to `main` (not recommended), replace branch steps with `git checkout main` and commit.

Cache / Public IP notes
-----------------------
- Because the app is served on a public IP, clients and any CDN/reverse-proxy might cache `assets/js/script.js`. Ensure caches are purged or instruct users to hard-refresh during verification.
- If you use a web proxy or CDN (e.g. Cloudflare), purge the cache for `/assets/js/script.js`.

Rollback plan
-------------
If something goes wrong after deployment, you can revert as follows:

1. If you pushed a branch and want to undo the commit locally before pushing:
```powershell
# undo last local commit but keep changes in working tree
git reset --soft HEAD~1
```

2. If you already pushed and want to revert a commit on the branch:
```powershell
# create a revert commit (safe for shared branches)
git revert <commit-hash>
```

3. If you must restore the previous file from an earlier commit:
```powershell
# restore file from commit
git checkout <old-commit-hash> -- assets/js/script.js
# then commit and push
```

Notes & next steps
------------------
- Recommended action: commit changes to a feature branch (suggested name `fix/pgk-duplicate`), verify on staging or local dev, then merge to main after review.
- If you want, I can also create a small automated test checklist (manual steps turned into a script) to validate the UI programmatically.

Contact
-------
If you need me to perform the git commit/push and you can provide remote access (or enable git on this environment), I can do it for you. Otherwise follow the Git commands above on your machine.

-- End of file
