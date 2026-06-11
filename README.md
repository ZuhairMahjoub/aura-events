Aura Events Management System 
A comprehensive event and hall management platform designed to provide a seamless, reliable booking experience. This project focuses on high-quality backend architecture, ensuring system stability, performance, and scalability.

 Tech Stack & Architecture
Framework: Laravel (PHP)

Design Pattern: Service-Oriented Architecture (SOA)

Database: MySQL with Polymorphic Relationships

Caching & Security: Redis (used for OTP storage)

Authentication: JWT/Passport implementation using Access Tokens and Refresh Tokens

Asynchronous Processing: Laravel Queues & Jobs

External Integrations: Google OAuth2, UltraMsg (WhatsApp API), SMTP, Firebase (FCM)

Monitoring & Logging: Robust logging system for audit trails and error tracking

Code Quality: Clean Code, SOLID Principles, Reusable Traits

 Key Features
Smart Booking System: Efficiently manage hall availability and bookings with an intuitive flow.

Conflict Prevention: Implemented a robust validation algorithm to detect overlapping bookings, ensuring zero conflicts in the reservation schedule.

Real-time Chat: Messaging system (In-Development) enabling direct communication between hall owners and users.

Versatile Authentication: Multi-factor authentication support including Google OAuth2, OTP via WhatsApp (UltraMsg API), and Email Verification (SMTP).

Instant Notifications: Real-time push notifications using Firebase Cloud Messaging (FCM).

Secure Account Management: Reliable password recovery flows via email with best practices.

 Engineering Highlights
Clean Code: Adhered to strict coding standards by decoupling logic into Services, making the codebase highly maintainable and testable.

Performance: Leveraged Redis for OTP storage, significantly reducing database load and ensuring secure, time-bound data management.

Authentication Security: Implemented a secure authentication flow using Access Tokens and Refresh Tokens to ensure robust session management.

Data Integrity: Utilized Database Transactions to ensure consistency during sensitive operations like booking and conflict checks.

Proactive Monitoring: A comprehensive Logging system implemented to track authentication attempts, critical system errors, and API integration status.

 How to Run
Clone the repository: git clone https://github.com/ZuhairMahjoub/aura-events

Install dependencies: composer install

Set up your .env file and database.

Run migrations: php artisan migrate

Start the development server: php artisan serve
