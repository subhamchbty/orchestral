# 🎼 Orchestral - Laravel Process Manager

[![Latest Version on Packagist](https://img.shields.io/packagist/v/subhamchbty/orchestral.svg?style=flat-square)](https://packagist.org/packages/subhamchbty/orchestral)
[![Total Downloads](https://img.shields.io/packagist/dt/subhamchbty/orchestral.svg?style=flat-square)](https://packagist.org/packages/subhamchbty/orchestral)
[![License](https://img.shields.io/packagist/l/subhamchbty/orchestral.svg?style=flat-square)](https://packagist.org/packages/subhamchbty/orchestral)
[![PHP Version](https://img.shields.io/packagist/php-v/subhamchbty/orchestral.svg?style=flat-square)](https://packagist.org/packages/subhamchbty/orchestral)

Orchestral is an elegant Laravel package for orchestrating and conducting long-running processes with the grace of a symphony conductor. It provides a Horizon-like supervisor configuration system for managing commands like `queue:work`, `schedule:work`, and any custom long-running processes.

## ✨ Features

- 🎭 **Musical-themed API** - Conduct, pause, and encore your processes
- 🎼 **Environment-specific configurations** - Different setups for local, staging, and production
- 👥 **Multiple process management** - Run multiple instances of the same command
- 💾 **Memory management** - Set memory limits and auto-restart on exceeded limits
- 🔄 **Auto-restart on failure** - Configurable restart attempts with delays
- 📊 **Real-time status monitoring** - Track CPU, memory, and uptime
- ⚡ **Graceful shutdown** - Properly stop processes without data loss
- 🗄️ **Flexible storage** - Support for Redis (fast) or Database (persistent)
- 🎯 **Process priority control** - Set nice values for process prioritization

## 📦 Installation

```bash
# Install via Composer
composer require subhamchbty/orchestral
```

The package will auto-register its service provider.

## 🚀 Quick Start

### 1. Run the Interactive Installer

```bash
php artisan orchestral:install
```

The installer will:
- Publish the configuration file
- Ask about your storage preference (Redis or Database)
- Set up necessary migrations if using database storage

### 2. Configure Your Processes

Edit `config/orchestral.php` to define your processes:

```php
'performances' => [
    'local' => [
        'queue-worker' => [
            'command' => 'queue:work',
            'performers' => 2,      // Number of processes
            'memory' => 128,        // Memory limit in MB
            'timeout' => 3600,      // Timeout in seconds
            'options' => [
                '--tries' => 3,
                '--sleep' => 3,
            ],
        ],
        'scheduler' => [
            'command' => 'schedule:work',
            'performers' => 1,
            'memory' => 64,
        ],
    ],
],
```

### 3. Start Conducting

```bash
# Start all processes
php artisan orchestral:conduct

# Start specific process
php artisan orchestral:conduct queue-worker

# Run in daemon mode with monitoring
php artisan orchestral:conduct --daemon
```

## 🎹 Commands

| Command | Description |
|---------|-------------|
| `orchestral:install` | Interactive setup wizard |
| `orchestral:conduct` | Start conducting processes |
| `orchestral:pause` | Gracefully pause all performances |
| `orchestral:encore` | Restart performances |
| `orchestral:status` | Show real-time process status |
| `orchestral:instruments` | List configured processes |

## 📊 Monitoring

Check the status of your processes:

```bash
# Basic status
php artisan orchestral:status

# With health information
php artisan orchestral:status --health

# JSON output for automation
php artisan orchestral:status --json
```

## 🔧 Advanced Configuration

### Storage Options

**Redis (Recommended)**
```php
'storage' => 'redis',
```
- Fast, no migration needed
- Better for high-frequency updates
- Requires Redis server

**Database**
```php
'storage' => 'database',
```
- Works with any Laravel-supported database
- Persistent storage for reporting
- Requires migration: `php artisan migrate`

### Process Management Settings

```php
'management' => [
    'restart_on_failure' => true,
    'restart_delay' => 5,           // Seconds before restart
    'max_restart_attempts' => 10,   // Maximum restart attempts
    'graceful_shutdown_timeout' => 30, // Seconds to wait for graceful stop
],
```

## 📄 Requirements

- PHP ^8.2
- Laravel ^12.0
- Symfony Process ^7.0
- Redis (optional, for Redis storage)

## 📝 License

MIT License - See [LICENSE](LICENSE) file for details.

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## 🐛 Issues

Found a bug? Please [open an issue](https://github.com/subhamchbty/orchestral/issues) on GitHub.

---

*"Orchestrate your processes with the elegance of a symphony"* 🎼