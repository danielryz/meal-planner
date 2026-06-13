# Architektura aplikacji

## Diagram warstwowy

```
┌─────────────────────────────────────────────────────────┐
│                      Przeglądarka                       │
│  HTML5 · CSS3 · JavaScript ES2022+ (vanilla, bez fw)   │
│  Osobny JS + CSS per feature (/features/<nazwa>/)       │
└────────────────────────┬────────────────────────────────┘
                         │ HTTP
┌────────────────────────▼────────────────────────────────┐
│                        Nginx                            │
│  Serwuje pliki statyczne, proxy → PHP-FPM               │
└────────────────────────┬────────────────────────────────┘
                         │ FastCGI
┌────────────────────────▼────────────────────────────────┐
│                      PHP 8.3-FPM                        │
│                                                         │
│  index.php → Routing.php → Controller → Response        │
│                                                         │
│  ┌─────────────┐  ┌──────────────┐  ┌───────────────┐  │
│  │ Controllers │  │ Repositories │  │   Services    │  │
│  │  (HTTP /    │→ │  (SQL przez  │  │ Mail, AI,     │  │
│  │   biznes)   │  │  PDO + PS)   │  │ PriceEstim.   │  │
│  └─────────────┘  └──────┬───────┘  └───────┬───────┘  │
│                           │                  │          │
│  ┌────────────────────────▼──────────────────▼───────┐  │
│  │                    Entities / DTO                  │  │
│  └────────────────────────────────────────────────────┘  │
└───────────┬───────────────────────┬─────────────────────┘
            │                       │
┌───────────▼──────┐    ┌───────────▼──────────────────────┐
│   PostgreSQL 16  │    │         Usługi zewnętrzne         │
│                  │    │                                   │
│  Schemat:        │    │  Ollama  — lokalny LLM (Docker)   │
│  users           │    │  Mailpit — pułapka SMTP (Docker)  │
│  recipes         │    │  SMTP    — prod (Resend / Mailgun) │
│  meal_plans      │    │  Paynow  — płatności (REST API)   │
│  grocery_lists   │    │  Google OAuth 2.0                 │
│  media_files     │    │  Apple Sign In                    │
│  …(16 migracji)  │    │                                   │
└──────────────────┘    └───────────────────────────────────┘
```

---

## Warstwy — krótki opis

| Warstwa | Odpowiedzialność |
|---------|-----------------|
| **Przeglądarka** | Widoki — osobny moduł JS + CSS per feature. Fetch API do backendu. Brak frameworka. |
| **Nginx** | Serwowanie plików statycznych (`/public/**`), proxy każdego innego żądania do PHP-FPM. |
| **Routing** | `index.php` → `Routing::run()` → `Router::dispatch()` — dopasowanie URL do kontrolera i akcji. Brak frameworka routingu. |
| **Controllers** | Warstwa HTTP. Odczyt `Request`, walidacja wejścia, wywołanie Repositories / Services, zwrot `Response` (JSON lub widok PHP). |
| **Repositories** | Dostęp do danych. Wyłącznie PDO + prepared statements. Brak ORM. |
| **Services** | Logika niezwiązana z HTTP: wysyłanie e-maili (PHPMailer), komunikacja z Ollama (curl), szacowanie cen. |
| **Entities** | Proste DTO / value objects (np. `AuthUser`). |
| **PostgreSQL** | Jedyne źródło prawdy. Schemat wersjonowany migracjami (001–016). |

---

## Przepływ żądania HTTP

```
Przeglądarka
  → GET/POST http://localhost:8080/<ścieżka>
  → Nginx (static lub proxy)
  → index.php
      bootstrap.php   (autoload, .env, error_reporting)
      SessionManager  (start sesji PHP)
      Routing::run()
        Router::dispatch()          (dopasowanie trasy)
          Controller->action()
            Repository / Service    (dane / logika)
          Response->send()          (nagłówki + body)
  ← JSON lub HTML
```

---

## Struktura katalogów

```
docker/
  db/migrations/     migracje SQL (001–016)
  nginx/nginx.conf
  php/Dockerfile
docs/
  ARCHITECTURE.md    ten plik
  CHECKLIST.md       lista funkcji i security
  SETUP.md           instrukcja uruchomienia
  erd.md             diagram ERD (Mermaid)
  schema.sql         skonsolidowany schemat
public/
  assets/            ikony, statyczne obrazy
  features/          widoki + JS per feature
    ai-chat/
    admin-panel/
    auth/
    grocery-list/
    meal-planner/
    profile/
    public/
    recipes/
    users/
  styles/            globalne CSS (base, button, form…)
scripts/
  run-migrations.php
  seed.php
  smoke.sh / smoke.ps1
src/
  Auth/              SessionManager, AuthUser
  Config/            Env (ładowanie .env)
  Controllers/       kontrolery HTTP
  Database/          Database (PDO factory), DataSeeder
  Entities/          DTO
  Http/              Request, Response, Router, ViewRenderer
  Repositories/      dostęp do danych
  Services/          MailService, AiService, PriceEstimator
  templates/emails/  szablony HTML e-maili
tests/
  Unit/Http/         PHPUnit (Request, Router, Response)
index.php            punkt wejścia
Routing.php          rejestr tras
docker-compose.yaml
.env.example
```
