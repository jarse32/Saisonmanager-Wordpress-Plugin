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
