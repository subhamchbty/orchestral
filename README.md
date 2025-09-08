# 🎼 Orchestral - Laravel Process Manager

Orchestral is an elegant Laravel package for orchestrating and conducting long-running processes with the grace of a symphony conductor. It provides a Horizon-like supervisor configuration system for managing commands like `queue:work`, `schedule:work`, and any custom long-running processes.

## ✨ Features

- 🎭 **Musical-themed API** - Conduct, pause, and encore your processes
- 🎼 **Environment-specific configurations** - Different setups for local, staging, and production
- 👥 **Multiple process management** - Run multiple instances of the same command
- 💾 **Memory management** - Set memory limits and auto-restart on exceeded limits
- 🔄 **Auto-restart on failure** - Configurable restart attempts with delays
- 📊 **Health monitoring** - Track CPU, memory, and uptime
- 🏥 **Health checks** - Automatic detection of unhealthy processes
- 📈 **Performance tracking** - MongoDB-based event logging
- ⚡ **Graceful shutdown** - Properly stop processes without data loss

## 📦 Installation

You can install the package via composer:

```bash
composer require subhamchbty/orchestral
```

The package will auto-register its service provider.

## 🎹 Installation & Configuration

Install Orchestral with interactive setup:

```bash
# Interactive installation (recommended)
php artisan orchestral:install
```

The installer will:
- 📄 Publish the configuration file
- 🗄️ Ask about storage preference (Redis vs Database)
- 📊 Publish migrations only if database storage is selected
- ⚙️ Configure the storage driver automatically

### Storage Options:

**Redis (Recommended)** - Fast, no migration needed
- Requires Redis server running
- Better performance for high-frequency updates
- No database table needed

**Database** - Traditional storage with migration
- Works with MySQL, PostgreSQL, SQLite, etc.
- Requires running `php artisan migrate`
- Good for data persistence and reporting

### Manual Installation:

```bash
# Install individually:
php artisan orchestral:install --config      # Just config file
php artisan orchestral:install --migrations  # Just migrations (always publishes)

# Traditional Laravel publishing (fallback):
php artisan vendor:publish --tag=orchestral-config      # Config only
php artisan vendor:publish --tag=orchestral-migrations  # Migrations only
```

Configure your performances in `config/orchestral.php`:

```php
'performances' => [
    'local' => [
        'queue-worker' => [
            'command' => 'queue:work',
            'performers' => 3,      // Number of processes
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

## 🎵 Usage

### Start the Orchestra

Start all configured performances:
```bash
php artisan orchestral:conduct
```

Start a specific performance:
```bash
php artisan orchestral:conduct queue-worker
```

Run in daemon mode with monitoring:
```bash
php artisan orchestral:conduct --daemon
```

### Control the Performance

Pause all performances:
```bash
php artisan orchestral:pause
```

Restart performances (encore):
```bash
php artisan orchestral:encore
```

### Monitor Status

Check the status of all performers:
```bash
php artisan orchestral:status
```

Include health check information:
```bash
php artisan orchestral:status --health
```

Get JSON output:
```bash
php artisan orchestral:status --json
```

### List Configured Instruments

View all configured processes:
```bash
php artisan orchestral:instruments
```

## 🎭 Commands

| Command | Description |
|---------|-------------|
| `orchestral:conduct` | Start conducting the orchestra |
| `orchestral:pause` | Pause all performances |
| `orchestral:encore` | Restart performances |
| `orchestral:status` | Show current status |
| `orchestral:instruments` | List configured processes |

## 🏗️ Architecture

- **Conductor**: Main orchestrator managing all performers
- **Performer**: Individual process wrapper
- **Score**: Configuration reader and validator
- **Performance**: MongoDB model for event tracking

## 🔧 Advanced Configuration

### Process Management
```php
'management' => [
    'restart_on_failure' => true,
    'restart_delay' => 5,
    'max_restart_attempts' => 10,
    'graceful_shutdown_timeout' => 30,
    'health_check_interval' => 60,
],
```

### Monitoring
```php
'monitoring' => [
    'track_memory' => true,
    'track_cpu' => true,
    'alert_on_high_memory' => true,
    'memory_alert_threshold' => 90,
],
```

## 📝 License

MIT License

## 🎼 Credits

Created with ❤️ by Subham Chowdhury

---

*"Orchestrate your processes with the elegance of a symphony"*