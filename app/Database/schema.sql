CREATE TABLE IF NOT EXISTS staff (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nip VARCHAR(100) NOT NULL UNIQUE,
    almutqon_user_id INT UNSIGNED NULL,
    nama_lengkap VARCHAR(255) NOT NULL,
    jabatan VARCHAR(255) NOT NULL,
    avatar VARCHAR(255) NULL DEFAULT NULL,
    theme ENUM('light','dark','system') NOT NULL DEFAULT 'light',
    email VARCHAR(255) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('direktur', 'admin', 'staff') NOT NULL DEFAULT 'staff',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_staff_almutqon_user (almutqon_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS system_actors (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_key VARCHAR(100) NOT NULL UNIQUE,
    display_name VARCHAR(255) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dokumen (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    token CHAR(64) NOT NULL UNIQUE,
    code_6digit CHAR(6) NOT NULL UNIQUE,
    nomor_urut INT UNSIGNED NULL,
    jenis ENUM('dokumen','invoice') NOT NULL DEFAULT 'dokumen',
    no_surat VARCHAR(255) NOT NULL UNIQUE,
    perihal VARCHAR(255) NOT NULL,
    penerima VARCHAR(255) NOT NULL,
    tanggal_dokumen DATE NOT NULL,
    penandatangan VARCHAR(255) NOT NULL,
    jabatan VARCHAR(255) NOT NULL,
    staff_id BIGINT UNSIGNED NULL,
    system_actor_id BIGINT UNSIGNED NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'active',
    pdf_path VARCHAR(255) NULL,
    waktu_pengesahan TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_dokumen_staff FOREIGN KEY (staff_id) REFERENCES staff(id),
    CONSTRAINT fk_dokumen_system_actor FOREIGN KEY (system_actor_id) REFERENCES system_actors(id),
    INDEX idx_dokumen_status (status),
    INDEX idx_dokumen_jenis (jenis),
    INDEX idx_dokumen_nomor_urut (nomor_urut)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id BIGINT UNSIGNED NULL,
    system_actor_id BIGINT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    dokumen_id BIGINT UNSIGNED NULL,
    description TEXT NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_activity_staff FOREIGN KEY (staff_id) REFERENCES staff(id) ON DELETE SET NULL,
    CONSTRAINT fk_activity_system_actor FOREIGN KEY (system_actor_id) REFERENCES system_actors(id) ON DELETE SET NULL,
    CONSTRAINT fk_activity_dokumen FOREIGN KEY (dokumen_id) REFERENCES dokumen(id) ON DELETE SET NULL,
    INDEX idx_activity_login (action, ip_address, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
