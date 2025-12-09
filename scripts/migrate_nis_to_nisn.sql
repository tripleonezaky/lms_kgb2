-- scripts/migrate_nis_to_nisn.sql
-- Backup affected rows and copy `nis` into `nisn` for siswa where `nisn` is empty.
-- Run this in phpMyAdmin (SQL tab) or via mysql client against database `lms_kgb2`.

-- NOTE: This file will create a backup table named `users_backup_nisn` (if it
-- doesn't exist) containing the rows that will be updated. You can inspect
-- that table before running the UPDATE. If you prefer a CSV backup, export
-- the `users_backup_nisn` table after running this script.

START TRANSACTION;

-- How many rows would be updated?
SELECT COUNT(*) AS rows_to_update
FROM users
WHERE role='siswa'
  AND (nisn IS NULL OR TRIM(nisn) = '')
  AND (nis IS NOT NULL AND TRIM(nis) <> '');

-- Create backup table (only the first time). It stores id, nama_lengkap, nis, nisn and a timestamp.
CREATE TABLE IF NOT EXISTS users_backup_nisn AS
SELECT id, nama_lengkap, nis, nisn, NOW() AS backup_at
FROM users
WHERE role='siswa'
  AND (nisn IS NULL OR TRIM(nisn) = '')
  AND (nis IS NOT NULL AND TRIM(nis) <> '');

-- Perform the update: copy nis -> nisn where nisn empty
UPDATE users
SET nisn = nis
WHERE role='siswa'
  AND (nisn IS NULL OR TRIM(nisn) = '')
  AND (nis IS NOT NULL AND TRIM(nis) <> '');

-- Show how many rows were affected by the UPDATE
SELECT ROW_COUNT() AS rows_updated;

COMMIT;

-- After running, verify:
-- SELECT COUNT(*) FROM users WHERE role='siswa' AND (nisn IS NULL OR TRIM(nisn)='');
-- SELECT id, nama_lengkap, nis, nisn FROM users WHERE role='siswa' LIMIT 20;
