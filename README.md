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

Schemat bazy danych jest na wczesnym etapie i będzie rozwijany podczas prac backendowych.

## Struktura Projektu

```text
docker/
  db/
  nginx/
  php/
public/
  styles/
  views/
    partials/
src/
  controllers/
index.php
Routing.php
docker-compose.yaml
.env.example
```

Lokalne pliki planowania znajdują się w `local/`. Ten katalog jest ignorowany przez Git.

## Aktualne Route'y

- `/` - startowy widok aplikacji
- `/login` - widok logowania
- `/dashboard` - startowy route dashboard/index

Route'y będą aktualizowane podczas implementacji widoków frontendowych.

## Zasady Developmentu

- Nie używamy frameworków PHP.
- Nie używamy frameworków frontendowych ani gotowych szablonów.
- PHP piszemy obiektowo.
- Nazwy techniczne zapisujemy po angielsku.
- Teksty widoczne w UI zapisujemy po polsku.
- Lokalne dane dostępowe trzymamy w `.env`.
- Przy dodaniu nowych zmiennych środowiskowych aktualizujemy `.env.example`.

## Planowane Widoki Frontendowe

Frontend jest planowany na podstawie projektu w Figmie:

- Strona główna
- Logowanie
- Rejestracja
- Plan posiłków
- Lista przepisów
- Szczegóły przepisu
- Lista zakupów
- O nas
- Zarządzanie użytkownikami dostępne tylko dla właściciela

Po każdym frontendowym issue dodajemy plik kontraktu backendowego w:

```text
local/contracts/frontend/
```

Te kontrakty zostaną później użyte do zaplanowania backendu.

## Testy

Testy nie są jeszcze skonfigurowane.

Planowane sprawdzenia:

- testy PHPUnit dla wybranych usług/repozytoriów
- proste testy integracyjne endpointów
- ręczny scenariusz testowy obejmujący logowanie, role, CRUD, 401/403, widoki i wyzwalacze bazy danych

## Dokumentacja Do Uzupełnienia

- diagram ERD
- diagram architektury
- screeny wersji webowej i mobilnej
- pełny scenariusz testowy
- finalna checklista ukończenia projektu
