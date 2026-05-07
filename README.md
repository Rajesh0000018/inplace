# InPlace — Placement Management System

A full-stack web application that digitises and streamlines the entire work placement lifecycle for universities. Built with PHP, MySQL, and vanilla JavaScript, InPlace connects students, placement tutors, employers (providers), and administrators on a single platform.

---

## Features

### Student Portal
- View and manage active placement details
- Submit interim and final placement reports (PDF upload)
- Track report status (pending review → approved / revision needed)
- Real-time messaging with tutors and providers
- View scheduled visits and add personal notes
- Request placement changes and track request status
- Announcements feed

### Tutor Portal
- Dashboard with live metrics (active placements, pending reports, at-risk students)
- Create and manage student placements (assign companies, roles, dates)
- Review and approve/request revisions on submitted reports
- Send automated email reminders to students with missing reports
- Schedule placement visits (in-person or virtual with meeting links)
- Send calendar invites (.ics) directly to students and employers via email
- Interactive map view of all placement locations
- At-risk student identification and tracking
- Provider directory and meeting scheduler
- Real-time messaging (AJAX-based, 2-second polling)
- Broadcast announcements

### Provider (Employer) Portal
- View students on placement at their company
- Evaluate student performance
- Raise and track placement issues
- Confirm or reject placement requests
- Message tutors and students
- View upcoming visits

### Admin Panel
- Full user management and approval workflow for new registrations
- System-wide placement oversight
- Configurable SMTP email settings
- Activity logs and audit trail
- Data export (CSV)
- Database backup

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.0 |
| Database | MySQL (PDO) |
| Frontend | HTML5, CSS3, Vanilla JavaScript |
| Email | PHPMailer (SMTP / Gmail) |
| Calendar | iCalendar (.ics) generation |
| Maps | Geocoded company locations |
| Server | Apache (XAMPP) |

---

## Project Structure

```
inplace/
├── admin/              # Admin panel pages
├── tutor/              # Tutor portal pages
├── student/            # Student portal pages
├── provider/           # Employer portal pages
├── api/                # AJAX endpoints (messaging, metrics, etc.)
├── actions/            # Form action handlers
├── includes/           # Shared: auth, header, footer, functions
├── config/
│   ├── db.example.php  # Database config template (copy to db.php)
│   └── app_config.php  # App-wide settings loader
├── assets/
│   ├── css/
│   ├── js/
│   └── uploads/        # Student-uploaded documents (gitignored)
└── PHPMailer-master/   # Email library
```

---

## Getting Started

### Prerequisites
- PHP 8.0+
- MySQL 5.7+ or MariaDB
- Apache (XAMPP recommended for local development)

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/prajwalsk53/inplace.git
   cd inplace
   ```

2. **Set up the database**
   - Create a MySQL database named `inplace_db`
   - Import the schema:
     ```bash
     mysql -u root -p inplace_db < database/schema.sql
     ```

3. **Configure the database connection**
   ```bash
   cp config/db.example.php config/db.php
   ```
   Edit `config/db.php` with your database credentials.

4. **Configure email (optional)**
   - Log in as admin and go to **Admin → Settings**
   - Enter your SMTP credentials (Gmail app password recommended)

5. **Set up the web server**
   - Place the project in your Apache `htdocs` or `www` folder
   - Access at `http://localhost/inplace/login.php`

---

## User Roles & Default Access

| Role | Description |
|---|---|
| **Student** | Placed student managing their own placement |
| **Tutor** | University placement tutor overseeing students |
| **Provider** | Employer hosting a student on placement |
| **Admin** | System administrator with full access |

New registrations require admin approval before login is granted.

---

## Key Highlights

- **Role-based access control** — every page enforces authentication and role checks
- **Real-time messaging** — AJAX polling delivers new messages without page reload
- **Email automation** — PHPMailer sends reminders, calendar invites, and notifications
- **Calendar integration** — Visit invites generate `.ics` files that auto-import into Outlook/Google Calendar
- **Secure file handling** — PDFs are stored server-side with randomised filenames
- **SQL injection safe** — all queries use PDO prepared statements

---

## Screenshots

> _Add screenshots of the dashboard, messaging, reports, and map view here_

---

## Author

**Prajwal** — [github.com/prajwalsk53](https://github.com/prajwalsk53)

---

## License

This project is for portfolio and educational demonstration purposes.
