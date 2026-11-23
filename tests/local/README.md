# Lokal testmiljö

WordPress med SQLite (ingen MySQL behövs) och en mock för Mailchimp och Metas Graph API.
Ingenting går till riktiga tjänster: `cvkp-mock.php` fångar alla anrop till
`*.api.mailchimp.com` och `graph.facebook.com`, svarar som API:erna och loggar till
`wp-content/cvkp-mock.log`.

```bash
W=/tmp/wp   # valfri mapp
git clone --depth 1 --branch 6.8.3 https://github.com/WordPress/wordpress $W
git clone --depth 1 https://github.com/WordPress/sqlite-database-integration /tmp/sqlite
D=$W/wp-content/plugins/sqlite-database-integration
mkdir -p $D && cp -rL /tmp/sqlite/packages/plugin-sqlite-database-integration/. $D/
sed -e "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#$D#" -e "s#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#" \
  /tmp/sqlite/packages/plugin-sqlite-database-integration/db.copy > $W/wp-content/db.php
ln -s "$PWD" $W/wp-content/plugins/cv-kanalpublicering
mkdir -p $W/wp-content/mu-plugins && cp tests/local/cvkp-mock.php $W/wp-content/mu-plugins/
```

Skapa `$W/wp-config.php` med `DB_NAME` m.fl. tomma, `define( 'CVKP_MAILCHIMP_API_KEY', 'fake-us1' );`
och `define( 'DISABLE_WP_CRON', true );`, installera med `wp_install()` och kör sedan:

```bash
W=$W php tests/local/setup.php   # aktiverar tillägget, skapar tre resor med bilder och användaren sarah (Redaktör)
W=$W php tests/local/flow.php    # hela flödet: utkast, schemaläggning, avbryt, cron, felfall
php -S localhost:8899 -t $W      # för att klicka runt (sarah/sarah)
```
