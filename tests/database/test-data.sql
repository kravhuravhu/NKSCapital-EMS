-- ============================================
-- 1. First, insert lookup/configuration data
-- ============================================

-- Insert employee types
INSERT INTO `employee_types` (`type_code`, `type_name`, `workflow_type`, `description`, `created_at`, `updated_at`) VALUES
('PS', 'Professional Services', 'PS_EXTERNAL', 'External PS employees', NOW(), NOW()),
('PR', 'Internal Permanent', 'PR_INTERNAL', 'Internal permanent employees', NOW(), NOW());

-- Insert service types
INSERT INTO `service_types` (`service_code`, `service_name`, `has_benefits`, `has_leave_accrual`, `leave_accrual_rate`, `has_probation_period`, `probation_days`, `description`, `created_at`, `updated_at`) VALUES
('PERM', 'Permanent', 1, 1, 1.25, 1, 90, 'Permanent employee', NOW(), NOW()),
('CON', 'Contractor', 0, 0, 0.00, 0, 0, 'Contractor', NOW(), NOW()),
('TEMP', 'Temporary', 0, 0, 0.00, 0, 0, 'Temporary employee', NOW(), NOW()),
('INT', 'Intern', 0, 0, 0.00, 1, 30, 'Intern', NOW(), NOW());

-- ============================================
-- 2. Then insert core business data
-- ============================================

-- Insert a test client
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

-- Insert a test project
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

-- ============================================
-- 3. Insert users (references clients and projects)
-- ============================================

-- Insert a test user (admin)
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
) VALUES (
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
);

-- Insert a test user (manager)
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
) VALUES (
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
);

-- Insert a test user (regular employee)
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
) VALUES (
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

-- ============================================
-- 4. Insert roles and permissions (for spatie/laravel-permission)
-- ============================================

-- Insert roles
INSERT INTO `roles` (`name`, `guard_name`, `created_at`, `updated_at`) VALUES
('super_admin', 'web', NOW(), NOW()),
('admin', 'web', NOW(), NOW()),
('manager', 'web', NOW(), NOW()),
('employee', 'web', NOW(), NOW());

-- Insert permissions
INSERT INTO `permissions` (`name`, `guard_name`, `created_at`, `updated_at`) VALUES
('view_users', 'web', NOW(), NOW()),
('edit_users', 'web', NOW(), NOW()),
('delete_users', 'web', NOW(), NOW()),
('view_projects', 'web', NOW(), NOW()),
('edit_projects', 'web', NOW(), NOW()),
('delete_projects', 'web', NOW(), NOW()),
('view_timesheets', 'web', NOW(), NOW()),
('edit_timesheets', 'web', NOW(), NOW()),
('delete_timesheets', 'web', NOW(), NOW()),
('view_leave_requests', 'web', NOW(), NOW()),
('approve_leave_requests', 'web', NOW(), NOW());

-- ============================================
-- 5. Assign roles to users (model_has_roles)
-- ============================================

-- Assign admin role to admin user (id=1)
INSERT INTO `model_has_roles` (`role_id`, `model_type`, `model_id`) VALUES
(2, 'App\\Models\\User', 1),  -- admin role to admin user
(3, 'App\\Models\\User', 2);  -- manager role to manager user

-- ============================================
-- 6. Assign permissions to roles (role_has_permissions)
-- ============================================

-- Give admin role (id=2) all permissions
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES
(1, 2), (2, 2), (3, 2), (4, 2), (5, 2), (6, 2), (7, 2), (8, 2), (9, 2), (10, 2), (11, 2);

-- Give manager role (id=3) some permissions
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES
(1, 3), (4, 3), (5, 3), (7, 3), (8, 3), (10, 3), (11, 3);

-- Give employee role (id=4) basic permissions
INSERT INTO `role_has_permissions` (`permission_id`, `role_id`) VALUES
(1, 4), (7, 4), (10, 4);

-- ============================================
-- 7. Insert workflow configs
-- ============================================

INSERT INTO `workflow_configs` (`workflow_type`, `approval_chain`, `notification_settings`, `validation_rules`, `escalation_rules`, `is_active`, `created_at`, `updated_at`) VALUES
('PS_EXTERNAL', '{"level1": "manager", "level2": "director"}', '{"email": true, "in_app": true}', '{"max_hours": 40, "overtime_limit": 10}', '{"escalate_after_days": 3}', 1, NOW(), NOW()),
('PR_INTERNAL', '{"level1": "manager", "level2": "hr"}', '{"email": true, "in_app": true}', '{"max_hours": 40, "overtime_limit": 10}', '{"escalate_after_days": 5}', 1, NOW(), NOW());