# MealPlanner

MealPlanner to aplikacja webowa bez frameworków, służąca do planowania posiłków, przeglądania przepisów i zarządzania listą zakupów.

Projekt jest przygotowywany zgodnie z wymaganiami WdPAI i używa:

- PHP bez frameworka backendowego
- HTML5, CSS i JavaScript bez frameworka frontendowego
- PostgreSQL
- Docker i Docker Compose
- nginx

Teksty widoczne w interfejsie użytkownika powinny być po polsku. Nazwy techniczne w kodzie, plikach, branchach, commitach, issue i MR powinny być po angielsku.

## Wymagania

- Docker
- Docker Compose
- Git

## Konfiguracja Środowiska

Utwórz lokalny plik `.env` na podstawie przykładu:

```bash
cp .env.example .env
```

W Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

Domyślne porty lokalne:

- Aplikacja: `http://localhost:8080`
- PostgreSQL: `localhost:5433`
- pgAdmin: `http://localhost:5050`

Domyślne dane logowania do pgAdmin są zapisane w `.env`:

```env
PGADMIN_DEFAULT_EMAIL=admin@example.com
PGADMIN_DEFAULT_PASSWORD=admin
```

Nie commituj pliku `.env`. Do repozytorium trafia tylko `.env.example`.

## Uruchomienie Aplikacji

Uruchom kontenery:

```bash
docker compose up --build
```

Otwórz aplikację:

```text
http://localhost:8080
```

Otwórz pgAdmin:

```text
http://localhost:5050
```

## Korzystanie Z pgAdmin

Zaloguj się do pgAdmin wartościami z `.env`:

```env
PGADMIN_DEFAULT_EMAIL=admin@example.com
PGADMIN_DEFAULT_PASSWORD=admin
```

Dodaj serwer PostgreSQL:

1. Kliknij prawym przyciskiem `Servers`.
2. Wybierz `Register` -> `Server...`.
3. W zakładce `General` wpisz dowolną nazwę, np. `MealPlanner`.
4. W zakładce `Connection` uzupełnij:
   - Host name/address: `db`
   - Port: `5432`
   - Maintenance database: wartość `POSTGRES_DB`, domyślnie `mealplanner`
   - Username: wartość `POSTGRES_USER`, domyślnie `mealplanner`
   - Password: wartość `POSTGRES_PASSWORD`, domyślnie `mealplanner`
5. Zapisz serwer.

Po połączeniu przejdź do:

```text
Servers -> MealPlanner -> Databases -> mealplanner -> Schemas -> public -> Tables
```

Zatrzymanie kontenerów:

```bash
docker compose down
```

## Baza Danych

PostgreSQL jest inicjalizowany z pliku:

```text
docker/db/init.sql
```

Lokalne zmienne bazy danych są skonfigurowane w `.env`:

```env
POSTGRES_DB=mealplanner
POSTGRES_USER=mealplanner
POSTGRES_PASSWORD=mealplanner_dev_password
POSTGRES_PORT=5433
```

Schemat bazy danych jest zarządzany przez migracje w `docker/db/migrations/`.

## Migracje i Dane Testowe

Migracje uruchamiane są automatycznie przy starcie kontenera `db` (przez `docker/db/init.sql`).

Aby załadować dane testowe (demo użytkownicy, przepisy, kategorie):

```bash
docker compose exec php php scripts/run-migrations.php
docker compose exec php php scripts/seed.php
```

Demo konta:

| E-mail | Hasło | Rola |
|---|---|---|
| `owner@example.com` | `password` | owner |
| `employee@example.com` | `password` | employee |
| `user@example.com` | `password` | user |

## Struktura Projektu

```text
docker/
  db/
    migrations/     # pliki SQL migracji (001–007)
  nginx/
  php/
public/
  features/         # widoki i JS per feature
  assets/
scripts/
  run-migrations.php
  seed.php
  smoke.sh          # smoke testy (bash)
  smoke.ps1         # smoke testy (PowerShell)
src/
  Auth/
  Config/
  Controllers/
  Database/
  Entities/
  Http/
  Repositories/
tests/
  Unit/
    Http/           # testy PHPUnit (Request, Router, Response)
  bootstrap.php
composer.json
phpunit.xml
index.php
Routing.php
docker-compose.yaml
.env.example
```

Lokalne pliki planowania znajdują się w `local/`. Ten katalog jest ignorowany przez Git.

## API — Główne Endpointy

Wszystkie endpointy API wymagają zalogowania (sesja PHP). Brak sesji → przekierowanie 302 na `/login`.

| Metoda | URL | Opis |
|---|---|---|
| GET | `/api/recipes` | Lista publicznych przepisów |
| GET | `/api/recipes/{id}` | Szczegóły przepisu |
| POST | `/api/recipes/drafts` | Utwórz szkic przepisu |
| POST | `/api/recipes/{id}/submit-for-review` | Wyślij do weryfikacji |
| GET | `/api/my-recipes` | Własne przepisy autora |
| DELETE | `/api/recipes/{id}` | Usuń szkic |
| GET | `/api/recipe-reviews` | Kolejka recenzji (owner/employee) |
| POST | `/api/recipe-reviews/{id}/approve` | Zatwierdź przepis |
| POST | `/api/recipe-reviews/{id}/request-changes` | Poproś o poprawki |
| POST | `/api/recipe-reviews/{id}/reject` | Odrzuć przepis |
| GET/POST | `/api/meal-plans` | Lista planów / utwórz nowy |
| GET | `/api/meal-plans/{id}` | Szczegóły planu |
| POST | `/api/meal-plans/{id}/slots/{slotId}/recipes` | Dodaj przepis do slotu |
| DELETE | `/api/meal-plans/{id}/slots/{slotId}/recipes/{recipeId}` | Usuń przepis ze slotu |
| GET | `/api/grocery-lists` | Aktywna lista zakupów |
| POST | `/api/grocery-lists/{id}/items` | Dodaj produkt |
| PATCH | `/api/grocery-lists/{id}/items/{itemId}` | Zaktualizuj produkt |
| DELETE | `/api/grocery-lists/{id}/items/{itemId}` | Usuń produkt |
| GET | `/api/users` | Lista użytkowników (owner) |
| GET | `/api/profile` | Profil zalogowanego użytkownika |

## Zasady Developmentu

- Nie używamy frameworków PHP ani frontendowych.
- PHP piszemy obiektowo, `declare(strict_types=1)` w każdym pliku.
- Nazwy techniczne zapisujemy po angielsku.
- Teksty widoczne w UI zapisujemy po polsku.
- Lokalne dane dostępowe trzymamy w `.env`.
- Przy dodaniu nowych zmiennych środowiskowych aktualizujemy `.env.example`.

## Testy

### Testy jednostkowe (PHPUnit)

Testy obejmują klasy HTTP (`Request`, `Router`, `Response`) bez zależności od bazy danych.

Uruchomienie w Docker:

```bash
docker compose exec php composer install
docker compose exec php vendor/bin/phpunit
```

Uruchomienie lokalnie (wymaga PHP z rozszerzeniami `mbstring`, `dom`, `xml`):

```bash
composer install
vendor/bin/phpunit
```

### Smoke testy (curl)

Smoke testy sprawdzają kluczowe endpointy przeciwko działającej instancji aplikacji.

Bash (Linux / Docker):

```bash
BASE_URL=http://localhost:8080 bash scripts/smoke.sh
```

PowerShell (Windows):

```powershell
$env:BASE_URL = "http://localhost:8080"
.\scripts\smoke.ps1
```
