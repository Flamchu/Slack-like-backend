# Dokumentace backendu Slack

## Přehled projektu

Tento repozitář obsahuje backend API postavené na Laravelu (verze 12.x) pro Slack-like chatovací aplikaci zaměřenou na týmovou spolupráci. Aplikace umožňuje registraci uživatelů, vytváření týmů, pozvání členů, tvorbu kanálů a komunikaci pomocí kanálových i přímých zpráv. Autentifikace je řešena pomocí JWT a systém podporuje role a oprávnění na úrovni týmu.

## Technologický stack

-   **Framework**: Laravel 12.x ([Laravel dokumentace](https://laravel.com/docs/12.x))
-   **PHP**: 8.2+
-   **Autentifikace**: JWT (firebase/php-jwt)
-   **Databáze**: MariaDB (kompatibilní s MySQL)
-   **Architektura**: RESTful API s CRUD operacemi

## Hlavní funkce

### 1) Správa uživatelů & autentifikace

Systém poskytuje kompletní uživatelskou správu s JWT autentifikací.

#### Modely

-   **User** (`app/Models/User.php`) – rozšiřuje Laravel `Authenticatable`
    -   Pole: name, first_name, last_name, email, password, avatar, role, is_active, last_login_at
    -   Vztahy: ownedTeams, teams, teamMembers, messages, directMessages
    -   Hesla jsou bezpečně hashována pomocí Laravel Hash (bcrypt/argon)

#### JWT a autentifikace

-   **JwtService** (`app/Services/JwtService.php`) – vlastní obsluha JWT
    -   Vytváření tokenů, validace, možnost refresh tokenů a blacklist při odhlášení
    -   Konfigurace v `config/jwt.php`

#### API endpointy (autentifikace)

-   `POST /api/auth/register` – registrace
-   `POST /api/auth/login` – přihlášení
-   `POST /api/auth/logout` – odhlášení (vyžaduje JWT)
-   `POST /api/auth/refresh` – obnova tokenu
-   `GET /api/auth/profile` – profil uživatele

Reference: [Laravel Authentication](https://laravel.com/docs/12.x/authentication)

### 2) Správa týmů

Tým (Team) je základní organizační jednotka pro spolupráci.

#### Modely

-   **Team** (`app/Models/Team.php`)

    -   Pole: name, slug, description, avatar, owner_id, is_active
    -   Vztahy: owner, members, channels, invitations, activityLogs

-   **TeamMember** (`app/Models/TeamMember.php`) – pivot tabulka s poli navíc

    -   Pole: team_id, user_id, role, joined_at, invited_by, is_active
    -   Role: member, admin, owner (`app/Enums/TeamMemberRole.php`)

-   **TeamInvitation** (`app/Models/TeamInvitation.php`)
    -   Pole: team_id, email, invited_by, expires_at, status

#### API endpointy (týmy)

-   `GET /api/teams` – seznam týmů uživatele
-   `POST /api/teams` – vytvoření týmu
-   `GET /api/teams/{team}` – detail týmu
-   `PUT /api/teams/{team}` – aktualizace týmu
-   `DELETE /api/teams/{team}` – smazání týmu
-   `POST /api/teams/{team}/invite` – pozvání uživatele do týmu
-   `POST /api/teams/join` – připojení přes pozvánku
-   `DELETE /api/teams/{team}/leave` – opuštění týmu
-   `GET /api/teams/{team}/members` – členové týmu

Reference: [Eloquent relationships](https://laravel.com/docs/12.x/eloquent-relationships)

### 3) Správa kanálů

Kanály (Channel) slouží jako komunikační prostory uvnitř týmu.

#### Modely

-   **Channel** (`app/Models/Channel.php`)
    -   Pole: team_id, name, description, type, is_private, created_by, is_active
    -   Vztahy: team, creator, messages
    -   Typy kanálů jsou definovány v `app/Enums/ChannelType.php`

#### API endpointy (kanály)

-   `GET /api/teams/{team}/channels` – seznam kanálů
-   `POST /api/teams/{team}/channels` – vytvoření kanálu
-   `GET /api/teams/{team}/channels/{channel}` – detail kanálu
-   `PUT /api/teams/{team}/channels/{channel}` – aktualizace kanálu
-   `DELETE /api/teams/{team}/channels/{channel}` – smazání kanálu

Reference: [Eloquent query scopes](https://laravel.com/docs/12.x/eloquent#query-scopes)

### 4) Zprávy (channel + DMs)

Podporované jsou zprávy v kanálech (s vlákny) i přímé zprávy mezi uživateli.

#### Kanálové zprávy

-   **Message** (`app/Models/Message.php`)
    -   Pole: channel_id, user_id, content, type, parent_id, is_edited, edited_at
    -   Podpora vláken (parent_id)

#### Přímé zprávy

-   **DirectMessage** (`app/Models/DirectMessage.php`)
    -   Pole: sender_id, receiver_id, content, is_read, read_at

#### Reakce

-   **Reaction** (`app/Models/Reaction.php`)
    -   Pole: message_id, user_id, emoji

#### API endpointy (zprávy)

Kanálové zprávy:

-   `GET /api/teams/{team}/channels/{channel}/messages` – seznam zpráv
-   `POST /api/teams/{team}/channels/{channel}/messages` – odeslat zprávu
-   `PUT /api/teams/{team}/channels/{channel}/messages/{message}` – upravit zprávu
-   `DELETE /api/teams/{team}/channels/{channel}/messages/{message}` – smazat zprávu

Přímé zprávy:

-   `POST /api/direct-messages` – odeslat přímou zprávu
-   `GET /api/direct-messages/conversations` – seznam konverzací
-   `GET /api/direct-messages/conversations/{user}` – konverzace s uživatelem
-   `POST /api/direct-messages/mark-read/{user}` – označit jako přečtené
-   `GET /api/direct-messages/unread-count` – počet nepřečtených

Reakce:

-   `GET /api/teams/{team}/channels/{channel}/messages/{message}/reactions` – seznam reakcí
-   `POST /api/teams/{team}/channels/{channel}/messages/{message}/reactions` – přidat reakci
-   `DELETE /api/teams/{team}/channels/{channel}/messages/{message}/reactions` – odebrat reakci

### 5) Protokolování aktivit (Activity log)

Pro audit a analytiku aplikace se zaznamenávají uživatelské akce.

#### Modely

-   **ActivityLog** (`app/Models/ActivityLog.php`)
    -   Pole: user_id, team_id, action, description, ip_address, user_agent, metadata

#### Služba

-   **ActivityLogService** (`app/Services/ActivityLogService.php`) – centralizovaná logika pro zapisování logů

Reference: [Laravel logging](https://laravel.com/docs/12.x/logging)

## Architektura služeb

Hlavní služby umístěné v `app/Services/`:

-   `JwtService` – správa tokenů a jejich validace
-   `TeamService` – obchodní logika spojená s týmy a pozvánkami
-   `ChannelService` – vytváření a správa kanálů
-   `MessageService` – správa zpráv a vláken
-   `DirectMessageService` – zprávy mezi uživateli
-   `ActivityLogService` – zapisování uživatelských akcí

Reference: [Laravel Service Container](https://laravel.com/docs/12.x/container)

## Middleware

-   `JwtAuthMiddleware` (`app/Http/Middleware/JwtAuthMiddleware.php`) – kontrola platnosti JWT tokenu
-   `TeamMemberMiddleware` (`app/Http/Middleware/TeamMemberMiddleware.php`) – ověření, zda uživatel patří do týmu

Reference: [Laravel middleware](https://laravel.com/docs/12.x/middleware)

## Enums

-   `UserRole` (`app/Enums/UserRole.php`) – `USER`, `ADMIN`
-   `TeamMemberRole` (`app/Enums/TeamMemberRole.php`) – `MEMBER`, `ADMIN`, `OWNER`
-   `ChannelType` (`app/Enums/ChannelType.php`) – typy kanálů
-   `MessageType` (`app/Enums/MessageType.php`) – typy zpráv

Reference: [PHP enums](https://www.php.net/manual/en/language.enumerations.php)

## Struktura databáze

Databáze je spravována pomocí Laravel migrací. Primárně je dokumentace a konfigurace přizpůsobena pro MariaDB/MySQL.

### Hlavní tabulky

1. `users` – uživatelské účty
2. `teams` – týmy
3. `team_members` – členství v týmech (pivot)
4. `team_invitations` – pozvánky do týmů
5. `channels` – kanály v týmech
6. `messages` – kanálové zprávy
7. `direct_messages` – přímé zprávy
8. `reactions` – reakce na zprávy
9. `activity_logs` – záznamy aktivit

Vztahy: uživatel může vlastnit více týmů, uživatelé patří do více týmů (pivot), týmy mají kanály, kanály mají zprávy, zprávy mohou mít rodiče (vlákna).

Reference: [Laravel migrations](https://laravel.com/docs/12.x/migrations)

## Bezpečnost API

### Autentifikace

-   Stateless JWT autentifikace
-   Expirace tokenů a refresh mechanizmus
-   Hesla hashována pomocí Laravel Hash facade

### Autorizace

-   Role-based kontrola přístupu (uživatel/tým)
-   Ověření členství v middleware

### Validace požadavků

-   Použití Laravel Form Requests / Validator
-   Ochrana proti SQL injection pomocí Eloquent ORM

Reference: [Laravel security](https://laravel.com/docs/12.x/security)

## Formát odpovědí API

Konzistentní JSON odpovědi:

### Úspěšná odpověď

```json
{
    "success": true,
    "data": {...},
    "message": "Operation completed successfully"
}
```

### Chybová odpověď

```json
{
    "error": "Error type",
    "message": "Detailed error message",
    "errors": {...}
}
```

Reference: [Eloquent resources / API resources](https://laravel.com/docs/12.x/eloquent-resources)

## Zpracování chyb

Speciální exceptions v projektu:

-   `JwtException` – chyby týkající se JWT
-   `TeamException` – chyby týkající se operací s týmy

HTTP status kódy: 200, 201, 400, 401, 403, 404, 422, 500

Reference: [Laravel error handling](https://laravel.com/docs/12.x/errors)

## Nastavení vývojového prostředí

1. Naklonovat repozitář
2. Nainstalovat závislosti: `composer install`
3. Zkopírovat env: `cp .env.example .env` a upravit DB údaje
4. Vygenerovat klíč aplikace: `php artisan key:generate`
5. Spustit migrace: `php artisan migrate`
6. Spustit lokální server: `php artisan serve`

Reference: [Laravel installation](https://laravel.com/docs/12.x/installation)
