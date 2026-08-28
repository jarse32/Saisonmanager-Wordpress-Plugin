# SM Floorball

WordPress-Plugin für Floorball-Vereine: zeigt Spielpläne, Ergebnisse und
Tabellen aus der [Saisonmanager](https://saisonmanager.de)-API auf beliebigen
WordPress-Seiten an – per Shortcode, ohne eigene Programmierung.

> **Inoffizielles Community-Projekt.** Dieses Plugin steht in keiner
> Verbindung zum Saisonmanager oder zum Floorball Verband Deutschland e. V.
> Datenquelle: Saisonmanager / Floorball Verband Deutschland e. V.

## Funktionen

- Liga-Tabelle, Spielplan, nächstes/letztes Spiel per Shortcode einbinden
- Vereinsübersicht: alle anstehenden und gespielten Spiele **aller Teams
  eines Vereins** auf einen Blick, über alle Wettbewerbe (Liga, Pokal,
  Relegation, …) hinweg – automatisch ermittelt über die Club-ID
- Team-Finder: Club-IDs, Team-IDs und Liga-IDs finden, ohne im
  Saisonmanager-Frontend danach suchen zu müssen
- Klick auf ein Spiel öffnet ein Modal mit Details (Ereignisse, Spielstand)
- Serverseitiges Caching, um API-Anfragen gering zu halten

## Voraussetzungen

- WordPress ≥ 5.0, PHP ≥ 7.4
- Ein eigener Saisonmanager-API-Key. Beantragung unter
  [saisonmanager.de/api-zugang](https://saisonmanager.de/api-zugang)
  (nicht-kommerzielles Vorhaben, ein Key pro Projekt/Website).

## Installation

Dieses Plugin wird nicht über das offizielle WordPress-Plugin-Verzeichnis
vertrieben, lässt sich aber direkt von GitHub installieren und danach ganz
normal über WordPress aktualisieren.

1. Auf der [GitHub-Seite des Projekts](https://github.com/jarse32/Saisonmanager-Wordpress-Plugin)
   auf **Code → Download ZIP** klicken.
2. In WordPress: **Plugins → Installieren → Plugin hochladen**, die
   heruntergeladene ZIP-Datei auswählen, hochladen, aktivieren.
   (Alternativ: Ordner per SFTP nach `wp-content/plugins/` kopieren.)
3. Unter **SM Floorball** (linkes Adminmenü) konfigurieren, siehe unten.

### Updates

Das Plugin bringt einen [Update-Checker](https://github.com/YahnisElsts/plugin-update-checker)
mit, der WordPress direkt gegen den `main`-Branch dieses Repositories prüfen
lässt (MIT-lizenziert, liegt in `vendor/plugin-update-checker/`). Sobald hier
eine neue Version veröffentlicht wird, zeigt WordPress ganz normal unter
**Plugins** ein "Update verfügbar" an – kein manuelles Neu-Herunterladen
nötig. Ein Update-Check lässt sich über den Link *"Nach Updates suchen"* auf
der Plugins-Seite jederzeit sofort auslösen (sonst alle 12 Std. automatisch).

## Konfiguration

### API-Key

Unter **SM Floorball → Allgemeine Einstellungen** den eigenen
Saisonmanager-API-Key eintragen. Der Key wird ausschließlich serverseitig als
`X-Api-Key`-Header verwendet – er erscheint nie im Seitenquelltext oder im
Browser der Besucher:innen.

### Team-Finder

Club-IDs und Team-IDs kennt man selten auswendig. Unter
**SM Floorball → Team-Finder** lässt sich nach Vereinsname (Teilstring
reicht, z. B. "Eiche Horn") oder Saisonmanager-Club-ID suchen – durchsucht
wird über alle bekannten Spielbetriebsstellen hinweg, optional für eine
bestimmte Saison-ID (leer = aktuelle Saison; nützlich für historische
Team-IDs vergangener Saisons, da sich Team-IDs von Saison zu Saison ändern
können). Das Ergebnis zeigt die gefundene Club-ID sowie alle Teams mit ihrer
ID zum Reinkopieren. Die Buttons **"Aktuelle Saison"**/**"Vorsaison"** neben
dem Saison-ID-Feld füllen die richtige Nummer automatisch aus (Saisons sind
fortlaufend nummeriert) – so muss man die Saison-ID nicht selbst
heraussuchen, um z. B. Team-IDs der Vorsaison für historische Ergebnisse in
der Vereinsübersicht zu finden.

### Vereine & Teams

Unter **SM Floorball → Vereine** einen Verein anlegen und die per
Team-Finder gefundene **Club-ID** eintragen. Auf "Teams laden" klicken –
das ermittelt automatisch alle Teams dieses Vereins der aktuellen Saison
inkl. ihrer Liga-IDs (praktisch, um diese direkt in `[sm_tabelle liga_id="…"]`
o. ä. auf anderen Seiten zu verwenden). Kein Zeitplan, keine automatische
Aktualisierung im Hintergrund – bei Bedarf (z. B. zu Saisonbeginn oder wenn
ein neues Team gemeldet wurde) einfach erneut klicken.

Zusätzlich lassen sich einzelne **Team-IDs manuell** ergänzen – als Fallback
für Sonderfälle, die die Club-ID-Erkennung nicht abdeckt (z. B.
Spielgemeinschaften).

## Shortcodes

| Shortcode | Beschreibung | Wichtigste Parameter |
|---|---|---|
| `[sm_tabelle liga_id="123"]` | Liga-Tabelle | `liga_id`, `titel`, `logos` |
| `[sm_spiele liga_id="123"]` | Spielplan einer Liga | `liga_id`, `anzahl`, `team`, `modus` (`alle`/`vergangen`/`kommend`), `titel`, `logos` |
| `[sm_naechstes_spiel liga_id="123"]` | Nächstes kommendes Spiel | `liga_id`, `team`, `logos` |
| `[sm_letztes_spiel liga_id="123"]` | Letztes gespieltes Spiel | `liga_id`, `team`, `logos` |
| `[sm_vereinsuebersicht verein="hannover"]` | Alle Spiele aller Teams eines Vereins | `verein` (Slug oder Name), `anzahl` |

Die `liga_id` findest du über den Team-Finder oder den "Teams laden"-Button
bei einem Verein (Spalte "Liga(en)"). Vollständige Referenz direkt in der
Admin-Oberfläche unter **SM Floorball → Shortcode-Referenz**.

## Hooks für Theme-Entwickler:innen

Über **SM Floorball → Design** lässt sich das Erscheinungsbild bereits ohne
Code anpassen (siehe oben). Für Sonderfälle, die die Design-Seite nicht
abdeckt, stehen folgende Filter/Templates zur Verfügung:

### Eigene Templates (`smf_template_path`)

`smf_render_template()` sucht jedes Template zuerst im aktiven Child-Theme,
dann im Parent-Theme, jeweils im Unterordner `saisonmanager-floorball/`,
und erst danach im Plugin selbst:

1. `wp-content/themes/<child-theme>/saisonmanager-floorball/{template}.php`
2. `wp-content/themes/<parent-theme>/saisonmanager-floorball/{template}.php`
3. `wp-content/plugins/saisonmanager-floorball/templates/{template}.php` (Default)

Die Template-Namen (ohne `.php`) entsprechen den Dateinamen in
`templates/`: `table`, `games-list`, `single-game`, `club-overview`,
`game-detail`. Am einfachsten kopierst du die Plugin-Datei als
Ausgangspunkt in dein Theme.

Für Sonderfälle (z.B. Templates aus einem anderen Plugin laden) gibt es
zusätzlich den Filter `smf_template_path`:

```php
add_filter( 'smf_template_path', function ( $file, $template, $data ) {
    if ( $template === 'table' ) {
        return '/pfad/zu/meinem/table.php';
    }
    return $file;
}, 10, 3 );
```

### Eigene Bezeichnungen (`smf_labels`)

Das Plugin hat keine eigene Textdomain (siehe Umsetzungsplan) - Texte lassen
sich stattdessen über den Filter `smf_labels` anpassen oder übersetzen:

```php
add_filter( 'smf_labels', function ( $labels ) {
    $labels['no_games_found'] = 'No games scheduled yet.';
    $labels['next_game_label'] = 'Next match';
    return $labels;
} );
```

Verfügbare Schlüssel und ihre Standardtexte:

| Schlüssel | Standardtext |
|---|---|
| `games_list_title_alle` | Alle Spiele |
| `games_list_title_vergangen` | Vergangene Spiele |
| `games_list_title_kommend` | Kommende Spiele |
| `games_list_title_default` | Spiele |
| `no_games_found` | Keine Spiele gefunden. |
| `no_table_data` | Keine Tabellendaten verfügbar. |
| `game_day_prefix` | Spieltag |
| `time_suffix` | Uhr |
| `detail_link_played` | Spielbericht |
| `detail_link_upcoming` | Details |
| `single_game_btn_played` | Spielbericht ansehen |
| `single_game_btn_upcoming` | Details ansehen |
| `next_game_label` | Nächstes Spiel |
| `last_game_label` | Letztes Spiel |
| `timeline_title` | Spielverlauf |
| `referees_title` | Schiedsrichter |
| `club_overview_upcoming_title` | Anstehende Spiele |
| `club_overview_no_upcoming` | Keine kommenden Spiele gefunden. |
| `club_overview_played_title` | Letzte Ergebnisse |
| `club_overview_no_played` | Noch keine Ergebnisse vorhanden. |
| `team_side_home` | Heim |
| `team_side_away` | Gast |
| `vs_label` | vs. |
| `vs_label_compact` | vs |

`team_side_away` z.B. auf "Auswärts" umstellen, ohne Template-Override:

```php
add_filter( 'smf_labels', function ( $labels ) {
    $labels['team_side_away'] = 'Auswärts';
    return $labels;
} );
```

Kurze Tabellenspalten-Abkürzungen (Sp/S/SV/N/Pkt) sind bewusst nicht Teil
dieses Filters - dafür reicht in der Regel eigenes CSS oder ein
Template-Override. Die Pflicht-Quellenangabe (Datenquelle Saisonmanager/FVD)
ist nicht überschreibbar, ihre Farbe zieht aber automatisch mit dem
gewählten Design mit.

### Design-Werte programmatisch anpassen (`smf_design_vars`)

Die auf **SM Floorball → Design** gespeicherte Konfiguration lässt sich vor
der CSS-Ausgabe per Filter anpassen oder ergänzen - z.B. für Werte, die die
UI nicht abdeckt:

```php
add_filter( 'smf_design_vars', function ( $vars, $config ) {
    $vars['--smf-color-primary'] = '#0055aa';
    return $vars;
}, 10, 2 );
```

`$vars` ist das Array der finalen CSS-Variablen (Name => Wert), `$config`
die gespeicherte Design-Konfiguration. Der Filter läuft bei jedem
Seitenaufruf, sollte also keine teuren Berechnungen enthalten.

## Sicherheit & Datenschutz

- Der API-Key verlässt den Server nie – alle Anfragen laufen serverseitig
  über `wp_remote_get()`.
- Es werden keine personenbezogenen Daten (Spielernamen, Kontaktdaten)
  dargestellt, nur Team-/Vereinsdaten, Spielpläne und Ergebnisse.

## Lizenz

GPL-2.0+

## Mitmachen

Pull Requests sind willkommen – insbesondere von anderen Floorball-Vereinen,
die das Plugin für ihre eigene Website nutzen möchten. Jede Installation
beantragt dabei einen eigenen Saisonmanager-API-Key.

## Entwicklung

`dev/preview.html` ist eine eigenständige HTML-Datei zum Ausprobieren des
Vereinsdesigns (Farben, Eckenradius, Schatten) direkt im Browser – ganz ohne
WordPress-Installation, einfach lokal öffnen. `dev/` ist reines
Entwicklungswerkzeug und in `.distignore` als Auslieferungs-Ausschluss
vermerkt. Achtung: GitHubs "Code → Download ZIP" wertet `.distignore` nicht
aus – beim manuellen Erstellen einer Release-ZIP die Ordner `dev/` und
`tests/` bitte von Hand weglassen.

`tests/test-design-sanitize.php` ist ein eigenständiger PHP-Test (keine
WordPress-Installation, kein Composer/PHPUnit nötig) für
`SMF_Design::sanitize()`, insbesondere dass unvollständige oder ungültige
Formular-Einsendungen die gespeicherte Konfiguration nicht stillschweigend
auf die Neutral-Defaults zurücksetzen:

```
php tests/test-design-sanitize.php
```

### Plugin Check: bewusst akzeptierte Findings

Das Plugin geht **nicht** ins WordPress.org-Verzeichnis, sondern wird über
GitHub per Plugin Update Checker (PUC) ausgeliefert (siehe unten). Ein Teil
der [Plugin Check](https://wordpress.org/plugins/plugin-check/)-Regeln passt
deshalb nicht auf dieses Projekt. Bei einem erneuten Plugin-Check-Lauf bitte
folgende Findings **nicht** erneut bewerten oder "vorsichtshalber" anfassen:

- **`plugin_updater_detected`** (2×): PUC ist unser Auslieferungsweg – auf
  wp.org verboten, für uns essenziell.
- **`readme_short_description_non_official_language`,
  `readme_description_non_official_language`**: `readme.txt` ist bewusst auf
  Deutsch verfasst, die Zielgruppe sind deutsche Floorball-Vereine.
- **Alle Findings unter `tests/`** (echo, `var_export`, fehlender
  Direktzugriffs-Schutz): reine CLI-Testskripte, die WordPress nie lädt.
- **`hidden_files`** für `.gitignore`/`.distignore`.

Die letzten beiden Punkte tauchen nur auf, weil `dev/`, `tests/`,
`.gitignore` und `.distignore` über den GitHub-Zipball auch in einer
produktiven Installation landen (siehe Hinweis zu `.distignore` oben) – der
Plugin-Check-Scanner sieht dieselben Dateien wie im Repo.

Alle anderen Findings (z.B. Escaping, Nonce-Verification, `wp_unslash()`,
direkte DB-Queries) werden regulär behoben oder – falls ein False Positive
vorliegt – mit einem begründeten `phpcs:ignore`-Kommentar an der jeweiligen
Stelle im Code versehen, nicht pauschal unterdrückt.

## Für Plugin-Maintainer: neue Version veröffentlichen

Der Update-Checker erkennt eine neue Version daran, dass der `Version`-Header
in `saisonmanager-floorball.php` höher ist als die installierte Version. Beim
Veröffentlichen eines Updates:

1. `Version:` im Datei-Header **und** die Konstante `SMF_VERSION` in
   `saisonmanager-floorball.php` erhöhen.
2. `Stable tag:` in `readme.txt` auf denselben Wert setzen und einen
   `== Changelog ==`-Eintrag ergänzen (wird Nutzer:innen beim Update
   angezeigt).
3. Nach `main` pushen.

WordPress-Installationen mit dem Plugin zeigen daraufhin (spätestens nach
12 Std., sofort über "Nach Updates suchen") ein reguläres Update an.
