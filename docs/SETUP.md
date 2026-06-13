# Uruchomienie aplikacji

## Wymagania

- Docker i Docker Compose
- Git
- Dostęp do internetu przy pierwszym pobraniu obrazu Ollama i modelu LLM

---

## Szybki start

### 1. Sklonuj repozytorium

```bash
git clone <adres-repozytorium>
cd MealPlanner
```

### 2. Utwórz plik `.env`

Linux / macOS:
```bash
cp .env.example .env
```

Windows PowerShell:
```powershell
Copy-Item .env.example .env
```

Uzupełnij wartości — patrz [Zmienne środowiskowe](#zmienne-środowiskowe).

### 3. Uruchom kontenery

```bash
docker compose up --build -d
```

### 4. Pobierz model AI (pierwsze uruchomienie)

```bash
docker compose exec ollama ollama pull qwen2.5:14b
```

Jeśli w `.env` ustawiono inny model, pobierz tę samą nazwę:
```bash
docker compose exec ollama ollama pull <nazwa-modelu>
```

### 5. Uruchom migracje i seed danych demo

```bash
docker compose exec php php scripts/run-migrations.php
docker compose exec php php scripts/seed.php
```

### 6. Otwórz aplikację

```
http://localhost:8080
```

Konta demo:

| E-mail | Hasło | Rola |
|--------|-------|------|
| `owner@mealplanner.test` | `Demo1234!` | owner |
| `employee@mealplanner.test` | `Demo1234!` | employee |
| `user@mealplanner.test` | `Demo1234!` | user |

---

## Porty lokalne

| Usługa | Adres |
|--------|-------|
| Aplikacja | `http://localhost:8080` |
| Mailpit (podgląd e-maili) | `http://localhost:8025` |
| pgAdmin | `http://localhost:5050` |
| Ollama API | `http://localhost:11434` |
| PostgreSQL | `localhost:5433` |

---

## Zmienne środowiskowe

Klucze trzymamy wyłącznie w `.env` — nie commitować. Plik `.env.example` zawiera puste szablony.

```env
# Aplikacja
APP_URL=http://localhost:8080          # używany w linkach w e-mailach

# Baza danych
POSTGRES_DB=mealplanner
POSTGRES_USER=mealplanner
POSTGRES_PASSWORD=mealplanner_dev_password
POSTGRES_PORT=5433

# pgAdmin
PGADMIN_DEFAULT_EMAIL=admin@example.com
PGADMIN_DEFAULT_PASSWORD=admin

# AI (Ollama)
OLLAMA_URL=http://ollama:11434         # URL kontenera wewnątrz Docker Compose
OLLAMA_MODEL=qwen2.5:14b

# E-mail (SMTP)
MAIL_HOST=mailpit                      # lub smtp.mailgun.org, smtp.resend.com itp.
MAIL_PORT=1025                         # Mailpit dev; 587 dla produkcji
MAIL_USERNAME=test
MAIL_PASSWORD=test
MAIL_FROM=no-reply@mealplanner.test
MAIL_FROM_NAME=MealPlanner

# Paynow (płatności)
PAYNOW_API_KEY=
PAYNOW_SIGNATURE_KEY=
PAYNOW_SANDBOX=true                    # false na produkcji

# Google OAuth
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=http://localhost:8080/auth/google/callback

# Apple Sign In
APPLE_CLIENT_ID=
APPLE_TEAM_ID=
APPLE_KEY_ID=
APPLE_PRIVATE_KEY=                     # zawartość pliku .p8 (z \n zamiast nowych linii)
APPLE_REDIRECT_URI=http://localhost:8080/auth/apple/callback
```

---

## Konfiguracja e-maila

### Środowisko deweloperskie — Mailpit

Mailpit uruchamia się automatycznie z Docker Compose i przechwytuje wszystkie e-maile. Podgląd: `http://localhost:8025`.

```env
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=test
MAIL_PASSWORD=test
```

### Produkcja — zewnętrzny SMTP

| Provider | Free tier | Host SMTP |
|----------|-----------|-----------|
| Resend | 3 000 e-maili/miesiąc | `smtp.resend.com:587` |
| Mailgun | 100/dzień (3 miesiące) | `smtp.mailgun.org:587` |
| Brevo | 300/dzień | `smtp-relay.brevo.com:587` |

### Szablony e-maili

Szablony HTML w `src/templates/emails/`:

| Plik | Zdarzenie |
|------|-----------|
| `activation.html` | Weryfikacja adresu po rejestracji |
| `password-reset.html` | Reset hasła |
| `email-change.html` | Potwierdzenie zmiany adresu e-mail |
| `invitation.html` | Zaproszenie pracownika |

---

## Konfiguracja Google OAuth

1. Utwórz projekt w [Google Cloud Console](https://console.cloud.google.com/).
2. Utwórz dane uwierzytelniające OAuth 2.0 (typ: aplikacja webowa).
3. Dodaj URI przekierowania: `http://localhost:8080/auth/google/callback` (dev) i domenę produkcyjną.
4. Skopiuj `Client ID` i `Client Secret` do `.env`.

---

## Konfiguracja Paynow

1. Załóż konto w panelu [Paynow](https://panel.paynow.pl/).
2. Skonfiguruj URL powiadomień (webhook): `https://twojadomena.pl/api/payments/notify`.
3. Skopiuj **API Key** i **Signature Key** do `.env`.
4. Na etapie testów ustaw `PAYNOW_SANDBOX=true`.

---

## Baza danych

PostgreSQL inicjalizowany z `docker/db/init.sql`. Migracje w `docker/db/migrations/` (001–016) stosowane automatycznie przy starcie kontenera DB.

Ręczne uruchomienie migracji:
```bash
docker compose exec php php scripts/run-migrations.php
```

Pełny schemat: `docs/schema.sql`.

### Połączenie w pgAdmin

1. Otwórz `http://localhost:5050`.
2. Zaloguj się: `PGADMIN_DEFAULT_EMAIL` / `PGADMIN_DEFAULT_PASSWORD` z `.env`.
3. Dodaj serwer: host `db`, port `5432`, baza / user / hasło z `.env`.

---

## Testy

### PHPUnit (jednostkowe)

```bash
docker compose exec php vendor/bin/phpunit
```

### Smoke testy

```bash
# bash
BASE_URL=http://localhost:8080 bash scripts/smoke.sh

# PowerShell
$env:BASE_URL = "http://localhost:8080"
.\scripts\smoke.ps1
```

---

## Zatrzymanie środowiska

```bash
docker compose down          # zatrzymaj kontenery
docker compose down -v       # zatrzymaj i usuń wolumeny (baza + modele Ollama)
```
