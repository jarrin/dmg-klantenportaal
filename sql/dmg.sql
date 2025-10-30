-- =============================================
-- DMG PORTAL - COMPLETE DATABASE INITIALIZATION
-- =============================================

-- Enable essential extensions
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";
CREATE EXTENSION IF NOT EXISTS "pg_stat_statements";

-- =============================================
-- ENUM TYPES
-- =============================================
CREATE TYPE user_status AS ENUM ('active', 'inactive', 'suspended', 'pending');
CREATE TYPE audit_action AS ENUM ('CREATE', 'UPDATE', 'DELETE', 'LOGIN', 'LOGOUT', 'PASSWORD_CHANGE');
CREATE TYPE payment_method AS ENUM ('invoice', 'direct_debit', 'credit_card', 'paypal');
CREATE TYPE product_type AS ENUM ('hosting', 'domain', 'email', 'ssl', 'support');

-- =============================================
-- CORE TABLES
-- =============================================

-- Main users table with comprehensive profile data
CREATE TABLE users (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    
    -- Personal information
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    company_name VARCHAR(255),
    
    -- Address information
    address_street VARCHAR(255) NOT NULL,
    address_number VARCHAR(20) NOT NULL,
    address_postalcode VARCHAR(20) NOT NULL,
    address_city VARCHAR(100) NOT NULL,
    address_country VARCHAR(100) DEFAULT 'Netherlands',
    
    -- Contact information
    email VARCHAR(255) UNIQUE NOT NULL,
    email_verified BOOLEAN DEFAULT FALSE,
    phone_number VARCHAR(50),
    phone_verified BOOLEAN DEFAULT FALSE,
    
    -- Security & Authentication
    password_hash VARCHAR(255) NOT NULL,
    mfa_secret VARCHAR(32),
    mfa_enabled BOOLEAN DEFAULT FALSE,
    
    -- Status & Tracking
    status user_status DEFAULT 'pending',
    last_login TIMESTAMPTZ,
    login_attempts INTEGER DEFAULT 0,
    locked_until TIMESTAMPTZ,
    
    -- Timestamps
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    version INTEGER DEFAULT 1
);

