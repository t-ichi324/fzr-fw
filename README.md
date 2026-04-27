# Fzr Framework (FW)

A modern, lightweight, and AI-friendly PHP framework skeleton designed for rapid development.

## Core Philosophy

- **AI-First**: Structure and patterns optimized for AI assistants to understand and generate code.
- **Lightweight**: Minimalist core with zero heavy dependencies.
- **Modular**: Core framework logic in `fzr/fw`, and essential utilities in `fzr/kit`.
- **Developer-Centric**: Simple, intuitive API inspired by modern standards.

## Project Structure

```text
/
├── app/            # Application logic
├── config/         # Configuration files
├── public/         # Document root
├── src/            # Framework Core (Fzr\ namespace)
├── storage/        # Cache, logs, and temp files
└── vendor/         # Composer dependencies (includes fzr/kit)
```

## Getting Started

### 1. Requirements
- PHP 8.1 or higher
- Composer

### 2. Installation
```bash
composer require fzr/fw
```
*Note: This will automatically include [fzr/kit](https://github.com/t-ichi324/fzr-kit) for utility functions.*

## Key Components

- **Engine**: The heart of the framework handling request/response lifecycle.
- **Routing**: Simple and powerful attribute-based or closure routing.
- **Attributes**: Next-gen validation and middleware using PHP 8 attributes.
- **Database**: Fluent query builder with pgvector (vector search) support.
- **Auth & Security**: JWT-based authentication and secure session management.

## Ecosystem

Fzr is divided into two main components:
1. **Fzr FW**: Routing, Request/Response, Middleware, Database Core.
2. **[Fzr Kit](https://github.com/t-ichi324/fzr-kit)**: CSV, ZIP, Date/Wareki, Advanced String/Array manipulation.

## License

MIT
