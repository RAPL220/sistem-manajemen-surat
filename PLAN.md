# Sistem Manajemen Surat - Comprehensive Development Plan

## Overview
Web-based letter management system built in PHP with the following key features:
- Multi-level access (Admin, Manager, Director need login; Users don't need login)
- Letter submission and approval workflow
- Template management
- In-app notifications (replacing WhatsApp)
- Digital signature upload system
- Real-time status tracking

## Technology Stack
- **Backend:** PHP 7.4+ with PDO
- **Database:** MySQL/MariaDB
- **Frontend:** HTML5, CSS3, JavaScript (no external frameworks)
- **File Upload:** PHP native file handling
- **Authentication:** Session-based for admin roles only

## Database Schema

### 1. users table
```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'manager', 'director') NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    digital_signature_path VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### 2. templates table
```sql
CREATE TABLE templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    template_content TEXT NOT NULL,
    fields_required JSON NOT NULL,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);
```

### 3. letters table
```sql
CREATE TABLE letters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    letter_number VARCHAR(50) UNIQUE NOT NULL,
    template_id INT NOT NULL,
    submitter_name VARCHAR(255) NOT NULL,
    submitter_email VARCHAR(255),
    submitter_phone VARCHAR(20),
    letter_data JSON NOT NULL,
    status ENUM('pending', 'manager_approved', 'director_approved', 'rejected', 'revision') DEFAULT 'pending',
    manager_action_by INT NULL,
    manager_action_at TIMESTAMP NULL,
    manager_notes TEXT NULL,
    director_action_by INT NULL,
    director_action_at TIMESTAMP NULL,
    director_notes TEXT NULL,
    final_letter_path VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES templates(id),
    FOREIGN KEY (manager_action_by) REFERENCES users(id),
    FOREIGN KEY (director_action_by) REFERENCES users(id)
);
```

### 4. notifications table
```sql
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    letter_id INT NULL,
    message TEXT NOT NULL,
    type ENUM('new_submission', 'approval', 'rejection', 'revision') NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (letter_id) REFERENCES letters(id)
);
```

## File Structure

```
/letter-management-system/
├── config/
│   ├── database.php          # Database connection
│   └── config.php           # App configuration
├── includes/
│   ├── functions.php        # Common functions
│   ├── auth.php            # Authentication functions
│   └── notifications.php   # Notification functions
├── admin/
│   ├── login.php           # Admin login
│   ├── dashboard.php       # Admin dashboard
│   ├── templates.php       # Template management
│   ├── letters.php         # Letter monitoring
│   ├── users.php          # User management
│   └── logout.php         # Logout
├── manager/
│   ├── login.php          # Manager login
│   ├── dashboard.php      # Manager dashboard
│   ├── review.php         # Letter review/approval
│   ├── signature.php      # Signature upload
│   └── logout.php         # Logout
├── director/
│   ├── login.php          # Director login
│   ├── dashboard.php      # Director dashboard
│   ├── review.php         # Letter review/approval
│   ├── signature.php      # Signature upload
│   └── logout.php         # Logout
├── public/
│   ├── index.php          # Public letter submission
│   ├── track.php          # Letter tracking
│   └── view.php           # View letter status
├── assets/
│   ├── css/
│   │   └── style.css      # Main stylesheet
│   ├── js/
│   │   └── main.js        # JavaScript functions
│   └── uploads/
│       ├── signatures/    # Digital signatures
│       └── letters/       # Generated letters
└── api/
    ├── submit.php         # Letter submission API
    ├── track.php          # Tracking API
    └── notifications.php  # Notifications API
```

## Key Features Implementation

### 1. Public Letter Submission (No Login Required)
- **File:** `public/index.php`
- **Features:**
  - Template selection dropdown
  - Dynamic form fields based on template
  - Auto-generated letter number
  - Email/phone for tracking
  - File upload for supporting documents

### 2. Letter Tracking (No Login Required)
- **File:** `public/track.php`
- **Features:**
  - Track by letter number or email
  - Real-time status updates
  - Timeline view of approval process
  - Download final letter when approved

### 3. Admin Panel
- **Files:** `admin/*`
- **Features:**
  - Template CRUD operations
  - Letter monitoring dashboard
  - User management
  - System notifications
  - Reports and statistics

### 4. Manager/Director Approval System
- **Files:** `manager/*`, `director/*`
- **Features:**
  - Pending letters list
  - Approve/Reject/Request Revision
  - Digital signature integration
  - Bulk actions
  - Notification system

### 5. Digital Signature System
- **Implementation:**
  - PNG upload with validation
  - Automatic signature application on approval
  - Signature preview
  - Multiple signature support per user

### 6. Notification System
- **Features:**
  - In-app notifications
  - Email notifications (optional)
  - Real-time updates
  - Notification history

## Security Considerations

1. **Input Validation:**
   - Sanitize all user inputs
   - Validate file uploads
   - SQL injection prevention with PDO

2. **File Security:**
   - Restrict upload file types
   - Generate unique filenames
   - Store uploads outside web root

3. **Session Security:**
   - Secure session configuration
   - CSRF protection
   - Session timeout

4. **Access Control:**
   - Role-based permissions
   - Route protection
   - API endpoint security

## Development Phases

### Phase 1: Core Infrastructure
1. Database setup and configuration
2. Basic authentication system
3. Template management system
4. Public letter submission form

### Phase 2: Approval Workflow
1. Manager/Director dashboards
2. Approval/rejection system
3. Digital signature integration
4. Letter generation with signatures

### Phase 3: Notifications & Tracking
1. Notification system
2. Letter tracking system
3. Email notifications
4. Status updates

### Phase 4: Advanced Features
1. Reporting and analytics
2. Bulk operations
3. Advanced search and filtering
4. System optimization

## UI/UX Design Principles

1. **Clean and Modern:**
   - Minimal design with proper spacing
   - Black and white color scheme
   - Typography-focused layout
   - No external icons or images

2. **Responsive Design:**
   - Mobile-first approach
   - Flexible grid system
   - Touch-friendly interfaces

3. **User Experience:**
   - Intuitive navigation
   - Clear status indicators
   - Progressive disclosure
   - Accessibility compliance

## Testing Strategy

1. **Unit Testing:**
   - Function-level testing
   - Database operations
   - File upload validation

2. **Integration Testing:**
   - Workflow testing
   - API endpoint testing
   - Cross-browser compatibility

3. **User Acceptance Testing:**
   - Role-based testing
   - End-to-end workflows
   - Performance testing

This plan provides a comprehensive roadmap for building the letter management system with all requested features while maintaining security, scalability, and user experience standards.
