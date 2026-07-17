-- ========================================
-- COMMUNICATION SYSTEM DATABASE
-- ========================================

CREATE DATABASE IF NOT EXISTS communication_system;
USE communication_system;

-- ========================================
-- 1. DEPARTMENTS TABLE
-- ========================================

CREATE TABLE departments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    head_id INT,
    description TEXT,
    email VARCHAR(100),
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status ENUM('active', 'inactive') DEFAULT 'active'
);

-- ========================================
-- 2. USERS TABLE
-- ========================================

CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    department_id INT NOT NULL,
    role ENUM('admin', 'manager', 'user') DEFAULT 'user',
    profile_photo VARCHAR(255),
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login DATETIME,
    status ENUM('active', 'locked', 'inactive') DEFAULT 'active',
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE RESTRICT
);

-- ========================================
-- 3. DOCUMENTS TABLE
-- ========================================

CREATE TABLE documents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT,
    file_type VARCHAR(50),
    department_id INT NOT NULL,
    uploaded_by INT NOT NULL,
    version VARCHAR(10) DEFAULT '1.0',
    status ENUM('approved', 'pending', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    tags VARCHAR(255),
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ========================================
-- 4. APPROVALS TABLE
-- ========================================

CREATE TABLE approvals (
    id INT PRIMARY KEY AUTO_INCREMENT,
    document_id INT NOT NULL,
    requested_by INT NOT NULL,
    assigned_to INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    comments TEXT,
    approved_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
);

-- ========================================
-- 5. DOCUMENT SHARING TABLE
-- ========================================

CREATE TABLE document_sharing (
    id INT PRIMARY KEY AUTO_INCREMENT,
    document_id INT NOT NULL,
    shared_by INT NOT NULL,
    shared_with_user_id INT,
    shared_with_department_id INT,
    can_view BOOLEAN DEFAULT TRUE,
    can_edit BOOLEAN DEFAULT FALSE,
    can_share BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (shared_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (shared_with_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (shared_with_department_id) REFERENCES departments(id) ON DELETE CASCADE
);

-- ========================================
-- 6. PERMISSIONS TABLE
-- ========================================

CREATE TABLE permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    department_id INT,
    can_view BOOLEAN DEFAULT TRUE,
    can_edit BOOLEAN DEFAULT FALSE,
    can_approve BOOLEAN DEFAULT FALSE,
    can_delete BOOLEAN DEFAULT FALSE,
    can_share BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    UNIQUE KEY unique_user_dept (user_id, department_id)
);

-- ========================================
-- 7. COMMUNICATIONS TABLE
-- ========================================

CREATE TABLE communications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    message_type ENUM('announcement', 'message', 'notification') DEFAULT 'message',
    sender_id INT NOT NULL,
    recipient_id INT,
    department_id INT,
    subject VARCHAR(255),
    message TEXT NOT NULL,
    attachment_id INT,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
);

-- ========================================
-- 8. AUDIT LOG TABLE
-- ========================================

CREATE TABLE audit_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    old_value TEXT,
    new_value TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
);

-- ========================================
-- 9. ACTIVITY LOG TABLE
-- ========================================

CREATE TABLE activity_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    activity VARCHAR(255) NOT NULL,
    document_id INT,
    department_id INT,
    activity_type ENUM('upload', 'download', 'view', 'edit', 'approve', 'reject', 'share', 'login', 'logout') DEFAULT 'view',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE SET NULL,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
);

-- ========================================
-- 10. DOCUMENT VERSIONS TABLE
-- ========================================

CREATE TABLE document_versions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    document_id INT NOT NULL,
    version_number VARCHAR(10),
    file_path VARCHAR(500) NOT NULL,
    uploaded_by INT NOT NULL,
    change_notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ========================================
-- 11. SESSIONS TABLE
-- ========================================

CREATE TABLE sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    session_token VARCHAR(255) NOT NULL UNIQUE,
    ip_address VARCHAR(45),
    user_agent TEXT,
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    logout_time DATETIME,
    is_active BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_session_token (session_token)
);

-- ========================================
-- 12. NOTIFICATIONS TABLE
-- ========================================

CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('approval', 'sharing', 'system', 'announcement') DEFAULT 'system',
    related_document_id INT,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (related_document_id) REFERENCES documents(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_is_read (is_read)
);

-- ========================================
-- SAMPLE DATA
-- ========================================

