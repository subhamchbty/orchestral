# Changelog

All notable changes to `orchestral` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0-beta] - 2025-01-08

### Added
- Initial beta release of Orchestral package
- Core process management functionality with Conductor
- Musical-themed command interface (conduct, pause, encore)
- Environment-specific configurations
- Multiple process instance management
- Memory management with auto-restart capabilities
- Health monitoring (CPU, memory, uptime tracking)
- MongoDB-based performance tracking
- Graceful shutdown mechanisms
- Interactive installation command
- Comprehensive test suite
- Laravel 12 compatibility
- Auto-discovery service provider

### Features
- `orchestral:install` - Interactive installation and configuration
- `orchestral:conduct` - Start conducting all configured processes
- `orchestral:pause` - Gracefully pause all running processes
- `orchestral:encore` - Restart processes that have stopped
- `orchestral:status` - View detailed status of all processes
- `orchestral:instruments` - List all available instruments (commands)

### Configuration
- Environment-based configuration files
- Memory limit controls
- Restart attempt configuration
- Process timeout settings
- Health check intervals

### Dependencies
- PHP ^8.2
- Laravel Framework ^12.0
- Symfony Process ^7.0

[0.1.0-beta]: https://github.com/subhamchbty/orchestral/releases/tag/v0.1.0-beta