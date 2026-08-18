# Élesítés előtti tesztlista

## Telepítés

- [ ] A plugin hiba nélkül aktiválható.
- [ ] Létrejön a `Hírkezelő` oldal.
- [ ] A `/hirkezelo/` oldal betölt.
- [ ] A meglévő híroldal változatlanul működik.

## Jogosultságok

- [ ] MK-vezető be tud lépni a Hírkezelőbe.
- [ ] Normál oktató nem fér hozzá a Hírkezelőhöz.
- [ ] MK-vezető nem tud hírt publikálni.
- [ ] MK-vezető nem látja más MK-vezető piszkozatát.
- [ ] MK-vezető nem tudja módosítani a `pending` hírt.
- [ ] Igazgatóhelyettes látja a jóváhagyási sort.
- [ ] Igazgató látja a jóváhagyási sort.

## Tartalom

- [ ] Cím menthető.
- [ ] Formázott szöveg menthető.
- [ ] Kiemelt kép feltölthető.
- [ ] Kategóriák jól mentődnek.
- [ ] Felhasználónkénti kategória-korlátozás működik.

## Workflow

- [ ] Piszkozat menthető.
- [ ] Piszkozat beküldhető jóváhagyásra.
- [ ] Beküldött hír `pending` státuszba kerül.
- [ ] Vezető szerkesztheti a beküldött hírt.
- [ ] Előnézet megnyitható.
- [ ] Visszaküldéshez kötelező megjegyzés.
- [ ] Visszaküldés után a hír szerkeszthető az MK-vezetőnek.
- [ ] Újraküldés működik.
- [ ] Jóváhagyás után a hír `publish` státuszú.
- [ ] A hír megjelenik a Petrik publikus híroldalán.

## Értesítések

- [ ] Beküldéskor a jóváhagyók e-mailt kapnak.
- [ ] Visszaküldéskor a szerző e-mailt kap.
- [ ] Az e-mailben szerepel a vezetői megjegyzés.
- [ ] Jóváhagyáskor a szerző e-mailt kap.

## Audit

- [ ] Létrehozás naplózódik.
- [ ] Beküldés naplózódik.
- [ ] Vezetői szerkesztés naplózódik.
- [ ] Visszaküldés és megjegyzés naplózódik.
- [ ] Jóváhagyás naplózódik.
- [ ] A naplóban helyes felhasználó és időpont látható.

## Reszponzivitás

- [ ] Desktopon jól használható.
- [ ] Mobilon jól használható.
- [ ] A WordPress aktuális theme-je nem töri szét a Hírkezelő CSS-ét.
