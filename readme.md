# Mini Wallet Application Overview

This project is a simplified digital wallet application developed as part of the technical assignment for **Pimonno Software Design LLC**.  
It allows users to transfer money to each other, mirroring the high-performance financial systems worked on by the company.  

The primary goal is to demonstrate **Full Stack skills using Laravel and Vue.js (with Inertia.js)**, showcase code quality, and exhibit problem-solving approaches, especially for high-traffic, large-scale systems.

---

## Technology Stack

- **Backend:** Laravel 12 (latest stable version)  
- **Frontend:** Vue.js (latest stable version) with Composition API, powered by **Inertia.js**  
- **Database:** MySQL  
- **Real-time:** Pusher

---

## Project Requirements

### Backend - Laravel with Inertia

#### Database Schema
- **users** table: Stores account info and balance.  
- **transactions** table: Stores transfers (`sender_id`, `receiver_id`, `amount`, `commission_fee`, `timestamp`).  

#### Endpoints / Controllers
- **Transactions (History & Balance)**
  Accessible through a route that provides the authenticated user’s current balance and transactions.
  Data is returned to the frontend using Inertia responses, making it directly available for Vue components.

- **Transactions (Create Transfer)**  
  A route that processes a transfer request (`receiver_id`, `amount`).
  Includes business rules like commission fees, concurrency-safe database transactions, and rollbacks.
  The result is delivered back to the frontend via Inertia so the user sees updated info immediately.

---

### Frontend - Vue.js with Inertia

- **Transfer Page**  
  - A form for entering recipient ID and amount.  
  - Submits via Inertia to the `TransactionsController@store` route.  

- **Transaction History & Balance**  
  - Inertia props populate past transactions and current balance (from `TransactionsController@index`).  
  - Vue components render the data with Composition API.  

---

## Evaluation Criteria

- Scalable balance management under high traffic.  
- Clean, readable, and maintainable code (PSR-12 standards).  
- Effective problem-solving for concurrency and data integrity.  
- Basic security measures (validation, authorization).  
- Clear Git commit history with meaningful messages.  

---

## Setup Instructions

### Prerequisites
- PHP 8.2+  
- Composer  
- Node.js and NPM  
- MySQL  
- Git
- Redis

### Installation Steps
```bash
# Clone the Repository
git clone https://github.com/codekinz/digital-wallet

# Install dependencies
composer install
npm install

# Environment setup
cp .env.example .env
# configure DB credentials

# Run frontend build
npm run dev

# Run migrations
php artisan migrate --seed

### Credentials
Login with:
Email: admin@gmail.com  
Password: admin123  
