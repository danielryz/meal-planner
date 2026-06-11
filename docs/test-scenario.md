# Scenariusz testów manualnych - MealPlanner

Scenariusz służy do ręcznej weryfikacji głównych przepływów aplikacji po uruchomieniu środowiska lub przed oddaniem projektu.

## Wymagania wstępne

- Aplikacja uruchomiona przez Docker Compose: `docker compose up -d`
- Migracje i seed danych wykonane: `php scripts/run-migrations.php` oraz `php scripts/seed.php`
- Dostęp do aplikacji pod adresem `http://localhost`
- Opcjonalnie dla czatu AI: uruchomiona Ollama oraz skonfigurowany model z tool calling

## Poprawne dane logowania

| Rola | E-mail | Hasło | Główne uprawnienia |
|------|--------|-------|--------------------|
| Właściciel | `owner@mealplanner.test` | `Demo1234!` | dashboard, przepisy, lista zakupów, użytkownicy, recenzje |
| Pracownik | `employee@mealplanner.test` | `Demo1234!` | dashboard, przepisy, lista zakupów, recenzje |
| Użytkownik | `user@mealplanner.test` | `Demo1234!` | dashboard, przepisy, planer, lista zakupów, profil |

---

## TC-01 - Start i nawigacja publiczna

### TC-01-A: Strona główna informuje o czacie AI

1. Wyloguj się.
2. Wejdź na `/`.
3. Sprawdź sekcję główną i listę funkcji.

**Oczekiwany wynik:** Strona informuje, że po zalogowaniu dostępny jest kulinarny asystent AI, który pomaga szukać przepisów i uzupełniać listę zakupów.

### TC-01-B: Ikona czatu AI nie jest widoczna dla gościa

1. Wyloguj się.
2. Wejdź na `/recipes`.
3. Sprawdź prawy dolny róg ekranu na desktopie i mobile.

**Oczekiwany wynik:** Ikona czatu AI nie jest renderowana dla niezalogowanego użytkownika.

### TC-01-C: Przekierowanie zalogowanego użytkownika z `/`

1. Zaloguj się jako `user@mealplanner.test`.
2. Wejdź na `/`.

**Oczekiwany wynik:** Aplikacja przekierowuje na `/dashboard`.

---

## TC-02 - Rejestracja, logowanie i sesja

### TC-02-A: Rejestracja nowego konta

1. Wejdź na `/register`.
2. Wypełnij formularz: imię, unikalny e-mail, hasło co najmniej 8 znaków, zaakceptuj regulamin.
3. Kliknij „Zarejestruj się”.

**Oczekiwany wynik:** Konto zostaje utworzone, użytkownik trafia do aplikacji albo do ekranu potwierdzenia e-mail zgodnie z aktualną konfiguracją.

### TC-02-B: Logowanie z poprawnymi danymi

1. Wyloguj się.
2. Wejdź na `/login`.
3. Zaloguj się jako `user@mealplanner.test` z hasłem `Demo1234!`.

**Oczekiwany wynik:** Przekierowanie do `/dashboard`, w nawigacji widoczny profil użytkownika.

### TC-02-C: Logowanie z błędnymi danymi

1. Na `/login` podaj poprawny lub dowolny e-mail i błędne hasło.
2. Kliknij „Zaloguj się”.

**Oczekiwany wynik:** Formularz pozostaje na `/login`, widoczny jest ogólny komunikat „Nieprawidłowy adres e-mail lub hasło.”.

### TC-02-D: Wylogowanie

1. Zaloguj się.
2. Kliknij „Wyloguj”.
3. Wejdź bezpośrednio na `/dashboard`.

**Oczekiwany wynik:** Sesja jest zakończona, a wejście na stronę chronioną przekierowuje do `/login`.

---

## TC-03 - Ochrona tras i role

### TC-03-A: Strona chroniona bez sesji

1. Wyloguj się.
2. Wejdź na `/dashboard`.

**Oczekiwany wynik:** Przekierowanie 302 do `/login`.

