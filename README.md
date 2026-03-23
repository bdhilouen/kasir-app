# 🧾 Kasir App (POS System)

A web-based Point of Sale (POS) system built using **Laravel**.
This application helps small shops manage transactions, inventory, debts, and financial reports efficiently.

---

## 🚀 Features

### 📦 Product Management

* Add, update, delete products
* Track stock in real-time
* Low stock alert system

### 🧾 Transaction System

* Multi-item transactions
* Automatic total calculation
* Payment handling (cash, transfer, QRIS)
* Auto stock deduction
* Invoice generation

### 💰 Debt Management

* Automatic debt creation (partial / unpaid transactions)
* Installment payments
* Debt status tracking (unpaid, partial, paid)

### 📊 Reports

* Daily, weekly, monthly reports
* Custom date range report
* Top-selling products
* Debt summary & overdue tracking

---

## 🛠 Tech Stack

* **Backend:** Laravel (PHP)
* **Database:** PostgreSQL
* **API:** RESTful API
* **Tools:** Postman, Git, Laragon

---

## ⚙️ Installation

### 1. Clone Repository

```bash
git clone https://github.com/bdhilouen/kasir-app.git
cd kasir-app
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Setup Environment

```bash
cp .env.example .env
php artisan key:generate
```

### 4. Setup Database

Edit `.env`:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=kasir_app
DB_USERNAME=postgres
DB_PASSWORD=yourpassword
```

### 5. Run Migration

```bash
php artisan migrate
```

### 6. Run Server

```bash
php artisan serve
```

---

## 🧠 Key Concepts Implemented

* Database Transactions (Atomic Operations)
* Eloquent Relationships
* Stock Management System
* Debt Tracking Logic
* Dynamic Report Generation
* RESTful API Design

---

## ⭐ Notes

This project was built as a learning project to implement real-world business logic in a POS system.

---
