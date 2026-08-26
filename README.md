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
  Relegation, …) hinweg
- Mehrere Verbände/Landesverbände gleichzeitig konfigurierbar
- Klick auf ein Spiel öffnet ein Modal mit Details (Ereignisse, Spielstand)
- Serverseitiges Caching, um API-Anfragen gering zu halten

## Voraussetzungen

- WordPress ≥ 5.0, PHP ≥ 7.4
- Ein eigener Saisonmanager-API-Key. Beantragung unter
  [saisonmanager.de/api-zugang](https://saisonmanager.de/api-zugang)
  (nicht-kommerzielles Vorhaben, ein Key pro Projekt/Website).

## Installation

1. Plugin-Ordner nach `wp-content/plugins/` kopieren (oder als ZIP über
   *Plugins → Installieren → Plugin hochladen* einspielen).
2. Im WordPress-Adminmenü unter **Plugins** aktivieren.
3. Unter **SM Floorball** (linkes Adminmenü) konfigurieren, siehe unten.

## Konfiguration

### API-Key

Unter **SM Floorball → Allgemeine Einstellungen** den eigenen
Saisonmanager-API-Key eintragen. Der Key wird ausschließlich serverseitig als
`X-Api-Key`-Header verwendet – er erscheint nie im Seitenquelltext oder im
Browser der Besucher:innen.

### Verbände (optional)

Unter **SM Floorball → Verbände** lassen sich mehrere Landesverbände mit
eigener API-Basis-URL hinterlegen (Slug, Name, URL, optional ein
abweichender API-Key). Ohne eigenen Verbands-Key gilt der globale Key aus den
Allgemeinen Einstellungen.

### Vereine & Teams

Unter **SM Floorball → Vereine** einen Verein anlegen und seine Teams
zuordnen:

- **Team-ID** (empfohlen): die Saisonmanager-Team-ID. Ein einziger Aufruf
  deckt alle Wettbewerbe der Saison ab (Liga, Pokal, Relegation, …).
- **Liga-ID** (Legacy): eine Zeile pro Wettbewerb, Zuordnung über einen
  Team-Namens-Filter. Nur nutzen, wenn keine Team-ID verfügbar ist.

Pro Zeile nur eines von beidem ausfüllen.

## Shortcodes

| Shortcode | Beschreibung | Wichtigste Parameter |
|---|---|---|
| `[sm_tabelle liga_id="123"]` | Liga-Tabelle | `liga_id`, `verband`, `titel`, `logos` |
| `[sm_spiele liga_id="123"]` | Spielplan einer Liga | `liga_id`, `verband`, `anzahl`, `team`, `modus` (`alle`/`vergangen`/`kommend`), `titel`, `logos` |
| `[sm_naechstes_spiel liga_id="123"]` | Nächstes kommendes Spiel | `liga_id`, `verband`, `team`, `logos` |
| `[sm_letztes_spiel liga_id="123"]` | Letztes gespieltes Spiel | `liga_id`, `verband`, `team`, `logos` |
| `[sm_vereinsuebersicht verein="hannover"]` | Alle Spiele aller Teams eines Vereins | `verein` (Slug oder Name), `anzahl` |

Vollständige Beispiele inkl. Mehrfach-Verband-Nutzung stehen direkt in der
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
