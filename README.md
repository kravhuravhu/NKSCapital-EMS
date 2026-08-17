# NKS Capital Employee Management System (EMS)

## Overview
The NKS Capital EMS is a comprehensive employee management system designed to handle:
- Professional Services (PS) - Client site workers with external timesheet approval
- Projects (PR) - Internal project workers with internal approval workflow

## Technology Stack
- Backend: Laravel 11+
- Frontend: Blade + Livewire
- Database: MySQL 8+
- Queue: Redis + Laravel Horizon
- Auth: Laravel Jetstream + 2FA

## Branch Structure
- `main` - Production ready code
- `preprod/v1.0.0` - Staging/Pre-production
- `develop` - Integration branch
- `feature/*` - Feature branches

## Installation

### Prerequisites
- PHP 8.2+
- MySQL 8.0+
- Redis
- Composer
- Node.js 20+

### Setup
```bash
# Clone the repository
git clone https://github.com/nkscapital/ems.git
cd ems

# Install dependencies
composer install
npm install

# Environment setup
cp .env.example .env
php artisan key:generate

# Database
php artisan migrate
php artisan db:seed

# Build assets
npm run build

# Run development server
php artisan serve