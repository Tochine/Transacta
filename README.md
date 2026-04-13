## Transacta

Transacta is a financial platform created for businesses to streamline transactions and reconciliation.

### Prerequisites

- PHP 8.2+
- Composer 2+
- MySQL 8+
- Redis 6+

### Manual Setup

git clone https://github.com/Tochine/Transacta.git
cd Transacta

composer install
cp .env.example .env
php artisan key:generate

## Edit .env with your DB and Redis credentials
.env

## Start dev server
php artisan serve
