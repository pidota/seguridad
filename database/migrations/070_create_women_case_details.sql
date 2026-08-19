CREATE TABLE IF NOT EXISTS women_case_aggressors (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id INT UNSIGNED NOT NULL,
    first_names VARCHAR(150) NULL,
    paternal_surname VARCHAR(120) NULL,
    maternal_surname VARCHAR(120) NULL,
    rut VARCHAR(20) NULL,
    rut_normalized VARCHAR(12) NULL,
    birth_date DATE NULL,
    approximate_age VARCHAR(40) NULL,
    phone VARCHAR(30) NULL,
    address VARCHAR(255) NULL,
    occupation VARCHAR(150) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_women_case_aggressors_case (case_id),
    INDEX idx_women_case_aggressors_deleted_at (deleted_at),
    CONSTRAINT fk_women_case_aggressors_case FOREIGN KEY (case_id) REFERENCES women_cases(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS women_case_violence_types (
    case_id INT UNSIGNED NOT NULL,
    violence_type_id INT UNSIGNED NOT NULL,
    other_text VARCHAR(180) NULL,
    PRIMARY KEY (case_id, violence_type_id),
    CONSTRAINT fk_women_case_violence_case FOREIGN KEY (case_id) REFERENCES women_cases(id) ON DELETE CASCADE,
    CONSTRAINT fk_women_case_violence_type FOREIGN KEY (violence_type_id) REFERENCES women_violence_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS women_case_risk_factors (
    case_id INT UNSIGNED NOT NULL,
    risk_factor_id INT UNSIGNED NOT NULL,
    other_text VARCHAR(180) NULL,
    PRIMARY KEY (case_id, risk_factor_id),
    CONSTRAINT fk_women_case_risk_case FOREIGN KEY (case_id) REFERENCES women_cases(id) ON DELETE CASCADE,
    CONSTRAINT fk_women_case_risk_factor FOREIGN KEY (risk_factor_id) REFERENCES women_risk_factors(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS women_case_needs (
    case_id INT UNSIGNED NOT NULL,
    need_id INT UNSIGNED NOT NULL,
    other_text VARCHAR(180) NULL,
    PRIMARY KEY (case_id, need_id),
    CONSTRAINT fk_women_case_needs_case FOREIGN KEY (case_id) REFERENCES women_cases(id) ON DELETE CASCADE,
    CONSTRAINT fk_women_case_needs_need FOREIGN KEY (need_id) REFERENCES women_needs(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS women_case_previous_reports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id INT UNSIGNED NOT NULL,
    institution_name VARCHAR(180) NOT NULL,
    report_date DATE NULL,
    reference_number VARCHAR(120) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_women_case_previous_reports_case (case_id),
    CONSTRAINT fk_women_case_previous_reports_case FOREIGN KEY (case_id) REFERENCES women_cases(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS women_case_formal_reports (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id INT UNSIGNED NOT NULL,
    institution_id INT UNSIGNED NULL,
    institution_other VARCHAR(180) NULL,
    reference_number VARCHAR(120) NULL,
    report_date DATE NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_women_case_formal_reports_case (case_id),
    CONSTRAINT fk_women_case_formal_reports_case FOREIGN KEY (case_id) REFERENCES women_cases(id) ON DELETE CASCADE,
    CONSTRAINT fk_women_case_formal_reports_institution FOREIGN KEY (institution_id) REFERENCES women_formal_report_institutions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS women_case_protective_measures (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id INT UNSIGNED NOT NULL,
    measure_type_id INT UNSIGNED NULL,
    institution VARCHAR(180) NULL,
    start_date DATE NULL,
    end_date DATE NULL,
    cause_number VARCHAR(120) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_women_case_protective_measures_case (case_id),
    CONSTRAINT fk_women_case_protective_measures_case FOREIGN KEY (case_id) REFERENCES women_cases(id) ON DELETE CASCADE,
    CONSTRAINT fk_women_case_protective_measures_type FOREIGN KEY (measure_type_id) REFERENCES women_protective_measure_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS women_case_linked_minors (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_id INT UNSIGNED NOT NULL,
    age_range_id INT UNSIGNED NULL,
    gender VARCHAR(20) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_women_case_linked_minors_case (case_id),
    CONSTRAINT fk_women_case_linked_minors_case FOREIGN KEY (case_id) REFERENCES women_cases(id) ON DELETE CASCADE,
    CONSTRAINT fk_women_case_linked_minors_age_range FOREIGN KEY (age_range_id) REFERENCES women_minor_age_ranges(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
