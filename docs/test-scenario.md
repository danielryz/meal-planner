# Scenariusz testów manualnych — MealPlanner

Scenariusz do ręcznej weryfikacji kluczowych przepływów w aplikacji. Uruchamiać po każdym większym wdrożeniu lub przed oddaniem projektu.

## Wymagania wstępne

- Aplikacja uruchomiona przez Docker Compose (`docker compose up -d`)
- Baza danych zainicjalizowana z danymi seed
- Dostęp do `http://localhost`

## Konta demo

| Rola | E-mail | Hasło |
|------|--------|-------|
| Właściciel | owner@mealplanner.test | Demo1234! |
| Pracownik | employee@mealplanner.test | Demo1234! |
| Użytkownik | user@mealplanner.test | Demo1234! |

---

## TC-01 — Rejestracja i logowanie

### TC-01-A: Rejestracja nowego konta

1. Wejdź na `/register`
2. Wypełnij formularz: imię, nowy e-mail, hasło ≥ 8 znaków, zaznacz regulamin
3. Kliknij „Zarejestruj się"

**Oczekiwany wynik:** Przekierowanie do `/dashboard`, widoczne imię użytkownika w nawigacji.

### TC-01-B: Logowanie z poprawnymi danymi

1. Wyloguj się (jeśli zalogowany)
2. Wejdź na `/login`
3. Podaj dane konta demo `user@mealplanner.test` / `Demo1234!`
4. Kliknij „Zaloguj się"

**Oczekiwany wynik:** Przekierowanie do `/dashboard`.

### TC-01-C: Logowanie z błędnymi danymi

1. Na `/login` podaj dowolny e-mail i błędne hasło
2. Kliknij „Zaloguj się"

**Oczekiwany wynik:** Formularz pozostaje na `/login`, komunikat „Nieprawidłowy adres e-mail lub hasło." bez wskazania, które pole jest błędne.

### TC-01-D: Rate limiting

1. Na `/login` podaj błędne dane 5 razy z rzędu
2. Przy 5. próbie lub po niej

**Oczekiwany wynik:** Komunikat o blokadzie na 15 minut, odpowiedź HTTP 429.

### TC-01-E: Wylogowanie

1. Zaloguj się
2. Kliknij „Wyloguj" w menu
3. Spróbuj wejść bezpośrednio na `/dashboard`

**Oczekiwany wynik:** Przekierowanie do `/login`.

---

## TC-02 — Ochrona tras

### TC-02-A: Strona chroniona bez sesji zwraca przekierowanie

1. Wyloguj się
2. Wejdź na `/dashboard` bezpośrednio w przeglądarce

**Oczekiwany wynik:** Przekierowanie 302 do `/login`.

### TC-02-B: Endpoint API bez sesji zwraca 401

1. Wyloguj się
2. W konsoli przeglądarki: `fetch('/api/my-recipes').then(r => console.log(r.status))`

**Oczekiwany wynik:** Status 401, odpowiedź JSON `{"error":"Wymagane logowanie."}`.

### TC-02-C: Pracownik nie ma dostępu do zarządzania użytkownikami

1. Zaloguj się jako `employee@mealplanner.test`
2. Wywołaj w konsoli: `fetch('/api/users').then(r => console.log(r.status))`

**Oczekiwany wynik:** Status 403, odpowiedź JSON `{"error":"Brak uprawnień."}`.

---

## TC-03 — Przepisy

### TC-03-A: Tworzenie szkicu przepisu

1. Zaloguj się jako `user@mealplanner.test`
2. Wejdź na `/recipes/add`
3. Wypełnij tytuł (min 3 znaki), opis (min 20 znaków), dodaj składniki i kroki
4. Kliknij „Zapisz jako szkic"

**Oczekiwany wynik:** Status 201, przepis widoczny na liście „Moje przepisy" ze statusem „Szkic".

### TC-03-B: Zgłoszenie przepisu do recenzji

1. Na liście „Moje przepisy" znajdź szkic z TC-03-A
2. Kliknij „Zgłoś do recenzji"

**Oczekiwany wynik:** Status przepisu zmienia się na „Oczekuje na recenzję".

### TC-03-C: Zatwierdzenie przepisu przez pracownika

1. Zaloguj się jako `employee@mealplanner.test`
2. Wejdź na `/recipes/reviews`
3. Znajdź zgłoszony przepis
4. Kliknij „Zatwierdź"

**Oczekiwany wynik:** Przepis znika z kolejki recenzji. Po ponownym zalogowaniu na konto użytkownika status przepisu to „Zatwierdzony".

