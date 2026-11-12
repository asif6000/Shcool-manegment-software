# Dream Roots Millennium School Management System
# Portal Connection Map and Architecture Analysis

## EXECUTIVE SUMMARY

**Project Status:** The system ALREADY has a centralized architecture with:
- ✅ Single shared database
- ✅ Single Laravel backend
- ✅ Central authentication system
- ✅ Role-based access control
- ✅ Central user management
- ✅ Immediate data synchronization (database-level)

**Current Architecture:** All four portals (Admin, Teacher, Student, Accountant) operate on the SAME codebase with role-based routing and middleware separation.

---

## 1. CENTRAL ARCHITECTURE OVERVIEW

### Single Database Schema
```
Single MySQL Database: dream
├── users (central authentication table)
├── schools (multi-tenancy root)
├── All operational tables (school_id foreign key)
└── All tables share the same database
```

### Authentication Flow
```
┌─────────────────────────────────────────────────────┐
│                  Central Auth System                 │
│  - LoginController (email for admin, mobile for others) │
│  - StudentMobileAuthController (student mobile login) │
│  - UserService (user account management)             │
│  - AuthenticationService (login/logout/rate limiting)   │
└─────────────────────────────────────────────────────┘
                        ↓
        ┌───────────────┴───────────────┐
        ↓               ↓               ↓
   Admin Portal   Teacher Portal   Student Portal  Accountant Portal
   (/admin/*)     (/admin/my/*)    (/student/*)    (/admin/*)
```

### Portal Separation Strategy
```
NOT separate databases or codebases
BUT role-based route separation:

- Admin Portal: /admin/* with staff.workspace middleware
- Teacher Portal: /admin/my/* with staff.workspace + teaching scope
- Student Portal: /student/* with student.portal middleware
- Accountant Portal: /admin/* with accountant role permissions
```

---

## 2. DATABASE CONNECTION MAP

### Central Tables (Shared by All Portals)
```
┌────────────────────────────────────────────────────────────┐
│                    CENTRAL USER SYSTEM                      │
├────────────────────────────────────────────────────────────┤
│ users (id, school_id, name, email, mobile_number, phone)   │
│ ├──→ teachers (user_id nullable, unique)                   │
│ ├──→ employees (user_id nullable, unique)                  │
│ ├──→ students (user_id nullable, unique)                   │
│ └──→ guardians (user_id nullable)                          │
├────────────────────────────────────────────────────────────┤
│ permissions (Spatie Permission package)                     │
│ roles (Spatie Permission package)                          │
│ model_has_permissions (polymorphic)                         │
│ model_has_roles (polymorphic)                               │
└────────────────────────────────────────────────────────────┘
```

### School Context (All Portals Scoped to School)
```
┌────────────────────────────────────────────────────────────┐
│                    SCHOOL CONTEXT                           │
├────────────────────────────────────────────────────────────┤
│ schools (id, name, code, settings)                         │
│ ├──→ All users belong to a school (school_id)               │
│ ├──→ All operational tables have school_id                 │
│ └──→ SchoolContext service provides current school_id        │
├────────────────────────────────────────────────────────────┤
│ BelongsToSchool trait on all major models:                  │
│ - Automatically filters by school_id                         │
│ - Automatically sets school_id on creation                  │
│ - Provides withoutSchoolScope() for cross-school queries    │
└────────────────────────────────────────────────────────────┘
```

### Academic Structure (Admin Configures, All Portals Use)
```
┌────────────────────────────────────────────────────────────┐
│              ACADEMIC STRUCTURE (Admin → All)                │
├────────────────────────────────────────────────────────────┤
│ academic_sessions (id, school_id, name, is_current)          │
│ ├──→ Used by all portals for session context               │
│ ├──→ Current session used by default                        │
│ └──→ All operational data is session-scoped                 │
├────────────────────────────────────────────────────────────┤
│ classes (id, school_id, name, level)                        │
│ ├──→ sections (id, class_id, name)                          │
│ ├──→ enrollments (student_id, class_id, session_id)         │
│ └──→ class_subjects (session_id, class_id, subject_id)      │
├────────────────────────────────────────────────────────────┤
│ subjects (id, school_id, name, code)                        │
│ └──→ class_subjects (links subjects to classes per session)  │
├────────────────────────────────────────────────────────────┤
│ class_routines (session_id, class_id, teacher_id, day)     │
│ ├──→ Teacher's timetable (teachers view own)                │
│ ├──→ Students view their class routine                      │
│ └──→ Admin views all routines                               │
└────────────────────────────────────────────────────────────┘
```

