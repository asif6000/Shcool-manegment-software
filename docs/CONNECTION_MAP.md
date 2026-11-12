# Dream Roots Millennium School - Module Connection Map

## Project Architecture

**Stack**: Laravel 12, PHP 8.2, MySQL  
**Architecture**: Single School Management System  
**Database**: One shared database for all portals  

---

## Database Schema Overview

### Core Tables

| Table | Purpose | Key Relationships |
|-------|---------|------------------|
| `schools` | Central school configuration | users, academic_sessions, classes, teachers, students, employees |
| `users` | Authentication & user accounts | school_id (FK), roles (via Spatie), teacher/employee/student (1:1) |
| `roles` | Role definitions (school_admin, principal, accountant, teacher, student) | via Spatie Permission |
| `permissions` | Granular permissions | via Spatie Permission |

### Academic Structure

| Table | Purpose | Key Relationships |
|-------|---------|------------------|
| `academic_sessions` | Academic years | school_id (FK) |
| `classes` | Classes/Grades | school_id (FK) |
| `sections` | Class sections | school_id, class_id (FK) |
| `subjects` | Subjects | school_id (FK) |
| `class_subjects` | Subject assignments to classes | school_id, academic_session_id, class_id, subject_id (FK) |
| `holidays` | School holidays | school_id, academic_session_id (FK) |

### People Management

| Table | Purpose | Key Relationships |
|-------|---------|------------------|
| `students` | Student records | school_id, user_id (FK), created_by (FK) |
| `teachers` | Teacher records | school_id, user_id (FK) |
| `employees` | Non-teaching staff | school_id, user_id (FK) |
| `guardians` | Student guardians | school_id, user_id (FK) |
| `student_guardians` | Student-guardian relationships | school_id, student_id, guardian_id (FK) |
| `enrollments` | Student class enrollment | school_id, student_id, academic_session_id, class_id, section_id (FK) |

### Academic Operations

| Table | Purpose | Key Relationships |
|-------|---------|------------------|
| `class_routines` | Teacher timetable | school_id, academic_session_id, class_id, section_id, subject_id, teacher_id (FK) |
| `student_attendances` | Student attendance | school_id, student_id, enrollment_id, academic_session_id (FK) |
| `staff_attendances` | Staff attendance | school_id, teacher_id, employee_id (FK) |
| `homework` | Homework assignments | school_id, academic_session_id, class_id, section_id, subject_id, teacher_id (FK) |
| `homework_submissions` | Student homework submissions | school_id, homework_id, student_id (FK) |
| `study_materials` | Classwork & study materials | school_id, academic_session_id, class_id, section_id, subject_id, teacher_id (FK) |
| `lessons` | Lesson plans | school_id, academic_session_id, class_id, section_id, subject_id, teacher_id (FK) |

### Fee Management

| Table | Purpose | Key Relationships |
|-------|---------|------------------|
| `fee_types` | Fee categories (tuition, library, etc.) | school_id (FK) |
| `fee_structures` | Fee amounts per class | school_id, academic_session_id, class_id, fee_type_id (FK) |
| `fee_invoices` | Student fee invoices | school_id, academic_session_id, student_id, enrollment_id (FK) |
| `fee_invoice_items` | Invoice line items | school_id, fee_invoice_id, fee_type_id (FK) |
| `fee_invoice_discounts` | Invoice discounts | school_id, fee_invoice_id, fee_discount_id (FK) |
| `fee_payments` | Payment records | school_id, student_id, received_by (FK) |
| `fee_payment_allocations` | Payment to invoice allocation | school_id, fee_payment_id, fee_invoice_id (FK) |
| `fee_receipts` | Receipt snapshots | school_id, fee_payment_id (FK) |

### Examination

| Table | Purpose | Key Relationships |
|-------|---------|------------------|
| `grade_scales` | Grading systems | school_id (FK) |
| `grade_scale_items` | Grade definitions | school_id, grade_scale_id (FK) |
| `exams` | Examinations | school_id, academic_session_id, created_by (FK) |
| `exam_subjects` | Exam papers | school_id, exam_id, class_id, subject_id (FK) |
| `exam_marks` | Student marks | school_id, exam_subject_id, student_id, entered_by (FK) |
| `student_results` | Published results | school_id, exam_id, student_id (FK) |

---

## Role Hierarchy & Permissions

### Role Levels (config/school.php)

| Role | Level | Workspace | Authentication |
|------|-------|-----------|----------------|
| school_admin | 90 | /admin | Email + Password |
| principal | 70 | /admin | Email + Password |
| accountant | 60 | /admin | Mobile + Password |
| teacher | 50 | /admin | Mobile + Password |
| student | 20 | /student | Mobile + Password |

### Permission Matrix

#### School Admin (90)
- Full access to all modules EXCEPT:
  - Homework/classwork creation (teacher-only)
  - Fee collection (accountant-only)

#### Principal (70)
- Oversight permissions for all modules
- Can view, approve, but limited creation rights

#### Accountant (60)
- Fee collection & management
- Expense management
- Banking & vouchers
- Reports (read-only)

