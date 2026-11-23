# CLAUDE.md – CV Kanalpublicering

Sammanhang för kommande sessioner. Läs också README.md och docs/.

## Vad det är

WordPress-tillägg för **cyklavandra.se** (tema Avada, WPML sv/no, AIOSEO, Wordfence, Mailchimp for WP). Man skriver ett **utskick** en gång och får utkast i flera kanaler: Mailchimp-nyhetsbrev, blogginlägg, Facebook och Instagram. **Inget publiceras automatiskt utan godkännande.** Mailchimp och blogg blir utkast. Facebook och Instagram blir utkast i WordPress och går ut först när en person klickar **Schemalägg**, minst 15 minuter fram.

Användare: Pär (admin) och Sarah (Redaktör). Verktyget kräver `edit_others_posts`, inställningarna kräver `manage_options`.

## Status (november 2026)

- **Fas 1** (grenen `fas-1`, PR #1): utskick, Resefakta, nyhetsbrevsmall, Mailchimp-utkast, blogginlägg och /aktuellt. Testat lokalt med simulerade API:er, **inte mot riktiga Mailchimp eller live-sajten än**. Checklistan finns i docs/installation.md.
- **Fas 2** (version 0.2.0): Facebook (länkinlägg som schemaläggs hos Meta) och Instagram (karusell som publiceras via WP-Cron vid godkänd tid). Testat lokalt med mockad Graph API. **Meta-appen är inte skapad än**, och det är inte kontrollerat att @cyklavandra.se är ett professionellt konto kopplat till FB-sidan (docs/meta-installation.md).
- Instagram har varit inaktivt sedan juli 2026. Bion har cyklavandra.se och linktr.ee/cyklavandra.se. Där ska en fast länk **Aktuellt → https://cyklavandra.se/aktuellt** in (görs för hand).

## Sajten

- Resor är **vanliga sidor** (ingen egen post type) med taggar: `cykla`/`vandra`, `sjalvguidad`, månader, nivå `bike1`–`bike4`/`hike1`–`hike4`. Inga ACF-fält. Dagar och land står bara i sidtexten, därav rutan Resefakta (`_cvkp_days`, `_cvkp_nights`, `_cvkp_country`, `_cvkp_label`).
- Resornas utvalda bilder är oftast **1200×630 JPEG** (1.9:1). Sidtexten har större egna foton, och det är därför man väljer Instagram-bild per resa.
- Publika REST API:t (`/wp-json/wp/v2/...`) går att läsa utan inloggning för att kolla taggar, sidor och bilder.

## Husstil

- **Nyhetsbrev:** logga överst. Länken till resan ligger i **"Läs mer" sist i varje stycke, aldrig på rubriken.** Den vanliga footern ligger kvar. Svenska, **ni-form**.
- **Instagram:** personliga texter, gärna en hälsning från någon i teamet (Anders, Ingrid, Sarah, Pär). **3–5 hashtaggar.** Länkar går inte att klicka, så texten hänvisar till "länken i vår bio → Aktuellt".
- **Facebook:** länkinlägg till blogginlägget.

## Mailchimp

Lista `aacb39e5d3`, server `us1`, avsändare `nyhetsbrev@cyklavandra.se`, betalt konto (ingen referral-badge). Samma kampanj uppdateras så länge den har status `save`. Ett nätverksfel vid kontrollen ska ge ett fel, inte en dubblett.

## Säkerhet: repot är PUBLIKT

- API-nycklar och tokens får **aldrig** in i koden, testerna eller commit-meddelanden. De läses från `wp-config.php` (`CVKP_MAILCHIMP_API_KEY`, `CVKP_META_PAGE_TOKEN`, valfritt `CVKP_META_APP_SECRET`) eller från inställningssidan.
- Logga eller visa aldrig token. Meta-anropen skickar den som `access_token`-parameter, och felmeddelanden får inte innehålla URL:en.

## Kod

- PHP **7.4+** (ingen `match`, `?->`, `str_contains`, namngivna argument eller union types). WordPress 6.0+. CI kör `php -l` på 7.4 och 8.3.
- Allt användargränssnitt och alla kommentarer på svenska. Prefix `cvkp`/`CVKP_`.
- `includes/class-mailing.php`: innehållstypen `cvkp_utskick`, redigeringsvyn och `run()` (skapar utkasten).
- `includes/class-social.php`: FB/IG-förslag, utkast, `schedule()`, `cancel()`, cron `cvkp_instagram_publish`, statusvy. Status i `_cvkp_facebook`/`_cvkp_instagram`: `none`, `scheduled`, `publishing`, `published` eller `failed`, plus `draft` och `scheduled`, som är en ögonblicksbild av det som godkändes.
- `includes/class-meta.php`: Graph API (standard v24.0, går att ändra i inställningarna). `includes/class-instagram-images.php`: beskurna JPEG-kopior i `uploads/cvkp-instagram/`.
- `includes/class-wpml.php` och `wpml-config.xml`: resor och blogginlägg hanteras alltid på standardspråket.
- Menyn: tillägget ligger under den gemensamma menyn **Cykla&Vandra** (`cyklavandra-meny.php`, samma fil kopieras oförändrad till alla våra tillägg – Bokningsformulär, AI-sök m.fl.; ändras den ska alla kopior uppdateras och `VERSION` höjas). Kortet på översikten läggs till via filtret `cyklavandra_verktyg`; ikon `assets/icon.svg` i samma stil som de andra.
- `bin/build-zip.sh` bygger zip-filen för uppladdning. Mappen i zip-filen måste heta `cv-kanalpublicering/`.

## Testa

Ingen riktig Mailchimp eller Meta här. Från molnmiljön är `*.api.mailchimp.com`, `graph.facebook.com` och wordpress.org blockerade, men GitHub-kloner av WordPress fungerar. Se `tests/local/README.md`: WordPress 6.8 med SQLite, `cvkp-mock.php` (simulerar API:erna och loggar anropen), `setup.php` och `flow.php` (hela flödet inklusive felfall). Gränssnittet går att klicka igenom med Playwright mot `php -S`.

## Arbetssätt

- Fråga innan något skickas eller publiceras på riktigt. Skapa inte PR utan att bli ombedd.
- Ändra aldrig ett publicerat blogginlägg från tillägget.
