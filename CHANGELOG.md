# Changelog

## 1.1.0 - 2026-08-26

- A plugin production módba került: `PNW_TEST_MODE` kikapcsolva.
- A vezetői jóváhagyás után a workflow-val kezelt hír ténylegesen `publish` állapotba kerül és megjelenik a nyilvános oldalon.
- A korábbi `pnw_test_ok` teszt-jóváhagyott hírek nem kerülnek automatikusan publikálásra.
- Az MK-vezető, igazgatóhelyettes és igazgató szerepkörök a dedikált frontend Hírkezelőt használják; a normál wp-admin felület webadmin/adminisztrátor számára marad elérhető.
- A frontend működéshez szükséges `admin-post.php`, AJAX és médiafeltöltési végpontok továbbra is engedélyezettek.
- A Hírkezelő productionben is `noindex, nofollow`, no-cache védelmet kap, és nem jelenik meg a publikus WordPress navigációban.
- A publikált hírek kezelőfelületén a régi és új hírek szerkeszthetők, illetve Lomtárba helyezhetők a megfelelő vezetői jogosultsággal.
- A GitHub-alapú natív WordPress updater marad az éles frissítési csatorna.

## 1.0.19-test - 2026-08-26

- A Hírkezelő TinyMCE eszköztárából eltávolítva a hibás link beszúrása és link törlése gomb.

## 1.0.18-test - 2026-08-26

- A Hírkezelő TinyMCE eszköztárából eltávolítva a Divi/téma stílusaival összeakadó teljes képernyős gomb.

## 1.0.17-test - 2026-08-26

- A táblázat- és kategóriaablak műveleti gombjai stabil, jól látható elrendezést kaptak.

## 1.0.16-test - 2026-08-26

- Vizuális **Táblázat beszúrása** funkció került a hírszerkesztőbe sor-/oszlopszám, fejlécsor és opcionális cím megadásával.
- Új kategória hozható létre közvetlenül a Hírkezelőből oldal-újratöltés nélkül, opcionális szülőkategóriával.
- Az új kategória létrehozás után automatikusan kiválasztásra kerül.

## 1.0.15-test - 2026-08-26

- A **Kint lévő hírek** műveleti gombjai külön, teljes szélességű sorba kerültek a jobb oldali levágás megszüntetésére.

## 1.0.13-test - 2026-08-26

- Új **Kint lévő hírek** felület az összes publikált WordPress-bejegyzéshez, beleértve a plugin előtti híreket is.
- Production módban szerkesztés és Lomtárba helyezés támogatása vezetői jogosultsággal.

## 1.0.9-test - 2026-08-24

- Beépített GitHub-alapú natív WordPress frissítő került a pluginba.
- A plugin az `update.json` manifestet ellenőrzi a GitHub deployment branch-en.
- Újabb verziónál a WordPress saját **Bővítmények → Frissítés most** felületén jelenik meg a frissítés.
- A GitHub branch ZIP eltérő könyvtárnevét a plugin frissítés közben automatikusan `petrik-news-workflow` névre normalizálja.
- A frissítéshez nincs szükség GitHub tokenre vagy fizetős WordPress pluginra, mert a repository publikus.
- Ettől a verziótól a további fejlesztésekhez nem kell kézzel új ZIP-et feltölteni a WordPressbe.

## 1.0.8-test - 2026-08-24

- Az MK-vezető belépése valódi WordPress munkamenetet hoz létre (`wp_set_auth_cookie`).
- A munkaközösség-vezető szerepkör Contributor-kompatibilis alapjogokat kap (`read`, `edit_posts`, `delete_posts`, `level_0`, `level_1`) + képfeltöltés + `pnw_submit_news`.
- Az MK-vezető továbbra sem kap `publish_posts`, `edit_others_posts`, adminisztrátori vagy workflow-jóváhagyási jogot.
- Sikeres WordPress belépés után az MK-vezetőt a plugin automatikusan a Hírkezelőbe irányítja.

## 1.0.6-test - 2026-08-24

- A Hírkezelő bejelentkezése teljesen frontend útvonalon történik; kijelentkezett felhasználónál sem használ `/wp-admin/admin-post.php` címet.
- A munkaközösség-vezetői szerepkör szükséges capability-jeit a plugin automatikusan helyreállítja frissítés után is.
- A vezetői/admin dashboard külön **Saját piszkozatok és javítások** blokkban mutatja a saját elmentett piszkozatokat.
- Az e-mailes bejelentkezés explicit felhasználónév-feloldást használ.

## 1.0.5-test - 2026-08-24

- A Hírkezelő saját frontend bejelentkezési végpontot kapott, így nem függ a `wp-login.php` URL-től és kompatibilis a WPS Hide Login használatával.
- Sikertelen belépésnél egyértelmű hibaüzenet jelenik meg.
- Sikeres belépés után csak MK-vezető / vezetői Hírkezelő-jogosultsággal lehet belépni a belső felületre.
- Felhasználónév és e-mail cím egyaránt használható.

## 1.0.4-test - 2026-08-24

- A vezetői részletező nézetben is megjelenik a **Hír módosítása** gomb, ha a visszaküldött hírt olyan felhasználó nézi, akinek híríró jogosultsága is van.
- A valódi igazgatóhelyettes/igazgató továbbra sem kap híríró jogosultságot, így a javítás az MK-vezető feladata marad.

## 1.0.3-test - 2026-08-24

- A javításra visszaküldött híreknél egyértelmű **Módosítás / Hír módosítása** művelet került a felületre.
- Javítás után a fő művelet neve **Újraküldés jóváhagyásra**.
- A hírszöveg szerkesztője letisztult, csak vizuális szerkesztési módot használ.
- Új **Gyors előnézet** popup a még el nem mentett tartalomhoz.
- A kiválasztott kiemelt kép feltöltés előtt azonnal előnézhető.

## 1.0.1-test - 2026-08-24

- A minimális PHP-verzió 7.4-re módosítva a Petrik jelenlegi szerverkörnyezetéhez.
- Hard TEST MODE bevezetve az első éles szerveres próbához.
- A plugin által kezelt hír TEST MODE-ban sem a Hírkezelőből, sem wp-admin/REST útvonalon nem kerülhet `publish`, `future` vagy `private` állapotba.
- Új, nem publikus `pnw_test_ok` státusz a teszt-jóváhagyásokhoz.
- A Hírkezelő TEST MODE bannerrel jelzi a publikálási tiltást.
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