---

## 3. PORTAL-SPECIFIC DATA FLOW

### ADMIN PORTAL (/admin/*)
**Purpose:** Central control center for all operations

**Admin Creates/Manages:**
```
┌────────────────────────────────────────────────────────────┐
│                    ADMIN OPERATIONS                         │
├────────────────────────────────────────────────────────────┤
│ User Management:                                            │
│ ├──→ UserService.create() - Creates User + assigns role    │
│ ├──→ Teacher creation via StaffService                     │
│ ├──→ Student creation via StudentService                   │
│ ├──→ Accountant creation via UserService                  │
│ └──→ Role management via RoleController                    │
├────────────────────────────────────────────────────────────┤
│ Academic Configuration:                                     │
│ ├──→ AcademicSession (years/sessions)                      │
│ ├──→ SchoolClass (classes/grades)                          │
│ ├──→ Section (class sections)                              │
│ ├──→ Subject (subject catalog)                             │
│ ├──→ ClassSubject (assign subjects to classes)              │
│ └──→ ClassRoutine (teacher schedules)                      │
├────────────────────────────────────────────────────────────┤
│ Fee Configuration:                                          │
│ ├──→ FeeType (fee catalog)                                 │
│ ├──→ FeeStructure (class-level fees)                       │
│ ├──→ FeeDiscount (discount rules)                         │
│ ├──→ FeeStudentAssignment (student-specific fees)          │
│ └──→ BankAccount (bank accounts)                           │
├────────────────────────────────────────────────────────────┤
│ Exam Configuration:                                         │
│ ├──→ GradeScale (grading rules)                            │
│ ├──→ Exam (exam definitions)                               │
│ └──→ ExamSubject (exam papers)                             │
├────────────────────────────────────────────────────────────┤
│ Communication:                                              │
│ ├──→ Notice (school announcements)                         │
│ └──→ Notification (targeted notifications)                  │
└────────────────────────────────────────────────────────────┘
```

**Admin Views All Data:**
- Can view all teachers' homework (but restricted from editing)
- Can view all attendance records
- Can view all fee collections
- Can view all exam marks (via MarkSubmission review)
- Monitors accountant performance via AccountantManagementController

---

### TEACHER PORTAL (/admin/my/*)
**Purpose:** Teaching operations scoped to teacher's timetable

**Teacher Uses Admin-Configured:**
```
┌────────────────────────────────────────────────────────────┐
│                  TEACHER OPERATIONS                         │
├────────────────────────────────────────────────────────────┤
│ Data Received from Admin:                                   │
│ ├──→ teacher profile (created by Admin)                    │
│ ├──→ assigned classes (from ClassRoutine)                   │
│ ├──→ assigned subjects (from ClassRoutine)                  │
│ ├──→ enrolled students (from Enrollment)                    │
│ ├──→ current academic session (from AcademicSession)        │
│ └── fee structure (read-only)                               │
├────────────────────────────────────────────────────────────┤
│ Teacher Creates (All use TeachingScope for validation):     │
│ ├──→ Homework (HomeworkService.create())                     │
│ │   └──→ Linked to class/section/subject                   │
│ ├──→ StudyMaterial (StudyMaterialService.create())           │
│ │   └──→ Linked to class/section/subject                   │
│ ├──→ Lesson (LessonService.create())                        │
│ │   └──→ Linked to class/section/subject                   │
│ ├──→ ClassVideo (ClassVideoController.store())              │
│ │   └──→ Linked to class/section/subject                   │
│ └──→ StudentAttendance (mark attendance)                    │
│     └──→ AttendanceSubmission workflow                     │
├────────────────────────────────────────────────────────────┤
│ Teacher Reviews:                                            │
│ ├──→ HomeworkSubmission (student submissions)              │
│ ├──→ MarkSubmission (mark sheet review)                    │
│ └──→ StudentLeave (leave requests)                         │
├────────────────────────────────────────────────────────────┤
│ Data Synchronization:                                       │
│ ├──→ Homework → Student sees (via StudentPortalHomework)    │
│ ├──→ Attendance → Student sees (via StudentPortalController) │
│ ├──→ Marks → Student sees (via StudentResult)              │
│ └──→ All data written to shared database                    │
└────────────────────────────────────────────────────────────┘
```

**TeachingScope Restriction:**
- Teachers can only access classes/subjects on their timetable
- Admin/Principal can view all teacher work but restricted from editing
- Implemented via TeachingScope service in controllers/services

---

