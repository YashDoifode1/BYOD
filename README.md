# 📁 Project Management Dashboard

![Dashboard Screenshot](https://screenshots/dashboard.png)

A powerful and intuitive project management tool built to streamline your workflow and supercharge team productivity. Get real-time analytics, track tasks, and manage users—all in one place.

---

## ✨ Overview

The Project Management Dashboard is a full-featured web application built with PHP and MySQL, designed to help teams manage tasks, track progress, and visualize performance. It works perfectly on **XAMPP** for local development and testing.

---

## 🚀 Features

### 📊 Real-Time Analytics
- Track user growth and engagement
- Monitor project progress and health
- Visual charts for tasks and activities
- Recent updates feed

### ✅ Task Management
- Kanban-style board
- Drag-and-drop functionality
- Task status tracking (To Do, In Progress, Done)
- Set task priority with visual indicators

### 📂 Project Tracking
- Assign and manage team members per project
- Visualize project progress
- Upload and share project-related files
- Built-in team communication

### 👥 User Management
- Role-based access (Admin, Manager, User)
- User activity logs
- Performance metrics per user

---

## 💻 Tech Stack

### Frontend
- HTML5, CSS3, JavaScript
- Bootstrap 5 for responsive UI
- Chart.js for data visualization
- jQuery for UI components
- Date Range Picker

### Backend
- PHP 8.0+
- MySQL 8.0+ (via XAMPP)
- PDO for secure database access

### 🔐 Security
- SQL Injection-safe queries (Prepared Statements)
- Role-based authentication
- Secure session management

---

## 🛠 Installation Guide (XAMPP)

### 📦 Prerequisites
- [XAMPP](https://www.apachefriends.org/) installed (PHP 8.0+ and MySQL 8.0+)
- Composer installed globally

### 🧰 Setup Instructions

1. **Download/Clone the project**
   ```bash
   git clone https://github.com/yourusername/project-management-dashboard.git
    ```

2. **Move the project to XAMPP's `htdocs` directory**

   ```bash
   mv project-management-dashboard/ C:/xampp/htdocs/
   ```

3. **Start XAMPP Control Panel**

   * Enable **Apache** and **MySQL**

4. **Set up the database**

   * Open [http://localhost/phpmyadmin](http://localhost/phpmyadmin)
   * Create a new database named `project_management`
   * Import `database/schema.sql` file into this database

5. **Configure Environment**

   * Copy `.env.example` to `.env`
   * Update database details in `.env` (if used) or directly in `config.php`:

     ```ini
     DB_HOST=localhost
     DB_NAME=project_management
     DB_USER=root
     DB_PASS=
     ```

6. **Install Composer dependencies**
   Open terminal in the project folder:

   ```bash
   composer install
   ```

7. **Access the project**
   Open your browser and navigate to:

   ```
   http://localhost/project-management-dashboard/public
   ```

---

## 📖 Usage

* Default login credentials:

  * **Email:** [admin@example.com](mailto:admin@example.com)
  * **Password:** admin123

* You can now:

  * Create and manage projects
  * Assign team members
  * Track project progress
  * View insightful analytics

---

## 🤝 Contributing

Contributions are always welcome! Here’s how you can contribute:

1. Fork this repository
2. Create your branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

---

## 📄 License

This project is licensed under the MIT License. See the `LICENSE` file for more details.

---

## 📬 Contact

**Maintainer:** Your Name
🔗 [GitHub Repository](https://github.com/yash.doifode1/BYOD)

> 📌 *For more details, check the [Wiki](https://github.com/yourusername/project-management-dashboard/wiki).*

```

### ✅ Suggestions:
- Replace `"Your Name"` and `"yourusername"` with your actual name and GitHub username.
- Ensure `database/schema.sql` exists in your project.
- Add the actual screenshot URL if available.

Let me know if you'd like badges, logo support, or a one-liner summary at the top!
```
