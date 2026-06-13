# MealPlanner

Aplikacja webowa do planowania posiłków, zarządzania przepisami i listą zakupów. Zbudowana bez frameworków — PHP obiektowy i vanilla JS.

→ **[Instrukcja uruchomienia](docs/SETUP.md)** · **[Architektura](docs/ARCHITECTURE.md)** · **[Funkcje i Security](docs/CHECKLIST.md)**

---

## Funkcje

| Obszar | Co robi |
|--------|---------|
| **Przepisy** | Biblioteka z wyszukiwaniem, filtrami, tagami dietetycznymi. Składniki ze skalowaniem porcji i cenami. Zdjęcia, wideo, oceny, ulubione. Weryfikacja przed publikacją (szkic → recenzja → opublikowany). |
| **Planer posiłków** | Kreator tygodniowych planów. Automatyczne dopasowanie przepisów do budżetu i diety. Ręczna zamiana slotów, nawigacja między tygodniami, regeneracja. |
| **Lista zakupów** | Generowanie z planu lub ręczne. Szacowanie cen przez AI (Ollama). Oznaczanie kupionych pozycji. |
| **Czat AI** | Pływający widget z asystentem (lokalny LLM — Ollama). Tool calling: wyszukiwanie, dodawanie do listy, tworzenie szkiców. |
| **Konta i role** | Rejestracja, logowanie, e-mail weryfikacja, reset hasła. Google OAuth i Apple Sign In. Role: `owner`, `employee`, `user`. Zapraszanie pracowników. |
| **Ustawienia** | Zmiana nazwy, loginu, e-maila (z potwierdzeniem), hasła, preferencji i zdjęcia profilowego. |
| **Zarządzanie** | Panel właściciela: lista użytkowników, role, statusy, zaproszenia. Panel admina ze statystykami i kolejką recenzji. |
| **Płatności** | Integracja Paynow v3 (PHP SDK). Tworzenie płatności, webhook z weryfikacją podpisu HMAC. |

---

## Tech stack

| Warstwa | Technologie |
|---------|-------------|
| Backend | PHP 8.3, bez frameworka, `declare(strict_types=1)` |
| Frontend | HTML5, CSS3, JavaScript ES2022+, bez frameworka |
| Baza danych | PostgreSQL 16 |
| Serwer HTTP | Nginx |
| Konteneryzacja | Docker, Docker Compose |
| E-mail (dev) | Mailpit |
| E-mail (prod) | PHPMailer + dowolny SMTP (Resend, Mailgun, Brevo) |
| AI | Ollama (lokalny LLM, domyślnie `qwen2.5:14b`) |
| Płatności | Paynow PHP SDK (v3 API) |
| OAuth | Google OAuth 2.0, Apple Sign In |
| Testy | PHPUnit (jednostkowe), smoke scripts (bash / PowerShell) |

---

## Szybki start

```bash
cp .env.example .env          # uzupełnij wartości
docker compose up --build -d
docker compose exec ollama ollama pull qwen2.5:14b
docker compose exec php php scripts/run-migrations.php
docker compose exec php php scripts/seed.php
```

Aplikacja: **http://localhost:8080** · Mailpit: **http://localhost:8025**

Konta demo: `owner@mealplanner.test` / `employee@mealplanner.test` / `user@mealplanner.test` — hasło `Demo1234!`

Pełna instrukcja → [docs/SETUP.md](docs/SETUP.md)

---

## Zasady developmentu

- Bez frameworków PHP i frontendowych.
- PHP obiektowy, `declare(strict_types=1)` w każdym pliku.
- Nazwy techniczne (kod, pliki, branche, commity) po angielsku.
- Teksty widoczne w UI po polsku.
- Dane dostępowe wyłącznie w `.env` — nie commitować; nowe zmienne dodawać do `.env.example`.

---

## API — główne endpointy

Endpointy `/api/*` wymagają sesji PHP (brak → 401).

### Przepisy

