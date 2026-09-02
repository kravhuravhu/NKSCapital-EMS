-- Insert a test employee type
INSERT INTO `employee_types` (`type_code`, `type_name`, `workflow_type`, `description`) VALUES
('PS', 'Professional Services', 'PS_EXTERNAL', 'External PS employees'),
('PR', 'Internal Permanent', 'PR_INTERNAL', 'Internal permanent employees');

-- Insert a test service type
INSERT INTO `service_types` (`service_code`, `service_name`, `has_benefits`, `has_leave_accrual`, `leave_accrual_rate`, `has_probation_period`, `probation_days`, `description`) VALUES
('PERM', 'Permanent', 1, 1, 1.25, 1, 90, 'Permanent employee'),
('CON', 'Contractor', 0, 0, 0.00, 0, 0, 'Contractor');

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

-- Insert a test user
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
  'test@example.com',
  NOW(),
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
  'Test',
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

-- Insert test roles and permissions (for spatie/laravel-permission)
INSERT INTO `roles` (`name`, `guard_name`, `created_at`, `updated_at`) VALUES
('super_admin', 'web', NOW(), NOW()),
('admin', 'web', NOW(), NOW()),
('manager', 'web', NOW(), NOW()),
('employee', 'web', NOW(), NOW());

-- Insert some basic permissions
INSERT INTO `permissions` (`name`, `guard_name`, `created_at`, `updated_at`) VALUES
('view_users', 'web', NOW(), NOW()),
('edit_users', 'web', NOW(), NOW()),
('delete_users', 'web', NOW(), NOW()),
('view_projects', 'web', NOW(), NOW()),
('edit_projects', 'web', NOW(), NOW()),
('view_timesheets', 'web', NOW(), NOW()),
('edit_timesheets', 'web', NOW(), NOW());

-- Assign admin role to test user
INSERT INTO `model_has_roles` (`role_id`, `model_type`, `model_id`) VALUES
(2, 'App\\Models\\User', 1);