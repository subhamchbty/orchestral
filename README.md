# 🎼 Orchestral - Laravel Process Manager

[![Latest Version on Packagist](https://img.shields.io/packagist/v/subhamchbty/orchestral.svg?style=flat-square)](https://packagist.org/packages/subhamchbty/orchestral)
[![Total Downloads](https://img.shields.io/packagist/dt/subhamchbty/orchestral.svg?style=flat-square)](https://packagist.org/packages/subhamchbty/orchestral)
[![License](https://img.shields.io/packagist/l/subhamchbty/orchestral.svg?style=flat-square)](https://packagist.org/packages/subhamchbty/orchestral)
[![PHP Version](https://img.shields.io/packagist/php-v/subhamchbty/orchestral.svg?style=flat-square)](https://packagist.org/packages/subhamchbty/orchestral)

Orchestral is an elegant Laravel package for orchestrating and conducting long-running processes with the grace of a symphony conductor. It provides a Horizon-like supervisor configuration system for managing commands like `queue:work`, `schedule:work`, and any custom long-running processes.

## 📚 Table of Contents

- [✨ Features](#-features)
- [🤔 Why use Orchestral?](#-why-use-orchestral)
  - [The Problem](#the-problem)
  - [The Orchestral Solution](#the-orchestral-solution)
  - [Use Cases](#use-cases)
  - [Example Scenario](#example-scenario)
- [📦 Installation](#-installation)
- [🚀 Quick Start](#-quick-start)
- [🎹 Commands](#-commands)
- [📊 Monitoring](#-monitoring)
- [🔧 Advanced Configuration](#-advanced-configuration)
  - [Storage Options](#storage-options)
  - [Process Management Settings](#process-management-settings)
- [📄 Requirements](#-requirements)
- [📝 License](#-license)
- [🤝 Contributing](#-contributing)
- [🐛 Issues](#-issues)

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

## 🤔 Why use Orchestral?

### The Problem

In traditional deployment environments like Kubernetes, managing long-running Laravel processes requires:
- **Separate pods** for each command (`queue:work`, `schedule:work`, custom workers)
- **Complex DevOps configurations** for each process type
- **Multiple deployment manifests** to maintain
- **Increased infrastructure complexity** and resource overhead
- **Dependency on DevOps team** for process configuration changes

### The Orchestral Solution

Orchestral simplifies this by allowing you to:

✅ **Keep everything in one place** - All your long-running processes managed from a single Laravel application  
✅ **Shift control to developers** - Configure and manage processes through Laravel config files, not Kubernetes manifests  
✅ **Reduce deployment complexity** - Deploy one application that manages all your background processes  
✅ **Save resources** - Run multiple process types in a single container/server with proper isolation  
✅ **Maintain consistency** - Use the same configuration approach across all environments (local, staging, production)  

### Use Cases

Orchestral is perfect when you need to:
- Run multiple queue workers with different configurations
- Manage scheduled tasks alongside queue workers
- Run custom long-running processes (WebSocket servers, file watchers, etc.)
- Quickly adjust process counts and memory limits
- Monitor and restart failed processes automatically
- Have a Horizon-like experience for all your processes, not just queues

### Example Scenario

**Without Orchestral (Kubernetes):**
```yaml
# Multiple separate deployments needed
- queue-default-deployment.yaml (3 replicas)
- queue-emails-deployment.yaml (2 replicas)  
- scheduler-deployment.yaml (1 replica)
- websocket-deployment.yaml (1 replica)
# Each needs separate configuration, monitoring, scaling rules
```

**With Orchestral:**
```php
// One configuration file manages everything
'performances' => [
    'production' => [
        'queue-default' => ['command' => 'queue:work', 'performers' => 3],
        'queue-emails' => ['command' => 'queue:work --queue=emails', 'performers' => 2],
        'scheduler' => ['command' => 'schedule:work', 'performers' => 1],
        'websocket' => ['command' => 'websocket:serve', 'performers' => 1],
    ],
],
```

Deploy once, manage everything from your Laravel application! 🎼

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

We welcome contributions to Orchestral! Whether you're fixing bugs, adding features, or improving documentation, your help is appreciated.

### Getting Started

1. **Fork the repository** on GitHub
2. **Clone your fork** locally
3. **Install dependencies**: `composer install`
4. **Create a feature branch**: `git checkout -b feature/your-feature-name`

### Development Guidelines

- Write clear, descriptive commit messages
- Keep changes focused and atomic
- Update documentation for any new features
- **Format your code** with Laravel Pint before submitting: `./vendor/bin/pint`

### Testing Requirements

**Important**: All contributions must include appropriate test cases. When submitting a pull request:

- ✅ **Add unit tests** for new functionality
- ✅ **Add integration tests** for command interactions
- ✅ **Update existing tests** if modifying behavior
- ✅ **Ensure all tests pass** before submitting
- ✅ **Test edge cases** and error conditions

Run the test suite:
```bash
./vendor/bin/pest
```

### Submitting Changes

1. **Run the test suite** to ensure everything works
2. **Push your changes** to your fork
3. **Create a pull request** with:
   - Clear description of changes
   - Reference to any related issues
   - Screenshots/examples if applicable
   - Confirmation that tests are included

## 🐛 Issues

Found a bug? Please [open an issue](https://github.com/subhamchbty/orchestral/issues) on GitHub.

---

*"Orchestrate your processes with the elegance of a symphony"* 🎼