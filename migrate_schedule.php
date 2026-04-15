<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$pdo->exec("CREATE TABLE IF NOT EXISTS `schedule` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    visit_date DATE NOT NULL,
    ma_id INT NOT NULL,
    patient_id INT NOT NULL,
    visit_time TIME NULL,
    visit_order SMALLINT NOT NULL DEFAULT 0,
    status ENUM('pending','en_route','completed','missed') NOT NULL DEFAULT 'pending',
    notes TEXT NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ma_id) REFERENCES staff(id),
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (created_by) REFERENCES staff(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

echo "schedule table created (or already exists).\n";
