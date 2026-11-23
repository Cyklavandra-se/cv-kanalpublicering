# CV Kanalpublicering

WordPress-tillägg för cyklavandra.se: skriv ett utskick en gång och skapa utkast i flera kanaler.

**Fas 1 (den här versionen)**

- **Nyhetsbrev** – utkast direkt i Mailchimp, med logga, rabattkodsruta, resor med bild och "Läs mer" och den vanliga footern.
- **Blogginlägg** – utkast på cyklavandra.se med samma innehåll och resans bild som utvald bild.
- **/aktuellt** – fast adress som alltid leder till senast publicerade blogginlägget från ett utskick. Läggs i Linktree en gång för alla.
- **Resefakta** – en ruta på resesidorna med dagar, nätter, land och egen etikett. Tomma fält fylls med det som tolkas ur sidtexten.

Inget skickas eller publiceras automatiskt. Allt blir utkast som granskas först.

**Fas 2 (kommer):** Facebook och Instagram via Metas API.

## Installera

1. Ladda ner tillägget: **Code → Download ZIP** här på GitHub.
2. I WordPress: **Tillägg → Lägg till nytt → Ladda upp tillägg**, välj zip-filen och aktivera.
3. Lägg Mailchimp-nyckeln i `wp-config.php` (rekommenderas):

   ```php
   define( 'CVKP_MAILCHIMP_API_KEY', 'din-nyckel-us1' );
   ```

   Alternativt under **Inställningar → Kanalpublicering**. Lägg aldrig nyckeln i det här repot – det är publikt.
4. Gå till **Inställningar → Kanalpublicering**, kontrollera lista, avsändare och footer och klicka **Testa anslutningen till Mailchimp**.
5. Valfritt: välj bloggkategori och lägg `https://cyklavandra.se/aktuellt` i Linktree.

## Använda

1. **Utskick → Nytt utskick.** Ge det ett internt namn.
2. Fyll i ämnesrad, förhandstext, rubrik, inledning och eventuell rabattkod.
3. Under **Resor**: välj resa och klicka **Lägg till resa**. Bild, länk, typ och antal dagar hämtas från resesidan, och resans beskrivning läggs in som starttext. Skriv om texten, ändra rubrik eller etikett vid behov och sortera med pilarna. "Läs mer" läggs till automatiskt.
4. **Förhandsgranska nyhetsbrevet** visar mejlet direkt, även osparade ändringar.
5. Bocka i kanaler och klicka **Spara och skapa utkast**. Klickar du igen uppdateras samma utkast – det blir inga dubbletter.
6. Öppna utkastet i Mailchimp, skicka ett test och skicka. Granska och publicera blogginlägget i WordPress.

Ett blogginlägg som redan är publicerat ändras aldrig av tillägget. Ändra det direkt i inlägget.

## Behörighet

Användare som får redigera andras inlägg (Redaktör och Administratör) kan skapa utskick. Inställningarna kräver Administratör.

## Teknik

- Utskick är en egen innehållstyp (`cvkp_utskick`). Fälten sparas i `_cvkp_data`, Mailchimp-kampanjens id i `_cvkp_mailchimp` och blogginläggets id i `_cvkp_blog_post_id`.
- Resor är sidor taggade `cykla` eller `vandra`. Typ kommer från de taggarna, nivå från `bike1`–`bike4` och `hike1`–`hike4`.
- Resefakta sparas som `_cvkp_days`, `_cvkp_nights`, `_cvkp_country` och `_cvkp_label` och syns i REST API:t (t.ex. för AI-sökningen).
- Mejlmallen finns i `templates/email.php` (640 px, inline-stilar, Mailchimps footer-taggar).
- Kräver WordPress 6.0 och PHP 7.4 eller senare.
