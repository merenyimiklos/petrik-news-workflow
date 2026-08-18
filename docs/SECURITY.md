# Biztonsági modell

## Alapelv

A frontend megjelenítés önmagában nem jogosultságkezelés. A plugin minden módosító műveletnél szerveroldali jogosultság- és nonce-ellenőrzést végez.

## MK-vezető

Az MK-vezető szerepkör:

- rendelkezik `pnw_submit_news` jogosultsággal;
- nem rendelkezik `publish_posts` jogosultsággal;
- nem rendelkezik `pnw_review_news` jogosultsággal;
- csak saját `draft` és `pnw_revision` státuszú hírt szerkeszthet.

A `wp_insert_post_data` védelmi filter az MK-vezető által megkísérelt `publish`, `future` vagy `private` státuszt `pending` státuszra cseréli.

A `map_meta_cap` ellenőrzés a plugin által kezelt híreknél capability-szinten is megtagadja az MK-vezetőtől a nem saját, illetve a már jóváhagyás alatt álló vagy publikált hírek szerkesztését/törlését. Ez a védelem nem csak a frontend felületre, hanem a WordPress belső jogosultsági ellenőrzéseire és REST-alapú szerkesztési kísérletekre is kiterjed.

## Jóváhagyók

Az igazgatóhelyettes és igazgató `pnw_review_news` capability alapján fér hozzá a jóváhagyási műveletekhez.

A jóváhagyási végpont csak `pending` státuszú, a plugin által `_pnw_managed=1` metaértékkel megjelölt normál WordPress `post` bejegyzést fogad el. Így más, hagyományos WordPress `pending` bejegyzést a Petrik Hírkezelő nem kezel véletlenül.

## CSRF

Minden POST művelethez külön WordPress nonce tartozik:

- `pnw_save_news`;
- `pnw_reviewer_save`;
- `pnw_review_news`;
- `pnw_delete_news`.

## Input kezelés

- cím: `sanitize_text_field`;
- tartalom: `wp_kses_post`;
- kivonat és vezetői megjegyzés: `sanitize_textarea_field`;
- azonosítók: `absint`;
- kategóriák: integer whitelist / intersection;
- query paraméterek: `sanitize_key`.

## Output kezelés

A plugin HTML-kimenetében URL-ekre `esc_url`, attribútumokra `esc_attr`, szövegre `esc_html` használatos. A WordPress által engedélyezett hír-HTML `wp_kses_post` segítségével jelenik meg.

## Fájlfeltöltés

A kiemelt kép a WordPress Media API-ján keresztül kerül feltöltésre. A plugin a létrejött attachment MIME típusát ellenőrzi, és csak `image/*` típusú fájlt állít be kiemelt képnek.

## Audit

A workflow legfontosabb eseményei külön adatbázistáblába kerülnek. A napló eltávolításkor szándékosan nem törlődik automatikusan.

## Élesítési ajánlás

Első telepítés előtt:

1. teljes WordPress adatbázis- és fájlmentés;
2. staging teszt;
3. jogosultsági teszt külön MK-vezető és vezető tesztfiókkal;
4. e-mailküldés ellenőrzése;
5. sablonkompatibilitás ellenőrzése.
