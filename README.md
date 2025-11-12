<div align="center">

# 📧 Native Mailer

### A Modern Desktop Email Testing Application

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.2+-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/Laravel-12-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel">
  <img src="https://img.shields.io/badge/Filament-4.0-FDAE4B?style=for-the-badge" alt="Filament">
  <img src="https://img.shields.io/badge/NativePHP-2.0-00D1B2?style=for-the-badge" alt="NativePHP">
  <img src="https://img.shields.io/badge/License-MIT-green.svg?style=for-the-badge" alt="License">
</p>

**Native Mailer** is a beautiful, cross-platform desktop application that provides a local SMTP server for catching and viewing emails during development. Built with Laravel, NativePHP, and Filament, it offers a modern, elegant solution for email testing without the need for external services.

[Features](#-features) • [Installation](#-installation) • [Usage](#-usage) • [Documentation](#-documentation)

</div>

---

## ✨ Features

### 🚀 Core Functionality

-   **Local SMTP Server** - Full-featured SMTP server running on `127.0.0.1:1025`
-   **Zero Configuration** - Works out of the box, no external dependencies
-   **Native Desktop App** - True native application for Windows, macOS, and Linux
-   **Automatic Email Capture** - Catches all emails sent to the SMTP server

### 💼 Email Management

-   **Beautiful Admin Panel** - Powered by Filament 4.0 with a modern, intuitive interface
-   **Rich Email Preview** - View HTML emails in a sandboxed iframe with full styling
-   **Advanced Search** - Search emails by sender, recipient, or subject
-   **Bulk Operations** - Delete multiple emails at once
-   **Email Details** - View complete email metadata including headers and timestamps

### 🎨 User Interface

-   **Modern Design** - Built with Tailwind CSS 4.0 for a clean, professional look
-   **Responsive Layout** - Works seamlessly on any screen size
-   **Dark Mode Ready** - Filament's built-in dark mode support
-   **Real-time Updates** - Instant email display as they arrive

### ⚡ Technical Excellence

-   **High Performance** - Non-blocking socket implementation for handling multiple connections
-   **SQLite Database** - Lightweight, serverless database for email storage
-   **Multipart Support** - Handles plain text and HTML emails with attachments
-   **Content Decoding** - Supports base64 and quoted-printable encoding
-   **Cross-platform** - Single codebase runs natively on all platforms

---

## 🛠️ Tech Stack

| Technology               | Version | Purpose                    |
| ------------------------ | ------- | -------------------------- |
| **PHP**                  | 8.2+    | Core language              |
| **Laravel**              | 12.0    | Application framework      |
| **Filament**             | 4.0     | Admin panel framework      |
| **NativePHP Desktop**    | 2.0     | Native application wrapper |
| **Livewire**             | 3.6     | Reactive components        |
| **Tailwind CSS**         | 4.0     | Styling framework          |
| **Vite**                 | 7.0     | Frontend build tool        |
| **SQLite**               | -       | Database                   |
| **php-mime-mail-parser** | 9.0     | Email parsing              |

---

## 📋 Requirements

Before you begin, ensure your system meets these requirements:

-   **PHP** 8.2 or higher
-   **Composer** (latest version recommended)
-   **Node.js** 18+ and npm
-   **SQLite** (usually included with PHP)

### Platform-Specific Requirements

#### Windows

-   Windows 10 or later (64-bit)
-   Visual C++ Redistributable (automatically handled by NativePHP)

#### macOS

-   macOS 10.15 (Catalina) or later
-   Xcode Command Line Tools (for building)

#### Linux

-   Modern Linux distribution (Ubuntu 20.04+, Fedora 35+, etc.)
-   GTK 3.0+ libraries

---

## 🚀 Installation

### Quick Setup (Recommended)

```bash
# Clone the repository
git clone https://github.com/projecthanif/nativemailer.git
cd nativemailer

# Run the automated setup
composer setup
```

The `composer setup` command will:

1. Install PHP dependencies via Composer
2. Create `.env` file from `.env.example`
3. Generate application key
4. Run database migrations
5. Install Node.js dependencies
6. Build frontend assets

### Manual Setup

If you prefer manual installation:

```bash
# 1. Clone the repository
git clone https://github.com/projecthanif/nativemailer.git
cd nativemailer

# 2. Install PHP dependencies
composer install

# 3. Set up environment
cp .env.example .env
php artisan key:generate

# 4. Create and migrate database
touch database/database.sqlite
php artisan migrate

# 5. Install Node dependencies
npm install

# 6. Build assets
npm run build
```

---

## 🎯 Usage

### Running in Development Mode

Start the application in development mode with hot-reload:

```bash
# Start the native application with auto-reload
php artisan native:serve
```

Or use the composer script for concurrent development:

```bash
# Runs app and watches for changes
composer native:dev
```

**What happens when you start the app:**

1. 🚀 NativePHP launches the native desktop window
2. 📡 SMTP server starts automatically on `127.0.0.1:1025`
3. 🎨 Filament admin panel opens at `/admin/emails`
4. 📬 Application is ready to receive emails

### Building for Production

Build native executables for distribution:

```bash
# Build for your current platform
php artisan native:build

# Builds will be available in: builds/
```

**Build Output:**

-   **Windows:** `.exe` installer
-   **macOS:** `.dmg` disk image
-   **Linux:** `.AppImage` executable

### Configuring Your Application

To send emails from your Laravel application (or any app) to Native Mailer:

#### Laravel Configuration

Update your `.env` file:

```env
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="test@example.com"
MAIL_FROM_NAME="${APP_NAME}"
```

#### Other PHP Applications

```php
// PHPMailer
$mail = new PHPMailer();
$mail->isSMTP();
$mail->Host = '127.0.0.1';
$mail->Port = 1025;
$mail->SMTPAuth = false;

// Symfony Mailer
$transport = (new Smtp())
    ->setHost('127.0.0.1')
    ->setPort(1025);
```

#### Testing

Send a test email:

```bash
php artisan tinker
```

```php
Mail::raw('Test email from Native Mailer', function ($message) {
    $message->to('test@example.com')
            ->subject('Test Email');
});
```

Check the Native Mailer app to see your email!

---

## 📁 Project Structure

```
native-mailer/
├── app/
│   ├── Console/
│   │   └── Commands/
│   │       └── StartSmtpCatcher.php      # Artisan command to start SMTP
│   ├── Filament/
│   │   └── Resources/
│   │       └── Emails/
│   │           ├── EmailResource.php     # Filament resource definition
│   │           ├── Pages/
│   │           │   ├── ListEmails.php    # Email list page
│   │           │   └── ViewEmail.php     # Email detail page
│   │           ├── Schemas/
│   │           │   └── EmailInfolist.php # Email display schema
│   │           └── Tables/
│   │               └── EmailsTable.php   # Table configuration
│   ├── Models/
│   │   └── Email.php                     # Email Eloquent model
│   ├── Providers/
│   │   └── NativeAppServiceProvider.php  # NativePHP config
│   └── Services/
│       ├── SmtpCatcher.php               # Core SMTP server implementation
│       └── SmtpServiceManager.php        # Service lifecycle management
├── database/
│   └── migrations/
│       └── 2025_10_25_020118_create_emails_table.php
├── resources/
│   ├── css/
│   │   └── app.css                       # Tailwind styles
│   ├── js/
│   │   └── app.js                        # Frontend JavaScript
│   └── views/
│       └── filament/
│           └── email-html-view.blade.php # Email HTML renderer
├── routes/
│   └── web.php                           # Route definitions
├── config/
│   └── nativephp.php                     # NativePHP configuration
└── composer.json                         # PHP dependencies
```

---

## 🔧 Architecture & Implementation

### SMTP Server Architecture

The SMTP server is implemented in `SmtpCatcher.php` using PHP's socket functions:

```
┌─────────────────────────────────────────────────────┐
│                   SMTP Catcher                      │
│                                                     │
│  ┌──────────────┐    ┌──────────────┐             │
│  │   Socket     │───▶│   Client     │             │
│  │   Listener   │    │   Handler    │             │
│  │ (Port 1025)  │    │  (Non-block) │             │
│  └──────────────┘    └──────────────┘             │
│         │                    │                      │
│         ▼                    ▼                      │
│  ┌──────────────┐    ┌──────────────┐             │
│  │   Accept     │    │   Parse      │             │
│  │  Connection  │    │   SMTP       │             │
│  └──────────────┘    └──────────────┘             │
│                             │                       │
│                             ▼                       │
│                      ┌──────────────┐              │
│                      │   Store in   │              │
│                      │   Database   │              │
│                      └──────────────┘              │
└─────────────────────────────────────────────────────┘
```

**Key Features:**

-   **Non-blocking I/O** - Handles multiple simultaneous connections
-   **State Machine** - Implements SMTP protocol states (HELO, MAIL, RCPT, DATA)
-   **Multi-part Parsing** - Separates HTML and plain text content
-   **Content Decoding** - Handles quoted-printable and base64 encoding

### Email Processing Flow

```
Email Sent ──▶ SMTP Server ──▶ Parse Headers ──▶ Extract Content
                   │                                    │
                   ▼                                    ▼
              Store Raw Data ──────────────▶ Save to Database
                                                       │
                                                       ▼
                                              Trigger Event ──▶ UI Update
```

### Database Schema

**`emails` table:**

| Column        | Type      | Description                      |
| ------------- | --------- | -------------------------------- |
| `id`          | bigint    | Primary key                      |
| `from`        | string    | Sender email address             |
| `to`          | string    | Recipient email address(es)      |
| `subject`     | string    | Email subject line               |
| `body_text`   | longtext  | Plain text content               |
| `body_html`   | longtext  | HTML content                     |
| `attachments` | json      | Array of attachment metadata     |
| `raw`         | longtext  | Complete raw email for debugging |
| `received_at` | timestamp | When email was received          |
| `created_at`  | timestamp | Database creation time           |
| `updated_at`  | timestamp | Last update time                 |

---

## 🎨 Features in Detail

### Filament Admin Panel

Native Mailer uses **Filament 4.0** for its admin interface, providing:

-   **Email List View**: Sortable, searchable table with pagination
-   **Email Detail View**: Full email preview with HTML rendering
-   **Bulk Actions**: Delete multiple emails at once
-   **Search Filters**: Find emails by sender, recipient, or subject
-   **Date Sorting**: Sort emails by received date

### Email Preview System

HTML emails are rendered in a sandboxed iframe for security:

```blade
<iframe
    srcdoc="{{ $email->body_html }}"
    sandbox="allow-same-origin"
    class="email-iframe"
/>
```

**Security Features:**

-   Sandboxed iframe prevents JavaScript execution
-   No external resource loading
-   Isolated from parent document

### SMTP Protocol Implementation

The SMTP server implements essential SMTP commands:

| Command     | Description           | Example                           |
| ----------- | --------------------- | --------------------------------- |
| `HELO/EHLO` | Initiate connection   | `EHLO client.example.com`         |
| `MAIL FROM` | Specify sender        | `MAIL FROM:<sender@example.com>`  |
| `RCPT TO`   | Specify recipient     | `RCPT TO:<recipient@example.com>` |
| `DATA`      | Begin message content | `DATA`                            |
| `QUIT`      | Close connection      | `QUIT`                            |

---

## 🔍 Development

### Running Tests

```bash
# Run all tests
php artisan test

# Run with Pest
./vendor/bin/pest

# Run specific test file
php artisan test --filter=EmailTest
```

### Code Quality

Format code using Laravel Pint:

```bash
# Fix code style
./vendor/bin/pint

# Preview changes without fixing
./vendor/bin/pint --test
```

### Development Workflow

For active development with hot-reload:

```bash
# Terminal 1: Run the native app
php artisan native:serve

# Terminal 2: Watch assets (in separate terminal)
npm run dev
```

Or use the convenience script:

```bash
# Runs everything concurrently
composer native:dev
```

### Manual SMTP Testing

Test the SMTP server directly using telnet:

```bash
telnet 127.0.0.1 1025
```

```
EHLO localhost
MAIL FROM:<test@example.com>
RCPT TO:<recipient@example.com>
DATA
Subject: Test Email
From: test@example.com
To: recipient@example.com

This is a test email body.
.
QUIT
```

### Debugging

Enable detailed logging in `config/nativephp.php`:

```php
'phpIni' => [
    'display_errors' => '1',
    'error_reporting' => 'E_ALL',
    'log_errors' => '1',
],
```

Logs are stored in:

-   `storage/logs/laravel.log` - Application logs
-   `storage/logs/smtp.log` - SMTP-specific logs

---

## 🚧 Troubleshooting

### Port Already in Use

If port 1025 is already occupied:

```bash
# Windows - Find process using port 1025
netstat -ano | findstr :1025
taskkill /F /PID <PID>

# macOS/Linux
lsof -ti:1025 | xargs kill -9
```

### Database Issues

Reset the database:

```bash
rm database/database.sqlite
touch database/database.sqlite
php artisan migrate:fresh
```

### Build Issues

Clear caches and rebuild:

```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
npm run build
```

---

## 🤝 Contributing

Contributions are welcome and appreciated! Here's how you can help:

### Reporting Bugs

1. Check if the issue already exists
2. Include detailed reproduction steps
3. Provide system information (OS, PHP version, etc.)
4. Include relevant logs or screenshots

### Suggesting Features

1. Describe the feature and its benefits
2. Provide use cases
3. Consider implementation complexity

### Pull Requests

1. **Fork** the repository
2. **Create** a feature branch
    ```bash
    git checkout -b feature/amazing-feature
    ```
3. **Commit** your changes
    ```bash
    git commit -m 'Add amazing feature'
    ```
4. **Push** to your branch
    ```bash
    git push origin feature/amazing-feature
    ```
5. **Open** a Pull Request

### Development Guidelines

-   Follow PSR-12 coding standards
-   Write tests for new features
-   Update documentation as needed
-   Run `./vendor/bin/pint` before committing
-   Keep commits atomic and well-described

---

## 📝 License

This project is open-source software licensed under the [MIT License](LICENSE).

```
MIT License

Copyright (c) 2025 Mustapha Hanif

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.
```

---

## 🙏 Acknowledgments

This project is built on the shoulders of giants:

-   **[Laravel](https://laravel.com)** - The PHP framework for web artisans
-   **[NativePHP](https://nativephp.com)** - Build native desktop applications with PHP
-   **[Filament](https://filamentphp.com)** - Beautiful admin panels for Laravel
-   **[Livewire](https://livewire.laravel.com)** - Full-stack framework for Laravel
-   **[Tailwind CSS](https://tailwindcss.com)** - Utility-first CSS framework
-   **[Vite](https://vitejs.dev)** - Next generation frontend tooling

Special thanks to:

-   The Laravel community for their amazing packages and support
-   The NativePHP team for making native PHP apps possible
-   All contributors and users of Native Mailer

---

## 👨‍💻 Author

**Mustapha Hanif**

-   GitHub: [@projecthanif](https://github.com/projecthanif)
-   Email: [Contact via GitHub](https://github.com/projecthanif)

---

## 🌟 Show Your Support

If you find this project helpful, please consider:

-   ⭐ Starring the repository
-   🐛 Reporting bugs
-   💡 Suggesting new features
-   🔀 Contributing code
-   📢 Sharing with others

---

## 📊 Project Stats

![GitHub stars](https://img.shields.io/github/stars/projecthanif/nativemailer?style=social)
![GitHub forks](https://img.shields.io/github/forks/projecthanif/nativemailer?style=social)
![GitHub issues](https://img.shields.io/github/issues/projecthanif/nativemailer)
![GitHub pull requests](https://img.shields.io/github/issues-pr/projecthanif/nativemailer)

---

<div align="center">

**Made with ❤️ using Laravel and NativePHP**

[⬆ Back to Top](#-native-mailer)

</div>
