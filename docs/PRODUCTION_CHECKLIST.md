# NKS Capital EMS — Production Readiness Checklist

## M13 Deliverable
- [x] All APIs from M1–M12 tested with Postman
- [x] Webhook integration operational
- [x] Calendar integration (OAuth connect + sync)
- [x] Payroll integration (CSV export + webhook)
- [x] Health check endpoint
- [x] UAT suite automated
- [x] Deployment check automated
- [x] Audit chain verified

## Server Setup
- [ ] PHP 8.2+ FPM installed
- [ ] Nginx with TLS 1.3 (Let's Encrypt)
- [ ] MySQL 8+ configured
- [ ] Redis installed (queues + cache)
- [ ] Supervisor configured for queue workers
- [ ] Cron scheduled (`* * * * * php artisan schedule:run`)
- [ ] S3 configured for file uploads
- [ ] Firewall: only 443/22 open
- [ ] fail2ban installed

## Application Deployment
- [ ] `APP_ENV=production` in .env
- [ ] `APP_DEBUG=false`
- [ ] `APP_KEY` generated
- [ ] `composer install --optimize-autoloader --no-dev`
- [ ] `php artisan config:cache`
- [ ] `php artisan route:cache`
- [ ] `php artisan view:cache`
- [ ] `php artisan event:cache`
- [ ] Storage + bootstrap/cache permissions (775)
- [ ] Laravel Horizon running (Supervisor)
- [ ] Zero-downtime deploy script

## Data
- [ ] Database restored from `nks_hrms.sql`
- [ ] Raw SQL enhancements applied in order:
  - `2024_leave_enhancements.sql`
  - `2024_asset_enhancements.sql`
  - `2024_contract_enhancements.sql`
  - `2024_recruitment_enhancements.sql`
  - `2024_recruitment_offer_enhancements.sql`
  - `2024_meeting_delegation_enhancements.sql`
  - `2024_notifications_audit_enhancements.sql`
  - `2024_dashboard_reporting_enhancements.sql`
  - `2024_integrations_enhancements.sql`
- [ ] Seed data: Roles, Permissions, Employee Types, Service Types, Leave Configs, System Configs
- [ ] Initial Director/Manager/Admin users seeded
- [ ] Daily S3 backup configured
- [ ] Retention: 90 days minimum

## Security
- [ ] 2FA enforced for Manager/Director/Admin
- [ ] Password policy enforced (90-day rotation, 3-cycle history)
- [ ] Session timeout 15 minutes
- [ ] All sensitive data encrypted at rest (AES-256)
- [ ] TLS 1.3 enforced
- [ ] OWASP Top 10 verified via test suite
- [ ] SQL injection tests passed
- [ ] XSS tests passed
- [ ] CSRF tests passed
- [ ] Rate limiting verified
- [ ] Audit chain integrity verified

## Monitoring
- [ ] Laravel daily logs configured
- [ ] Sentry/Bugsnag (optional)
- [ ] UptimeRobot configured
- [ ] Email alerts for critical errors
- [ ] Queue worker health monitor

## UAT- [ ] UAT session recorded in `uat_sessions`
- [ ] All scenarios passed
- [ ] Sign-off by Director
- [ ] Sign-off by Operations Manager

## Go-Live
- [ ] Final security audit
- [ ] L1 + L2 actors trained
- [ ] Employee communication sent
- [ ] Existing data migrated
- [ ] Parallel run 1 month (recommended)
- [ ] First automated payroll export validated
- [ ] Error logs monitored daily for first week
- [ ] Support contact published