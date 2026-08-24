# Changelog

## 1.0.4-test - 2026-08-24

- A vezetői részletező nézetben is megjelenik a **Hír módosítása** gomb, ha a visszaküldött hírt olyan felhasználó nézi, akinek híríró jogosultsága is van (pl. admin tesztfiók).
- A valódi igazgatóhelyettes/igazgató továbbra sem kap híríró jogosultságot, így a javítás az MK-vezető feladata marad.

## 1.0.3-test - 2026-08-24

- A javításra visszaküldött híreknél egyértelmű **Módosítás / Hír módosítása** művelet került a felületre.
- Javítás után a fő művelet neve **Újraküldés jóváhagyásra**, így a teljes visszaküldés → javítás → újraellenőrzés workflow egyértelmű.
- A hírszöveg szerkesztője letisztult, csak vizuális szerkesztési módot használ; a technikai Kód/Quicktags/„tagek lezárása” elemek eltűntek.
- Egyszerűbb TinyMCE eszköztár: címsor, félkövér/dőlt, listák, idézet, igazítás, link, visszavonás, formázás törlése és teljes képernyő.
- Új **Gyors előnézet** popup a még el nem mentett cím, kategóriák, kivonat, formázott hírszöveg és kiemelt kép megtekintéséhez.
- A kiválasztott kiemelt kép már feltöltés előtt azonnal előnézhető.
- A vezetői szerkesztőben külön gyors előnézet és külön WordPress/Divi weboldal-előnézet érhető el.
- Asset verzió emelve, hogy a WordPress/böngésző cache biztosan az új CSS/JS fájlokat töltse be.

## 1.0.1-test - 2026-08-24

- A minimális PHP-verzió 7.4-re módosítva a Petrik jelenlegi szerverkörnyezetéhez.
- Hard TEST MODE bevezetve az első éles szerveres próbához.
- A plugin által kezelt hír TEST MODE-ban sem a Hírkezelőből, sem wp-admin/REST útvonalon nem kerülhet `publish`, `future` vagy `private` állapotba.
- Új, nem publikus `pnw_test_ok` státusz a teszt-jóváhagyásokhoz.
- A Hírkezelő TEST MODE bannerrel jelzi a publikálási tiltást.
- A jóváhagyó gomb és az e-mail értesítések tesztüzemben egyértelműen jelzik, hogy nem történt publikálás.
- A Hírkezelő oldal `noindex, nofollow` és no-cache fejléceket kap tesztmódban.
- GitHub Actions PHP lint célverzió PHP 7.4.

## 1.0.0 - 2026-08-18

- Első teljes verzió.
- Munkaközösség-vezető, igazgatóhelyettes és igazgató szerepkörök.
- Frontend Hírkezelő és bejelentkezés.
- Piszkozat → jóváhagyás → publikálás workflow.
- Javításra visszaküldés vezetői megjegyzéssel.
- Vezetői szerkesztés és előnézet.
- Kiemelt kép és WordPress kategóriák kezelése.
- MK-vezetőnkénti kategória-korlátozás.
- E-mail értesítések.
- Audit napló.
- Biztonsági korlátozások az illetéktelen publikálás ellen.
- GitHub Actions PHP syntax lint.
- Telepítési, biztonsági és tesztelési dokumentáció.