### STUDENT PORTAL (/student/*)
**Purpose:** Student-specific data viewing and limited actions

**Student Uses Admin-Configured:**
```
┌────────────────────────────────────────────────────────────┐
│                  STUDENT OPERATIONS                          │
├────────────────────────────────────────────────────────────┤
│ Data Received from Admin:                                   │
│ ├──→ student profile (created by Admin)                    │
│ ├──→ enrollment (current class/section/session)             │
│ ├──→ guardian details (linked by student_guardians)        │
│ ├──→ class routine (from ClassRoutine)                      │
│ └── current academic session                               │
├────────────────────────────────────────────────────────────┤
│ Student Views (read-only, own data only):                  │
│ ├──→ Homework (filtered by enrollment via scopeForEnrollment)│
│ ├──→ StudyMaterial (filtered by enrollment)                 │
│ ├──→ Lesson (filtered by enrollment)                        │
│ ├──→ ClassVideo (filtered by enrollment)                    │
│ ├──→ StudentAttendance (own records only)                  │
│ ├──→ StudentResult (own results only)                       │
│ ├──→ FeeInvoice (own invoices only)                         │
│ ├──→ FeePayment (own payments only)                         │
│ └── Notice (public or student-audience)                     │
├────────────────────────────────────────────────────────────┤
│ Student Actions:                                             │
│ ├──→ HomeworkSubmission (submit homework)                   │
│ ├──→ StudentLeave (request leave)                          │
│ └──→ FeePayment (online payment via gateway)               │
├────────────────────────────────────────────────────────────┤
│ Data Synchronization:                                       │
│ ├──→ All data read from shared database                    │
│ ├──→ Submissions visible to Teacher (via TeacherHomework)   │
│ ├──→ Payments visible to Accountant/Admin                  │
│ └──→ No separate database - immediate sync                  │
└────────────────────────────────────────────────────────────┘
```

**Student Portal Middleware:**
- EnsureStudentPortal - restricts to student role with student record
- Redirects to applicant dashboard if no student record
- All queries scoped to student's enrollment

---

### ACCOUNTANT PORTAL (/admin/* with accountant role)
**Purpose:** Fee collection and financial operations

**Accountant Uses Admin-Configured:**
```
┌────────────────────────────────────────────────────────────┐
│                ACCOUNTANT OPERATIONS                         │
├────────────────────────────────────────────────────────────┤
│ Data Received from Admin:                                   │
│ ├──→ accountant profile (created by Admin)                  │
│ ├──→ FeeType (fee catalog)                                  │
│ ├──→ FeeStructure (class-level fees)                        │
│ ├──→ FeeStudentAssignment (student fees)                    │
│ ├──→ student records (via StudentController)                │
│ ├──→ BankAccount (bank accounts)                            │
│ └──→ current academic session                               │
├────────────────────────────────────────────────────────────┤
│ Accountant Actions:                                          │
│ ├──→ FeeInvoice (generate invoices)                         │
│ ├──→ FeePayment (receive payments)                         │
│ │   └──→ FeePaymentAllocation (link payment to invoice)    │
│ │   └──→ FeeReceipt (issue receipt)                        │
│ ├──→ Expense (record expenses)                              │
│ ├──→ CashTransaction (cashbook entries)                     │
│ ├──→ Voucher (manual vouchers)                              │
│ └──→ SalaryPayment (process salaries)                      │
├────────────────────────────────────────────────────────────┤
│ Data Synchronization:                                       │
│ ├──→ Payments visible to Student (via StudentPortalController)│
│ ├──→ Payments visible to Admin (via AccountantManagementController)│
│ ├──→ Cashbook visible to Admin (via CashbookController)     │
│ └──→ All data written to shared database                    │
├────────────────────────────────────────────────────────────┤
│ Collection Authority:                                        │
│ ├──→ FeeCollectionAuthority service checks permissions      │
│ ├──→ Accountant: unrestricted collection                   │
│ ├──→ Teacher: scoped collection (own students only)        │
│ └──→ Admin/Principal: monitoring-only, cannot collect       │
└────────────────────────────────────────────────────────────┘
```

**Accountant Controllers:**
- AccountantFeeCollectionController - dedicated collection counter
- AccountantManagementController - roster and performance reports
- Reports: daily, monthly, export functionality

---

## 4. DATA SYNCHRONIZATION FLOW

