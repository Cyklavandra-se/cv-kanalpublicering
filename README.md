# CV Kanalpublicering

WordPress-tillägg för cyklavandra.se: skriv ett utskick en gång och skapa utkast i flera kanaler.

- **Nyhetsbrev** – utkast direkt i Mailchimp, med logga, rabattkodsruta, resor med bild och "Läs mer" och den vanliga footern.
- **Blogginlägg** – utkast på cyklavandra.se med samma innehåll och resans bild som utvald bild.
- **Facebook** – länkinlägg till blogginlägget. Schemaläggs hos Meta när någon har godkänt det.
- **Instagram** – karusell med resornas bilder (beskurna till 4:5) och en personlig bildtext med 3–5 hashtaggar. Publiceras vid den tid någon har godkänt.
- **/aktuellt** – fast adress som alltid leder till senast publicerade blogginlägget från ett utskick. Läggs i Linktree och Instagram-bion en gång för alla.
- **Resefakta** – en ruta på resesidorna med dagar, nätter, land och egen etikett. Tomma fält fylls med det som tolkas ur sidtexten.

Inget skickas eller publiceras automatiskt. Nyhetsbrev och blogginlägg blir utkast. Facebook och Instagram blir utkast i WordPress och publiceras först när någon har granskat dem och klickat **Schemalägg** (minst 15 minuter fram, går att avbryta).

## Installera

Se **[docs/installation.md](docs/installation.md)** (installation och test mot Mailchimp) och **[docs/meta-installation.md](docs/meta-installation.md)** (Facebook och Instagram).

Kortversion:

1. Bygg zip-filen med `bin/build-zip.sh`, eller ladda ner den från en release. GitHubs "Download ZIP" ger fel mappnamn.
2. **Tillägg → Lägg till nytt → Ladda upp tillägg** och aktivera.
3. Lägg nycklarna i `wp-config.php`. Lägg dem aldrig i det här repot, det är publikt.

   ```php
   define( 'CVKP_MAILCHIMP_API_KEY', 'din-nyckel-us1' );
   define( 'CVKP_META_PAGE_TOKEN', 'EAA…' ); // Facebook och Instagram, se docs/meta-installation.md
   ```

4. **Inställningar → Kanalpublicering**: kontrollera uppgifterna och testa anslutningarna.
5. Lägg `https://cyklavandra.se/aktuellt` i Linktree och i Instagram-bion.

## Använda

1. **Utskick → Nytt utskick.** Ge det ett internt namn.
2. Fyll i ämnesrad, förhandstext, rubrik, inledning och eventuell rabattkod.
3. Under **Resor**: välj resa och klicka **Lägg till resa**. Bild, länk, typ och antal dagar hämtas från resesidan, och resans beskrivning läggs in som starttext. Skriv om texten, ändra rubrik eller etikett vid behov och sortera med pilarna. "Läs mer" läggs till automatiskt. Välj också bild för Instagram, eller ta bort resan från Instagram.
4. **Förhandsgranska nyhetsbrevet** visar mejlet direkt, även osparade ändringar.
5. Bocka i kanaler och klicka **Spara och skapa utkast**. Klickar du igen uppdateras samma utkast – det blir inga dubbletter. Facebook- och Instagram-texterna fylls med förslag som du skriver om.
6. Öppna utkastet i Mailchimp, skicka ett test och skicka. Granska och publicera blogginlägget i WordPress.
7. Under **Facebook och Instagram**: granska texter och bilder, välj tid och klicka **Schemalägg**. Facebook kräver att blogginlägget är publicerat.

Ett blogginlägg som redan är publicerat ändras aldrig av tillägget. Ändra det direkt i inlägget.

## Behörighet

Användare som får redigera andras inlägg (Redaktör och Administratör) kan skapa utskick. Inställningarna kräver Administratör.

## Teknik

- Utskick är en egen innehållstyp (`cvkp_utskick`). Fälten sparas i `_cvkp_data`, Mailchimp-kampanjens id i `_cvkp_mailchimp` och blogginläggets id i `_cvkp_blog_post_id`.
- Resor är sidor taggade `cykla` eller `vandra`. Typ kommer från de taggarna, nivå från `bike1`–`bike4` och `hike1`–`hike4`.
- Resefakta sparas som `_cvkp_days`, `_cvkp_nights`, `_cvkp_country` och `_cvkp_label` och syns i REST API:t (t.ex. för AI-sökningen).
- Mejlmallen finns i `templates/email.php` (640 px, inline-stilar, Mailchimps footer-taggar).
- Facebook och Instagram: `includes/class-meta.php` (Graph API), `class-social.php` (förslag, utkast, schemaläggning) och `class-instagram-images.php` (beskurna JPEG-kopior i `uploads/cvkp-instagram/`). Status sparas i `_cvkp_facebook` och `_cvkp_instagram`. Instagram publiceras via WP-Cron-händelsen `cvkp_instagram_publish`.
- WPML: utskick översätts inte, och resor och blogginlägg hanteras alltid på standardspråket (`wpml-config.xml`, `class-wpml.php`).
- Lokal testmiljö med simulerade API:er: `tests/local/`.
- Kräver WordPress 6.0 och PHP 7.4 eller senare.
