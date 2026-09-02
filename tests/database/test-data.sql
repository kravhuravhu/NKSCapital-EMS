-- Insert a test user for authentication tests
INSERT INTO `users` (`name`, `email`, `password`, `email_verified_at`, `created_at`, `updated_at`) VALUES
('Test User', 'test@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NOW(), NOW(), NOW());

