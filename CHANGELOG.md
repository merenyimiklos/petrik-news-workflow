# Changelog

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
