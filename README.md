# Petrik News Workflow

WordPress hírbeküldési és vezetői jóváhagyási rendszer a Petrik weboldalához.

A plugin célja, hogy a hírek publikálása kontrollált, visszakövethető folyamat legyen **fizetős WordPress-bővítmény nélkül**. A meglévő WordPress `post` bejegyzéseket és kategóriákat használja, ezért nem hoz létre külön híradatbázist és nem cseréli le a jelenlegi híroldalt.

## Jelenlegi pilot verzió

A `1.0.1-test` verzió kifejezetten a Petrik jelenlegi környezetéhez készült:

- WordPress 6.8.8;
- PHP 7.4.33;
- Divi 4.27.4;
- éles szerveren végzett, de **nem publikus** funkcionális próba.

A csomagban hard-coded `PNW_TEST_MODE = true` védelem aktív. Emiatt a plugin által kezelt hír **semmilyen jóváhagyási vagy kerülőútvonalon nem tehető publikus, privát vagy időzített állapotba**. A vezetői jóváhagyás helyett egy nem publikus `pnw_test_ok` tesztstátusz kerül rögzítésre.

A tesztmód szándékosan nem kapcsolható ki a WordPress admin felületéről. Élesítéshez külön production build készül majd.

## Mit tud?

- külön frontend **Hírkezelő** a `/hirkezelo/` oldalon;
- bejelentkezés közvetlenül a Hírkezelőn;
- **Munkaközösség-vezető** hírt készít, képet tölt fel, kategóriát választ és beküldi jóváhagyásra;
- az MK-vezető **nem publikálhat**;
- beküldés után a hír zárolt az MK-vezető számára;
- **Igazgatóhelyettes / Igazgató** látja a jóváhagyási sort, előnézetet nyithat, szerkeszthet, jóváhagyhat vagy indoklással visszaküldhet;
- a visszaküldött hír újra szerkeszthető és újraküldhető;
- e-mail értesítés beküldéskor, visszaküldéskor és jóváhagyáskor;
- teljes audit napló;
- MK-vezetőnként korlátozhatók a használható hírkategóriák;
- mobilbarát frontend;
- nincs PublishPress vagy más fizetős függőség.

## Normál workflow

```text
Munkaközösség-vezető
        │
        ▼
    Piszkozat
        │
        ▼
Jóváhagyásra vár
        │
        └──────► Igazgatóhelyettes / Igazgató
                         │
                ┌────────┴────────┐
                ▼                 ▼
           Jóváhagyás        Visszaküldés
                │                 │
                ▼                 ▼
            Publikálva    Javításra visszaküldve
                                  │
                                  └──► javítás + újraküldés
```

## TEST MODE workflow

```text
Piszkozat
   ↓
Jóváhagyásra vár
   ↓
Teszt jóváhagyás
   ↓
pnw_test_ok
   ↓
NEM publikus
```

Visszaküldés/javítás ugyanúgy tesztelhető, mint production módban.

## Követelmények

- WordPress 6.4+;
- **PHP 7.4+**;
- működő WordPress e-mailküldés (`wp_mail`) az értesítésekhez;
- a publikus hírekhez a szabványos WordPress `post` bejegyzéstípus használata.

## Telepítés

### ZIP-ből

```bash
./scripts/build-zip.sh
```

Ez létrehozza:

```text
dist/petrik-news-workflow.zip
```

WordPressben: **Bővítmények → Új bővítmény → Bővítmény feltöltése**, majd aktiválás.

### Forrásból

A repository tartalma kerüljön ide:

```text
wp-content/plugins/petrik-news-workflow/
```

Ezután aktiváld a **Petrik News Workflow** plugint.

## Mi történik aktiváláskor?

1. Létrejönnek a Petrik szerepkörök.
2. Létrejön az audit napló adatbázistáblája.
3. Regisztrálódnak a workflow státuszok.
4. Létrejön a **Hírkezelő** WordPress oldal a `[petrik_news_manager]` shortcode-dal.
5. Az alapértelmezett URL: `/hirkezelo/`.

A meglévő hírekhez, kategóriákhoz, Divi sablonhoz és korábbi bejegyzésekhez a plugin nem nyúl.

## Szerepkörök és jogosultságok

| Funkció | MK-vezető | Igazgatóhelyettes | Igazgató | Administrator |
|---|:---:|:---:|:---:|:---:|
| Hír létrehozása | ✅ | ❌ | ❌ | ✅ |
| Saját piszkozat szerkesztése | ✅ | ❌ | ❌ | ✅ |
| Kép feltöltése | ✅ | ✅ | ✅ | ✅ |
| Beküldés jóváhagyásra | ✅ | ❌ | ❌ | ✅ |
| Jóváhagyási sor | ❌ | ✅ | ✅ | ✅ |
| Beküldött hír szerkesztése | ❌ | ✅ | ✅ | ✅ |
| Visszaküldés javításra | ❌ | ✅ | ✅ | ✅ |
| Éles publikálás production buildben | ❌ | ✅ | ✅ | ✅ |
| Éles publikálás `1.0.1-test` buildben | ❌ | ❌ | ❌ | ❌ |
| Audit napló | ❌ | ✅ | ✅ | ✅ |

WordPress role slugok:

```text
petrik_mk_leader
petrik_deputy_director
petrik_director
```

**Normál oktatói szerepkör nincs a Hírkezelőben.** Jogosultság nélkül a felület nem használható.

## Felhasználók és kategóriák