-- User products/services (hosting, domains, etc.)
CREATE TABLE user_products (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    
    product_type product_type NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    domain_name VARCHAR(255),
    
    -- Subscription details
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    auto_renew BOOLEAN DEFAULT TRUE,
    
    -- Pricing
    price DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'EUR',
    
    -- Status
    status VARCHAR(50) DEFAULT 'active',
    
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

-- Payment preferences and billing information
CREATE TABLE payment_preferences (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    
    preferred_method payment_method DEFAULT 'invoice',
    
    -- Bank details (encrypted in production)
    iban VARCHAR(34),
    bic VARCHAR(11),
    account_holder VARCHAR(255),
    
    -- Credit card info (tokenized in production)
    card_last_four VARCHAR(4),
    card_brand VARCHAR(50),
    card_expiry DATE,
    
    -- PayPal
    paypal_email VARCHAR(255),
    
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    
    UNIQUE(user_id)
);

-- Invoices and billing history
CREATE TABLE invoices (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    
    invoice_number VARCHAR(100) UNIQUE NOT NULL,
    invoice_date DATE NOT NULL,
    due_date DATE NOT NULL,
    
    -- Amounts
    subtotal DECIMAL(10,2) NOT NULL,
    tax_amount DECIMAL(10,2) NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    
    -- Status
    status VARCHAR(50) DEFAULT 'pending', -- pending, paid, overdue, cancelled
    
    -- PDF storage reference
    pdf_path VARCHAR(500),
    
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

-- Support tickets
CREATE TABLE support_tickets (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    
    subject VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    priority VARCHAR(20) DEFAULT 'medium', -- low, medium, high, critical
    
    -- Tracking
    status VARCHAR(50) DEFAULT 'open', -- open, in_progress, resolved, closed
    assigned_to UUID REFERENCES users(id),
    
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

-- =============================================
-- SECURITY & AUDIT TABLES
-- =============================================

-- Comprehensive audit logging
CREATE TABLE audit_logs (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id UUID REFERENCES users(id) ON DELETE SET NULL,
    
    action audit_action NOT NULL,
    table_name VARCHAR(100) NOT NULL,
    record_id UUID,
    
    -- Change tracking
    old_values JSONB,
    new_values JSONB,
    changed_fields TEXT[],
    
    -- Context
    ip_address INET,
    user_agent TEXT,
    
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

-- Password history for security compliance
CREATE TABLE password_history (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

-- User sessions for authentication
CREATE TABLE user_sessions (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    
    session_token VARCHAR(500) NOT NULL,
    expires_at TIMESTAMPTZ NOT NULL,
    
    -- Device info
    ip_address INET,
    user_agent TEXT,
    
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

-- =============================================
-- INDEXES FOR PERFORMANCE
-- =============================================

-- Users indexes
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_status ON users(status);
CREATE INDEX idx_users_company ON users(company_name);
CREATE UNIQUE INDEX idx_users_email_lower ON users(LOWER(email));

-- Products indexes
CREATE INDEX idx_user_products_user_id ON user_products(user_id);
CREATE INDEX idx_user_products_domain ON user_products(domain_name);
CREATE INDEX idx_user_products_end_date ON user_products(end_date);

-- Payment indexes
CREATE INDEX idx_payment_user_id ON payment_preferences(user_id);

-- Invoice indexes
CREATE INDEX idx_invoices_user_id ON invoices(user_id);
CREATE INDEX idx_invoices_status ON invoices(status);
CREATE INDEX idx_invoices_due_date ON invoices(due_date);

-- Support tickets indexes
CREATE INDEX idx_tickets_user_id ON support_tickets(user_id);
CREATE INDEX idx_tickets_status ON support_tickets(status);
CREATE INDEX idx_tickets_priority ON support_tickets(priority);

-- Audit logs indexes
CREATE INDEX idx_audit_user_id ON audit_logs(user_id);
CREATE INDEX idx_audit_created_at ON audit_logs(created_at);

-- Sessions indexes
CREATE INDEX idx_sessions_user_id ON user_sessions(user_id);
CREATE INDEX idx_sessions_token ON user_sessions(session_token);
CREATE INDEX idx_sessions_expires ON user_sessions(expires_at);

-- =============================================
-- AUTOMATIC UPDATED_AT TRIGGERS
-- =============================================
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Apply triggers to all tables with updated_at
CREATE TRIGGER update_users_updated_at BEFORE UPDATE ON users
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_user_products_updated_at BEFORE UPDATE ON user_products
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_payment_preferences_updated_at BEFORE UPDATE ON payment_preferences
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_support_tickets_updated_at BEFORE UPDATE ON support_tickets
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- =============================================
-- SAMPLE DATA FOR DEVELOPMENT
-- =============================================

-- Insert sample users with securely hashed passwords
INSERT INTO users (first_name, last_name, company_name, address_street, address_number, 
                  address_postalcode, address_city, email, password_hash, email_verified, status) 
VALUES 
('John', 'Dough', 'Dough Enterprises', 'Hoofdstraat', '123', '1234 AB', 'Amsterdam', 
 'john.dough@example.com', crypt('SecurePass123!', gen_salt('bf')), TRUE, 'active'),
 
('Ingmar', 'van Rheenen', 'Tech Solutions BV', 'Kerkstraat', '45', '1071 AA', 'Rotterdam', 
 'ingmar@example.com', crypt('MySecurePassword456!', gen_salt('bf')), TRUE, 'active');

-- Insert sample products
INSERT INTO user_products (user_id, product_type, product_name, domain_name, start_date, end_date, price) 
SELECT id, 'hosting', 'Hosting Professional', 'dummysite.nl', '2025-02-22', '2026-02-22', 299.00 
FROM users WHERE email = 'john.dough@example.com';

INSERT INTO user_products (user_id, product_type, product_name, domain_name, start_date, end_date, price) 
SELECT id, 'domain', 'Domain Registration', 'dummysite.com', '2025-02-22', '2026-02-22', 15.00 
FROM users WHERE email = 'john.dough@example.com';

-- Insert payment preferences
INSERT INTO payment_preferences (user_id, preferred_method, iban, account_holder) 
SELECT id, 'direct_debit', 'NL91ABNA0417164300', 'John Dough' 
FROM users WHERE email = 'john.dough@example.com';

-- Insert sample invoices
INSERT INTO invoices (user_id, invoice_number, invoice_date, due_date, subtotal, tax_amount, total_amount, status) 
SELECT id, 'INV-2025-001', '2025-01-01', '2025-01-08', 314.00, 65.94, 379.94, 'paid'
FROM users WHERE email = 'john.dough@example.com';

-- Insert sample support ticket
INSERT INTO support_tickets (user_id, subject, description, priority) 
SELECT id, 'Vraag over verlenging', 'Ik heb een vraag over de verlenging van mijn hostingpakket.', 'medium'
FROM users WHERE email = 'john.dough@example.com';

-- =============================================
-- DATABASE INITIALIZATION COMPLETE
-- =============================================