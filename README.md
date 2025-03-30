<<<<<<< HEAD
# multitenantecom_be_laravel
=======

# MULTITENANT ECOMMERCE - BACKEND - LARAVEL 12.3

This project is a multitenant e-commerce platform built with Laravel. It allows multiple tenants to have their own isolated data within a shared Laravel application.

## Features
- **Multitenancy**: The application supports multiple tenants, each with their own database and schema.
- **Tenant Isolation**: Each tenant has their own isolated data, ensuring that data from one tenant
- API authentication using Laravel Sanctum
- Product Management Per Tenant
- Add to cart for multi product and multi tenant
- User authentication (Login, Register, Logout)

## Installation Guide

### Prerequisites
    Ensure you have the following installed:
    -   PHP 8.2+
    -   Composer
    -   PostgreSQL
    -   Node.js & npm

## Setup Steps
1.  Clone the Repository
    ```
    git clone https://github.com/henrysetiadi/multitenantecom_be_laravel.git
    cd multi-tenant-ecommerce-laravel
    ```
2. Install Dependencies
    ```
    composer install
    composer require tenancy/tenancy
    ```
    Publish Configuration Files
    ```
    php artisan vendor:publish --tag=tenancy
    ```
3.  Copy the File and Configuration Database
    ```
    > cp .env.example .env
    ```
    Update your database configuration in .env file
    ```
    DB_CONNECTION=pgsql
    DB_HOST=127.0.0.1
    DB_PORT=5432
    DB_DATABASE=central_database_multitenant_ecom
    DB_USERNAME=your_username
    DB_PASSWORD=your_password

    ```
4.  Generate Application Key:
    ```
    php artisan key:generate
    ```
5.  Run Migrations:
    ```
    php artisan migrate
    ```
6.  Run the Development Server:
    ```
    php artisan serve
    ```

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
>>>>>>> f69b5ff (Initial commit)
