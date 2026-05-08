---
inclusion: fileMatch
fileMatchPattern: ['**/Controllers/**', '**/Middleware/**', '**/Exceptions/**', '**/Requests/**']
---

# Error Handling & Security

## Exceptions
- Custom exceptions when needed
- `context()` method on exceptions for log context
- try-catch for expected exceptions only
- Global exception context in `bootstrap/app.php` via `withExceptions()`

## Logging (Laravel Context)
- Add request context in middleware via `Context::add([...])`:
  - `user`, `url`, `route`, `locale`, `scope`
- `Context::addHidden()` for sensitive data (tokens, passwords)
- `Context::scope()` for temporary context
- Context auto-shared with queued jobs
- Always include: user ID, request ID, route, trace ID

## Security
- CSRF + XSS protection always
- Sanctum for API tokens
- Gates + Policies for authz
- Middleware for request filtering
- Input sanitization

## Validation
- Form Requests always — never inline validation in controllers
