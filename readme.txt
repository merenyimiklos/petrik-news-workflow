=== Petrik News Workflow ===
Contributors: petrik
Tags: editorial workflow, approval, news, school
Requires at least: 6.4
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Belső hírbeküldési és vezetői jóváhagyási workflow a Petrik WordPress oldalához, fizetős függőség nélkül.

== Description ==

A plugin a WordPress szabványos bejegyzéseit használja. Munkaközösség-vezetők hírt készíthetnek és beküldhetik vezetői jóváhagyásra, de közvetlenül nem publikálhatnak. Igazgatóhelyettes vagy igazgató szerkesztheti, jóváhagyhatja és publikálhatja a hírt, vagy indoklással visszaküldheti javításra.

Az 1.1.0 verzió production módú kiadás: a Hírkezelőben jóváhagyott hír ténylegesen `publish` állapotba kerül és megjelenik a nyilvános weboldalon. A korábbi teszt-jóváhagyott hírek nem kerülnek automatikusan publikálásra.

A Hírkezelő továbbra is belső alkalmazásoldal: noindex/no-cache védelemmel működik, és nem jelenik meg a nyilvános navigációban. A Petrik saját workflow szerepkörei a Hírkezelőt használják; a normál wp-admin felület webadmin/adminisztrátor számára marad elérhető.

Az 1.0.9-test verziótól a plugin saját GitHub-updaterrel rendelkezik. A további verziók a WordPress natív bővítményfrissítőjében jelennek meg; nincs szükség újabb kézi ZIP-feltöltésre.

Fő funkciók:

* frontend Hírkezelő;
* teljesen frontend belépés WPS Hide Login kompatibilitással;
* MK-vezető / igazgatóhelyettes / igazgató szerepkörök;
* szerepkör-capability automatikus javítás frissítés után;
* piszkozat → beküldés → vezetői jóváhagyás → publikálás workflow;
* visszaküldés javításra vezetői megjegyzéssel;
* e-mail értesítések;
* kategória-korlátozás felhasználónként;
* új kategória létrehozása közvetlenül a Hírkezelőből;
* vizuális táblázatbeszúrás nem technikai felhasználóknak;
* kiemelt kép és gyors előnézet;
* publikált és korábbi WordPress hírek szerkesztése / Lomtárba helyezése vezetői jogosultsággal;
* audit napló;
* GitHub-alapú natív WordPress frissítés;
* nincs fizetős pluginfüggőség.

== Installation ==

1. Aktiváld / hagyd aktívan a bővítményt.
2. Rendeld hozzá a megfelelő felhasználói szerepköröket.
3. Nyisd meg a `/hirkezelo/` oldalt.
4. A további verziók a WordPress Bővítmények / Frissítések felületén jelennek meg.

== Changelog ==

= 1.1.0 =
* Production mód: jóváhagyás után a hír ténylegesen publikálódik.
* A Hírkezelő noindex/no-cache és nyilvános menüből rejtett marad.
* MK-vezető, igazgatóhelyettes és igazgató frontend-only workflow szerepkörként működik; a wp-admin webadminnak marad.
* A korábbi teszt-jóváhagyott hírek nem kerülnek automatikusan publikálásra.

= 1.0.19-test =
* Hibás TinyMCE link/linktörlés gombok eltávolítva a Hírkezelőből.

= 1.0.18-test =
* Hibás TinyMCE teljes képernyős gomb eltávolítva.

= 1.0.16-test =
* Vizuális táblázatbeszúrás.
* Új kategória létrehozása oldal-újratöltés nélkül.

= 1.0.15-test =
* Kint lévő hírek műveleteinek reszponzív elrendezése.

= 1.0.13-test =
* Kint lévő hírek felület a korábbi WordPress hírekkel együtt.

= 1.0.9-test =
* GitHub-alapú natív WordPress updater.
* A további verziók kézi ZIP-feltöltés nélkül telepíthetők.
* A GitHub archive könyvtárnevét frissítés közben automatikusan normalizálja.

= 1.0.8-test =
* Valódi WordPress auth cookie az MK-vezető frontend belépésénél.
* Contributor-kompatibilis MK alapjogok publikálási jog nélkül.

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

= 1.0.0 =
* Első működő verzió.