-- Insert Departments
INSERT INTO departments (name, email, phone, status) VALUES
('Finance', 'finance@company.com', '+254716582301', 'active'),
('Human Resource', 'hr@company.com', '+254716582302', 'active'),
('ICT', 'ict@company.com', '+254716582303', 'active'),
('Supply Chain', 'supply@company.com', '+254716582304', 'active'),
('Production', 'production@company.com', '+254716582305', 'active'),
('Sales', 'sales@company.com', '+254716582306', 'active'),
('Quality Control', 'qa@company.com', '+254716582307', 'active'),
('Regulatory Affairs', 'regulatory@company.com', '+254716582308', 'active');

-- Insert Users
INSERT INTO users (username, email, password, first_name, last_name, department_id, role, phone, status) VALUES
('admin', 'admin@company.com', SHA2('admin123', 256), 'System', 'Administrator', 1, 'admin', '+254716582301', 'active'),
('john.daniel', 'john.daniel@company.com', SHA2('john123', 256), 'John', 'Daniel', 1, 'manager', '+254716582309', 'active'),
('mary.jane', 'mary.jane@company.com', SHA2('mary123', 256), 'Mary', 'Jane', 2, 'manager', '+254716582310', 'active'),
('david.kim', 'david.kim@company.com', SHA2('david123', 256), 'David', 'Kim', 3, 'user', '+254716582311', 'active'),
('sarah.smith', 'sarah.smith@company.com', SHA2('sarah123', 256), 'Sarah', 'Smith', 4, 'user', '+254716582312', 'active'),
('michael.brown', 'michael.brown@company.com', SHA2('michael123', 256), 'Michael', 'Brown', 5, 'user', '+254716582313', 'active'),
('jane.doe', 'jane.doe@company.com', SHA2('jane123', 256), 'Jane', 'Doe', 6, 'user', '+254716582314', 'active'),
('robert.wilson', 'robert.wilson@company.com', SHA2('robert123', 256), 'Robert', 'Wilson', 7, 'user', '+254716582315', 'active');

-- Update Department Heads
UPDATE departments SET head_id = 2 WHERE id = 1; -- John Daniel - Finance
UPDATE departments SET head_id = 3 WHERE id = 2; -- Mary Jane - HR

-- Insert Sample Documents
INSERT INTO documents (title, description, file_name, file_path, department_id, uploaded_by, status, tags) VALUES
('Budget Report 2026', 'Annual budget planning for Finance Department', 'Budget_Report_2026.pdf', '/documents/budget_report_2026.pdf', 1, 2, 'approved', 'finance, budget, annual'),
('Employee Policy Update', 'Updated HR policies for 2026', 'Employee_Policy_2026.docx', '/documents/employee_policy_2026.docx', 2, 3, 'pending', 'hr, policy, update'),
('Network Security Manual', 'IT Department security guidelines', 'Network_Security.pdf', '/documents/network_security.pdf', 3, 4, 'approved', 'it, security, manual'),
('Supply Chain Procedure', 'Standard procedures for supply chain', 'Supply_Chain_SOP.docx', '/documents/supply_chain_sop.docx', 4, 5, 'pending', 'supply, procedure, sop'),
('Production Schedule Q3', 'Q3 production planning', 'Production_Schedule_Q3.pdf', '/documents/production_schedule_q3.pdf', 5, 6, 'approved', 'production, schedule, planning'),
('Sales Target 2026', 'Sales targets and goals', 'Sales_Target_2026.xlsx', '/documents/sales_target_2026.xlsx', 6, 7, 'pending', 'sales, targets, goals');

-- Insert Sample Approvals
INSERT INTO approvals (document_id, requested_by, assigned_to, status, comments) VALUES
(2, 3, 2, 'pending', 'Awaiting Finance manager review'),
(4, 5, 3, 'pending', 'Awaiting HR manager approval'),
(6, 7, 2, 'pending', 'Awaiting approval');

-- Insert Sample Permissions
INSERT INTO permissions (user_id, department_id, can_view, can_edit, can_approve, can_delete, can_share) VALUES
(1, NULL, TRUE, TRUE, TRUE, TRUE, TRUE), -- Admin - all permissions
(2, 1, TRUE, TRUE, TRUE, TRUE, TRUE), -- Finance Manager
(3, 2, TRUE, TRUE, TRUE, TRUE, TRUE), -- HR Manager
(4, 3, TRUE, TRUE, FALSE, FALSE, TRUE), -- ICT User
(5, 4, TRUE, FALSE, FALSE, FALSE, FALSE), -- Supply Chain User
(6, 5, TRUE, FALSE, FALSE, FALSE, FALSE), -- Production User
(7, 6, TRUE, FALSE, FALSE, FALSE, FALSE), -- Sales User
(8, 7, TRUE, FALSE, FALSE, FALSE, FALSE); -- QA User

