Project Management Dashboard
https://screenshots/dashboard.png

Overview
A professional project management dashboard with comprehensive analytics and task tracking capabilities. This web application provides real-time insights into projects, tasks, and team performance with beautiful visualizations and interactive elements.

Features
Real-time Analytics Dashboard

User growth tracking

Project status distribution

Task completion metrics

Recent activity feed

Task Management

Kanban-style task board

Drag-and-drop functionality

Task status tracking (To Do, In Progress, Done)

Priority indicators

Project Tracking

Project progress visualization

Team member assignment

File sharing and messaging

User Management

Role-based access control (Admin, Manager, User)

Activity logging

User performance metrics

Technologies Used
Frontend
HTML5, CSS3, JavaScript

Bootstrap 5

Chart.js for data visualization

jQuery (for UI components)

Date Range Picker

Backend
PHP 8.0+

MySQL 8.0

PDO for database access

Security
Prepared statements for SQL queries

Role-based authentication

Session management

Installation
Prerequisites
Web server (Apache/Nginx)

PHP 8.0+

MySQL 8.0+

Composer (for dependencies)

Setup Instructions
Clone the repository:

bash
git clone https://github.com/yourusername/project-management-dashboard.git
cd project-management-dashboard
Install dependencies:

bash
composer install
Set up the database:

Create a new MySQL database

Import the SQL schema from database/schema.sql

Configure the application:

Copy .env.example to .env

Update database credentials in .env

ini
DB_HOST=localhost
DB_NAME=project_management
DB_USER=root
DB_PASS=
Set up file permissions:

bash
chmod -R 755 storage/
chown -R www-data:www-data public/uploads/
Start the development server:

bash
php -S localhost:8000 -t public
Usage
Access the application at http://localhost:8000

Login with admin credentials:

Username: 

Password: 

Explore the dashboard and project management features



Contributing
Fork the project

Create your feature branch (git checkout -b feature/AmazingFeature)

Commit your changes (git commit -m 'Add some AmazingFeature')

Push to the branch (git push origin feature/AmazingFeature)

Open a Pull Request

License
Distributed under the MIT License. See LICENSE for more information.

Contact
Project Maintainer - Your Name

Project Link: https://github.com/yash.doifode1/BYOD

Note: For detailed documentation, please refer to the Wiki pages.

