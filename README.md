<!-- Meta redirect: some renderers ignore meta refresh, so a manual link is provided below -->
<meta http-equiv="refresh" content="0; url=./documentation.md" />

# Slack backend

Toto je stručné shrnutí projektu. Kompletní dokumentaci najdete v souboru `documentation.md` v kořenovém adresáři repozitáře.

[Otevřít dokumentaci](./documentation.md)

## Krátké shrnutí

Backend je postavený na Laravelu 12.x a poskytuje REST API pro Slack-like aplikaci s těmito hlavními funkcemi:

-   Registrace, přihlášení a správa uživatelů (JWT autentifikace)
-   Správa týmů: vytváření, pozvánky, role (owner/admin/member)
-   Kanály v týmech: veřejné a soukromé kanály
-   Zprávy: kanálové zprávy s podporou vláken a přímé (DM) zprávy
-   Reakce na zprávy (emoji)
-   Protokolování aktivit (audit log)

Databáze: MariaDB (kompatibilní s MySQL). Konfigurace DB se provádí přes `.env` a `config/database.php`.

## Rychlé nastavení (MariaDB)

1. Nainstalujte závislosti:

```bash
composer install
```

2. Vytvořte `.env` a upravte připojení k mariadb/mysql:

```env
DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=slack_db
DB_USERNAME=slack_user
DB_PASSWORD=secret_password
```

3. Vygenerujte klíč aplikace a spusťte migrace:

```bash
php artisan key:generate
php artisan migrate
```

4. Spusťte lokálně:

```bash
php artisan serve
```

## Kde najdete více

-   Kompletní technická dokumentace (detaily modelů, endpointy, služby, middleware) je v `documentation.md`.
