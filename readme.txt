=== Petrik News Workflow ===
Contributors: petrik
Tags: editorial workflow, approval, news, school
Requires at least: 6.4
Requires PHP: 7.4
Stable tag: 1.0.6-test
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Belső hírbeküldési és vezetői jóváhagyási workflow a Petrik WordPress oldalához, fizetős függőség nélkül.

== Description ==

A plugin a WordPress szabványos bejegyzéseit használja. Munkaközösség-vezetők hírt készíthetnek és beküldhetik vezetői jóváhagyásra, de nem publikálhatnak. Igazgatóhelyettes vagy igazgató szerkesztheti, jóváhagyhatja és publikálhatja a hírt, vagy indoklással visszaküldheti javításra.

A tesztcsomagban hard safety TEST MODE aktív: a plugin által kezelt hír sem a Hírkezelőből, sem wp-admin/REST kerülőúton nem tehető publikus, privát vagy időzített állapotba. A jóváhagyás `pnw_test_ok` tesztstátuszt rögzít, ezért a hír nem jelenik meg a nyilvános Petrik oldalon.

Fő funkciók:

* frontend Hírkezelő;
* teljesen frontend belépés WPS Hide Login kompatibilitással;
* MK-vezető / igazgatóhelyettes / igazgató szerepkörök;
* szerepkör-capability automatikus javítás frissítés után;
* pending review workflow;
* visszaküldés javításra;
* e-mail értesítések;
* kategória-korlátozás felhasználónként;
* audit napló;
* admin tesztfiók saját piszkozatainak külön listázása;
* hard test-mode publikálásvédelem;
* nincs fizetős pluginfüggőség.

== Installation ==

1. Másold a bővítményt a `wp-content/plugins/petrik-news-workflow` mappába vagy töltsd fel ZIP-ként.
2. Aktiváld a WordPress adminban.
3. Rendeld hozzá a megfelelő felhasználói szerepköröket.
4. Nyisd meg a `/hirkezelo/` oldalt.
5. Tesztverziónál ellenőrizd, hogy a TESZT MÓD banner látható.

== Changelog ==

= 1.0.6-test =
* A login POST sem használ többé `/wp-admin` útvonalat.
* MK-vezetői capability-k automatikus helyreállítása.
* Admin/vezetői nézetben külön saját piszkozat-lista.

= 1.0.5-test =
* Saját frontend login a WPS Hide Login kompatibilitás miatt.
* Érthető belépési hibaüzenetek és Hírkezelő-jogosultság ellenőrzés.

= 1.0.4-test =
* Visszaküldött hír módosítása admin tesztfiókból is.

= 1.0.3-test =
* Letisztult vizuális hírszerkesztő és gyors előnézet.
* Visszaküldött hír javítása és újraküldése.

= 1.0.1-test =
* PHP 7.4 kompatibilitási cél a jelenlegi Petrik tárhelyhez.
* Hard TEST MODE: kezelt hír nem publikálható.
* Teszt-jóváhagyási státusz, noindex/no-cache védelem a Hírkezelőn.
* Tesztmódhoz igazított felületi és e-mail üzenetek.

= 1.0.0 =
* Első működő verzió.
