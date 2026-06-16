Aura Events Management System 
A comprehensive event and hall management platform designed to provide a seamless, reliable booking experience. This project focuses on high-quality backend architecture, ensuring system stability, performance, and scalability.

 Tech Stack & Architecture
Framework: Laravel (PHP)

Authorization: Spatie Laravel-Permission (Role-Based Access Control - RBAC)

Design Pattern: Service-Oriented Architecture (SOA)

Database: MySQL with Polymorphic Relationships

Caching & Security: Redis (used for OTP storage)

Authentication: JWT/Passport implementation using Access Tokens and Refresh Tokens

Asynchronous Processing: Laravel Queues & Jobs

External Integrations: Google OAuth2, UltraMsg (WhatsApp API), SMTP, Firebase (FCM)

Monitoring & Logging: Robust logging system for audit trails and error tracking

Code Quality: Clean Code, SOLID Principles, Reusable Traits

 Key Features
Advanced Access Control: Granular permissions and roles managed via Spatie, ensuring secure access management for Admins, Hall Owners, and Users.

Smart Booking System: Efficiently manage hall availability and bookings with an intuitive flow.

Conflict Prevention: Implemented a robust validation algorithm to detect overlapping bookings, ensuring zero conflicts in the reservation schedule.

Versatile Authentication: Multi-factor authentication support including Google OAuth2, OTP via WhatsApp (UltraMsg API), and Email Verification (SMTP).

Instant Notifications: Real-time push notifications using Firebase Cloud Messaging (FCM).

 Engineering Highlights
Security & Roles: Implemented custom Policies and integrated Spatie to enforce strict access control, ensuring that every request is authorized based on the user's role and permissions.

Clean Code: Adhered to strict coding standards by decoupling logic into Services, making the codebase highly maintainable and testable.

Performance: Leveraged Redis for OTP storage, significantly reducing database load and ensuring secure, time-bound data management.

Authentication Security: Implemented a secure authentication flow using Access Tokens and Refresh Tokens to ensure robust session management.

Data Integrity: Utilized Database Transactions to ensure consistency during sensitive operations like booking and conflict checks.

 How to Run
Clone the repository: git clone https://github.com/ZuhairMahjoub/aura-events

Install dependencies: composer install

Set up your .env file and database configurations.

Run migrations: php artisan migrate

Start the development server: php artisan serve
