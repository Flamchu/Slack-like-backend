# Zadanie

Tvoje úlohou je vytvoriť backend pre chatovú aplikáciu Slack. To znamená, že vytvoríš API a CMS.

Primárnou API prioritou je existujúci register firemnej komunity, ktorý dokáže:

-   keď používateľ pristúpi na stránku,
-   zaregistrovať sa,
-   prihlásiť sa,
-   a pripojiť sa k existujúcemu tímu (alebo vytvoriť nový tím).

Používateľovi CMS umožníme, aby spravoval tieto firemné komunity, ktoré môžu byť propagované na partnerskej stránke CMS. Do budúcnosti je potrebné pripraviť rôzne politiky, pravidlá pre existujúce projekty, hodnotiť správcov, členov, určovať výkon jednotlivcov a merať angažovanosť.

---

# Pointa aplikácie

-   Používateľ sa môže registrovať, prihlásiť a odhlásiť.
-   Pri registrácii (vyplnení sa vytvárajú osobné používateľské údaje iba 1×).
-   S týmito údajmi používateľ môže byť autentifikovaný.

## V kontexte aplikácie musí používateľ mať tím:

-   Môže vytvoriť nový tím (nastaví parametre tímu: názov, slug, stavový obrázok).
-   Musí byť umožnené používateľa do tímu pozvať.
-   Následne sa bude vedieť prihlásiť do svojho účtu, kde bude mať k dispozícii CMS Settings:
    -   Admin nastavuje administrátorov tímu.
    -   Logy budú obsahovať záznam o činnosti.
    -   Administrátori musia mať možnosť exportu logov, aby sa udržovala dostupnosť prehľadu.

---

# Budeš mať dvoch autorov

-   Aplikáciu bude využívať **bežný používateľ** (prihlásenie a chatovanie, spravovanie, mazanie, atď.).
-   **Admin** bude mať rozšírené práva.

---

# Autentifikácia

Používatelia sa musia autentifikovať pomocou **prístupového tokenu a loginu**.

-   Využi JWT (JSON Web Token).
-   Token bude mať **expiráciu – stanovený životný cyklus**.
-   Autentifikácia sa bude kontrolovať na každom requeste, či už pri route alebo Middleware.

## Heslá

-   Heslá musia byť bezpečne uložené (hashovanie pomocou bcrypt/argon).
-   Heslá sa neukladajú v plaintexte.

---

# Poznámky

-   Použi REST architektúru a JSON.
-   Správaj sa podľa princípov API a CRUD.
-   Sústredi sa na najefektívnejšie a najlogickejšie spustiteľné riešenia.

---

# Záver

Cieľom je implementovať základnú logiku autentifikácie a autorizácie. Taktiež samotnú funkčnosť práce s používateľmi a tímami. Do budúcnosti sa predpokladá aj možnosť pridania notifikácií a integrácie s externými API.

Tvoj backend musí byť rozšíriteľný, aby si doň vedel doplniť ďalšie moduly, ako napr. filtrovacie mechanizmy, metriky, predplatné, role a ďalšie funkcie.