-- Insert Sample Communications
INSERT INTO communications (message_type, sender_id, department_id, subject, message) VALUES
('announcement', 1, NULL, 'System Maintenance', 'The system will undergo maintenance on July 15, 2026'),
('message', 2, 1, 'Budget Review', 'Please review the updated budget proposal'),
('notification', 3, 2, 'New Employees', 'Welcome to our new employees joining HR department');

-- Create Indexes for Performance
CREATE INDEX idx_documents_department ON documents(department_id);
CREATE INDEX idx_documents_status ON documents(status);
CREATE INDEX idx_documents_created_at ON documents(created_at);
CREATE INDEX idx_users_department ON users(department_id);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_approvals_status ON approvals(status);
CREATE INDEX idx_approvals_document ON approvals(document_id);
CREATE INDEX idx_communications_sender ON communications(sender_id);
CREATE INDEX idx_communications_type ON communications(message_type);

-- ========================================
-- VIEWS FOR EASIER DATA RETRIEVAL
-- ========================================

-- View: Pending Documents Count
CREATE VIEW vw_pending_documents AS
SELECT 
    d.id,
    d.title,
    d.status,
    dep.name AS department,
    u.first_name,
    u.last_name,
    d.created_at
FROM documents d
JOIN departments dep ON d.department_id = dep.id
JOIN users u ON d.uploaded_by = u.id
WHERE d.status = 'pending'
ORDER BY d.created_at DESC;

-- View: Document Approval Status
CREATE VIEW vw_approval_status AS
SELECT 
    a.id,
    d.title,
    d.file_name,
    u1.first_name AS requested_by_first,
    u1.last_name AS requested_by_last,
    u2.first_name AS assigned_to_first,
    u2.last_name AS assigned_to_last,
    a.status,
    a.created_at,
    a.approved_at
FROM approvals a
JOIN documents d ON a.document_id = d.id
JOIN users u1 ON a.requested_by = u1.id
JOIN users u2 ON a.assigned_to = u2.id
ORDER BY a.created_at DESC;

-- View: Department Activity Summary
CREATE VIEW vw_department_activity AS
SELECT 
    dep.id,
    dep.name,
    COUNT(DISTINCT d.id) AS total_documents,
    SUM(CASE WHEN d.status = 'approved' THEN 1 ELSE 0 END) AS approved_documents,
    SUM(CASE WHEN d.status = 'pending' THEN 1 ELSE 0 END) AS pending_documents,
    SUM(CASE WHEN d.status = 'rejected' THEN 1 ELSE 0 END) AS rejected_documents,
    COUNT(DISTINCT u.id) AS total_employees
FROM departments dep
LEFT JOIN documents d ON dep.id = d.department_id
LEFT JOIN users u ON dep.id = u.department_id
GROUP BY dep.id, dep.name;

-- View: User Detailed Information
CREATE VIEW vw_user_details AS
SELECT 
    u.id,
    u.username,
    u.email,
    CONCAT(u.first_name, ' ', u.last_name) AS full_name,
    u.first_name,
    u.last_name,
    dep.name AS department,
    u.role,
    u.phone,
    u.status,
    u.created_at,
    u.last_login
FROM users u
LEFT JOIN departments dep ON u.department_id = dep.id
ORDER BY u.created_at DESC;

-- View: Document Sharing Status
CREATE VIEW vw_document_sharing_status AS
SELECT 
    ds.id,
    d.title AS document_title,
    u1.first_name AS shared_by_first,
    u1.last_name AS shared_by_last,
    CASE 
        WHEN ds.shared_with_user_id IS NOT NULL THEN CONCAT(u2.first_name, ' ', u2.last_name)
        WHEN ds.shared_with_department_id IS NOT NULL THEN d2.name
    END AS shared_with,
    ds.can_view,
    ds.can_edit,
    ds.can_share,
    ds.created_at
FROM document_sharing ds
JOIN documents d ON ds.document_id = d.id
JOIN users u1 ON ds.shared_by = u1.id
LEFT JOIN users u2 ON ds.shared_with_user_id = u2.id
LEFT JOIN departments d2 ON ds.shared_with_department_id = d2.id
ORDER BY ds.created_at DESC;