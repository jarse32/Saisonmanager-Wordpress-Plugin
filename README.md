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
- Optionale Livestream-/Aufzeichnungs-Einbettung (YouTube/Twitch) im
  Spieldetail-Modal und als eigenständiger großer Player (`sm_livestream`),
  datenschutzfreundlich per Zwei-Klick-Lösung mit optional merkbarer
  Einwilligung oder als reiner Link (Standard), auch ganz abschaltbar
- Serverseitiges Caching, um API-Anfragen gering zu halten

## Voraussetzungen

- WordPress ≥ 5.3 (wegen `wp_date()`, DST-korrekte Anzeige von Spieldaten), PHP ≥ 7.4
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

## Ausfallverhalten

Ist `saisonmanager.de` nicht erreichbar, soll die eigene Seite trotzdem
vollständig und schnell laden – dafür sorgen drei Mechanismen, die
zusammenspielen:

1. **Kurzer Timeout** statt langem Warten: Unter **SM Floorball →
   Allgemeine Einstellungen** einstellbar (3/5/6/8/10 Sekunden, Standard 6).
   Ein einzelner hängender Request blockiert den Seitenaufbau also nur
   wenige Sekunden, nicht 15 wie in älteren Versionen.
2. **Circuit Breaker pro Verbandsserver**: Nach 3 Fehlversuchen in Folge
   (Netzwerkfehler, Timeout, HTTP ≥ 500) wird für 2 Minuten gar nicht mehr
   versucht, eine Verbindung aufzubauen – jeder weitere Shortcode auf
   derselben Seite kostet dann keine zusätzliche Zeit mehr. HTTP 429
   (Ratelimit) öffnet den Breaker sofort für die vom Server vorgegebene
   Zeit (`Retry-After`, gedeckelt auf 15 Minuten). Eine einzelne falsche
   Liga-/Team-ID (HTTP 4xx) löst den Breaker bewusst **nicht** aus – sonst
   würde ein Tippfehler alle anderen Shortcodes mit ausbremsen.
3. **Notreserve (Langzeit-Spiegel)**: Jede erfolgreiche Antwort von Tabellen,
   Spielplänen, Liganamen und Team-Spielplänen wird zusätzlich bis zu 7 Tage
   gespeichert. Ist der Server nicht erreichbar, wird diese zuletzt bekannte
   Antwort ausgeliefert, mit einem dezenten Hinweis auf den Stand ("Stand:
   Freitag, 11.09.2026, 18:30 Uhr – der Verbandsserver liefert gerade keine
   aktuellen Daten."). Bewusst **ausgeschlossen** sind Spieldetails
   (`[sm_naechstes_spiel]`/`[sm_letztes_spiel]`/Spieldetail-Modal beim Klick
   auf ein Spiel) und die Scorerliste (`[sm_scorer]`), da diese
   personenbezogene Daten (Spieler-/Schiedsrichternamen) enthalten können –
   hier bleibt es bei der bisherigen kurzzeitigen Zwischenspeicherung
   (Cache-Dauer, siehe unten), nichts davon liegt länger als nötig in der
   Datenbank.

Sind weder frische noch gespiegelte Daten vorhanden, erscheint statt einer
leeren Tabelle/eines leeren Spielplans nur ein freundlicher Hinweis.
Administrator:innen sehen zusätzlich die technischen Details (HTTP-Code,
URL, Breaker-Status) – für alle anderen bleibt es bei einem allgemeinen
Satz, ohne Serverinterna preiszugeben.

Unter **SM Floorball → Allgemeine Einstellungen** zeigt der Block "Status"
den aktuellen Zustand (Verbandsserver erreichbar/gesperrt, letzter Ausfall,
Anzahl und Alter der Notreserve-Einträge). Zwei getrennte Knöpfe im
Cache-Bereich: **"Frische-Cache leeren"** (wie bisher) und **"Cache
vollständig zurücksetzen"** (löscht zusätzlich die Notreserve – mit
Sicherheitsabfrage, da damit die Ausfallabsicherung bis zum nächsten
erfolgreichen Abruf entfällt).

Die **Cache-Dauer** (Stufe 1, "frischer" Cache) ist ebenfalls eine
Auswahlliste (5/10/15/30/60 Minuten, neuer Standard 10 Minuten statt bisher
5) – der Saisonmanager-API-Key hat serverseitig ohnehin rund 10 Minuten
Verzögerung, kürzeres Cachen liefert also keine aktuelleren Daten, nur mehr
Anfragen an den Verbandsserver.

