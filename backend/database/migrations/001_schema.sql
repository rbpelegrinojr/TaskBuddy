-- TaskBuddy schema (MySQL 8, InnoDB, utf8mb4)

CREATE TABLE users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(150) NOT NULL,
  role ENUM('client','provider','admin') NOT NULL,
  status ENUM('active','suspended') NOT NULL DEFAULT 'active',
  phone VARCHAR(30) NULL,
  last_login_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_role_status (role, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_profiles (
  user_id BIGINT UNSIGNED NOT NULL,
  bio TEXT NULL,
  location VARCHAR(150) NULL,
  headline VARCHAR(150) NULL,
  experience_summary TEXT NULL,
  years_experience TINYINT UNSIGNED NULL,
  min_rate DECIMAL(12,2) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id),
  CONSTRAINT fk_profiles_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  description VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_categories_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE skills (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_skills_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE category_skills (
  category_id INT UNSIGNED NOT NULL,
  skill_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (category_id, skill_id),
  KEY idx_category_skills_skill (skill_id),
  CONSTRAINT fk_cs_category FOREIGN KEY (category_id) REFERENCES service_categories (id) ON DELETE CASCADE,
  CONSTRAINT fk_cs_skill FOREIGN KEY (skill_id) REFERENCES skills (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE provider_categories (
  provider_id BIGINT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (provider_id, category_id),
  KEY idx_pc_category (category_id),
  CONSTRAINT fk_pc_provider FOREIGN KEY (provider_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_pc_category FOREIGN KEY (category_id) REFERENCES service_categories (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE provider_skills (
  provider_id BIGINT UNSIGNED NOT NULL,
  skill_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (provider_id, skill_id),
  KEY idx_ps_skill (skill_id),
  CONSTRAINT fk_ps_provider FOREIGN KEY (provider_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_ps_skill FOREIGN KEY (skill_id) REFERENCES skills (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Weekly availability windows. day_of_week: 0 = Sunday ... 6 = Saturday.
CREATE TABLE provider_availability (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  provider_id BIGINT UNSIGNED NOT NULL,
  day_of_week TINYINT UNSIGNED NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_availability_slot (provider_id, day_of_week, start_time, end_time),
  CONSTRAINT fk_pa_provider FOREIGN KEY (provider_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT chk_pa_day CHECK (day_of_week BETWEEN 0 AND 6),
  CONSTRAINT chk_pa_time CHECK (end_time > start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_blocks (
  blocker_id BIGINT UNSIGNED NOT NULL,
  blocked_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (blocker_id, blocked_id),
  KEY idx_blocks_blocked (blocked_id),
  CONSTRAINT fk_blocks_blocker FOREIGN KEY (blocker_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_blocks_blocked FOREIGN KEY (blocked_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_reports (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  reporter_id BIGINT UNSIGNED NOT NULL,
  reported_id BIGINT UNSIGNED NOT NULL,
  reason VARCHAR(1000) NOT NULL,
  status ENUM('OPEN','REVIEWED','DISMISSED') NOT NULL DEFAULT 'OPEN',
  admin_note VARCHAR(1000) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_reports_status (status),
  CONSTRAINT fk_reports_reporter FOREIGN KEY (reporter_id) REFERENCES users (id) ON DELETE CASCADE,
  CONSTRAINT fk_reports_reported FOREIGN KEY (reported_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_requests (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_id BIGINT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  title VARCHAR(200) NOT NULL,
  description TEXT NOT NULL,
  requirements TEXT NULL,
  deadline DATETIME NOT NULL,
  compensation DECIMAL(12,2) NOT NULL,
  estimated_hours DECIMAL(6,2) NULL,
  requires_exclusive TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('OPEN','PENDING_ACCEPTANCE','IN_PROGRESS','SUBMITTED','UNDER_REVIEW','REVISION_REQUESTED','COMPLETED','CANCELLED','EXPIRED','DISPUTED') NOT NULL DEFAULT 'OPEN',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_requests_client (client_id, status),
  KEY idx_requests_status_deadline (status, deadline),
  KEY idx_requests_category (category_id),
  CONSTRAINT fk_requests_client FOREIGN KEY (client_id) REFERENCES users (id),
  CONSTRAINT fk_requests_category FOREIGN KEY (category_id) REFERENCES service_categories (id),
  CONSTRAINT chk_requests_comp CHECK (compensation > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_request_skills (
  request_id BIGINT UNSIGNED NOT NULL,
  skill_id INT UNSIGNED NOT NULL,
  is_mandatory TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (request_id, skill_id),
  KEY idx_srs_skill (skill_id),
  CONSTRAINT fk_srs_request FOREIGN KEY (request_id) REFERENCES service_requests (id) ON DELETE CASCADE,
  CONSTRAINT fk_srs_skill FOREIGN KEY (skill_id) REFERENCES skills (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_request_attachments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_id BIGINT UNSIGNED NOT NULL,
  uploaded_by BIGINT UNSIGNED NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name CHAR(40) NOT NULL,
  mime_type VARCHAR(100) NOT NULL,
  size_bytes BIGINT UNSIGNED NOT NULL,
  sha256 CHAR(64) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sra_stored (stored_name),
  KEY idx_sra_request (request_id),
  CONSTRAINT fk_sra_request FOREIGN KEY (request_id) REFERENCES service_requests (id) ON DELETE CASCADE,
  CONSTRAINT fk_sra_user FOREIGN KEY (uploaded_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE matching_configurations (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  version INT UNSIGNED NOT NULL,
  algorithm_version VARCHAR(20) NOT NULL,
  weight_category TINYINT UNSIGNED NOT NULL,
  weight_skills TINYINT UNSIGNED NOT NULL,
  weight_availability TINYINT UNSIGNED NOT NULL,
  weight_budget TINYINT UNSIGNED NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 0,
  note VARCHAR(255) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mc_version (version),
  CONSTRAINT fk_mc_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
  CONSTRAINT chk_mc_total CHECK (weight_category + weight_skills + weight_availability + weight_budget = 100),
  CONSTRAINT chk_mc_range CHECK (weight_category <= 100 AND weight_skills <= 100 AND weight_availability <= 100 AND weight_budget <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE matching_results (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_id BIGINT UNSIGNED NOT NULL,
  configuration_id INT UNSIGNED NOT NULL,
  algorithm_version VARCHAR(20) NOT NULL,
  weights_json JSON NOT NULL,
  candidates_evaluated INT UNSIGNED NOT NULL DEFAULT 0,
  eligible_count INT UNSIGNED NOT NULL DEFAULT 0,
  exclusion_summary_json JSON NOT NULL,
  triggered_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_mr_request (request_id, id),
  CONSTRAINT fk_mr_request FOREIGN KEY (request_id) REFERENCES service_requests (id) ON DELETE CASCADE,
  CONSTRAINT fk_mr_config FOREIGN KEY (configuration_id) REFERENCES matching_configurations (id),
  CONSTRAINT fk_mr_user FOREIGN KEY (triggered_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE matching_result_details (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  result_id BIGINT UNSIGNED NOT NULL,
  provider_id BIGINT UNSIGNED NOT NULL,
  rank_position INT UNSIGNED NOT NULL,
  final_score DECIMAL(5,2) NOT NULL,
  category_score DECIMAL(5,2) NOT NULL,
  skills_score DECIMAL(5,2) NOT NULL,
  availability_score DECIMAL(5,2) NOT NULL,
  budget_score DECIMAL(5,2) NOT NULL,
  budget_status ENUM('WITHIN_BUDGET','OVER_BUDGET') NOT NULL,
  explanation_json JSON NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_mrd_provider (result_id, provider_id),
  UNIQUE KEY uq_mrd_rank (result_id, rank_position),
  KEY idx_mrd_provider (provider_id),
  CONSTRAINT fk_mrd_result FOREIGN KEY (result_id) REFERENCES matching_results (id) ON DELETE CASCADE,
  CONSTRAINT fk_mrd_provider FOREIGN KEY (provider_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_invitations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_id BIGINT UNSIGNED NOT NULL,
  provider_id BIGINT UNSIGNED NOT NULL,
  client_id BIGINT UNSIGNED NOT NULL,
  matching_result_id BIGINT UNSIGNED NULL,
  message VARCHAR(1000) NULL,
  status ENUM('PENDING','ACCEPTED','DECLINED','EXPIRED','WITHDRAWN') NOT NULL DEFAULT 'PENDING',
  -- pending_key is request_id while PENDING and NULL otherwise: at most one pending invitation per request.
  pending_key BIGINT UNSIGNED NULL,
  expires_at DATETIME NOT NULL,
  responded_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_invitation_pending (pending_key),
  KEY idx_inv_provider (provider_id, status),
  KEY idx_inv_request (request_id),
  KEY idx_inv_expiry (status, expires_at),
  CONSTRAINT fk_inv_request FOREIGN KEY (request_id) REFERENCES service_requests (id) ON DELETE CASCADE,
  CONSTRAINT fk_inv_provider FOREIGN KEY (provider_id) REFERENCES users (id),
  CONSTRAINT fk_inv_client FOREIGN KEY (client_id) REFERENCES users (id),
  CONSTRAINT fk_inv_result FOREIGN KEY (matching_result_id) REFERENCES matching_results (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_assignments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  request_id BIGINT UNSIGNED NOT NULL,
  invitation_id BIGINT UNSIGNED NOT NULL,
  client_id BIGINT UNSIGNED NOT NULL,
  provider_id BIGINT UNSIGNED NOT NULL,
  assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_assignment_request (request_id),
  UNIQUE KEY uq_assignment_invitation (invitation_id),
  KEY idx_assign_provider (provider_id),
  KEY idx_assign_client (client_id),
  CONSTRAINT fk_asg_request FOREIGN KEY (request_id) REFERENCES service_requests (id),
  CONSTRAINT fk_asg_invitation FOREIGN KEY (invitation_id) REFERENCES service_invitations (id),
  CONSTRAINT fk_asg_client FOREIGN KEY (client_id) REFERENCES users (id),
  CONSTRAINT fk_asg_provider FOREIGN KEY (provider_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE conversations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  assignment_id BIGINT UNSIGNED NOT NULL,
  client_id BIGINT UNSIGNED NOT NULL,
  provider_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_conv_assignment (assignment_id),
  KEY idx_conv_client (client_id),
  KEY idx_conv_provider (provider_id),
  CONSTRAINT fk_conv_assignment FOREIGN KEY (assignment_id) REFERENCES service_assignments (id) ON DELETE CASCADE,
  CONSTRAINT fk_conv_client FOREIGN KEY (client_id) REFERENCES users (id),
  CONSTRAINT fk_conv_provider FOREIGN KEY (provider_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE messages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  conversation_id BIGINT UNSIGNED NOT NULL,
  sender_id BIGINT UNSIGNED NOT NULL,
  body TEXT NOT NULL,
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_msg_conv (conversation_id, id),
  KEY idx_msg_unread (conversation_id, sender_id, read_at),
  CONSTRAINT fk_msg_conv FOREIGN KEY (conversation_id) REFERENCES conversations (id) ON DELETE CASCADE,
  CONSTRAINT fk_msg_sender FOREIGN KEY (sender_id) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE task_submissions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  assignment_id BIGINT UNSIGNED NOT NULL,
  submitted_by BIGINT UNSIGNED NOT NULL,
  note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_sub_assignment (assignment_id),
  CONSTRAINT fk_sub_assignment FOREIGN KEY (assignment_id) REFERENCES service_assignments (id) ON DELETE CASCADE,
  CONSTRAINT fk_sub_user FOREIGN KEY (submitted_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE submission_files (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  submission_id BIGINT UNSIGNED NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name CHAR(40) NOT NULL,
  mime_type VARCHAR(100) NOT NULL,
  size_bytes BIGINT UNSIGNED NOT NULL,
  sha256 CHAR(64) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_sf_stored (stored_name),
  KEY idx_sf_submission (submission_id),
  CONSTRAINT fk_sf_submission FOREIGN KEY (submission_id) REFERENCES task_submissions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE task_revisions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  assignment_id BIGINT UNSIGNED NOT NULL,
  submission_id BIGINT UNSIGNED NOT NULL,
  requested_by BIGINT UNSIGNED NOT NULL,
  reason TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_rev_assignment (assignment_id),
  CONSTRAINT fk_rev_assignment FOREIGN KEY (assignment_id) REFERENCES service_assignments (id) ON DELETE CASCADE,
  CONSTRAINT fk_rev_submission FOREIGN KEY (submission_id) REFERENCES task_submissions (id) ON DELETE CASCADE,
  CONSTRAINT fk_rev_user FOREIGN KEY (requested_by) REFERENCES users (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_disputes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  assignment_id BIGINT UNSIGNED NOT NULL,
  opened_by BIGINT UNSIGNED NOT NULL,
  reason TEXT NOT NULL,
  previous_status VARCHAR(30) NOT NULL,
  status ENUM('OPEN','RESOLVED') NOT NULL DEFAULT 'OPEN',
  resolution ENUM('COMPLETE_TASK','CANCEL_TASK','RESUME_TASK') NULL,
  resolution_note VARCHAR(1000) NULL,
  resolved_by BIGINT UNSIGNED NULL,
  resolved_at DATETIME NULL,
  -- open_key is assignment_id while OPEN and NULL once resolved: at most one open dispute per assignment.
  open_key BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_dispute_open (open_key),
  KEY idx_disp_status (status),
  CONSTRAINT fk_disp_assignment FOREIGN KEY (assignment_id) REFERENCES service_assignments (id),
  CONSTRAINT fk_disp_opened FOREIGN KEY (opened_by) REFERENCES users (id),
  CONSTRAINT fk_disp_resolver FOREIGN KEY (resolved_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE simulated_payments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  reference VARCHAR(40) NOT NULL,
  request_id BIGINT UNSIGNED NOT NULL,
  assignment_id BIGINT UNSIGNED NOT NULL,
  client_id BIGINT UNSIGNED NOT NULL,
  provider_id BIGINT UNSIGNED NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  state ENUM('PENDING','COMPLETED','CANCELLED','ON_HOLD') NOT NULL DEFAULT 'PENDING',
  is_simulated TINYINT(1) NOT NULL DEFAULT 1,
  completed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_payment_reference (reference),
  UNIQUE KEY uq_payment_request (request_id),
  UNIQUE KEY uq_payment_assignment (assignment_id),
  KEY idx_pay_client (client_id, state),
  KEY idx_pay_provider (provider_id, state),
  CONSTRAINT fk_pay_request FOREIGN KEY (request_id) REFERENCES service_requests (id),
  CONSTRAINT fk_pay_assignment FOREIGN KEY (assignment_id) REFERENCES service_assignments (id),
  CONSTRAINT fk_pay_client FOREIGN KEY (client_id) REFERENCES users (id),
  CONSTRAINT fk_pay_provider FOREIGN KEY (provider_id) REFERENCES users (id),
  CONSTRAINT chk_pay_simulated CHECK (is_simulated = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  type VARCHAR(40) NOT NULL,
  title VARCHAR(150) NOT NULL,
  body VARCHAR(500) NOT NULL,
  request_id BIGINT UNSIGNED NULL,
  read_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notif_user (user_id, read_at, id),
  CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE announcements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id BIGINT UNSIGNED NULL,
  title VARCHAR(150) NOT NULL,
  body VARCHAR(500) NOT NULL,
  audience ENUM('all','clients','providers') NOT NULL DEFAULT 'all',
  recipients INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_ann_admin FOREIGN KEY (admin_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE activity_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  actor_id BIGINT UNSIGNED NULL,
  actor_role VARCHAR(20) NULL,
  action VARCHAR(60) NOT NULL,
  entity_type VARCHAR(40) NULL,
  entity_id BIGINT UNSIGNED NULL,
  details_json JSON NULL,
  ip_address VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_log_entity (entity_type, entity_id),
  KEY idx_log_actor (actor_id),
  KEY idx_log_action (action),
  KEY idx_log_created (created_at),
  CONSTRAINT fk_log_actor FOREIGN KEY (actor_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE mobile_auth_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  user_agent VARCHAR(255) NULL,
  expires_at DATETIME NOT NULL,
  revoked_at DATETIME NULL,
  last_used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_token_hash (token_hash),
  KEY idx_token_user (user_id),
  CONSTRAINT fk_token_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rate_limits (
  bucket_key CHAR(64) NOT NULL,
  window_start DATETIME NOT NULL,
  hits INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (bucket_key, window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