### Teacher → Student (Academic Content)
```
┌──────────────┐       ┌──────────────┐       ┌──────────────┐
│   Teacher    │       │   Database   │       │   Student    │
│   Portal     │       │   (MySQL)    │       │   Portal     │
└──────┬───────┘       └──────┬───────┘       └──────┬───────┘
       │                      │                      │
       │ 1. Create Homework    │                      │
       ├─────────────────────→│                      │
       │                      │                      │
       │                      │ 2. Store in homework │
       │                      │    table             │
       │                      │                      │
       │                      │ 3. Student queries   │
       │                      │    homework table    │
       │                      │                      │
       │                      │←─────────────────────┤
       │                      │                      │
       │                      │ 4. Student views     │
       │                      │    homework          │
       │                      │                      │
       │ 5. Student submits    │                      │
       │                      │←─────────────────────┤
       │                      │                      │
       │                      │ 6. Store in          │
       │                      │    homework_submissions│
       │                      │                      │
       │ 7. Teacher reviews    │                      │
       │←─────────────────────┤                      │
       │                      │                      │
       │ 8. Update submission │                      │
       │    status/marks       │                      │
       ├─────────────────────→│                      │
       │                      │                      │
       │                      │ 9. Student sees       │
       │                      │    updated submission │
       │                      │←─────────────────────┤
       └──────────────────────┴──────────────────────┘

IMMEDIATE SYNC: All operations write directly to shared database
```

### Teacher → Admin (Attendance & Marks)
```
┌──────────────┐       ┌──────────────┐       ┌──────────────┐
│   Teacher    │       │   Database   │       │    Admin     │
│   Portal     │       │   (MySQL)    │       │   Portal     │
└──────┬───────┘       └──────┬───────┘       └──────┬───────┘
       │                      │                      │
       │ 1. Mark attendance    │                      │
       ├─────────────────────→│                      │
       │                      │                      │
       │                      │ 2. Store in          │
       │                      │    student_attendance │
       │                      │                      │
       │ 3. Submit register   │                      │
       ├─────────────────────→│                      │
       │                      │                      │
       │                      │ 4. Store in          │
       │                      │    attendance_submission│
       │                      │                      │
       │                      │ 5. Admin queries     │
       │                      │    submission table  │
       │                      │                      │
       │                      │←─────────────────────┤
       │                      │                      │
       │ 6. Admin approves     │                      │
       │←─────────────────────┤                      │
       │                      │                      │
       │ 7. Update submission  │                      │
       │    status             │                      │
       ├─────────────────────→│                      │
       │                      │                      │
       │ 8. Admin can view     │                      │
       │                      │    all attendance      │
       │                      │←─────────────────────┤
       └──────────────────────┴──────────────────────┘

ATTENDANCE SUBMISSION WORKFLOW:
- Teacher submits → Admin reviews → Admin approves/rejects
- Snapshot counts stored in attendance_submission
- Admin monitors via AttendanceSubmissionController
```

### Accountant → Student (Fees)
```
┌──────────────┐       ┌──────────────┐       ┌──────────────┐
│  Accountant  │       │   Database   │       │   Student    │
│   Portal     │       │   (MySQL)    │       │   Portal     │
└──────┬───────┘       └──────┬───────┘       └──────┬───────┘
       │                      │                      │
       │ 1. Generate invoice   │                      │
       ├─────────────────────→│                      │
       │                      │                      │
       │                      │ 2. Store in          │
       │                      │    fee_invoices      │
       │                      │                      │
       │ 3. Student views      │                      │
       │                      │    invoices          │
       │                      │←─────────────────────┤
       │                      │                      │
       │ 4. Student pays      │                      │
       │                      │←─────────────────────┤
       │                      │                      │
       │ 5. Receive payment    │                      │
       ├─────────────────────→│                      │
       │                      │                      │
       │                      │ 6. Store in          │
       │                      │    fee_payments       │
       │                      │    fee_allocations    │
       │                      │    fee_receipts       │
       │                      │                      │
       │ 7. Update invoice     │                      │
       │    balances          │                      │
       │                      │                      │
       │ 8. Student sees       │
       │    updated invoice    │
       │                      │←─────────────────────┤
       └──────────────────────┴──────────────────────┘

PAYMENT FLOW:
- FeeInvoice generated → Student views → Student pays
- Accountant receives → FeePayment created → Allocations created
- Receipt issued → Invoice balances updated
- Student sees updated status immediately
```

