-- OPSECS Database Schema

-- Create Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20) NOT NULL,
    password VARCHAR(255) NOT NULL,
    account_type ENUM('individual', 'business') DEFAULT 'individual',
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    kyc_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX(email),
    INDEX(phone)
);

-- Create Accounts Table
CREATE TABLE IF NOT EXISTS accounts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    account_number VARCHAR(20) UNIQUE NOT NULL,
    account_type ENUM('savings', 'current', 'salary') DEFAULT 'savings',
    balance DECIMAL(15, 2) DEFAULT 0.00,
    currency VARCHAR(3) DEFAULT 'XAF',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX(user_id),
    INDEX(account_number)
);

-- Create Transactions Table
CREATE TABLE IF NOT EXISTS transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    account_id INT NOT NULL,
    transaction_type ENUM('deposit', 'withdrawal', 'transfer', 'loan_disbursement') NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    description VARCHAR(255),
    reference_number VARCHAR(50) UNIQUE,
    status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    INDEX(account_id),
    INDEX(created_at)
);

-- Create Deposits Table
CREATE TABLE IF NOT EXISTS deposits (
    id INT PRIMARY KEY AUTO_INCREMENT,
    account_id INT NOT NULL,
    transaction_id INT,
    user_id INT NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    deposit_method ENUM('bank_transfer', 'cash', 'cheque', 'mobile_money') DEFAULT 'bank_transfer',
    reference_number VARCHAR(50) UNIQUE,
    description TEXT,
    status ENUM('pending', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    processed_by VARCHAR(100),
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id),
    INDEX(user_id),
    INDEX(account_id),
    INDEX(status),
    INDEX(created_at)
);

-- Create Loans Table
CREATE TABLE IF NOT EXISTS loans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    account_id INT NOT NULL,
    loan_amount DECIMAL(15, 2) NOT NULL,
    loan_type VARCHAR(50) NOT NULL,
    interest_rate DECIMAL(5, 2) NOT NULL,
    term_months INT NOT NULL,
    monthly_payment DECIMAL(15, 2) NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'active', 'completed') DEFAULT 'pending',
    disbursement_date DATE,
    repayment_start_date DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES accounts(id),
    INDEX(user_id),
    INDEX(status)
);

-- Create Loan Payments Table
CREATE TABLE IF NOT EXISTS loan_payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    loan_id INT NOT NULL,
    payment_amount DECIMAL(15, 2) NOT NULL,
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'completed') DEFAULT 'completed',
    FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE,
    INDEX(loan_id)
);

-- Create Contact Messages Table
CREATE TABLE IF NOT EXISTS contact_messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('unread', 'read', 'replied') DEFAULT 'unread',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(email),
    INDEX(created_at)
);

-- Create Admin Users Table
CREATE TABLE IF NOT EXISTS admin_users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'manager', 'officer') DEFAULT 'officer',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create Audit Log Table
CREATE TABLE IF NOT EXISTS audit_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(255) NOT NULL,
    table_name VARCHAR(100),
    record_id INT,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(user_id),
    INDEX(created_at)
);

-- Create Loan Request Details Table
CREATE TABLE IF NOT EXISTS loan_request_details (
    id INT PRIMARY KEY AUTO_INCREMENT,
    loan_id INT NOT NULL,
    employment_status VARCHAR(50),
    monthly_income DECIMAL(15, 2),
    additional_info TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE,
    INDEX(loan_id)
);
-- Create Mobile Transfers Table
CREATE TABLE IF NOT EXISTS mobile_transfers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    sender_name VARCHAR(100) NOT NULL,
    sender_phone VARCHAR(20) NOT NULL,
    recipient_name VARCHAR(100) NOT NULL,
    recipient_phone VARCHAR(20) NOT NULL,
    provider VARCHAR(50) NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    reference_number VARCHAR(50) UNIQUE,
    notes TEXT,
    status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX(reference_number),
    INDEX(status),
    INDEX(created_at)
);

-- Create Remittances Table
CREATE TABLE IF NOT EXISTS remittances (
    id INT PRIMARY KEY AUTO_INCREMENT,
    account_id INT NOT NULL,
    recipient_name VARCHAR(100) NOT NULL,
    recipient_phone VARCHAR(20) NOT NULL,
    recipient_country VARCHAR(100) NOT NULL,
    send_amount DECIMAL(15, 2) NOT NULL,
    recipient_currency VARCHAR(3) NOT NULL,
    fee_amount DECIMAL(15, 2) DEFAULT 0.00,
    reference_number VARCHAR(50) UNIQUE,
    purpose VARCHAR(255),
    status ENUM('pending', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    INDEX(account_id),
    INDEX(status),
    INDEX(created_at)
);

-- Create Savings Accounts Table
CREATE TABLE IF NOT EXISTS savings_accounts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    account_id INT NOT NULL,
    user_id INT NOT NULL,
    savings_goal VARCHAR(255),
    term_months INT DEFAULT 12,
    notes TEXT,
    status ENUM('active', 'inactive', 'closed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX(user_id),
    INDEX(account_id),
    INDEX(status)
);