#### Teacher (50)
- Attendance marking
- Homework/classwork management
- Study materials
- Marks entry
- Lessons
- Own profile & leave

#### Student (20)
- View own attendance
- View own homework
- View own materials
- View own results
- View own fees
- View notices

---

## Portal Connections

### 1. Admin Portal (/admin)

**Controller**: UserController, StaffController, StudentController, DashboardController  
**Middleware**: EnsureStaffWorkspace, EnsureAccountIsActive, ResolveSchoolContext  
**Service**: UserService, StaffService, StudentService

**Data Sources**:
- `users` table (all roles)
- `teachers` table (teacher records)
- `employees` table (accountant & other staff)
- `students` table (student records)
- `enrollments` table (class assignments)
- All configuration tables (classes, sections, subjects, fee_types, etc.)

**Control Capabilities**:
- ✅ Create/Update/Delete Teacher accounts
- ✅ Create/Update/Delete Student accounts
- ✅ Create/Update/Delete Accountant accounts
- ✅ Class management (classes, sections)
- ✅ Subject management
- ✅ Timetable (class_routines)
- ✅ Fee types & fee structures
- ✅ Academic sessions
- ✅ Attendance settings
- ✅ Examination configuration
- ✅ Notices management
- ✅ System permissions (roles)
- ✅ Reports (all modules)

**Synchronization Outbound**:
- → Teacher Portal: Assigned classes, subjects, timetable
- → Student Portal: Class enrollment, fee invoices, exam results
- → Accountant Portal: Fee structures, student records

---

### 2. Teacher Portal (/admin/teacher/*)

**Controller**: TeacherWorkspaceController, TeacherHomeworkController, TeacherWorkController  
**Middleware**: EnsureStaffWorkspace, EnsureAccountIsActive, ResolveSchoolContext  
**Service**: TeacherWorkspaceService, HomeworkService, StudyMaterialService

**Data Sources**:
- `users` table (own account)
- `teachers` table (own profile)
- `class_routines` table (assigned periods via teacher_id)
- `enrollments` table (students in assigned classes)
- `homework` table (homework they created)
- `homework_submissions` table (student submissions)
- `study_materials` table (materials they created)
- `exam_marks` table (marks they entered)

**Access Restrictions**:
- Only sees classes/subjects assigned in timetable
- Only sees students in those classes
- Can only manage own homework/materials
- Can only enter marks for assigned subjects

**Synchronization Inbound (from Admin)**:
- ← Admin: Class assignments via class_routines
- ← Admin: Subject assignments via class_subjects
- ← Admin: Student enrollments via enrollments

**Synchronization Outbound**:
- → Admin: Attendance records (student_attendances)
- → Admin: Homework assignments (homework)
- → Student: Homework submissions (homework_submissions)
- → Student: Study materials (study_materials)
- → Admin: Exam marks (exam_marks)

---

### 3. Student Portal (/student/*)

**Controller**: StudentPortalController, StudentPortalHomeworkController, StudentPortalResultController  
**Middleware**: EnsureStudentPortal, EnsureAccountIsActive, ResolveSchoolContext  
**Service**: StudentPortalService

**Data Sources**:
- `users` table (own account)
- `students` table (own profile)
- `enrollments` table (own class enrollment)
- `student_attendances` table (own attendance)
- `guardians` table (guardian info)
- `fee_invoices` table (own invoices)
- `fee_payments` table (own payments)
- `exam_marks` table (own marks)
- `student_results` table (published results)
- `homework` table (homework for own class)
- `homework_submissions` table (own submissions)
- `study_materials` table (materials for own class)
- `notices` table (student-visible notices)

**Access Restrictions**:
- Only sees own records (enrollment, attendance, fees, results)
- Can only see homework/materials for own class
- Can only submit own homework
- Cannot access other students' data

**Synchronization Inbound (from Admin)**:
- ← Admin: Class enrollment via enrollments
- ← Admin: Fee invoices via fee_invoices
- ← Admin: Exam results via student_results
- ← Teacher: Homework via homework
- ← Teacher: Materials via study_materials
- ← Teacher: Attendance via student_attendances

**Synchronization Outbound**:
- → Teacher: Homework submissions (homework_submissions)
- → Accountant: No direct writes (read-only fees)

---

### 4. Accountant Portal (/admin/fees/counter)

**Controller**: AccountantFeeCollectionController, FeePaymentController  
**Middleware**: EnsureStaffWorkspace, EnsureAccountIsActive, ResolveSchoolContext  
**Service**: AccountantFeeCollectionService, FeePaymentService

**Data Sources**:
- `users` table (own account)
- `employees` table (own profile)
- `students` table (student records)
- `enrollments` table (to find students by class)
- `fee_types` table (fee categories)
- `fee_structures` table (fee amounts)
- `fee_invoices` table (outstanding invoices)
- `fee_payments` table (payment records)
- `fee_receipts` table (receipt snapshots)

**Access Restrictions**:
- Can only collect payments (not configure fees)
- Cannot modify fee structures
- Cannot delete invoices
- Cannot access admin settings

