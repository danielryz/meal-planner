# Checklist funkcji i Security Bingo

## Funkcje aplikacji

### Uwierzytelnianie i konta

- [x] Rejestracja z walidacją e-mail i hasła
- [x] Logowanie (e-mail + hasło)
- [x] Weryfikacja e-maila po rejestracji (token SHA-256, link aktywacyjny)
- [x] Reset hasła (token SHA-256, link w e-mailu, wygasanie)
- [x] Logowanie przez Google OAuth 2.0
- [x] Logowanie przez Apple Sign In
- [x] Zaproszenie pracownika — e-mail z tokenem (TTL 7 dni), formularz tworzenia konta
- [x] Zmiana adresu e-mail — potwierdzenie na nowy adres przed zapisem
- [x] Zmiana hasła z weryfikacją obecnego
- [x] Wylogowanie

### Role i uprawnienia

- [x] Role: `owner`, `employee`, `user`
- [x] Kontrola dostępu w kontrolerach (wymaganie roli)
- [x] Właściciel: zmiana roli i statusu użytkownika
- [x] Pracownik: recenzja przepisów (zatwierdzenie / odrzucenie / prośba o poprawki)

### Przepisy

- [x] Biblioteka przepisów z paginacją
- [x] Wyszukiwanie pełnotekstowe i filtry (kategoria, dieta, alergen, trudność)
- [x] Szczegóły przepisu — składniki, kroki, wartości odżywcze, zdjęcie hero, wideo
- [x] Skalowanie składników, cen i wartości odżywczych przy zmianie liczby porcji
- [x] Tworzenie przepisu (szkic) z poziomu użytkownika
- [x] Edycja szkicu
- [x] Proces weryfikacji: szkic → recenzja → opublikowany / zmiany wymagane / odrzucony
- [x] Zdjęcie przepisu (upload, podgląd)
- [x] Wideo przepisu (URL YouTube/Vimeo lub upload pliku)
- [x] Ocenianie przepisów (1–5 gwiazdek) z widokiem średniej
- [x] Dodawanie do ulubionych
- [x] Przepisy powiązane ("Może Ci się spodobać") ze zdjęciami
- [x] Wartości odżywcze (kalorie, białko, tłuszcze, węglowodany, błonnik) w formularzu i widoku
- [x] Dane demo — 100+ przepisów ze zdjęciami Unsplash i 3 filmami YouTube

### Planer posiłków

- [x] Kreator tygodniowego planu (wybór dni, pór posiłków, budżetu, diety, alergii)
- [x] Automatyczne generowanie przepisów do slotów
- [x] Widok tygodniowy — nawigacja ‹ › między tygodniami
- [x] Ręczna zamiana przepisu w slocie (picker z wyszukiwaniem)
- [x] Regeneracja przepisów dla istniejącego planu
- [x] Dodawanie przepisu do planu z widoku szczegółów przepisu

### Lista zakupów

- [x] Generowanie listy z aktywnego planu posiłków
- [x] Ręczne dodawanie i usuwanie pozycji
- [x] Szacowanie cen składników przez AI (Ollama)
- [x] Oznaczanie pozycji jako kupionej
- [x] Dodawanie pojedynczego składnika z widoku przepisu

### AI

- [x] Pływający widget czatu (minimalizowany, persistentna historia w sessionStorage)
- [x] Tool calling: wyszukiwanie przepisów, dodawanie do listy zakupów, tworzenie szkiców
- [x] Rozgrzewanie modelu przed pierwszym zapytaniem (`/api/ai/warmup`)

### Ustawienia i profil

- [x] Edycja nazwy wyświetlanej i nazwy użytkownika
- [x] Zmiana e-maila z weryfikacją przez link
- [x] Zmiana hasła
- [x] Zdjęcie profilowe (upload, usuwanie)
- [x] Preferencje powiadomień
- [x] Preferencje dietetyczne i alergie

### Zarządzanie użytkownikami (owner)

- [x] Lista wszystkich użytkowników z rolą i statusem
- [x] Zmiana roli użytkownika
- [x] Włączanie / wyłączanie konta
- [x] Wysyłanie zaproszeń pracownicze

### Panel administracyjny

- [x] Osobna sekcja `/admin-panel` z własnym logowaniem
- [x] Statystyki (użytkownicy, przepisy, aktywność)
- [x] Zarządzanie użytkownikami
- [x] Kolejka recenzji przepisów

### Płatności

- [x] Integracja z Paynow v3 (oficjalny PHP SDK)
- [x] Tworzenie płatności — zwrot `redirectUrl` do bramki
- [x] Webhook z weryfikacją podpisu HMAC-SHA256
- [x] Tryb sandbox (testowy) i produkcyjny

---

## Security Bingo

| # | Mechanizm | Status | Szczegóły |
|---|-----------|:------:|-----------|
| 1 | **Hasła hashowane bcrypt** | ✅ | `password_hash()` + `password_verify()` |
| 2 | **SQL Injection — prepared statements** | ✅ | PDO, wyłącznie bindValue/bindParam, zero string concat w SQL |
| 3 | **XSS — escapowanie wyjścia** | ✅ | `escapeHtml()` w JS, `htmlspecialchars()` w PHP, brak innerHTML z danymi użytkownika |
| 4 | **Sesje — SameSite + HttpOnly** | ✅ | Ciasteczko sesji PHP z `SameSite=Lax`, `HttpOnly` |
| 5 | **Tokeny e-mailowe hashowane SHA-256** | ✅ | Raw token wysyłany użytkownikowi, w bazie tylko hash; jednorazowe użycie |
| 6 | **Tokeny z wygasaniem (TTL)** | ✅ | Reset hasła, aktywacja, zaproszenie — `expires_at` w bazie |
| 7 | **Kontrola dostępu (RBAC)** | ✅ | `requireLogin()` / `requireRole()` w każdym kontrolerze wymagającym uprawnień |
| 8 | **Walidacja plików (MIME + rozmiar)** | ✅ | Backend: `finfo_file()` sprawdza MIME, limit rozmiaru; frontend: walidacja przed uploadem |
| 9 | **Weryfikacja podpisu webhooka Paynow** | ✅ | HMAC-SHA256 z `PAYNOW_SIGNATURE_KEY` przed przetworzeniem zdarzenia |
| 10 | **Dane wrażliwe wyłącznie w `.env`** | ✅ | `.env` w `.gitignore`; `.env.example` bez wartości |
| 11 | **Tokeny OAuth — state parameter** | ✅ | Generowany `state` zapisywany w sesji, weryfikowany w callbacku |
| 12 | **Rate limiting (brute-force)** | ❌ | Brak — do zaimplementowania (np. blokada po N nieudanych logowaniach) |
| 13 | **CSRF protection** | ⚠️ | Sesja + SameSite=Lax chroni przed typowym CSRF; brak explicite tokenu CSRF w formularzach |
| 14 | **Content Security Policy** | ❌ | Brak nagłówka CSP — do dodania w konfiguracji Nginx |
| 15 | **HTTPS / HSTS** | ⚠️ | Dev na HTTP; produkcja powinna mieć HTTPS + `Strict-Transport-Security` w Nginx |
| 16 | **Bezpieczne nazwy plików przy uploadzide** | ✅ | Oryginalna nazwa nie jest używana jako ścieżka; pliki zapisywane pod UUID / hash |

### Legenda
- ✅ Zaimplementowane
- ⚠️ Częściowe / wymaga konfiguracji produkcyjnej
- ❌ Brakuje — kandydat do uzupełnienia