## Shortcodes

| Shortcode | Beschreibung | Wichtigste Parameter |
|---|---|---|
| `[sm_tabelle liga_id="123"]` | Liga-Tabelle | `liga_id`, `titel`, `logos`, `logo_groesse`, `hervorheben` |
| `[sm_spiele liga_id="123"]` | Spielplan einer Liga | `liga_id`, `anzahl`, `team`, `modus` (`alle`/`vergangen`/`kommend`), `titel`, `logos`, `namen`, `logo_groesse`, `hervorheben`, `live_badge` |
| `[sm_naechstes_spiel liga_id="123"]` | Nächstes kommendes Spiel | `liga_id`, `team`, `logos`, `namen`, `logo_groesse`, `hervorheben` (ohne sichtbaren Effekt), `live_badge` |
| `[sm_letztes_spiel liga_id="123"]` | Letztes gespieltes Spiel | `liga_id`, `team`, `logos`, `namen`, `logo_groesse`, `hervorheben` (ohne sichtbaren Effekt), `live_badge` |
| `[sm_spiel_duo liga_id="123"]` | Nächstes und letztes Spiel nebeneinander in einem gemeinsamen Grid | wie `sm_naechstes_spiel`/`sm_letztes_spiel`, zusätzlich `reihenfolge` (`naechstes-zuerst`/`letztes-zuerst`, Standard `naechstes-zuerst`) |
| `[sm_vereinsuebersicht verein="hannover"]` | Alle Spiele aller Teams eines Vereins | `verein` (Slug oder Name), `anzahl`, `namen`, `logo_groesse`, `hervorheben` (Standard `false`), `live_badge` |
| `[sm_scorer team_id="6754"]` | Scorerliste (Punkteliste) eines Teams | `team_id` (Pflicht), `anzahl`, `spalten` (`voll`/`kompakt`), `namen` (`voll`/`abgekuerzt`), `titel`, `summe` |
| `[sm_livestream team_id="6754"]` | Großer Livestream-/Aufzeichnungs-Player für das laufende, sonst nächste Spiel eines Teams | `team_id` (Pflicht), `nach_spielende` (`aufzeichnung`/`ausblenden`), `titel`, `hinweis` |