### Accountant → Admin (Financial Reports)
```
┌──────────────┐       ┌──────────────┐       ┌──────────────┐
│  Accountant  │       │   Database   │       │    Admin     │
│   Portal     │       │   (MySQL)    │       │   Portal     │
└──────┬───────┘       └──────┬───────┘       └──────┬───────┘
       │                      │                      │
       │ 1. Record payment    │                      │
       ├─────────────────────→│                      │
       │                      │                      │
       │                      │ 2. Store in          │
       │                      │    fee_payments       │
       │                      │    cash_transactions  │
       │                      │                      │
       │ 3. Admin queries      │
       │    financial reports  │
       │                      │←─────────────────────┤
       │                      │                      │
       │ 4. Admin sees         │
       │    collection data    │
       │←─────────────────────┤                      │
       │                      │                      │
       │ 5. Admin monitors     │
       │    accountant perf.   │
       │←─────────────────────┤ (via AccountantManagementController)
       └──────────────────────┴──────────────────────┘

ACCOUNTANT MONITORING:
- AccountantManagementController provides:
  - Roster of all accountants
  - Individual accountant performance
  - Daily collection reports
  - Monthly collection reports
  - Export functionality
- Admin can view but NOT use collection workflow (if also school_admin/principal)
```

### Admin → All Portals (Configuration)
```
┌──────────────┐       ┌──────────────┐       ┌──────────────┐
│    Admin     │       │   Database   │       │   All Portals│
│   Portal     │       │   (MySQL)    │       │              │
└──────┬───────┘       └──────┬───────┘       └──────┬───────┘
       │                      │                      │
       │ 1. Create class       │                      │
       ├─────────────────────→│                      │
       │                      │                      │
       │ 2. Teacher sees      │
       │    in timetable      │
       │                      │←─────────────────────┤
       │                      │                      │
       │ 3. Student enrolled  │
       │    in class          │
       ├─────────────────────→│                      │
       │                      │                      │
       │ 4. Student sees      │
       │    enrollment        │
       │                      │←─────────────────────┤
       │                      │                      │
       │ 5. Create fee type   │
       ├─────────────────────→│                      │
       │                      │                      │
       │ 6. Accountant uses    │
       │    for collection    │
       │                      │←─────────────────────┤
       │                      │                      │
       │ 7. Publish notice    │
       ├─────────────────────→│                      │
       │                      │                      │
       │ 8. All portals see    │
       │    notice            │
       │                      │←─────────────────────┤
       └──────────────────────┴──────────────────────┘

ADMIN AS CENTRAL CONTROL:
- All configuration flows from Admin
- Other portals read configuration
- No separate databases - all share same MySQL
- Immediate synchronization via database
```

---

## 5. CROSS-PORTAL MODEL RELATIONSHIPS

### User-Based Relationships
```
User (central)
├──→ Teacher (if user has teacher role)
│   └──→ ClassRoutine (teacher_id)
│       └──→ Homework (teacher_id)
│       └──→ StudyMaterial (teacher_id)
│       └──→ Lesson (teacher_id)
├──→ Employee (if user has employee role)
│   └──→ StaffAttendance
│   └──→ StaffLeave
├──→ Student (if user has student role)
│   └──→ Enrollment
│       └──→ StudentAttendance
│       └──→ HomeworkSubmission
│       └──→ FeeInvoice
│       └──→ FeePayment
└──→ (no profile = admission applicant)
    └──→ Admission
```

### Academic Relationships
```
AcademicSession (configured by Admin)
├──→ Enrollment (student session enrollment)
├──→ ClassSubject (subject assignments per session)
├──→ ClassRoutine (timetable per session)
├──→ Homework (session-scoped assignments)
├──→ StudyMaterial (session-scoped materials)
├──→ Lesson (session-scoped lessons)
├──→ Exam (session exams)
└──→ FeeInvoice (session billing)

SchoolClass (configured by Admin)
├──→ Section (class sections)
├──→ Enrollment (student enrollments)
├──→ ClassSubject (subject assignments)
├──→ FeeStructure (class fees)
└──→ ClassRoutine (timetable)

Section (configured by Admin)
└──→ Enrollment (student enrollments)

Subject (configured by Admin)
└──→ ClassSubject (subject assignments)
```

### Fee Relationships
```
FeeType (configured by Admin)
├──→ FeeStructure (class-level amounts)
├──→ FeeStudentAssignment (student-specific amounts)
├──→ FeeInvoiceItem (invoice line items)
└──→ FeeInvoiceDiscount (discount applications)

FeeStructure (configured by Admin)
└──→ FeeInvoice (used for billing)

FeeStudentAssignment (configured by Admin/Accountant)
└──→ FeeInvoice (used for billing)

FeeInvoice (generated by Accountant)
├──→ FeeInvoiceItem (line items)
├──→ FeeInvoiceDiscount (discounts)
├──→ FeePaymentAllocation (payment links)
└──→ FeePayment (payments received)

FeePayment (received by Accountant)
├──→ FeePaymentAllocation (invoice links)
├──→ FeeReceipt (issued receipt)
└──→ CashTransaction (cashbook entry)
```

