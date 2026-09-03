-- ============================================
-- MINIMAL TEST DATA FOR CI
-- Disable foreign key checks to avoid issues
-- ============================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Insert employee types
INSERT INTO `employee_types` (`type_code`, `type_name`, `workflow_type`, `description`, `created_at`, `updated_at`) VALUES
('PS', 'Professional Services', 'PS_EXTERNAL', 'External PS employees', NOW(), NOW()),
('PR', 'Internal Permanent', 'PR_INTERNAL', 'Internal permanent employees', NOW(), NOW());

-- 2. Insert service types
INSERT INTO `service_types` (`service_code`, `service_name`, `has_benefits`, `has_leave_accrual`, `leave_accrual_rate`, `has_probation_period`, `probation_days`, `description`, `created_at`, `updated_at`) VALUES
('PERM', 'Permanent', 1, 1, 1.25, 1, 90, 'Permanent employee', NOW(), NOW()),
('CON', 'Contractor', 0, 0, 0.00, 0, 0, 'Contractor', NOW(), NOW());

-- 3. Insert a test client
INSERT INTO `clients` (
  `company_name`, 
  `registration_number`, 
  `vat_number`, 
  `primary_contact_name`, 
  `primary_contact_email`, 
  `primary_contact_phone`, 
  `status`,
  `is_active`,
  `created_at`, 
  `updated_at`
) VALUES (
  'Test Client',
  'REG001',
  'VAT001',
  'Test Contact',
  'test@example.com',
  '+27123456789',
  'active',
  1,
  NOW(),
  NOW()
);

-- 4. Insert a test project
INSERT INTO `projects` (
  `client_id`,
  `project_code`,
  `name`,
  `description`,
  `project_type`,
  `status`,
  `created_at`,
  `updated_at`
) VALUES (
  1,
  'PROJ001',
  'Test Project',
  'Test project description',
  'client',
  'active',
  NOW(),
  NOW()
);

-- 5. Insert test users
INSERT INTO `users` (
  `employee_number`,
  `email`,
  `email_verified_at`,
  `password`,
  `first_name`,
  `last_name`,
  `phone`,
  `role`,
  `employee_type`,
  `service_type`,
  `is_active`,
  `leave_balance_annual`,
  `leave_balance_sick`,
  `two_factor_enabled`,
  `created_at`,
  `updated_at`
) VALUES 
(
  'EMP0001',
  'admin@example.com',
  NOW(),
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
  'Admin',
  'User',
  '+27123456789',
  'admin',
  'ps',
  'permanent',
  1,
  20.00,
  10.00,
  0,
  NOW(),
  NOW()
),
(
  'EMP0002',
  'manager@example.com',
  NOW(),
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
  'Manager',
  'User',
  '+27123456790',
  'manager',
  'ps',
  'permanent',
  1,
  25.00,
  12.00,
  0,
  NOW(),
  NOW()
),
(
  'EMP0003',
  'employee@example.com',
  NOW(),
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
  'Regular',
  'Employee',
  '+27123456791',
  'employee',
  'ps',
  'permanent',
  1,
  15.00,
  8.00,
  0,
  NOW(),
  NOW()
);

-- 6. Insert workflow configs
INSERT INTO `workflow_configs` (`workflow_type`, `approval_chain`, `notification_settings`, `validation_rules`, `escalation_rules`, `is_active`, `created_at`, `updated_at`) VALUES
('PS_EXTERNAL', '{"level1": "manager", "level2": "director"}', '{"email": true, "in_app": true}', '{"max_hours": 40, "overtime_limit": 10}', '{"escalate_after_days": 3}', 1, NOW(), NOW()),
('PR_INTERNAL', '{"level1": "manager", "level2": "hr"}', '{"email": true, "in_app": true}', '{"max_hours": 40, "overtime_limit": 10}', '{"escalate_after_days": 5}', 1, NOW(), NOW());

-- 7. Insert system configs
INSERT INTO `system_configs` (`config_key`, `config_value`, `description`, `config_type`, `created_at`, `updated_at`) VALUES
('company_name', 'NKS Capital', 'Company name', 'string', NOW(), NOW()),
('timezone', 'Africa/Johannesburg', 'System timezone', 'string', NOW(), NOW());

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;