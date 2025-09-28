# Mini Wallet Application

## Overview
This project is a simplified digital wallet application developed as part of the technical assignment for Pimonno Software Design LLC. It allows users to transfer money to each other, mirroring the high-performance financial systems worked on by the company. The primary goal is to demonstrate Full Stack skills using Laravel and Vue.js, showcase code quality, and exhibit problem-solving approaches, especially for high-traffic, large-scale systems.

## Technology Stack
- **Backend**: Laravel 12 (latest stable version)
- **Frontend**: Vue.js (latest stable version, with Composition API encouraged)
- **Database**: MySQL
- **Real-time**: Pusher (not implemented in this version)

## Project Requirements
### Backend - Laravel API
- **Database Schema**:
  - `users` table: Includes a `balance` column (decimal type) to store user funds, alongside standard Laravel columns.
  - `transactions` table: Records every transfer with details like `sender_id`, `receiver_id`, `amount`, `commission_fee`, and `timestamp`.
- **API Endpoints**:
  1. `GET /api/transactions`: Returns transaction history (incoming and outgoing) and current balance for the authenticated user.
  2. `POST /api/transactions`: Executes a new money transfer with `receiver_id` and `amount` in the request body.
- **Business Logic & Performance**:
  - Handles high concurrency with hundreds of transfers per second, preventing race conditions and database locking.
  - Scales with millions of transaction rows, using a pre-calculated balance approach.
  - Applies a 1.5% commission fee debited from the sender.
  - Ensures atomic transactions with rollback on failure.
  - Validates requests (e.g., receiver existence, positive amount, sufficient balance).

### Frontend - Vue.js Interface
- **Transfer Page**: Includes a form with fields for recipient ID, amount, and a "Send" button.
- **Transaction History & Balance**: Displays past transactions and current balance, populated by the `/api/transactions` endpoint.

### Evaluation Criteria
- Scalable balance management under high traffic.
- Clean, readable, and maintainable code adhering to PSR-12 standards.
- Effective problem-solving for concurrency and data integrity.
- Basic security measures (validation, authorization).
- Clear Git commit history with meaningful messages.

## Setup Instructions

### Prerequisites
- PHP 8.2 or higher
- Composer
- Node.js and NPM
- MySQL
- Git

### Installation Steps
1. **Clone the Repository**
   ```bash
   git clone <your-repository-url>

2. composer install

3. npm install
4. chmod +x clean.sh
5. ./clean.sh
6. php artisan serve
7. npm run dev


# login with 
  admin@gmail.com
  admin123