---

## 6. ROUTE MAPPING BY PORTAL

### Admin Portal Routes (/admin/*)
```
/admin
├── /dashboard - Main dashboard
├── /users - User management
├── /roles - Role/permission management
├── /school - School settings
├── /academic - Academic structure (sessions, classes, sections, subjects)
├── /students - Student management
├── /admissions - Admission management
├── /staff - Staff management (teachers, employees)
├── /attendance - Attendance management
├── /timetable - Timetable management
├── /homework - Homework management (view all, restricted from editing)
├── /materials - Study materials (view all, restricted from editing)
├── /lessons - Lessons (view all, restricted from editing)
├── /videos - Class videos
├── /notices - Notice board
├── /notifications - Notifications
├── /fees - Fee management (types, structures, discounts, invoices)
├── /accountants - Accountant management and monitoring
├── /reports - Financial reports
├── /exams - Exam management
├── /marks - Mark management (review submissions)
├── /results - Result management
├── /audit - Audit logs
└── /profile - Admin profile
```

### Teacher Portal Routes (/admin/my/*)
```
/admin/my
├── /profile - Teacher profile
├── /classes - Teacher's assigned classes
├── /routine - Teacher's timetable
├── /leave - Teacher leave requests
└── /work - Teacher work records (combined classwork+homework form)

/admin/homework (teacher-specific)
├── / - List own homework
├── /create - Create homework
├── /{homework} - View homework with submissions
├── /{homework}/edit - Edit homework
└── /submissions/{submission}/review - Review submission

/admin/lessons (teacher-specific)
├── / - List own lessons
├── /create - Create lesson
└── /{lesson} - View lesson

/admin/materials (teacher-specific)
├── / - List own materials
├── /create - Create material
└── /{material} - View material
```

### Student Portal Routes (/student/*)
```
/student
├── /dashboard - Student dashboard
├── /profile - Student profile
├── /attendance - Attendance view
├── /routine - Class routine
├── /calendar - Calendar with events
├── /notices - Notice board
├── /homework
│   ├── / - Homework list
│   ├── /{homework} - Homework detail
│   └── /{homework}/submit - Submit homework
├── /materials - Study materials
├── /lessons - Lessons
├── /results - Exam results
├── /fees
│   ├── / - Fee invoices
│   ├── /{invoice} - Invoice detail
│   └── /{invoice}/receipt - Receipt view
├── /leave - Leave requests
└── /password - Password change
```

### Accountant Portal Routes (/admin/* with accountant role)
```
/admin/fees
├── /counter - Fee collection counter (AccountantFeeCollectionController)
├── /invoices - Invoice management
├── /payments - Payment management
└── /ledger - Fee ledger

/admin/accountants
├── / - Accountant roster
├── /{accountant} - Individual accountant performance
├── /daily - Daily collection reports
├── /monthly - Monthly collection reports
└── /export - Export collection data

/admin/reports
├── / - Financial reports
└── /export - Report exports

/admin/accounting
├── /expenses - Expense management
├── /cashbook - Cashbook
├── /bank - Bank accounts
└── /vouchers - Vouchers
```

---

## 7. MIDDLEWARE STACK BY PORTAL

### Admin Portal Middleware Stack
```
/admin/*
├── auth (session-based authentication)
├── active.account (check is_active = true)
├── school.context (set school_id from user)
├── password.changed (force password change if required)
└── staff.workspace (restrict to staff roles: school_admin, principal, accountant, teacher)
```

### Teacher Portal Middleware Stack
```
/admin/my/*
├── auth
├── active.account
├── school.context
├── password.changed
├── staff.workspace
└── TeachingScope (service-level: restrict to timetable)
```

### Student Portal Middleware Stack
```
/student/*
├── auth
├── active.account
├── school.context
├── password.changed
└── student.portal (restrict to student role with student record)
```

### Accountant Portal Middleware Stack
```
/admin/* (accountant role)
├── auth
├── active.account
├── school.context
├── password.changed
├── staff.workspace
└── FeeCollectionAuthority (service-level: check collection permissions)
```

---

## 8. AUTHENTICATION SYSTEM