### TC-03-D: Odrzucenie przepisu z powodem

1. Zaloguj się jako `employee@mealplanner.test`
2. Na `/recipes/reviews` kliknij „Odrzuć" przy dowolnym przepisie
3. Wpisz powód odrzucenia

**Oczekiwany wynik:** Przepis znika z kolejki. Użytkownik-autor widzi status „Odrzucony".

### TC-03-E: Usunięcie szkicu

1. Zaloguj się jako `user@mealplanner.test`
2. Na liście „Moje przepisy" kliknij „Usuń" przy szkicu

**Oczekiwany wynik:** Przepis znika z listy, status odpowiedzi 200.

---

## TC-04 — Planer posiłków

### TC-04-A: Tworzenie planu tygodniowego

1. Zaloguj się jako `user@mealplanner.test`
2. Wejdź na `/meal-planner`
3. Przejdź przez wizard: wybierz dni (np. poniedziałek, środa, piątek), typy posiłków (np. śniadanie, obiad)
4. Na ostatnim kroku kliknij „Zapisz plan"

**Oczekiwany wynik:** Pojawia się komunikat sukcesu z nazwą planu w formacie „Tydzień od DD.MM.YYYY".

### TC-04-B: Tworzenie planu dla już istniejącego tygodnia

1. Powtórz TC-04-A bez zmiany tygodnia

**Oczekiwany wynik:** Status 409, komunikat o konflikcie — dla tego tygodnia plan już istnieje.

---

## TC-05 — Lista zakupów

### TC-05-A: Wyświetlanie aktywnej listy

1. Zaloguj się jako `user@mealplanner.test`
2. Wejdź na `/grocery-list`

**Oczekiwany wynik:** Widoczna lista zakupów pogrupowana według kategorii. Jeśli pierwsza wizyta — lista zostaje automatycznie utworzona.

### TC-05-B: Dodanie pozycji do listy

1. Na stronie listy zakupów kliknij „Dodaj produkt"
2. Wpisz nazwę (min 2 znaki) i opcjonalnie ilość, kategorię
3. Zatwierdź

**Oczekiwany wynik:** Nowa pozycja pojawia się na liście we właściwej kategorii.

### TC-05-C: Oznaczenie produktu jako kupiony

1. Na liście zakupów kliknij checkbox przy dowolnej pozycji

**Oczekiwany wynik:** Checkbox natychmiast się zaznacza (optymistyczna aktualizacja UI). Odświeżenie strony zachowuje stan.

### TC-05-D: Usunięcie pozycji

1. Przy dowolnej pozycji kliknij przycisk usuwania

**Oczekiwany wynik:** Pozycja znika z listy, status odpowiedzi 200.

---

## TC-06 — Walidacja i błędy

### TC-06-A: Zbyt krótki tytuł przepisu

1. Spróbuj zapisać przepis z tytułem krótszym niż 3 znaki

**Oczekiwany wynik:** Status 400, pole tytułu z komunikatem błędu.

### TC-06-B: Nieznaleziony zasób

1. W przeglądarce wejdź na `/api/recipes/999999`

**Oczekiwany wynik:** Status 404, odpowiedź JSON `{"error":"Nie znaleziono przepisu."}` lub podobna.

### TC-06-C: Nieprawidłowy token CSRF

1. Otwórz formularz logowania
2. Poczekaj, aż sesja wygaśnie (lub ręcznie wyczyść ciasteczko)
3. Spróbuj zalogować się

**Oczekiwany wynik:** Status 400, komunikat „Sesja formularza wygasła. Spróbuj ponownie."

---

## TC-07 — Zarządzanie użytkownikami (właściciel)

### TC-07-A: Lista użytkowników

1. Zaloguj się jako `owner@mealplanner.test`
2. Wejdź na `/users`

**Oczekiwany wynik:** Widoczna lista wszystkich użytkowników z ich rolami.

### TC-07-B: Zmiana roli użytkownika

1. Na stronie `/users` wybierz dowolnego użytkownika
2. Zmień rolę i zapisz

**Oczekiwany wynik:** Rola zaktualizowana, zmiana widoczna na liście.

---

## TC-08 — Smoke test (skrypt)

Uruchom jeden z poniższych skryptów, żeby sprawdzić podstawowe endpointy:

```bash
# Linux/macOS
bash scripts/smoke.sh

# Windows PowerShell
.\scripts\smoke.ps1
```

**Oczekiwany wynik:** Wszystkie sprawdzenia oznaczone jako `OK`. Żaden endpoint nie zwraca nieoczekiwanego statusu.