### TC-03-B: API bez sesji

1. Wyloguj się.
2. W konsoli przeglądarki uruchom: `fetch('/api/my-recipes').then(r => console.log(r.status))`.

**Oczekiwany wynik:** Status 401 i odpowiedź JSON z komunikatem o wymaganym logowaniu.

### TC-03-C: Pracownik bez dostępu do zarządzania użytkownikami

1. Zaloguj się jako `employee@mealplanner.test`.
2. Wejdź na `/users` albo wywołaj `fetch('/api/users').then(r => console.log(r.status))`.

**Oczekiwany wynik:** Brak dostępu do zarządzania użytkownikami, status 403 dla API.

---

## TC-04 - Przepisy

### TC-04-A: Biblioteka przepisów z seeda

1. Zaloguj się jako `user@mealplanner.test`.
2. Wejdź na `/recipes`.
3. Użyj wyszukiwarki i filtrów: czas do 30 min, dieta wegetariańska, trudność „Zaawansowany”.

**Oczekiwany wynik:** Seed zawiera różne przepisy publiczne, kategorie, czasy i poziomy trudności. Filtry zawężają listę bez błędów.

### TC-04-B: Szczegóły przepisu

1. Na `/recipes` otwórz dowolny przepis.
2. Sprawdź składniki, kroki, wartości odżywcze i akcje użytkownika.

**Oczekiwany wynik:** Szczegóły przepisu są czytelne, a dane pochodzą z bazy.

### TC-04-C: Tworzenie szkicu przepisu

1. Wejdź na `/add-recipe`.
2. Wypełnij tytuł, opis, kategorię, trudność, czas, porcje, składniki i kroki.
3. Kliknij zapis szkicu.

**Oczekiwany wynik:** Status 201, przepis widoczny na `/recipe-management` ze statusem „Szkic”.

### TC-04-D: Zgłoszenie przepisu do recenzji

1. Na `/recipe-management` znajdź szkic.
2. Kliknij „Zgłoś do recenzji”.

**Oczekiwany wynik:** Status przepisu zmienia się na „Oczekuje na recenzję”.

### TC-04-E: Recenzja przepisu przez pracownika

1. Zaloguj się jako `employee@mealplanner.test`.
2. Wejdź na `/recipe-reviews`.
3. Zatwierdź, odrzuć albo odeślij do poprawek wybrany przepis.

**Oczekiwany wynik:** Akcja zmienia status przepisu, a pozycja znika z kolejki recenzji.

---

## TC-05 - Planer posiłków

### TC-05-A: Tworzenie planu tygodniowego

1. Zaloguj się jako `user@mealplanner.test`.
2. Wejdź na `/meal-planner`.
3. Wybierz dni, typy posiłków i zapisz plan.

**Oczekiwany wynik:** Plan zostaje zapisany, a dashboard pokazuje aktywny plan.

### TC-05-B: Konflikt planu dla tego samego tygodnia

1. Utwórz plan dla tygodnia.
2. Spróbuj utworzyć kolejny plan dla tego samego tygodnia.

**Oczekiwany wynik:** API zwraca 409, a UI pokazuje komunikat o istniejącym planie.

---

## TC-06 - Lista zakupów

### TC-06-A: Aktywna lista zakupów

1. Zaloguj się jako `user@mealplanner.test`.
2. Wejdź na `/grocery-list`.

**Oczekiwany wynik:** Widoczna jest aktywna lista zakupów, tworzona automatycznie przy pierwszej wizycie.

### TC-06-B: Dodanie produktu ręcznie

1. Kliknij „Dodaj produkt”.
2. Podaj nazwę, ilość i opcjonalną kategorię.
3. Zatwierdź.

**Oczekiwany wynik:** Produkt pojawia się na liście we właściwej kategorii.

### TC-06-C: Oznaczenie i usunięcie produktu

1. Zaznacz produkt jako kupiony.
2. Odśwież stronę.
3. Usuń produkt.

