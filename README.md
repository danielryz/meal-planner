# MealPlanner

MealPlanner to aplikacja webowa do planowania posiłków, zarządzania przepisami i listą zakupów, zbudowana bez frameworków — PHP obiektowy i vanilla JS po stronie frontendu.

## Funkcje

- **Przepisy** — biblioteka z wyszukiwaniem, filtrowaniem, tagami dietetycznymi i alergenami. Składniki z szacowanymi cenami, kroki przygotowania, zdjęcia, wideo, oceny. Przepisy przechodzą przez proces weryfikacji (szkic → recenzja → opublikowany).
- **Planer posiłków** — kreator tygodniowych planów żywieniowych. Automatyczne losowanie przepisów z uwzględnieniem budżetu, preferencji dietetycznych (wegańska, wegetariańska, bezglutenowa itp.) i alergii. Ręczna zamiana przepisów w dowolnym slocie. Obsługa planów na bieżący i przyszłe tygodnie.
- **Lista zakupów** — generowanie z planu lub ręczne dodawanie produktów. Szacowanie cen przez AI. Oznaczanie zakupionych pozycji.
- **Czat AI** — pływający widget z asystentem opartym na Ollama (lokalny LLM). Tool calling: wyszukiwanie przepisów, dodawanie produktów do listy, tworzenie szkiców przepisów. Historia konwersacji w sessionStorage.
- **Konta i role** — rejestracja, logowanie, aktywacja e-mailem, reset hasła, Google OAuth i Apple OAuth. Role: `owner`, `employee`, `user`. Zapraszanie pracowników przez e-mail (token z terminem ważności 7 dni).
- **Ustawienia konta** — zmiana nazwy, loginu, e-maila (wymaga potwierdzenia na nowy adres), hasła, preferencji powiadomień i diety. Zdjęcie profilowe.
- **Zarządzanie użytkownikami** — panel właściciela: lista użytkowników, zmiana roli i statusu, zapraszanie pracowników.
- **Panel administracyjny** — oddzielna sekcja `/admin-panel` z własnym logowaniem, statystykami, zarządzaniem użytkownikami i kolejką recenzji.
- **Płatności** — integracja z Paynow v3 (paynow PHP SDK). Tworzenie płatności i webhook z weryfikacją podpisu.

## Tech stack

| Warstwa | Technologie |
|---------|-------------|
| Backend | PHP 8.2, bez frameworka, `declare(strict_types=1)` |
| Frontend | HTML5, CSS3, JavaScript ES2022+, bez frameworka |
| Baza danych | PostgreSQL 16 |
| Serwer HTTP | Nginx |
| Konteneryzacja | Docker, Docker Compose |
| E-mail (dev) | Mailpit — lokalna pułapka SMTP |
| E-mail (prod) | PHPMailer — dowolny SMTP (Mailgun, Resend, Brevo) |
| AI | Ollama (lokalny LLM, domyślnie `qwen2.5:14b`) |
| Płatności | Paynow PHP SDK (v3 API) |
| OAuth | Google OAuth 2.0, Apple Sign In |
| Testy | PHPUnit (jednostkowe), skrypty smoke (bash/PowerShell) |

## Wymagania

- Docker i Docker Compose
- Git
- Dostęp do internetu przy pierwszym pobraniu obrazu Ollama i modelu

## Szybki start

### 1. Sklonuj repozytorium

```bash
git clone <adres-repozytorium>
cd MealPlanner
```

### 2. Utwórz plik `.env`

Linux/macOS:
```bash
cp .env.example .env
```

Windows PowerShell:
```powershell
Copy-Item .env.example .env
```

### 3. Uruchom kontenery

```bash
docker compose up --build -d
```

### 4. Pobierz model AI (pierwsze uruchomienie)

```bash
docker compose exec ollama ollama pull qwen2.5:14b
```

Jeśli ustawisz inny model w `.env`, pobierz tę samą nazwę:
```bash
docker compose exec ollama ollama pull <nazwa-modelu>
```

### 5. Uruchom migracje i dane demo

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

## Porty lokalne

| Usługa | Adres |
|--------|-------|
| Aplikacja | `http://localhost:8080` |
| Mailpit (podgląd e-maili) | `http://localhost:8025` |
| pgAdmin | `http://localhost:5050` |
| Ollama API | `http://localhost:11434` |
| PostgreSQL | `localhost:5433` |

## Zmienne środowiskowe

Klucze trzymamy w `.env` — nie commitować. Plik `.env.example` zawiera puste szablony.

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