Die `liga_id`/`team_id` findest du über den Team-Finder oder den "Teams
laden"-Button bei einem Verein (Spalte "Liga(en)" bzw. Team-ID).
Vollständige Referenz direkt in der Admin-Oberfläche unter
**SM Floorball → Shortcode-Referenz**. `[sm_scorer]` zeigt dauerhaft
Klarnamen von Spieler:innen an – siehe
[Personenbezogene Daten](#personenbezogene-daten) weiter unten, bevor du
den Shortcode einsetzt.

### Teamnamen und Logogröße

`namen` (`true`/`false`, Standard `true`) und `logo_groesse` (`klein`/
`mittel`/`gross`/`sehr_gross`, Standard `mittel`) steuern die Darstellung bei
`sm_spiele`, `sm_naechstes_spiel`, `sm_letztes_spiel` und
`sm_vereinsuebersicht`. `mittel` entspricht exakt der bisherigen Größe,
bestehende Einbettungen ohne diese Attribute sehen unverändert aus.

`sm_tabelle` kennt nur `logo_groesse`, kein `namen` – die Ligatabelle listet
üblicherweise viele verschiedene Vereine statt mehrerer Teams desselben
Vereins, ein Ausblenden des Namens brächte dort keinen Nutzen.

Ist `logos="false"` gesetzt, wird `namen` unabhängig vom übergebenen Wert auf
`true` erzwungen – sonst wäre die Begegnung ohne Logo und ohne Namen leer.
Unabhängig davon bleibt der Name pro Team auch bei `namen="false"` sichtbar,
wenn für genau dieses eine Team kein Logo vorliegt.

**Wichtig bei mehreren eigenen Teams mit demselben Vereinslogo** (z.B. 1. und
2. Mannschaft): `namen="false"` macht solche Begegnungen am identischen Logo
nicht mehr unterscheidbar – das Attribut eignet sich nur, wenn die
gegenüberstehenden Teams tatsächlich unterschiedliche Logos haben. Für den
Fall gleicher Vereinslogos wurde stattdessen das Abschneiden langer
Teamnamen (z.B. „TV Eiche Horn…") in `sm_spiele` und `sm_vereinsuebersicht`
entfernt – Namen brechen jetzt mehrzeilig um, statt per Ellipsis
abgeschnitten zu werden.

Beispiel für eine Startseite mit größeren Logos:
`[sm_vereinsuebersicht verein="hannover" logo_groesse="gross"]`

### Nächstes und letztes Spiel nebeneinander (sm_spiel_duo)

`[sm_naechstes_spiel]` und `[sm_letztes_spiel]` einzeln nebeneinander zu
platzieren (z.B. in zwei Theme-Spalten) funktioniert weiterhin, beide Karten
sind seit dieser Version auch unabhängig voneinander gleich hoch, mit Button
auf gleicher Höhe. `[sm_spiel_duo liga_id="123"]` fasst beide zusätzlich in
einem gemeinsamen Grid zusammen und ist der empfohlene Weg für eine
Nebeneinander-Darstellung:

```
[sm_spiel_duo liga_id="123" team="Eichehorn"]
```

Bricht unterhalb von ca. 576px Grid-Breite automatisch untereinander um
(abhängig von der tatsächlichen Breite des Grids selbst, nicht vom
Fenster/Bildschirm – z.B. bleibt es in einer schmalen Sidebar auch auf einem
breiten Bildschirm gestapelt). `reihenfolge="letztes-zuerst"` vertauscht die
Reihenfolge im Markup.

Nimmt alle Attribute von `sm_naechstes_spiel`/`sm_letztes_spiel` entgegen
(`team`, `logos`, `namen`, `logo_groesse`, `hervorheben`) und reicht sie
unverändert an beide Karten durch.

**Hinweis für Bestandsseiten mit manuell nebeneinandergesetzten
Shortcodes:** Ein sichtbarer Strich zwischen oder über den Karten ist
typischerweise kein Plugin-Markup, sondern ein im Editor zwischen die beiden
Shortcodes getippter Trenner (z.B. `[sm_naechstes_spiel] - [sm_letztes_spiel]`
in einem Absatz), den `wpautop` als eigenen Textblock umsetzt. Einfach aus
dem Editor entfernen, oder gleich auf `[sm_spiel_duo]` umsteigen, das ihn gar
nicht erst nötig macht.

### Team-Highlighting

`hervorheben` markiert das eigene Team in `sm_tabelle` und `sm_spiele`
optisch (Hintergrundton, fette Schrift, in der Tabelle zusätzlich ein
Akzentbalken am linken Rand) - drei Merkmale in der Tabelle, zwei in den
Spielkarten (kein Akzentbalken dort, der linke Kartenrand zeigt bereits den
Spielstatus), keins davon allein über Farbe kodiert, damit die Markierung
auch bei Farbfehlsichtigkeit und im Schwarzweißdruck erkennbar bleibt. Für
Screenreader trägt ein unsichtbares Textlabel dieselbe Information nach.

Drei Werte:

- **leer/nicht gesetzt** (Standard bei `sm_tabelle`/`sm_spiele`): Automatik -
  markiert werden die Team-IDs aller unter **SM Floorball → Vereine**
  konfigurierten Vereine. Kein zusätzliches Attribut nötig.
- **`false`**: keine Markierung.
- **Team-ID oder Namensfragment**: markiert genau dieses eine Team, auch
  wenn es nicht zu den konfigurierten Vereinen gehört bzw. unabhängig davon,
  welche der eigenen Teams sonst automatisch markiert würden. Praktisch,
  wenn mehrere eigene Teams in derselben Liga stehen und nur eines markiert
  werden soll.

Erkennung ist ID-basiert (Saisonmanager-`team_id`, dieselbe ID wie in der
Vereinskonfiguration/im Team-Finder) - zuverlässig, weil diese ID innerhalb
einer Liga stabil ist. Ist eine Team-ID unbekannt oder nicht eindeutig
zuordenbar, bleibt die Zeile/Karte unmarkiert statt falsch markiert zu
werden.

`sm_naechstes_spiel`/`sm_letztes_spiel` (und damit auch `sm_spiel_duo`, das
beide intern aufruft) akzeptieren `hervorheben` (kein Fehlerkasten), zeigen
aber keine Markierung - die Karte stellt ohnehin nur zwei Teams gleichzeitig
dar, „eins davon eigen" hat dort keinen Unterscheidungswert. In
`sm_vereinsuebersicht` ist der Standard `false`,
weil dort jedes gezeigte Spiel bereits eins der eigenen Teams betrifft; ein
expliziter Wert funktioniert trotzdem, z.B. um auf einer Seite nur die
1. Mannschaft zu markieren.

### LIVE-Kennzeichnung

Ein angepfiffenes, noch nicht beendetes Spiel wird in Karten und Spiellisten
(`sm_naechstes_spiel`, `sm_letztes_spiel`, `sm_spiel_duo`, `sm_spiele`,
`sm_vereinsuebersicht`) automatisch als **laufend** erkannt - abgeleitet
ausschließlich aus `started`/`ended`, **nicht** aus dem Spielbericht-Status
(ein angepfiffenes Spiel ohne angelegten Spielbericht gilt korrekt als
laufend). Bei `sm_naechstes_spiel`/`sm_spiel_duo` wechselt dabei zusätzlich
das Karten-Label von "Nächstes Spiel" auf "Läuft gerade".

**Kein erfundener Spielstand:** Hat der eigene Saisonmanager-Key keine
Echtzeit-Freigabe (Regelfall, siehe „Ausfallverhalten" oben), blendet die
API das Ergebnis eines laufenden Spiels aus, statt es veraltet zu liefern.
Die Karte zeigt in diesem Fall "Live – Ergebnis folgt" statt eines Spielstands
- niemals ein geratenes oder abgeschnittenes Ergebnis wie „0:0".

**Zeitfenster (vier Stunden):** Ein Spiel gilt nur bis zu vier Stunden nach
Anstoß als laufend. Setzt der Verband `ended` bei einem Spiel nie (z.B. weil
der Spielbericht nie abgeschlossen wurde), fällt es danach in die normale
Vergangenheits-Logik zurück und erscheint als "Letztes Spiel" statt auf
unbestimmte Zeit das tatsächlich nächste Spiel zu verdrängen.

**Staleness-Schutz:** Kommen die angezeigten Daten aus der Notreserve (der
Verbandsserver ist gerade nicht erreichbar, siehe „Ausfallverhalten"), zeigt
die Karte **keine** Live-Kennzeichnung - ein möglicherweise veralteter
Live-Stand wäre schlimmer als gar keiner. Die Karte fällt in diesem Fall
optisch komplett auf "bevorstehend" zurück, der bestehende Stand-Hinweis
bleibt die einzige Kommunikation über den veralteten Datenstand.

**Abgesagte Spiele** (`notice_type` = `"Canceled"`) erhalten unabhängig von
Datum oder Status ein "Abgesagt"-Abzeichen.

Steuerbar über die Option **SM Floorball → Allgemeine Einstellungen →
LIVE-Kennzeichnung** (Standard: an) sowie pro Einbindung über das
Shortcode-Attribut `live_badge` (`true`/`false`, Standard `true`), z.B.
`[sm_naechstes_spiel liga_id="123" live_badge="false"]`. Die Abzeichenfarben
sind über die CSS-Variablen `--smf-color-live`/`--smf-color-on-live` bzw.
`--smf-color-canceled`/`--smf-color-on-canceled` themebar (siehe „Hooks für
Theme-Entwickler:innen" unten) - nicht Teil der Design-Seite im Backend.

### Livestream-/Aufzeichnungs-Einbettung

Hat ein Verein im Saisonmanager zu einem Spiel einen Livestream- oder
Aufzeichnungs-Link hinterlegt, zeigen die Karten
(`sm_naechstes_spiel`, `sm_letztes_spiel`, `sm_spiel_duo`) dafür einen
kleinen zusätzlichen Button ("Livestream" vor/während des Spiels,
"Aufzeichnung" danach), der das Spieldetail-Modal öffnet - der eigentliche
Player erscheint dort, nicht in Spiellisten (`sm_spiele`) oder der
Vereinsübersicht (`sm_vereinsuebersicht`). Wer stattdessen einen
eigenständigen, großen Player auf einer Seite einbinden möchte (z.B. eine
"Livestream heute"-Seite), nutzt den Shortcode `sm_livestream` - siehe
[Großer Livestream-Player](#großer-livestream-player-sm_livestream) unten;
beide nutzen dasselbe Markup und dieselben Einstellungen.

**Link-Wahl:** Vor und während des Spiels `live_stream_link`, nach
Spielende `vod_link` - falls vorhanden, sonst `live_stream_link` als
Fallback (manche Vereine tragen nie eine separate Aufzeichnung ein, der
Live-Link funktioniert bei manchen Anbietern danach trotzdem weiter). Ein
abgesagtes Spiel zeigt nie einen Stream-Button, selbst wenn vorab ein Link
eingetragen war.

**Nur YouTube und Twitch werden eingebettet** - alle anderen Hosts
erscheinen als reiner "Auf &lt;Anbieter&gt; ansehen"-Link statt eines
Players, ebenso ein Kanal-Link ohne konkretes Video (z.B.
`twitch.tv/<kanalname>` oder ein YouTube-Kanal-Link) - dafür fehlt eine
verlässliche, einbettbare Video-ID. Nur `https`-Links werden überhaupt
berücksichtigt.

Steuerbar über **SM Floorball → Allgemeine Einstellungen →
Livestream-Einbettung**:

| Option        | Verhalten |
|---------------|-----------|
| **Nur Link** (Standard für Neuinstallationen) | Button öffnet das Modal, dort erscheint nur ein Link zum Anbieter - kein iframe, keine Anbieter-Anfrage beim Seitenaufruf oder Öffnen des Modals. |
| **Zwei-Klick** | Wie oben, zusätzlich bei einbettbaren Links ein Platzhalter mit Datenschutzhinweis; erst ein zweiter, bewusster Klick auf "Video laden" erzeugt das iframe (YouTube: `youtube-nocookie.com`, Twitch: `player.twitch.tv`). Vor diesem Klick lädt die Seite kein Bild, kein Skript und keine Schrift vom Anbieter. Die Einwilligung gilt standardmäßig nur für dieses eine Video und diesen einen Aufruf - kein Cookie, kein `localStorage`, keine Abhängigkeit von einem Consent-Plugin. Optional per Checkbox dauerhaft merkbar, siehe [Einwilligung merken](#einwilligung-merken) unten. Schließt man das Modal, wird ein bereits geladenes iframe entfernt, damit kein Stream im Hintergrund weiterläuft. |
| **Aus**        | Kein Button, kein Modal-Abschnitt, kein Player bei `sm_livestream`, kein zusätzlicher API-Request für Stream-Daten. |

**Zusätzlicher API-Request pro Seite:** Die Stream-Links stehen nur in
`games/{id}`, nicht im Spielplan/`teams/{id}/matches` - für Karten und für
`sm_livestream` (nicht für das Modal, das `games/{id}` ohnehin schon lädt)
braucht es also einen kurzen Zusatzabruf pro angezeigtem Spiel. Referenzieren
mehrere Shortcodes auf derselben Seite dasselbe Spiel (z.B.
`sm_naechstes_spiel` **und** `sm_spiel_duo` **und** `sm_livestream` für
dasselbe Team), löst das trotzdem nur einen Request pro tatsächlich
unterschiedlichem Spiel aus - im Regelfall (ein Team, "nächstes" + "letztes"
Spiel) also höchstens zwei zusätzliche Requests je Seitenaufruf. Eigene, kurze
Zwischenspeicherung (getrennt von der Cache-Dauer-Einstellung oben): 5 Minuten
für anstehende/laufende Spiele (der Link wird oft erst kurz vorher
eingetragen), 6 Stunden für bereits beendete Spiele mit Aufzeichnung.

**Datenschutz:** Sobald ein Video eingebettet wird (Modus "Zwei-Klick",
nach dem zweiten Klick), lädt der Browser der Besucher:innen Inhalte
direkt von YouTube bzw. Twitch - eine Verbindung, auf die dieses Plugin
keinen Einfluss hat. Vereine, die diese Funktion nutzen, sollten den
jeweils eingebundenen Anbieter in ihrer eigenen Datenschutzerklärung
nennen (siehe auch „Sicherheit & Datenschutz" unten). Im Standardmodus
("Nur Link") passiert das nicht - dort verlässt niemand die eigene Seite
ohne einen expliziten Klick auf einen normalen Link.

### Einwilligung merken

Standardmäßig gilt der Klick auf "Video laden" im Modus "Zwei-Klick" immer
nur für diesen einen Aufruf - beim nächsten Besuch erscheint wieder der
Platzhalter. Über **SM Floorball → Allgemeine Einstellungen → Einwilligung
merken erlauben** (Standard: **Aus**) lässt sich das ändern:

- Ist die Option an, erscheint am Platzhalter zusätzlich eine (nicht
  angehakte) Checkbox "&lt;Anbieter&gt;-Inhalte künftig immer laden". Wird
  sie beim Klick auf "Video laden" angehakt, merkt sich der Browser der
  Besucher:in diese Entscheidung **getrennt pro Anbieter** (YouTube/Twitch)
  für **12 Monate**.
- Gespeichert wird ausschließlich clientseitig in `localStorage` - kein
  Cookie, keine Übertragung an diese oder eine andere Website, keine
  Abhängigkeit von einem Consent-Plugin. Jeder Zugriff läuft in `try`/`catch`:
  ist `localStorage` nicht verfügbar (z.B. privater Modus), verhält sich
  alles wie bisher (immer erneut fragen).
- Bei künftigen Aufrufen (Modal **und** `sm_livestream`, dieselbe Logik) wird
  der Player bei einer gültigen, gespeicherten Einwilligung direkt geladen,
  ohne den Platzhalter überhaupt zu zeigen.
- Am geladenen Player erscheint dann ein kleiner Link "Automatisches Laden
  beenden" - widerruft die Einwilligung sofort und zeigt wieder den
  Platzhalter.
- Wird die Option nachträglich wieder auf "Aus" gestellt, greift das sofort:
  eine im Browser noch vorhandene ältere Einwilligung wird dann ignoriert,
  nicht nur das künftige Merken verhindert.

Nutzt ein Verein diese Funktion, sollte die Datenschutzerklärung auch die
**gespeicherte** Einwilligung erwähnen (nicht nur die Einbettung selbst) -
z.B. dass sie rein clientseitig in `localStorage` liegt, 12 Monate gilt und
sich über den Widerrufs-Link jederzeit löschen lässt.

### Großer Livestream-Player (sm_livestream)

`[sm_livestream team_id="6754"]` zeigt den Stream des für dieses Team gerade
relevantesten Spiels als eigenständigen, großen Player - z.B. für eine
Landingpage "Heute live". Nutzt `team_id` statt `liga_id` (wie `sm_scorer`):
ein Request deckt alle Wettbewerbe der Saison ab, keine Liga-ID nötig.

**Auswahl des Spiels** (automatisch, ohne dass sich am eingebundenen
Shortcode etwas ändert), in dieser Reihenfolge:

1. Das gerade laufende Spiel.
2. Die Aufzeichnung des zuletzt gespielten Spiels - unabhängig davon, wie
   lange dessen Anstoß zurückliegt, solange Stufe 3 noch nicht greift. Eine
   Woche alte Aufzeichnung schlägt also weiterhin ein Spiel, das erst in
   zwei Wochen ansteht.
3. Das nächste kommende Spiel - aber erst, wenn **beides** zutrifft: sein
   Anstoß ist höchstens **24 Stunden** entfernt, UND dafür ist bereits ein
   Stream-Link eingetragen. Ein Termin in einer Woche verdrängt die
   Aufzeichnung nicht, selbst mit bereits gesetztem Link - und ein Termin in
   einer Stunde ohne Link verdrängt sie ebenso wenig (seit 1.9.1; ersetzt
   die alte, vom Anstoß des *letzten* Spiels aus gemessene 48h-Regel).
4. Existiert daneben gar kein kommendes Spiel (z.B. zwischen den Saisons):
   die Aufzeichnung des zuletzt gespielten Spiels, ganz gleich wie alt -
   abschaltbar über `nach_spielende="ausblenden"` (wirkt nur auf diese
   letzte Stufe, nicht auf Stufe 2 - existiert ein kommendes Spiel, bleibt
   die Aufzeichnung sichtbar, bis Stufe 3 greift, unabhängig von diesem
   Attribut).

Die 24h+Link-Prüfung gilt bewusst nur für den Wechsel auf das kommende
Spiel: ein laufendes Spiel (Stufe 1) und die Aufzeichnung (Stufe 2) werden
immer ohne Weiteres angezeigt - sonst bliebe die Seite ausgerechnet in dem
Zeitraum leer, in dem der Link für das kommende Spiel erst noch eingetragen
wird.

Die Beschriftung ("Livestream" vs. "Aufzeichnung") richtet sich dabei immer
nach dem Spielstatus, nicht danach, aus welchem API-Feld der Link stammt:
ein beendetes Spiel heißt immer "Aufzeichnung", auch wenn mangels eigenem
Aufzeichnungslink auf den ursprünglichen Live-Link zurückgegriffen wird
(YouTube wandelt eine beendete Live-Übertragung z.B. automatisch in ein
normales Video an derselben URL um) - seit 1.9.1, siehe CHANGELOG.

Oberhalb des Players zeigt der Shortcode immer, um welches Spiel es geht:
Heim- und Gastteam, Datum/Uhrzeit, und bei einem laufenden Spiel zusätzlich
das LIVE-Abzeichen (dieselbe Option/Logik wie bei den Karten, siehe
[LIVE-Kennzeichnung](#live-kennzeichnung) oben, inkl. Staleness-Gate).

Attribute:

| Attribut | Werte | Standard | Bedeutung |
|---|---|---|---|
| `team_id` | Team-ID | – (Pflicht) | siehe Team-Finder |
| `nach_spielende` | `aufzeichnung` / `ausblenden` | `aufzeichnung` | Verhalten, wenn weder ein laufendes noch ein kommendes Spiel existiert |
| `titel` | freier Text | leer | Eigene Überschrift zusätzlich zum automatischen "Livestream"-/"Aufzeichnung"-Label |
| `hinweis` | `true` / `false` | `false` | Ohne verfügbaren Stream: `false` gibt nichts aus (keine leere Box), `true` zeigt einen dezenten Hinweistext |

Respektiert dieselbe Option **Livestream-Einbettung** wie die Karten: bei
"Aus" erscheint nichts (wie ohne Stream-Link), bei "Nur Link" nur ein
Link-Button, bei "Zwei-Klick" der volle Platzhalter/Player-Ablauf inkl.
[gemerkter Einwilligung](#einwilligung-merken). Ein Kanal-Link ohne
konkretes Video (siehe oben) erscheint wie im Modal als reiner Link-Button,
nie als Player.

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
`game-detail`, `scorer`. Am einfachsten kopierst du die Plugin-Datei als
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
| `scorer_list_title` | Scorerliste |
| `scorer_no_data` | Für dieses Team liegen noch keine Scorerpunkte vor. |
| `scorer_not_visible` | Die Scorerliste ist für dieses Team nicht verfügbar. |
| `scorer_names_hidden` | Die Scorerliste mit Personennamen ist in den Einstellungen deaktiviert. |
| `scorer_totals_label` | Team gesamt |
| `stream_btn_live_label` | Livestream |
| `stream_btn_vod_label` | Aufzeichnung |
| `stream_section_title_live` | Livestream |
| `stream_section_title_vod` | Aufzeichnung |
| `stream_privacy_notice` | Wird von %1$s eingebettet. Beim Laden werden Daten an %1$s übertragen. |
| `stream_reveal_btn` | Video laden |
| `stream_external_link` | Auf %s ansehen |
| `stream_remember_checkbox` | %s-Inhalte künftig immer laden |
| `stream_forget_link` | Automatisches Laden beenden |
| `livestream_no_stream_notice` | Aktuell kein Livestream verfügbar. |

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
- Keine Weitergabe an Dritte: Das Plugin ruft ausschließlich die
  konfigurierte Saisonmanager-API-URL auf, es gibt keine weiteren
  Aufrufe an Drittanbieter (Tracking, Werbung o.ä.) - **Ausnahme:** die
  optionale Livestream-/Aufzeichnungs-Einbettung (siehe „Livestream-/
  Aufzeichnungs-Einbettung" unter Shortcodes), die im Standardmodus
  ("Nur Link") aber ohnehin keine Anbieter-Inhalte einbettet.
- Keine externen Schriften oder sonstigen Fremd-Assets – Design und
  Schrift werden aus dem Plugin bzw. dem Theme der Installation
  bedient (siehe *SM Floorball → Design* im Adminmenü).

### Personenbezogene Daten

Das Plugin kann an zwei Stellen **Namen** von Spieler:innen und
Schiedsrichter:innen anzeigen:

- **Spieldetail** (Modal, öffnet sich beim Klick auf eine Spielkarte): in
  der Ereignis-Chronik ("Spielverlauf") Torschütz:innen und
  Vorlagengeber:innen bei Toren, die bestrafte Person bei Strafzeiten,
  ggf. die eingesetzten Schiedsrichter:innen. Diese Namen stammen
  unverändert aus der Saisonmanager-API zu einem einzelnen Spiel.
- **Scorerliste** (`[sm_scorer]`, siehe Shortcode-Tabelle oben): eine
  **dauerhafte, nach Saisonleistung sortierte Liste** aller Spieler:innen
  eines Teams mit Namen. Anders als beim Spieldetail ist das keine
  beiläufige Erwähnung in einem Ereignisprotokoll, sondern eine gezielte
  Zusammenstellung – datenschutzrechtlich entsprechend gewichtiger.

Diese Namen sind personenbezogene Daten im Sinne von Art. 4 DSGVO und
können auch minderjährige Spieler:innen betreffen, z.B. in Jugendligen.
Wichtig dazu:

- Das Plugin **erhebt diese Daten nicht selbst** und speichert sie nicht
  dauerhaft. Sie stammen unverändert aus der Saisonmanager-API und werden
  bei Abruf lediglich kurzzeitig serverseitig zwischengespeichert
  (WordPress-Transient-Cache, Standarddauer 10 Minuten, unter
  *SM Floorball → Einstellungen* als Auswahlliste von 5 bis 60 Minuten
  konfigurierbar) und danach automatisch verworfen. Anders als Tabellen,
  Spielpläne und Liganamen landen Spieldetails und die Scorerliste
  **bewusst nicht** im zusätzlichen 7-Tage-Langzeit-Spiegel für den
  Ausfallfall (siehe Abschnitt "Ausfallverhalten") – bei einem Ausfall des
  Verbandsservers zeigt `[sm_scorer]` dann einen Hinweis statt einer
  veralteten Namensliste.
- Die Einstellung **"Personennamen anzeigen"** unter
  *SM Floorball → Einstellungen* steuert **beide** Stellen gemeinsam
  (Spieldetail-Modal **und** `[sm_scorer]`) – ein einzelner Schalter, damit
  eine Vereinsentscheidung nicht versehentlich nur an einer Stelle greift.
  **Standard: aus.** Ist die Option deaktiviert, gelangen keine Namen ins
  ausgelieferte HTML: Im Spieldetail erscheint stattdessen die
  Trikotnummer, `[sm_scorer]` zeigt einen Hinweis statt der Liste.
  Dieser Schalter ist bewusst **nicht per Shortcode-Attribut
  übersteuerbar** – nur die Vereinsseite (Backend) darf entscheiden, ob
  Namen überhaupt erscheinen.
  - *Bestandsinstallationen* (Update von einer Version vor diesem
    Feature): Migration setzt die Option einmalig auf "an", damit sich am
    bisherigen Verhalten des Spieldetail-Modals nichts ändert. Wer die
    Scorerliste neu einsetzt, sollte an dieser Stelle bewusst
    entscheiden, ob das weiterhin gewünscht ist.
  - *Neuinstallationen* starten mit "aus" (datensparsamer Default).
- Die Einstellung **"Format der Personennamen"** (`voll` oder
  `abgekuerzt`, z.B. "Max M.", Standard: `abgekuerzt`) reduziert den
  Personenbezug, sofern Namen angezeigt werden. Im Shortcode
  `[sm_scorer]` lässt sich dieses Format pro Einbindung über
  `namen="voll"`/`namen="abgekuerzt"` überschreiben – der Schalter
  "Personennamen anzeigen" selbst bleibt davon unberührt.
- Alle übrigen vom Plugin dargestellten Daten (Team-/Vereinsnamen,
  Spielpläne, Ergebnisse, Tabellen) sind keine personenbezogenen Daten.

**Verantwortung der nachnutzenden Vereine:** Wer dieses Plugin einsetzt,
insbesondere den Shortcode `[sm_scorer]`, muss die Anzeige von Spieler-
und Schiedsrichternamen in der eigenen Datenschutzerklärung
berücksichtigen (z.B. Datenquelle, Umgang mit Betroffenenanfragen) und
braucht dafür eine eigene Rechtsgrundlage (Art. 6 DSGVO). Dieser
Abschnitt ist keine Rechtsberatung und trifft keine Aussage darüber, auf
welcher Rechtsgrundlage die Veröffentlichung dieser Daten zulässig ist –
das zu bewerten liegt bei den Website-Betreiber:innen, ggf. in
Abstimmung mit dem eigenen Datenschutzbeauftragten. Die Optionen
"Personennamen anzeigen" (aus) und "Format der Personennamen"
(abgekürzt) machen die datensparsame Nutzung zum Standard, ersetzen aber
keine eigene Prüfung.

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
