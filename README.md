# Native Mailer 📧

A desktop email testing application built with Laravel, NativePHP, and Livewire. Native Mailer provides a local SMTP server that catches and displays emails sent from your development environment, making it easy to test email functionality without sending actual emails.

## Features

✨ **Local SMTP Server** - Built-in SMTP server running on `127.0.0.1:1025`  
📬 **Email Inbox** - Beautiful inbox interface to view all captured emails  
🔍 **Search Functionality** - Quickly find emails by subject or sender  
⚡ **Real-time Updates** - Powered by Livewire for a reactive user experience  
💾 **SQLite Database** - Lightweight local database for email storage  
🖥️ **Cross-platform** - Native desktop application for Windows, macOS, and Linux  
🎨 **Modern UI** - Built with Tailwind CSS for a clean, modern interface

## Tech Stack

-   **PHP 8.2+**
-   **Laravel 12**
-   **NativePHP Desktop** - Build native desktop applications with PHP
-   **Livewire 3** - Full-stack framework for Laravel
-   **Tailwind CSS 4** - Utility-first CSS framework
-   **SQLite** - Lightweight database
-   **Vite** - Fast frontend build tool

## Requirements

-   PHP 8.2 or higher
-   Composer
-   Node.js & npm
-   SQLite

## Installation

1. **Clone the repository**

    ```bash
    git clone https://github.com/projecthanif/nativemailer.git
    cd nativemailer
    ```

2. **Install dependencies**

    ```bash
    composer install
    npm install
    ```

3. **Set up environment**

    ```bash
    cp .env.example .env
    php artisan key:generate
    ```

4. **Run migrations**

    ```bash
    php artisan migrate
    ```

5. **Build assets**
    ```bash
    npm run build
    ```

Or use the quick setup script:

```bash
composer setup
```

## Usage

### Development Mode

Run the application in development mode:

```bash
php artisan native:serve
```

This will:

-   Start the NativePHP desktop application
-   Launch the SMTP server on `127.0.0.1:1025`
-   Open the email inbox interface

### Building for Production

Build native executables for your platform:

```bash
php artisan native:build
```

The built application will be available in the `builds` directory.

### Configuring Your Application to Use Native Mailer

Update your application's mail configuration to send emails through Native Mailer:

**.env**

```env
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
```

Now all emails sent from your application will be captured by Native Mailer!

## Project Structure

```
app/
├── Livewire/
│   └── EmailInbox.php          # Main inbox component
├── Models/
│   └── Email.php               # Email model
├── Services/
│   ├── SmtpCatcher.php         # SMTP server implementation
│   └── SmtpServiceManager.php  # Service manager
└── Providers/
    └── NativeAppServiceProvider.php  # NativePHP configuration

database/
└── migrations/
    └── 2025_10_25_020118_create_emails_table.php

resources/
├── views/
│   └── livewire/
│       └── email-inbox.blade.php  # Inbox UI
└── css/
    └── app.css                     # Tailwind styles
```

## Features in Detail

### SMTP Server

The built-in SMTP server (`SmtpCatcher.php`) implements a basic SMTP protocol that:

-   Listens on port 1025
-   Accepts connections from any mail client
-   Parses incoming emails using `php-mime-mail-parser`
-   Stores emails in the local SQLite database

### Email Inbox

The inbox interface provides:

-   Paginated list of all received emails
-   Search by subject or sender
-   Email preview with full content display
-   Responsive design

### Database Schema

Emails are stored with the following structure:

-   `from` - Sender email address
-   `to` - Recipient email address(es)
-   `subject` - Email subject
-   `body` - Email body content
-   `headers` - Raw email headers
-   `received_at` - Timestamp when email was received

## Development

### Running Tests

```bash
php artisan test
```

Or with Pest:

```bash
./vendor/bin/pest
```

### Code Style

Format code using Laravel Pint:

```bash
./vendor/bin/pint
```

### Watching Assets

During development, watch for asset changes:

```bash
npm run dev
```

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Acknowledgments

-   [Laravel](https://laravel.com) - The PHP Framework for Web Artisans
-   [NativePHP](https://nativephp.com) - Build native desktop applications with PHP
-   [Livewire](https://livewire.laravel.com) - A full-stack framework for Laravel
-   [Tailwind CSS](https://tailwindcss.com) - A utility-first CSS framework

## Author

**Mustapha Hanif**  
GitHub: [@projecthanif](https://github.com/projecthanif)

---

Made with ❤️ using Laravel and NativePHP
