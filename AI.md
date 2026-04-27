# Fzr Framework: AI Collaboration Guide

Fzr Framework is designed specifically to be "AI-Understandable". This document guides both developers and AI assistants on how to effectively build applications using Fzr.

## Design Philosophy for AI

1. **Explicit over Implicit**: We prefer Attributes and explicit method calls over "magic" hidden behavior.
2. **Type Safety**: Strong typing in PHP 8.1+ helps AI understand data structures without ambiguity.
3. **Consistency**: Naming conventions and directory structures are strictly enforced across the ecosystem.
4. **Small Surface Area**: By splitting into `fw` (core) and `kit` (utils), the context window needed to understand a specific feature is minimized.

## Core Patterns

### 1. Controllers & Attributes
Use attributes for validation and middleware. This allows AI to see the "contract" of an endpoint at a glance.

```php
#[Api, Post, Csrf]
public function update(#[Required, MaxLength(50)] string $name): Response {
    // Logic here
}
```

### 2. Database & Models
Fzr uses a fluent query builder. For AI, this is easier to generate correctly than complex ORM relations.

```php
$users = Db::table('users')->where('status', 'active')->all();
```

### 3. Using Kit (Utilities)
When an AI assistant needs to handle dates, strings, or files, it should always look into `Fzr\Util` or `Fzr\Date` from the `fzr/kit` library.

- **Wareki**: Use `Wareki::parse($dateString)` for Japanese dates.
- **Zengin**: Use `Str::formatZengin($name)` for banking data.
- **Cleanup**: Use `Str::cleanString($input)` for sanitizing form inputs.

## Prompting Tips for Fzr

When asking an AI to write code for Fzr:
- "Create a Fzr controller for [Feature] using Attributes for validation."
- "Write a Fzr database migration and model for [Table]."
- "Use Fzr/Kit to handle the CSV import of [Data Structure]."

## Conclusion
Fzr is your AI's favorite framework. By following these patterns, you ensure high-quality code generation and minimal debugging.