# Apple OAuth
APPLE_CLIENT_ID=
APPLE_TEAM_ID=
APPLE_KEY_ID=
APPLE_PRIVATE_KEY=                     # zawartość pliku .p8 (z \n zamiast nowych linii)
APPLE_REDIRECT_URI=http://localhost:8080/auth/apple/callback
```

## Konfiguracja e-maila

### Środowisko deweloperskie — Mailpit

Mailpit uruchamia się automatycznie w Docker Compose i przechwytuje wszystkie wysyłane e-maile. Podgląd: `http://localhost:8025`.

Wymagana konfiguracja `.env`:
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

## Konfiguracja Google OAuth

1. Utwórz projekt w [Google Cloud Console](https://console.cloud.google.com/).
2. Utwórz dane uwierzytelniające OAuth 2.0 (typ: aplikacja webowa).
3. Dodaj URI przekierowania: `http://localhost:8080/auth/google/callback` (dev) i domenę produkcyjną.
4. Skopiuj `Client ID` i `Client Secret` do `.env`.

## Konfiguracja Paynow

1. Załóż konto w panelu [Paynow](https://panel.paynow.pl/).
2. Skonfiguruj URL powiadomień (webhook): `https://twojadomena.pl/api/payments/notify`.
3. Skopiuj **API Key** i **Signature Key** do `.env`.
4. Na etapie testów ustaw `PAYNOW_SANDBOX=true`.

## Struktura projektu

```text
docker/
  db/
    migrations/        # migracje SQL (001–016)
    init.sql           # inicjalizacja bazy przy starcie kontenera
  nginx/nginx.conf
  php/Dockerfile
docs/
  erd.md               # diagram ERD (Mermaid)
  schema.sql           # skonsolidowany schemat SQL
  test-scenario.md     # scenariusz testów manualnych
public/
  assets/              # ikony, zdjęcia statyczne
  features/            # widoki i JS per feature
    ai-chat/
    admin-panel/
    auth/              # login, register, invitation
    grocery-list/
    meal-planner/
    profile/           # settings, preferences
    public/            # strony publiczne (index, about, support…)
    recipes/
    users/
  views/               # szablony błędów i misc
scripts/
  run-migrations.php
  seed.php
  smoke.sh             # smoke testy (bash)
  smoke.ps1            # smoke testy (PowerShell)
src/
  Auth/
  Config/
  Controllers/
  Database/
  Entities/
  Http/
  Repositories/
  Services/
  templates/emails/    # szablony HTML e-maili
tests/
  Unit/Http/           # testy PHPUnit (Request, Router, Response)
index.php
Routing.php
docker-compose.yaml
.env.example
```

## API — główne endpointy

Endpointy `api/*` wymagają sesji PHP (brak sesji → 401).

### Przepisy

| Metoda | URL | Opis |
|--------|-----|------|
| GET | `/api/recipes` | Lista przepisów (filtry: q, diet, allergy, category, status) |
| GET | `/api/recipes/{id}` | Szczegóły przepisu |
| POST | `/api/recipes/drafts` | Utwórz szkic |
| DELETE | `/api/recipes/{id}` | Usuń szkic |
| POST | `/api/recipes/{id}/submit-for-review` | Wyślij do weryfikacji |
| POST | `/api/recipes/{id}/favorite` | Dodaj/usuń z ulubionych |
| POST | `/api/recipes/{id}/rating` | Oceń przepis (1–5) |
| GET | `/api/my-recipes` | Własne przepisy autora |

### Recenzje przepisów (owner/employee)

| Metoda | URL | Opis |
|--------|-----|------|
| GET | `/api/recipe-reviews` | Kolejka recenzji |
| POST | `/api/recipe-reviews/{id}/approve` | Zatwierdź |
| POST | `/api/recipe-reviews/{id}/request-changes` | Poproś o poprawki |
| POST | `/api/recipe-reviews/{id}/reject` | Odrzuć |

### Planer posiłków

| Metoda | URL | Opis |
|--------|-----|------|
| GET | `/api/meal-plans` | Lista planów użytkownika |
| POST | `/api/meal-plans` | Utwórz plan (patrz ciało żądania poniżej) |
| GET | `/api/meal-plans/{id}` | Szczegóły planu ze slotami |
| POST | `/api/meal-plans/{id}/generate` | Regeneruj przepisy dla istniejącego planu |
| POST | `/api/meal-plans/{id}/slots/{slotId}/recipes` | Dodaj przepis do slotu |
| DELETE | `/api/meal-plans/{id}/slots/{slotId}/recipes/{recipeId}` | Usuń przepis ze slotu |

Ciało `POST /api/meal-plans`:
```json
{
  "weekStartDate": "2026-06-15",
  "planningDays": ["monday", "tuesday", "wednesday"],
  "mealTypes": ["breakfast", "lunch", "dinner"],
  "weeklyBudget": 300,
  "dietPreference": "standard",
  "allergies": ["gluten", "lactose"],
  "generate": true
}
```

### Lista zakupów

| Metoda | URL | Opis |
|--------|-----|------|
| GET | `/api/grocery-lists` | Aktywna lista zakupów |
| POST | `/api/grocery-lists/{id}/items` | Dodaj produkt |
| PATCH | `/api/grocery-lists/{id}/items/{itemId}` | Aktualizuj produkt |
| DELETE | `/api/grocery-lists/{id}/items/{itemId}` | Usuń produkt |
| POST | `/api/grocery-lists/generate` | Wygeneruj listę z planu posiłków |

### Użytkownicy (owner)

| Metoda | URL | Opis |
|--------|-----|------|
| GET | `/api/users` | Lista użytkowników |
| POST | `/api/users/invitations` | Wyślij zaproszenie pracownika |
| PATCH | `/api/users/{id}/role` | Zmień rolę |
| PATCH | `/api/users/{id}/status` | Zmień status |

### Profil i ustawienia

| Metoda | URL | Opis |
|--------|-----|------|
| GET | `/api/profile` | Profil zalogowanego użytkownika |
| GET | `/api/settings/account` | Dane konta |
| PATCH | `/api/settings/profile` | Zmień nazwę i login |
| POST | `/api/settings/change-email` | Zainicjuj zmianę e-maila |
| POST | `/api/settings/password-change` | Zmień hasło |

### Uwierzytelnianie

| Metoda | URL | Opis |
|--------|-----|------|
| POST | `/api/auth/login` | Logowanie |
| POST | `/api/auth/register` | Rejestracja |
| GET | `/auth/google` | Logowanie przez Google OAuth |
| GET | `/auth/google/callback` | Callback Google OAuth |
| GET | `/invitation/{token}` | Strona przyjęcia zaproszenia |
| POST | `/api/invitation/{token}/accept` | Utwórz konto z zaproszenia |
| GET | `/confirm-email-change` | Potwierdź nowy e-mail (link z e-maila) |

### Czat AI

| Metoda | URL | Opis |
|--------|-----|------|
| POST | `/api/ai/chat` | Wyślij wiadomość do asystenta (Ollama) |
| POST | `/api/ai/warmup` | Rozgrzej model przed pierwszym zapytaniem |

### Płatności

| Metoda | URL | Opis |
|--------|-----|------|
| POST | `/api/payments/create` | Utwórz płatność Paynow — zwraca `redirectUrl` |
| POST | `/api/payments/notify` | Webhook Paynow (weryfikacja podpisu) |

### Media

| Metoda | URL | Opis |
|--------|-----|------|
| POST | `/api/media/avatars` | Prześlij zdjęcie profilowe |
| DELETE | `/api/media/avatars/current` | Usuń zdjęcie profilowe |
| POST | `/api/media/recipe-photos` | Prześlij zdjęcie przepisu |
| POST | `/api/media/recipe-videos` | Prześlij wideo przepisu |

## Baza danych

PostgreSQL inicjalizowany z `docker/db/init.sql`. Migracje w `docker/db/migrations/` stosowane automatycznie przy starcie kontenera DB.

Ręczne uruchomienie migracji:
```bash
docker compose exec php php scripts/run-migrations.php
```

Pełny schemat: `docs/schema.sql`.

### Połączenie w pgAdmin

1. Otwórz `http://localhost:5050`.
2. Zaloguj się: `PGADMIN_DEFAULT_EMAIL` / `PGADMIN_DEFAULT_PASSWORD` z `.env`.
3. Dodaj serwer: host `db`, port `5432`, baza/user/hasło z `.env`.

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

## Zasady developmentu

- Bez frameworków PHP i frontendowych.
- PHP obiektowy, `declare(strict_types=1)` w każdym pliku.
- Nazwy techniczne (kod, pliki, branche, commity) po angielsku.
- Teksty widoczne w UI po polsku.
- Dane dostępowe wyłącznie w `.env` — nie commitować.
- Nowe zmienne środowiskowe dodawać do `.env.example`.

## Zatrzymanie środowiska

```bash
docker compose down          # zatrzymaj kontenery
docker compose down -v       # zatrzymaj i usuń wolumeny (baza + modele Ollama)
```