### Login Endpoints
```
Main Login: /login (LoginController)
├── Admin: email + password
└── Staff (Teacher/Accountant): mobile_number + password

Student Login: /student/login (StudentMobileAuthController)
└── Student: mobile_number + password

API Login: /api/v1/auth/login (AuthController)
└── Email + password (returns Sanctum token)
```

### User Creation Flow
```
Admin Creates User:
1. Admin uses UserController or specialized controllers
2. UserService.create() creates User record
3. Profile record created (Teacher/Employee/Student)
4. Role assigned via syncRoles()
5. Password must_change = true
6. Email verified (for admin)

Student Self-Registration:
1. Student uses RegisterController
2. StudentMobileAccountService creates User with mobile_number
3. Student role assigned
4. Must complete admission application
5. No email required
```

### Password Reset
```
Current System: Email-based password reset
- ForgotPasswordController
- ResetPasswordController
- Uses password_reset_tokens table

REQUIRED CHANGE: Implement mobile-based password reset for non-admin roles
```

---

## 9. DATA ISOLATION STRATEGY

### School-Level Isolation
```
BelongsToSchool trait on all major models:
- Automatically adds WHERE school_id = current_school_id to all queries
- SchoolContext service provides current school_id from authenticated user
- Prevents cross-school data access
- withoutSchoolScope() available for admin cross-school queries
```

### User-Level Isolation
```
Student Portal:
- ResolvesPortalStudent trait ensures user has student record
- Enrollment filtering ensures student sees only own data
- scopeForEnrollment() on Homework, StudyMaterial, Lesson

Teacher Portal:
- TeachingScope service restricts to teacher's timetable
- Authorship checks on Homework, StudyMaterial, Lesson
- Cannot view/edit other teachers' work

Accountant Portal:
- FeeCollectionAuthority checks collection permissions
- Accountant: unrestricted collection
- Teacher: scoped collection (own students only)
- Admin/Principal: monitoring-only
```

---

## 10. CRITICAL FINDINGS

### ✅ ALREADY IMPLEMENTED (No Changes Needed)
1. **Single Shared Database** - All portals use same MySQL database
2. **Central Authentication** - Single users table with role-based routing
3. **Central User Management** - Admin creates all user accounts
4. **Role-Based Access Control** - Comprehensive permission system with Spatie Permission
5. **School Context Isolation** - BelongsToSchool trait on all models
6. **Immediate Data Sync** - Database-level synchronization (no separate databases)
7. **API Architecture** - Dedicated API v1 routes with Sanctum authentication
8. **Audit Trail** - Comprehensive audit_logs table
9. **Notification System** - Database notifications with push support
10. **Multi-Tenancy** - School-level data isolation

### ⚠️ REQUIRES ATTENTION (Based on User Requirements)
1. **Email Requirement for Non-Admin Roles**
   - Current: Email is nullable in users table (migration 2026_11_01_130000)
   - Required: Email should be optional/removed for Teacher, Student, Accountant
   - Status: Partially addressed (email nullable, but still appears in some forms)

2. **Password Reset System**
   - Current: Email-based password reset
   - Required: Mobile-based password reset for non-admin roles
   - Status: NOT IMPLEMENTED

3. **Admin Credentials**
   - Required: Admin email: drms2027@gmail.com, password: admin123
   - Status: Need to verify/update admin account

### 🔴 POTENTIAL ISSUES
1. **Real-Time Sync Claims**
   - Current: Database-level sync (immediate after operation + refresh)
   - Claimed: WebSocket real-time functionality
   - Reality: No WebSocket implementation found
   - Action: Do not claim real-time unless implemented

2. **Portal Separation**
   - Current: Role-based route separation in single codebase
   - Requirement: All portals must be connected
   - Status: Already connected via shared database
   - Action: Verify all data flows work correctly

---

## 11. RECOMMENDED ACTION PLAN

### Phase 2: Central Admin Control (VALIDATION NEEDED)
1. ✅ Verify Admin can create Teacher accounts
2. ✅ Verify Admin can create Student accounts
3. ✅ Verify Admin can create Accountant accounts
4. ✅ Verify Admin manages classes, sections, subjects
5. ✅ Verify Admin manages timetables
6. ✅ Verify Admin manages fee types and schedules
7. ✅ Verify Admin manages academic years
8. ✅ Verify Admin can activate/deactivate accounts
9. ⚠️ Verify admin credentials (drms2027@gmail.com / admin123)
10. ⚠️ Remove email requirement from non-admin forms

