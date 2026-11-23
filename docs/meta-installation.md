# Facebook och Instagram: koppla tillägget till Meta

Engångsjobb, cirka en timme. Metas gränssnitt byter namn på saker ofta, så knapparna kan heta lite annorlunda än här.

## Så fungerar det

| | Utkast | Godkännande | Publicering |
|---|---|---|---|
| **Facebook** | Text och länk sparas i utskicket i WordPress | Du väljer tid och klickar **Schemalägg** | Meta publicerar vid tiden. Inlägget syns i Meta Business Suite → Planerare och kan ändras eller tas bort där fram till dess. |
| **Instagram** | Bildtext och beskurna bilder (karusell) sparas i utskicket | Du väljer tid och klickar **Schemalägg** | WordPress publicerar vid tiden. Metas API saknar utkast och schemaläggning för Instagram, så tillägget håller inlägget till dess. **Avbryt** stoppar det. |

- Tiden måste ligga minst 15 minuter fram, så att det alltid går att ångra.
- Ändringar efter schemaläggningen kommer inte med. Avbryt och schemalägg igen. Utskicket varnar om något har ändrats.
- Facebook-inlägget länkar till blogginlägget. **Blogginlägget måste vara publicerat innan Facebook schemaläggs**, eftersom Facebook hämtar länkens bild och rubrik när inlägget skapas.
- Om ett Instagram-inlägg blir mer än 6 timmar försenat (WP-Cron har stått still) publiceras det inte. Den som schemalade får ett mejl, både när det lyckas och när det inte gör det.

## 1. Instagram ska vara ett professionellt konto kopplat till Facebook-sidan

1. Instagram-appen, inloggad som **@cyklavandra.se** → Profil → ☰ → **Kontotyp och verktyg**. Står det *Byt till privat konto* är kontot redan professionellt. Annars väljer du **Byt till professionellt konto → Företag**.
2. **Meta Business Suite** (business.facebook.com) → **Inställningar** → **Konton → Instagram-konton**. @cyklavandra.se ska finnas där och vara kopplat till Facebook-sidan **Cykla & Vandra**. Om inte: **Lägg till → Koppla Instagram-konto**.

## 2. Skapa en Meta-app

1. Gå till **developers.facebook.com** → **Mina appar → Skapa app**. Logga in med ett konto som är administratör för Facebook-sidan.
2. Välj användningsfallen **Hantera allt på din sida** och **Hantera meddelanden och innehåll på Instagram** (Instagram API med Facebook-inloggning). Appen ska vara av typen *Business* och kopplas till er företagsportfölj.
3. Under **Appinställningar → Grundläggande**: fyll i integritetspolicyns adress (sidan med integritetspolicy på cyklavandra.se) och spara.
4. Sätt appen i läget **Live (Publicerad)**. Inlägg från en app i utvecklingsläge kan bli osynliga för andra än appens administratörer.

Behörigheter som behövs: `pages_show_list`, `pages_read_engagement`, `pages_manage_posts`, `instagram_basic`, `instagram_content_publish` och `business_management`. Ni använder appen bara för er egen sida och har själva roller i appen, så standardåtkomst räcker och ni behöver inte ansöka om granskning (App Review).

## 3. Skapa en sidtoken som inte går ut

**Alternativ A – Graph API Explorer (enklast)**

1. **developers.facebook.com/tools/explorer** → välj er app → **User Token** → lägg till behörigheterna ovan → **Generate Access Token** och godkänn för sidan och Instagram-kontot.
2. Klicka på **i** vid token → **Open in Access Token Tool** → **Extend Access Token**. Kopiera den långlivade token.
3. Klistra in den långlivade token i Explorer och kör `GET me/accounts`. Svaret har `access_token` för sidan **Cykla & Vandra**. Det är **sidtoken**, och den går inte ut. Kontrollera gärna i Access Token Debugger att det står *Expires: Never*.

**Alternativ B – Systemanvändare (stabilast, oberoende av en persons inloggning)**

1. **business.facebook.com/settings → Användare → Systemanvändare → Lägg till** (roll: Admin).
2. **Tilldela tillgångar**: Facebook-sidan (full kontroll), Instagram-kontot och appen.
3. **Generera ny token** för appen med utgångstiden *Aldrig* och behörigheterna ovan.
4. Kör `GET me/accounts` med den token, som i A3, och använd sidans `access_token`.

## 4. Lägg token i wp-config.php

```php
define( 'CVKP_META_PAGE_TOKEN', 'EAA…sidtoken…' );
// Bara om appen har "Kräv apphemlighet" påslaget:
// define( 'CVKP_META_APP_SECRET', '…' );
```

Lägg aldrig token i repot. Det är publikt.

## 5. Testa

**Inställningar → Kanalpublicering → Testa anslutningen till Meta.** Svaret ska vara *Facebook-sidan "Cykla & Vandra" (ID …) hittades. Instagram-kontot @cyklavandra.se är kopplat (ID …).* Sid-ID och Instagram-ID sparas automatiskt.

Om testet säger att inget Instagram-konto är kopplat, gå tillbaka till steg 1.

## 6. Första riktiga inlägget

1. Skapa ett utskick med **Facebook** och **Instagram** valda och klicka **Spara och skapa utkast**.
2. Under **Resor** väljer du bild per resa för Instagram. Resesidornas egna foton blir oftast bättre än de breda utvalda bilderna.
3. Skriv om texterna i rutan **Facebook och Instagram**.
4. Publicera blogginlägget, välj tid och klicka **Schemalägg** för respektive kanal.
5. Kontrollera Facebook-inlägget i Meta Business Suite → Planerare.

## 7. Pålitlig tid för Instagram (rekommenderas)

WP-Cron körs bara när någon besöker sajten. Inlägg vid lugna tider kan därför komma några minuter sent. Om webbhotellet har "Cron-jobb", lägg in:

```
*/5 * * * * curl -s https://cyklavandra.se/wp-cron.php?doing_wp_cron > /dev/null
```

Lägg sedan `define( 'DISABLE_WP_CRON', true );` i wp-config.php.

## 8. När Meta byter API-version

Tillägget använder Graph API **v24.0**, som går att ändra under Inställningar. Meta stöder en version i ungefär två år. Om felmeddelanden börjar nämna versionen, byt till den senaste och testa anslutningen igen.