**Synchronization Inbound (from Admin)**:
- ← Admin: Fee structures via fee_structures
- ← Admin: Student records via students
- ← Admin: Fee invoices via fee_invoices

**Synchronization Outbound**:
- → Admin: Payment records (fee_payments)
- → Admin: Collection dashboard (via reports)
- → Student: Payment history (via fee_payments)
- → Student: Invoice status updates (via fee_payment_allocations)

---

## Data Synchronization Flow

### Teacher → Admin
1. Teacher marks attendance → `student_attendances` table
2. Teacher creates homework → `homework` table
3. Teacher publishes materials → `study_materials` table
4. Teacher enters marks → `exam_marks` table
5. Admin views all data from shared database

### Teacher → Student
1. Teacher creates homework → `homework` table
2. Student views homework (filtered by class)
3. Student submits homework → `homework_submissions` table
4. Teacher views submissions

### Accountant → Admin
1. Accountant collects payment → `fee_payments` table
2. Payment allocated to invoice → `fee_payment_allocations` table
3. Receipt generated → `fee_receipts` table
4. Admin views collection dashboard

### Accountant → Student
1. Accountant records payment → `fee_payments` table
2. Invoice balance updated → `fee_invoices` table
3. Student views updated payment history

### Admin → Teacher
1. Admin assigns teacher to class → `class_routines` table
2. Teacher sees assigned classes in workspace
3. Admin assigns subject to teacher → `class_subjects` table
4. Teacher sees assigned subjects

### Admin → Student
1. Admin enrolls student → `enrollments` table
2. Student sees class/section in portal
3. Admin creates fee invoice → `fee_invoices` table
4. Student sees outstanding fees
5. Admin publishes result → `student_results` table
6. Student sees published result

### Admin → Accountant
1. Admin configures fee structure → `fee_structures` table
2. Accountant sees fee types & amounts
3. Admin creates fee invoices → `fee_invoices` table
4. Accountant sees outstanding invoices

---

## Security & Permission Layers

### Middleware Stack (for protected routes)

```
Route Middleware:
1. auth (session authentication)
2. active.account (EnsureAccountIsActive)
3. school.context (ResolveSchoolContext)
4. password.changed (EnsurePasswordIsChanged)
5. staff.workspace (EnsureStaffWorkspace) OR student.portal (EnsureStudentPortal)
6. Role/Permission checks in controllers/policies
```

### Key Security Features

1. **School Context**: All queries automatically scoped to `school_id`
2. **Role Hierarchy**: Higher roles can manage lower roles
3. **Permission Matrix**: Granular permissions per module
4. **Teacher Scoping**: Teachers only see their assigned classes
5. **Student Isolation**: Students only see their own records
6. **Accountant Limits**: Accountants cannot configure fees
7. **Audit Trail**: All critical actions logged to `audit_logs` table

---

## API Architecture

### API Routes (/api/v1/*)

**Authentication**: Laravel Sanctum (token-based)

**Endpoints**:
- POST `/api/v1/auth/login` - Authenticate
- POST `/api/v1/auth/logout` - Logout
- GET `/api/v1/auth/me` - Current user
- GET `/api/v1/dashboard` - Dashboard data
- GET `/api/v1/notifications/*` - Notifications
- GET `/api/v1/school` - School info
- GET/PUT `/api/v1/users/*` - User management
- POST `/api/v1/attendance/events` - Biometric device events

**API Middleware**:
- auth:sanctum
- active.account
- school.context
- password.changed
- throttle:api

---

## Current Status

### ✅ Implemented & Connected
- Single database with proper foreign keys
- Central authentication (Admin: email, Others: mobile)
- Role-based access control (Satie Permission)
- School context scoping
- Teacher workspace with timetable integration
- Student portal with enrollment integration
- Accountant fee collection desk
- Middleware stack for workspace separation
- API endpoints for mobile/web clients

### 🔍 Verification Needed
- Teacher → Admin attendance sync
- Teacher → Student homework sync
- Accountant → Admin payment sync
- Accountant → Student payment history sync
- Permission checks for cross-role access
- Data isolation between students
- Invoice allocation correctness

---

## Critical Integration Points

1. **class_routines** table: Central link between Admin assignments and Teacher workspace
2. **enrollments** table: Central link between Admin and Student class assignment
3. **fee_invoices** table: Central link between Admin and Accountant/Student fees
4. **student_attendances** table: Central link between Teacher and Admin/Student attendance
5. **homework_submissions** table: Central link between Student and Teacher homework
6. **fee_payment_allocations** table: Central link between Accountant and Student payment tracking

---

## Production Readiness Checklist

- [ ] All portal routes tested with actual login
- [ ] Permission checks verified for each role
- [ ] Data isolation verified (students can't see other students)
- [ ] Teacher scoping verified (teachers only see assigned classes)
- [ ] Accountant limits verified (cannot configure fees)
- [ ] Synchronization flows tested end-to-end
- [ ] Database transactions tested for critical operations
- [ ] Audit trail verified for all critical actions
- [ ] API endpoints tested with Sanctum tokens
- [ ] Mobile responsiveness verified for all portals
- [ ] Error handling verified for edge cases
