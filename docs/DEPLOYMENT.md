# Telepítés és élesítés

## 1. Biztonsági mentés

Éles WordPress módosítás előtt készíts teljes mentést:

- adatbázis;
- `wp-content`;
- aktuális theme;
- aktív pluginlista.

## 2. Staging telepítés

A plugin első telepítését lehetőleg staging környezeten végezd el.

## 3. ZIP készítés

```bash
./scripts/build-zip.sh
```

## 4. WordPress feltöltés

**Bővítmények → Új bővítmény → Bővítmény feltöltése**

Aktiválás után ellenőrizd, hogy létrejött-e a `/hirkezelo/` oldal.

## 5. Szerepkörök kiosztása

Hozz létre legalább két tesztfelhasználót:

- egy `Munkaközösség-vezető`;
- egy `Igazgatóhelyettes` vagy `Igazgató`.

## 6. Kategóriák

Az MK-vezető profilján állítsd be a használható kategóriákat. Üres kiválasztás esetén minden kategória engedélyezett.

## 7. E-mail

Ellenőrizd, hogy a WordPress képes-e levelet küldeni. A plugin nem tartalmaz saját SMTP szervert; a WordPress `wp_mail()` rendszerét használja.

## 8. Hírfolyam teszt

1. MK-vezető belép.
2. Piszkozatot ment.
3. Beküldi jóváhagyásra.
4. Ellenőrizd a vezetői e-mailt.
5. Vezető visszaküldi javításra.
6. Ellenőrizd az MK-vezető e-mailjét és megjegyzését.
7. MK-vezető javít és újraküld.
8. Vezető jóváhagyja.
9. Ellenőrizd a publikus hírt.
10. Ellenőrizd az audit naplót.

## 9. Éles telepítés

A staging teszt után ugyanaz a ZIP telepíthető élesben.