A megfelelő felhasználónak WordPress adminban rendeld hozzá a Petrik szerepkört.

MK-vezető profilján megjelenik az **Engedélyezett hírkategóriák** beállítás:

- ha nincs kijelölés, minden kategória engedélyezett;
- kijelölés esetén csak a megadott kategóriák jelennek meg a hírbeküldőben.

## Hírstátuszok

| Státusz | Felületi név | Jelentés |
|---|---|---|
| `draft` | Piszkozat | Az MK-vezető dolgozik rajta |
| `pending` | Jóváhagyásra vár | Vezetői döntés szükséges |
| `pnw_revision` | Javításra visszaküldve | Javítani és újraküldeni kell |
| `pnw_test_ok` | Tesztben jóváhagyva – nem publikus | Csak a pilot buildben |
| `publish` | Publikálva | Csak production buildben |

## Biztonsági modell

A publikálás nem csak a felületen van elrejtve.

- az MK-vezető nem kap `publish_posts` capabilityt;
- az MK-vezető `publish`, `future` vagy `private` státuszkísérlete blokkolva van;
- `map_meta_cap` szinten is zárolódnak a beküldött, plugin által kezelt hírek;
- minden módosítás szerveroldali capability- és nonce-ellenőrzést kap;
- input sanitization és output escaping használatos;
- a jóváhagyó csak a plugin által `_pnw_managed=1` jelölt hírt kezelheti;
- kiemelt képnél MIME-ellenőrzés történik;
- a lényeges események audit naplóba kerülnek;
- **TEST MODE-ban egy külön, első prioritású `wp_insert_post_data` guard minden kezelt hír publikus/private/future állapotát `pnw_test_ok` státuszra cseréli**, így wp-admin vagy REST útvonalon sem kerülhet ki véletlenül teszthír;
- a Hírkezelő oldal tesztmódban `noindex, nofollow` és no-cache fejléceket kap.

Részletesen: [`docs/SECURITY.md`](docs/SECURITY.md)

## E-mail értesítések

**Beküldés:** minden igazgatóhelyettes és igazgató értesítést kap. Ha még egyik szerepkörhöz sincs felhasználó, pilot fallbackként az administratorok kapják meg.

**Visszaküldés:** a szerző megkapja a vezetői megjegyzést és a közvetlen szerkesztési linket.

**Jóváhagyás:** production buildben publikálási értesítés, TEST MODE-ban külön `[TESZT]` értesítés készül, amely egyértelműen jelzi, hogy a hír nem publikus.

Az e-mail a WordPress `wp_mail()` rendszerét használja. A tárhely levelezési beállításait külön ellenőrizni kell; szükség esetén SMTP konfigurálható.

## Audit napló

Adatbázistábla:

```text
{wp_prefix}_pnw_audit_log
```

Többek között naplózódik a piszkozat létrehozása/módosítása, beküldés, vezetői szerkesztés, visszaküldés és indoklás, jóváhagyás, valamint lomtárba helyezés. TEST MODE-ban a jóváhagyás `test_approved` eseményként kerül naplózásra.

A napló és a híranyag a plugin eltávolításakor szándékosan nem törlődik automatikusan.

## Kapcsolat a meglévő Petrik híroldallal

A plugin nem hoz létre külön `news` custom post type-ot. Normál WordPress `post` objektumot készít, így production módban jóváhagyás után ugyanabba a WordPress hírstruktúrába kerül, mint a hagyományosan felvitt bejegyzések. A meglévő kategóriák, permalinkek, kiemelt képek és Divi sablon továbbra is használhatók.

TEST MODE-ban a kezelt bejegyzések nem jelennek meg a nyilvános híroldalon.

## Fejlesztői struktúra

```text
petrik-news-workflow/
├── petrik-news-workflow.php
├── includes/
│   ├── class-pnw-plugin.php
│   ├── class-pnw-roles.php
│   ├── class-pnw-statuses.php
│   ├── class-pnw-audit.php
│   ├── class-pnw-notifications.php
│   ├── class-pnw-test-mode.php
│   ├── class-pnw-access.php
│   ├── class-pnw-actions.php
│   ├── class-pnw-admin.php
│   ├── class-pnw-frontend.php
│   ├── trait-pnw-frontend-shell.php
│   ├── trait-pnw-frontend-dashboard.php
│   ├── trait-pnw-frontend-editor.php
│   └── trait-pnw-frontend-audit.php
├── assets/
│   ├── css/pnw.css
│   └── js/pnw.js
├── docs/
│   ├── DEPLOYMENT.md
│   ├── SECURITY.md
│   └── TEST-CHECKLIST.md
├── scripts/build-zip.sh
├── uninstall.php
└── .github/workflows/php-lint.yml
```

## Ellenőrzések

Lokális PHP lint:

```bash
find . -type f -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l
```

A GitHub Actions minden push és pull request során **PHP 7.4** környezetben linteli a PHP fájlokat.

Élesítés előtt a [`docs/TEST-CHECKLIST.md`](docs/TEST-CHECKLIST.md) alapján végig kell tesztelni a Petrik aktuális Divi sablonjával és pluginjaival.

## Dokumentáció

- [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md) – telepítés/élesítés;
- [`docs/SECURITY.md`](docs/SECURITY.md) – jogosultsági és biztonsági modell;
- [`docs/TEST-CHECKLIST.md`](docs/TEST-CHECKLIST.md) – élesítés előtti tesztlista.
