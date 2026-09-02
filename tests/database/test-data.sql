-- Insert a test user with all required fields
INSERT INTO `users` ( `employee_number`, `email`, `email_verified_at`, `password`,`first_name`, `last_name`, `phone`, `role`, `employee_type`, `service_type`, `is_active`, `leave_balance_annual`, `leave_balance_sick`,`two_factor_enabled`, `created_at`, `updated_at`)
VALUES ('EMP001', 'test@example.com', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Test', 'User', '+27123456789', 'employee', 'ps', 'permanent', 1, 20.00, 10.00, 0, NOW(), NOW());

-- Insert a manager for testing
INSERT INTO `users` ( `employee_number`, `email`, `email_verified_at`, `password`, `first_name`, `last_name`, `phone`, `role`, `employee_type`, `service_type`, `is_active`,`leave_balance_annual`, `leave_balance_sick`, `two_factor_enabled`, `created_at`, `updated_at`) 
VALUES ('EMP002', 'manager@example.com', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Manager', 'User', '+27123456790', 'manager', 'ps', 'permanent', 1, 25.00, 12.00, 0, NOW(), NOW());

-- Insert an admin for testing
INSERT INTO `users` (`employee_number`, `email`, `email_verified_at`, `password`, `first_name`, `last_name`, `phone`, `role`, `employee_type`, `service_type`, `is_active`, `leave_balance_annual`, `leave_balance_sick`, `two_factor_enabled`, `created_at`, `updated_at`)
VALUES ('EMP003', 'admin@example.com', NOW(), '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'User', '+27123456791', 'admin', 'ps', 'permanent', 1, 30.00, 15.00, 0, NOW(), NOW());