**Oczekiwany wynik:** Stan kupienia utrzymuje się po odświeżeniu, a usunięty produkt znika z listy.

---

## TC-07 - Czat AI

### TC-07-A: Ikona czatu jest widoczna po zalogowaniu

1. Zaloguj się jako `user@mealplanner.test`.
2. Wejdź na `/dashboard`, `/recipes` albo `/grocery-list`.

**Oczekiwany wynik:** W prawym dolnym rogu widoczna jest ikona asystenta AI.

### TC-07-B: Otwarcie i zamknięcie czatu

1. Kliknij ikonę czatu AI.
2. Zamknij panel przyciskiem zamknięcia.
3. Otwórz ponownie i zamknij klawiszem Escape.
4. Na mobile sprawdź backdrop i bottom sheet.

**Oczekiwany wynik:** Panel otwiera się i zamyka poprawnie na desktopie oraz mobile.

### TC-07-C: Wyszukiwanie przepisu przez AI

1. W czacie wpisz: `Znajdź szybki przepis wegetariański do 30 minut`.

**Oczekiwany wynik:** AI używa narzędzia wyszukiwania przepisów albo zwraca tekstową odpowiedź, jeśli model nie obsługuje tool calling. Przy działającym tool calling odpowiedź zawiera propozycje z bazy.

### TC-07-D: Dodanie produktu do listy zakupów przez AI

1. W czacie wpisz: `Dodaj 500 g pomidorów do listy zakupów`.
2. Wejdź na `/grocery-list`.

**Oczekiwany wynik:** Produkt jest dodany do aktywnej listy zakupów, a AI potwierdza wykonanie akcji.

### TC-07-E: Pobranie listy zakupów przez AI

1. W czacie wpisz: `Pokaż moją listę zakupów`.

**Oczekiwany wynik:** AI zwraca aktualną zawartość aktywnej listy zakupów.

### TC-07-F: Utworzenie szkicu przepisu przez AI

1. W czacie wpisz: `Utwórz szkic przepisu na sałatkę z kaszą bulgur dla 2 osób`.
2. Wejdź na `/recipe-management`.

**Oczekiwany wynik:** Nowy szkic przepisu jest widoczny na liście „Moje przepisy”.

---

## TC-08 - Zarządzanie użytkownikami

### TC-08-A: Lista użytkowników

1. Zaloguj się jako `owner@mealplanner.test`.
2. Wejdź na `/users`.

**Oczekiwany wynik:** Widoczna jest lista użytkowników z rolami i statusami.

### TC-08-B: Zmiana roli albo statusu użytkownika

1. Wybierz użytkownika testowego.
2. Zmień rolę lub status zgodnie z dostępnymi akcjami.

**Oczekiwany wynik:** Zmiana jest zapisana i widoczna po odświeżeniu listy.

---

## TC-09 - Walidacja i błędy

### TC-09-A: Niepoprawne dane przepisu

1. Spróbuj zapisać przepis z tytułem krótszym niż 3 znaki albo opisem krótszym niż 20 znaków.

**Oczekiwany wynik:** API zwraca 400, a UI pokazuje komunikat walidacyjny.

### TC-09-B: Nieznaleziony zasób

1. Wejdź na `/api/recipes/999999`.

**Oczekiwany wynik:** Status 404 i odpowiedź JSON z komunikatem o braku przepisu.

### TC-09-C: Nieprawidłowy token CSRF

1. Otwórz formularz logowania.
2. Usuń ciasteczko sesji albo poczekaj na wygaśnięcie sesji.
3. Spróbuj wysłać formularz.

**Oczekiwany wynik:** Status 400 i komunikat o wygaśnięciu sesji formularza.

---

## TC-10 - Smoke test

Uruchom jeden ze skryptów:

```bash
# Linux/macOS
bash scripts/smoke.sh

# Windows PowerShell
.\scripts\smoke.ps1
```

**Oczekiwany wynik:** Wszystkie sprawdzenia kończą się wynikiem `OK`, a endpointy zwracają oczekiwane statusy.
