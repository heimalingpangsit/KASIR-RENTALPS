-- Opsional: tabel ini dibuat otomatis oleh aplikasi. Import manual hanya kalau hosting menolak CREATE TABLE dari PHP.
CREATE TABLE IF NOT EXISTS package_units (
  package_id INT UNSIGNED NOT NULL,
  unit_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (package_id, unit_id)
) ENGINE=InnoDB;
