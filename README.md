# Softlabs – Česká typografie

WordPress plugin, který při zobrazení stránky nahrazuje mezery za krátkými slovy nedělitelnou mezerou (`&nbsp;`), aby v českém textu nezůstávala jednopísmenná slova, předložky, spojky, čísla a tituly na konci řádku.

Autor: Softlabs, weby@softlabs.cz, https://softlabs.cz

## Co plugin dělá

• Jednopísmenná slova (a, i, k, o, s, u, v, z…)
• Předložky a spojky do 3 písmen (seznam lze upravit)
• Další slova, např. číslovky (dva, tři, pět…)
• Tituly a zkratky (MUDr., Ing., tel., č.…), rozlišují velikost písmen
• Číslo s následujícím slovem nebo jednotkou (3 měsíce, 500 Kč, 11. 11. 2026)
• Pomlčka (–, —, i jako HTML entita) nezačne nový řádek
• Krátké poslední slovo odstavce `<p>` (výchozí do 5 znaků) se spojí s předchozím
• Výjimka: prvek s CSS třídou `bez-nbsp` se i s obsahem přeskočí

Nastavení a návod pro klienta: Nastavení → Česká typografie (záložky Nastavení a Návod).

## Kde plugin nezasahuje

`<head>`, `script`, `style`, `textarea`, `pre`, `code`, `svg`, `noscript`, `template`, `title`, administrace, Divi Builder (`et_fb`, `et_pb_preview`, `et_tb`), AJAX, REST, RSS, cron a odpovědi, které nejsou HTML.

## Technicky

• Jeden soubor `softlabs-ceska-typografie.php`, třída `Softlabs_Ceska_Typografie`
• Výstup se upravuje přes output buffer na `template_redirect`
• Volba v DB: `softlabs_ceska_typografie_options`
• Filtr pro vypnutí: `add_filter( 'softlabs_ceska_typografie_enabled', '__return_false' );`
• Requires PHP 7.4, testováno na PHP 8.3

## Instalace na web

Plugin musí být ve WordPressu ve složce `softlabs-ceska-typografie`. Tlačítko „Download ZIP“ na GitHubu vytvoří složku `softlabs-ceska-typografie-main`, WordPress by ji bral jako jiný plugin a nastavení by se nepřeneslo. Proto na weby nahrávejte ZIP z Releases, nebo ho vytvořte příkazem:

```
git archive --format=zip --prefix=softlabs-ceska-typografie/ -o softlabs-ceska-typografie.zip HEAD
```

Po nahrání nové verze smažte cache (FlyingPress, Cloudflare).

## Nová verze

1. Zvednout `Version:` v hlavičce `softlabs-ceska-typografie.php`
2. Zapsat změny do `CHANGELOG.md`
3. Commit, tag `vX.Y.Z`, ZIP do Releases

## Známá omezení

• Když je poslední slovo odstavce v inline tagu (`<strong>`, `<a>`…), nespojí se s předchozím slovem mimo tag.
• Písmeno hned za HTML entitou se bere jako samostatné slovo (rock&#8217;n roll → za „n“ přibude `&nbsp;`). Neškodí.
• Aktualizace se zatím nahrávají na každý web ručně.
