# Installera på cyklavandra.se och testa mot riktiga Mailchimp

Räkna med 30–45 minuter. Inget i stegen skickar något till prenumeranterna.

## 1. Säkerhetskopia

Ta en säkerhetskopia av sajten (eller kontrollera att webbhotellets dagliga kopia finns). Tillägget skapar bara egna data, men det är god vana.

## 2. Ladda upp tillägget

1. Ladda ner `cv-kanalpublicering.zip`. Bygg den själv med `bin/build-zip.sh` eller använd den du fått. Använd **inte** GitHubs "Download ZIP". Den ger mappen fel namn (`cv-kanalpublicering-fas-1`), och då blir nästa uppdatering ett nytt tillägg i stället för en uppdatering.
2. **Tillägg → Lägg till nytt → Ladda upp tillägg**, välj zip-filen, **Installera nu** och **Aktivera**.
3. Vid senare uppdateringar laddar du upp den nya zip-filen på samma sätt och väljer **Ersätt nuvarande med uppladdad**.

## 3. Mailchimp-nyckeln i wp-config.php

1. I Mailchimp: **Profil → Extras → API keys → Create A Key**. Döp den till "cyklavandra.se – Kanalpublicering".
2. Öppna `wp-config.php` via webbhotellets filhanterare eller SFTP. Lägg in raden ovanför `/* That's all, stop editing! */`:

   ```php
   define( 'CVKP_MAILCHIMP_API_KEY', 'nyckeln-som-slutar-med-us1' );
   ```

3. **Cykla&Vandra → Kanalpublicering → Testa anslutningen till Mailchimp.** Du ska få se *"Anslutningen fungerar. Listan … har N prenumeranter."*

Om testet misslyckas:

| Meddelande | Orsak |
|---|---|
| *nyckeln saknas eller har fel format* | Nyckeln ska sluta med `-us1`. |
| *API Key Invalid* | Fel nyckel, eller nyckeln är borttagen i Mailchimp. |
| *listan aacb39e5d3 hittades inte* | Fel Audience-ID. Kolla under Audience → Settings. |
| Timeout eller *cURL error* | Webbhotellet eller Wordfence blockerar utgående anrop. Tillåt `us1.api.mailchimp.com`. |

## 4. Kontrollera inställningarna

Under **Cykla&Vandra → Kanalpublicering**:

- Avsändarnamn **Cykla & Vandra**, avsändare **nyhetsbrev@cyklavandra.se**. Adressen måste vara verifierad i Mailchimp, och domänen bör vara autentiserad under Website → Domains.
- Loggan och adressen i footern.
- Välj **Kategori för blogginlägg**.

## 5. Första testutskicket

1. **Utskick → Nytt utskick**, t.ex. med namnet "TEST – får raderas".
2. Fyll i ämnesrad, förhandstext och rubrik och lägg till 2 resor. Bocka bara i **Mailchimp** och **Blogginlägg**.
3. **Förhandsgranska nyhetsbrevet** och kontrollera layouten.
4. **Spara och skapa utkast.** Det ska komma två gröna rutor med länkar.
5. Klicka **Öppna i Mailchimp ↗** och kontrollera:
   - [ ] Kampanjen är ett **utkast** (Draft) till rätt lista.
   - [ ] Avsändare, ämnesrad och förhandstext är rätt, och å/ä/ö och emoji ser rätt ut.
   - [ ] Mailchimps checklista är grön. **Viktigt:** om den varnar för saknad adress i footern (merge-taggen för adress), säg till. Footern har adressen som vanlig text för att Gmail inte ska göra den till en blå länk.
   - [ ] Mailchimp lägger inte till någon egen extra footer.
   - [ ] **Send a test** till er själva. Öppna testet i Gmail och Outlook och på mobilen. Bilderna ska synas, "Läs mer" ska leda till rätt resa och avprenumerationslänken ska fungera.
6. Ändra något i utskicket och klicka **Spara och skapa utkast** igen. Samma kampanj ska uppdateras, utan någon kopia i Mailchimp.
7. Radera testkampanjen i Mailchimp och testinlägget i WordPress (utkast under Inlägg).

## 6. WPML (svenska och norska)

- [ ] Ställ språkväljaren i adminfältet på **Norsk** och öppna ett utskick. Reselistan ska fortfarande visa de **svenska** resorna.
- [ ] Ett nytt blogginlägg från ett utskick ska hamna på **svenska** under Inlägg.
- [ ] Under **WPML → Inställningar → Översättning av inläggstyper** ska *Utskick* vara "Översätts inte". Det styrs av `wpml-config.xml`.

## 7. /aktuellt, Linktree och Instagram-bion

- [ ] Gå till `https://cyklavandra.se/aktuellt`. Den ska leda till senaste blogginlägget från ett utskick. Innan det finns något sådant leder den till senaste blogginlägget över huvud taget.
- [ ] Om sajten har en cache (t.ex. webbhotellets): kontrollera att /aktuellt byter mål när ett nytt inlägg publiceras. Tillägget skickar "no-cache", men vissa cachar måste få adressen undantagen.
- **Linktree:** Lägg till länken **Aktuellt** → `https://cyklavandra.se/aktuellt` och dra den överst. Den behöver aldrig ändras igen.
- **Instagram-bion:** Instagram-appen → Redigera profil → Länkar → Lägg till extern länk → `https://cyklavandra.se/aktuellt` med titeln **Aktuellt**. Instagram-texterna hänvisar dit ("länken i vår bio → Aktuellt").

## 8. Behörighet

Logga in som Sarah (Redaktör) och kontrollera att hon ser **Cykla&Vandra → Utskick** och kan skapa utkast, men inte **Cykla&Vandra → Kanalpublicering**.

## 9. Facebook och Instagram

Se [meta-installation.md](meta-installation.md). Utan Meta-anslutning fungerar utkasten ändå: bildtext och beskurna bilder går att kopiera eller ladda ner och lägga upp för hand.
