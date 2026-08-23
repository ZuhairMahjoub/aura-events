Aura Events Management System
A comprehensive event and hall management platform designed to provide a seamless, reliable booking experience. This project focuses on high-quality backend architecture, ensuring system stability, performance, and scalability.

🛠 Tech Stack & Architecture
Framework: Laravel (PHP)

Design Pattern: Service-Oriented Architecture (SOA)

Database: MySQL with Polymorphic Relationships

Real-time Messaging: Google Firestore (NoSQL)

Authentication: JWT/Sanctum implementation (Access & Refresh Tokens)

Authorization: Spatie Laravel-Permission (RBAC)

Caching & Security: Redis (OTP storage)

Asynchronous Processing: Laravel Queues & Jobs

External Integrations: Google OAuth2, UltraMsg (WhatsApp API), SMTP, Firebase (FCM)

💬 Real-time Messaging System
To provide a seamless and highly responsive user experience, the platform incorporates a robust, real-time messaging engine:

Real-time Infrastructure: Built using Google Firestore, enabling sub-millisecond data synchronization between users without the need for traditional HTTP polling.

Hybrid Architecture:

Laravel (Backend): Acts as the orchestrator to manage business logic, authorization, and room creation.

Firestore (NoSQL): Handles high-frequency message streams, ensuring performance and scalability.

Instant Notifications: Integrated with Firebase Cloud Messaging (FCM) to deliver push notifications when users are offline, ensuring no conversation is missed.

Scalable Communication: Decouples messaging load from the primary MySQL database, ensuring the system remains performant even under heavy concurrent usage.

✨ Key Features
🌐 Localization
The Aura Events platform is designed to be globally accessible, featuring full support for multi-language environments.

Dynamic Language Switching: Utilizing Laravel's robust localization engine, the system detects user language preferences via the Accept-Language header.

Seamless Translation: All system responses, API messages, and real-time notifications are dynamically translated, ensuring a consistent user experience in both Arabic and English.

Scalable Localization: The codebase is structured to allow easy addition of new languages by managing translation keys in structured JSON files, ensuring that developers can maintain and scale language support without modifying core business logic.

🛡 Advanced Access Control
Granular permissions and roles managed via Spatie for Admins, Hall Owners, and Users.

📅 Smart Booking System
Efficiently manage hall availability with a robust validation algorithm to prevent overlapping bookings.

🔐 Versatile Authentication
Multi-factor authentication support including Google OAuth2, WhatsApp OTP, and Email verification.

💬 Real-time Communication
Secure, instant messaging between customers and service providers.

🏗 Engineering Highlights
Clean Code & SOLID: Logic is decoupled into Services, making the codebase maintainable and testable.

Data Integrity: Database Transactions are utilized to ensure consistency during sensitive operations like booking and conflict checks.

Authentication Security: Secure flow using Access Tokens and Refresh Tokens for robust session management.

🚀 How to Run
Clone the repository: git clone [https://github.com/ZuhairMahjoub/aura-events](https://github.com/ZuhairMahjoub/aura-events)

Install dependencies: composer install

Configure environment: Set up your .env file with database and Firebase credentials.

Run migrations: php artisan migrate

Start the server: php artisan serve
