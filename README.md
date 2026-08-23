# Aura Events Management System
A comprehensive event and hall management platform designed to provide a seamless, reliable booking experience. This project focuses on high-quality backend architecture, ensuring system stability, performance, and scalability.

🛠 Tech Stack & Architecture
* **Framework:** Laravel (PHP)
* **Design Pattern:** Service-Oriented Architecture (SOA) & Modular Design Patterns
* **Database:** MySQL with Polymorphic Relationships
* **Real-time Messaging:** Google Firestore (NoSQL)
* **Authentication:** JWT/Sanctum implementation (Access & Refresh Tokens)
* **Authorization:** Spatie Laravel-Permission (RBAC)
* **Caching & Security:** Redis (OTP storage)
* **Asynchronous Processing:** Laravel Queues & Jobs
* **External Integrations:** Google OAuth2, UltraMsg (WhatsApp API), SMTP, Firebase (FCM)

🏗 Architectural Task Distribution
To maintain a clean, highly modular, and decoupled codebase, the system distributes responsibilities across distinct layers:
* **Services:** Encapsulate complex business logic and core orchestration.
* **Actions:** Handle single-responsibility domain tasks to keep controllers lightweight.
* **Events & Listeners:** Drive asynchronous background processes and decoupled system reactions (e.g., dispatching notifications).
* **API Resources:** Transform Eloquent models cleanly into structured JSON responses.

💬 Real-time Messaging System
To provide a seamless and highly responsive user experience, the platform incorporates a robust, real-time messaging engine:
* **Real-time Infrastructure:** Built using Google Firestore, enabling sub-millisecond data synchronization between users without the need for traditional HTTP polling.
* **Hybrid Architecture:**
  * **Laravel (Backend):** Acts as the orchestrator to manage business logic, authorization, and room creation.
  * **Firestore (NoSQL):** Handles high-frequency message streams, ensuring performance and scalability.
* **Instant Notifications:** Integrated with Firebase Cloud Messaging (FCM) to deliver push notifications when users are offline, ensuring no conversation is missed.
* **Scalable Communication:** Decouples messaging load from the primary MySQL database, ensuring the system remains performant even under heavy concurrent usage.

✨ Key Features
* **🌐 Localization:** Full support for dynamic language switching via Accept-Language headers and structured JSON files for Arabic and English.
* **🛡 Advanced Access Control:** Granular permissions and roles managed via Spatie for Admins, Hall Owners, and Users.
* **📅 Smart Booking System:** Efficiently manage hall availability with a robust validation algorithm and strict concurrency controls to completely prevent overlapping bookings.
* **🔐 Versatile Authentication:** Multi-factor authentication support including Google OAuth2, WhatsApp OTP, and Email verification.
* **💬 Real-time Communication:** Secure, instant messaging between customers and service providers.

🚀 How to Run
1. **Clone the repository:** `git clone https://github.com/ZuhairMahjoub/aura-events`
2. **Install dependencies:** `composer install`
3. **Configure environment:** Set up your `.env` file with database and Firebase credentials.
4. **Run migrations:** `php artisan migrate`
5. **Start the server:** `php artisan serve`
