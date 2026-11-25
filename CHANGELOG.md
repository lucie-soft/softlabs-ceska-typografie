# Changelog

## 1.4.2 – 2026-11-25

• Licence GPL-2.0-or-later (soubor `LICENSE`, hlavička pluginu, copyright SOFTLABS CZECH s.r.o.).
• Záložka Návod: sekce „Autor a kontakt“, text vhodný i pro weby, které spravují jiní webaři.
• ZIP v Releases se jmenuje `softlabs-ceska-typografie.zip` bez čísla verze, přímý odkaz ke stažení se tak nemění.
• Kód úpravy textu beze změny oproti 1.4.1.

## 1.4.1 – 2026-11-18

• Oprava: plugin na webech s FlyingPress nic neupravoval. Kritické CSS obsahuje zápisy jako `@media (width<=980px)`, plugin znak `<` považoval za začátek značky a zbytek stránky bral jako CSS. Bloky `<script>` a `<style>` se teď berou vcelku (hledání přes `stripos`, bez limitu PCRE i u velkých inline bloků).
• Znak `>` v hodnotách atributů (např. `alt="a > b"`) už nerozdělí značku.
• Ověřeno na staging.softlabs.cz.

## 1.4.0 – 2026-11-11

• Přejmenováno z „Medicoweb – Česká typografie“ na „Softlabs – Česká typografie“ (složka, soubor, třída, volba v DB, filtr). Nastavení z verze Medicoweb se nepřenáší.
• Kontakt v záložce Návod: tým Softlabs, weby@softlabs.cz, softlabs.cz.
• Pomlčka se hlídá i jako HTML entita (`&#8211;`, `&#8212;`, `&ndash;`, `&mdash;`).
• Příklad v nastavení obecný, bez názvu klienta.

## 1.3.0

• Poslední verze pod názvem „Medicoweb – Česká typografie“, vytvořená pro butmed.cz.
