# HC Terminal

## Installation
1. `git pull`
2. `composer install`
3. `cp .env.example .env`
4. `php artisan key:generate`
5. Add your Stripe test key in .env as `STRIPE_TEST_KEY`
6. `php artisan optimize`
7. `php artisan test`