### Phase 3: Teacher Connection (VALIDATION NEEDED)
1. ✅ Teacher login uses mobile + password
2. ✅ Teacher sees assigned classes from Admin-configured timetable
3. ✅ Teacher sees assigned subjects from Admin-configured timetable
4. ✅ Teacher sees enrolled students from Admin-configured enrollments
5. ✅ Teacher can create homework → Student sees it
6. ✅ Teacher can mark attendance → Student sees it
7. ✅ Teacher can enter marks → Student sees results
8. ✅ TeachingScope restricts teacher to own classes

### Phase 4: Student Connection (VALIDATION NEEDED)
1. ✅ Student login uses mobile + password
2. ✅ Student sees profile from Admin-created record
3. ✅ Student sees class/section from enrollment
4. ✅ Student sees academic year from enrollment
5. ✅ Student sees guardian details
6. ✅ Student sees fee schedule from Admin/Accountant
7. ✅ Student sees homework from Teacher
8. ✅ Student can submit homework → Teacher sees it
9. ✅ Student sees marks from Teacher/Admin
10. ✅ Student sees fee invoices/payments

### Phase 5: Accountant Connection (VALIDATION NEEDED)
1. ⚠️ Accountant login uses mobile + password (verify)
2. ✅ Accountant sees student records
3. ✅ Accountant sees class/section
4. ✅ Accountant sees fee types from Admin
5. ✅ Accountant sees fee schedules from Admin
6. ✅ Accountant collects payments → Student sees it
7. ✅ Accountant generates invoices
8. ✅ Admin sees collection reports
9. ✅ Student sees payment history
10. ✅ Admin monitors accountant performance

### Phase 6: Data Synchronization (VALIDATION NEEDED)
1. ✅ Teacher → Admin (attendance, marks via submission workflow)
2. ✅ Teacher → Student (homework, materials, marks)
3. ✅ Accountant → Admin (payments via cashbook)
4. ✅ Accountant → Student (payments via invoices)
5. ✅ Admin → Teacher (configuration via timetable)
6. ✅ Admin → Student (configuration via enrollment)
7. ✅ Admin → Accountant (configuration via fee structure)
8. ✅ Database transactions for critical operations
9. ⚠️ Events/Listeners for notifications (verify working)
10. ❌ WebSocket real-time (not implemented - document as such)

### Phase 7: Security (VALIDATION NEEDED)
1. ✅ Role-based access control implemented
2. ✅ TeachingScope restricts teachers
3. ✅ FeeCollectionAuthority restricts accountants
4. ✅ Student portal scoped to own data
5. ✅ School context isolation
6. ⚠️ Verify teacher cannot access other teachers' students
7. ⚠️ Verify student cannot access another student's records
8. ✅ Middleware not disabled for permissions
9. ⚠️ Verify unauthorized API requests blocked
10. ⚠️ Verify direct ID manipulation prevented

---

## 12. PORTAL CONNECTION STATUS

### Database Connection: ✅ CONNECTED
- Single MySQL database (dream)
- All portals use same database
- School_id foreign key for multi-tenancy
- BelongsToSchool trait for automatic scoping

### Authentication Connection: ✅ CONNECTED
- Central users table
- Single login system with role-based routing
- Email for admin, mobile for others
- Session-based authentication for web
- Sanctum token-based for API

### Data Synchronization: ✅ CONNECTED
- Database-level synchronization
- Immediate availability after operation + refresh
- No separate databases
- Shared models across portals
- Transaction safety for critical operations

### API Connection: ✅ CONNECTED
- API v1 routes at /api/v1/*
- Sanctum token authentication
- Role-based authorization
- School context isolation
- Rate limiting on sensitive endpoints

### Portal Separation: ✅ IMPLEMENTED
- Role-based route separation
- Middleware stack for each portal
- TeachingScope for teacher restrictions
- FeeCollectionAuthority for accountant restrictions
- Student portal scoped to own data

---

## CONCLUSION

**The Dream Roots Millennium School Management System ALREADY HAS:**
- ✅ Single shared database
- ✅ Single Laravel backend
- ✅ Central authentication system
- ✅ Role-based access control
- ✅ Central user management
- ✅ Immediate data synchronization (database-level)
- ✅ All four portals connected via shared architecture

**REMAINING TASKS:**
1. Verify admin credentials (drms2027@gmail.com / admin123)
2. Complete email field removal from non-admin forms (partially done)
3. Implement mobile-based password reset for non-admin roles
4. Validate all data flows with actual testing
5. Document real-time sync limitations (no WebSocket)
6. Verify all security restrictions work correctly

**NO REBUILDING REQUIRED** - The architecture is already centralized. Focus on validation and minor adjustments to meet specific requirements.