| Metoda | URL | Opis |
|--------|-----|------|
| GET | `/api/recipes` | Lista (filtry: q, diet, allergy, category, status) |
| GET | `/api/recipes/{id}` | Szczegóły |
| POST | `/api/recipes/drafts` | Utwórz szkic |
| DELETE | `/api/recipes/{id}` | Usuń szkic |
| POST | `/api/recipes/{id}/submit-for-review` | Wyślij do weryfikacji |
| POST | `/api/recipes/{id}/favorite` | Dodaj/usuń z ulubionych |
| POST | `/api/recipes/{id}/rating` | Oceń (1–5) |
| GET | `/api/my-recipes` | Własne przepisy autora |

### Recenzje (owner / employee)

| Metoda | URL | Opis |
|--------|-----|------|
| GET | `/api/recipe-reviews` | Kolejka |
| POST | `/api/recipe-reviews/{id}/approve` | Zatwierdź |
| POST | `/api/recipe-reviews/{id}/request-changes` | Poproś o poprawki |
| POST | `/api/recipe-reviews/{id}/reject` | Odrzuć |

### Planer posiłków

| Metoda | URL | Opis |
|--------|-----|------|
| GET | `/api/meal-plans` | Lista planów |
| POST | `/api/meal-plans` | Utwórz plan |
| GET | `/api/meal-plans/{id}` | Szczegóły ze slotami |
| POST | `/api/meal-plans/{id}/generate` | Regeneruj przepisy |
| POST | `/api/meal-plans/{id}/slots/{slotId}/recipes` | Dodaj przepis do slotu |
| DELETE | `/api/meal-plans/{id}/slots/{slotId}/recipes/{recipeId}` | Usuń z slotu |

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
| GET | `/api/grocery-lists` | Aktywna lista |
| POST | `/api/grocery-lists/{id}/items` | Dodaj produkt |
| PATCH | `/api/grocery-lists/{id}/items/{itemId}` | Aktualizuj |
| DELETE | `/api/grocery-lists/{id}/items/{itemId}` | Usuń |
| POST | `/api/grocery-lists/generate` | Wygeneruj z planu |

### Użytkownicy (owner)

| Metoda | URL | Opis |
|--------|-----|------|
| GET | `/api/users` | Lista |
| POST | `/api/users/invitations` | Wyślij zaproszenie |
| PATCH | `/api/users/{id}/role` | Zmień rolę |
| PATCH | `/api/users/{id}/status` | Zmień status |

### Profil i ustawienia

| Metoda | URL | Opis |
|--------|-----|------|
| GET | `/api/profile` | Profil zalogowanego |
| PATCH | `/api/settings/profile` | Zmień nazwę i login |
| POST | `/api/settings/change-email` | Zainicjuj zmianę e-maila |
| POST | `/api/settings/password-change` | Zmień hasło |

### Uwierzytelnianie

| Metoda | URL | Opis |
|--------|-----|------|
| POST | `/api/auth/login` | Logowanie |
| POST | `/api/auth/register` | Rejestracja |
| GET | `/auth/google` | Google OAuth — redirect |
| GET | `/auth/google/callback` | Google OAuth — callback |
| GET | `/invitation/{token}` | Strona zaproszenia |
| POST | `/api/invitation/{token}/accept` | Utwórz konto z zaproszenia |
| GET | `/confirm-email-change` | Potwierdź zmianę e-maila |

### Czat AI

| Metoda | URL | Opis |
|--------|-----|------|
| POST | `/api/ai/chat` | Wyślij wiadomość |
| POST | `/api/ai/warmup` | Rozgrzej model |

### Płatności

| Metoda | URL | Opis |
|--------|-----|------|
| POST | `/api/payments/create` | Utwórz płatność → `redirectUrl` |
| POST | `/api/payments/notify` | Webhook Paynow |

### Media

| Metoda | URL | Opis |
|--------|-----|------|
| POST | `/api/media/avatars` | Zdjęcie profilowe |
| DELETE | `/api/media/avatars/current` | Usuń zdjęcie profilowe |
| POST | `/api/media/recipe-photos` | Zdjęcie przepisu |
| POST | `/api/media/recipe-videos` | Wideo przepisu |
