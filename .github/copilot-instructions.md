# Slack-like Backend - AI Assistant Instructions

## Project Overview

This is a Laravel-based Slack-like chat application backend with JWT authentication, team management, and activity logging. The system supports multi-team environments where users can create/join teams, manage channels, and send messages.

## Architecture & Key Patterns

### Authentication Flow

-   **Custom JWT implementation** using `firebase/php-jwt` (not Laravel Sanctum/Passport)
-   JWT service in `app/Services/JwtService.php` handles token generation, validation, and refresh
-   Custom middleware `JwtAuthMiddleware` validates tokens on protected routes
-   Configuration in `config/jwt.php` - tokens expire after 60min by default

### Multi-layered Route Protection

```php
// API routes follow this pattern:
Route::middleware(['jwt.auth'])->group(function () {
    Route::apiResource('teams', TeamController::class);

    Route::middleware(['team.member'])->group(function () {
        Route::apiResource('teams.channels', ChannelController::class);
        Route::apiResource('teams.channels.messages', MessageController::class);
    });
});
```

### Service Layer Pattern

-   `JwtService` - JWT token management and validation
-   `ActivityLogService` - Comprehensive activity tracking across teams
-   `TeamService` - Team operations and member management
-   Services are injected via constructor dependency injection

### Data Models & Relationships

-   **Users** can belong to multiple **Teams** via **TeamMembers** (pivot)
-   **Teams** contain **Channels**, **Channels** contain **Messages**
-   **ActivityLogs** track all user actions with metadata
-   Uses Laravel Enums: `UserRole`, `TeamMemberRole`, `ChannelType`, `MessageType`

## Development Workflow

### Key Commands

```bash
# Start development
php artisan serve
npm run dev

# Database operations
php artisan migrate
php artisan db:seed

# Testing
php artisan test
./vendor/bin/phpunit

# Code quality
./vendor/bin/pint  # Laravel Pint for formatting
```

### Database Setup

-   Uses SQLite by default (`database/database.sqlite`)
-   Migration files follow timestamp pattern: `2024_01_01_000001_*.php`
-   Models use `$fillable` arrays for mass assignment protection

## Project-Specific Conventions

### API Response Format

Controllers return standardized JSON responses:

```php
// Success
return response()->json([
    'message' => 'Operation successful',
    'data' => $result
], 200);

// Error with validation
return response()->json([
    'error' => 'Validation failed',
    'messages' => $validator->errors()
], 422);
```

### Exception Handling

-   Custom `JwtException` for authentication errors
-   Custom `TeamException` for team-related errors
-   Exceptions include static factory methods: `JwtException::tokenNotProvided()`

### Activity Logging

All significant actions are logged via `ActivityLogService`:

```php
$this->activityLogService->log(
    'user_registered',
    'User registered successfully',
    $user,
    $team, // optional
    ['ip' => $request->ip()],
    $request
);
```

### Code Organization

-   **Controllers** in `app/Http/Controllers/Api/` (namespace-grouped by feature)
-   **Middleware** in `app/Http/Middleware/` with descriptive names
-   **Services** in `app/Services/` for business logic
-   **Enums** in `app/Enums/` for type safety
-   **Resources** for API transformation (following Laravel conventions)

## Integration Points

### Frontend Integration

-   API endpoints prefixed with `/api/`
-   CORS configured for frontend consumption
-   Token passed via `Authorization: Bearer {token}` header

### Key Configuration Files

-   `config/jwt.php` - JWT settings and providers
-   `config/slack.php` - Application-specific settings
-   `.env` - Environment variables including JWT_SECRET

## Common Tasks

### Adding New Features

1. Create migration: `php artisan make:migration create_feature_table`
2. Create model with relationships
3. Add routes to `routes/api.php` with appropriate middleware
4. Create controller with dependency injection
5. Add activity logging for audit trail

### Testing New Endpoints

Use the existing JWT flow - register/login to get token, then test protected endpoints with Bearer token.

### Working with Teams

Team-scoped operations require both JWT auth AND team membership validation via `team.member` middleware (currently a TODO in `TeamMemberMiddleware.php`).
