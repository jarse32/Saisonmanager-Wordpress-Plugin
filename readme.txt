=== SM Floorball ===
Contributors: jarse32
Tags: floorball, sport, spielplan, ergebnisse, tabelle
Requires at least: 5.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.9.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Zeigt Floorball-Spielpläne, Ergebnisse und Tabellen aus der Saisonmanager-API auf WordPress-Seiten an - per Shortcode, ohne eigene Programmierung.

== Description ==

SM Floorball ist ein inoffizielles Community-Projekt für Floorball-Vereine.
Es bindet Ligatabellen, Spielpläne und eine vereinsweite Übersicht aller
Teams über Shortcodes in beliebige WordPress-Seiten ein.

Datenquelle: Saisonmanager / Floorball Verband Deutschland e. V. Dieses
Plugin steht in keiner Verbindung zum Saisonmanager oder zum Floorball
Verband Deutschland e. V.

= Funktionen =

* Liga-Tabelle, Spielplan, nächstes/letztes Spiel per Shortcode
* Vereinsübersicht: alle anstehenden und gespielten Spiele aller Teams eines
  Vereins, automatisch über die Saisonmanager-Club-ID ermittelt
* Team-Highlighting: eigene Teams werden in Tabelle und Spielplan automatisch
  hervorgehoben, sobald ein Verein konfiguriert ist
* Darstellung pro Einbettung steuerbar: Teamnamen ein-/ausblendbar,
  Logogröße wählbar (`namen`/`logo_groesse`, siehe `README.md`)
* Team-Finder: Club-IDs, Team-IDs und Liga-IDs im Adminbereich finden
* Serverseitiges Caching, ein eigener Saisonmanager-API-Key pro Installation
* Ausfallsicher: Ist der Verbandsserver nicht erreichbar, lädt die Seite
  trotzdem schnell weiter und zeigt wo möglich zuletzt bekannte Tabellen/
  Spielpläne mit Stand-Hinweis (siehe "Ausfallverhalten" in `README.md`)

Ein eigener Saisonmanager-API-Key ist erforderlich (kostenlos, nicht-kommerzielle
Nutzung) - Details siehe `README.md` im Repository.

Hinweis zu personenbezogenen Daten: Im Spieldetail sowie im Shortcode
[sm_scorer] (Scorerliste) kann das Plugin Namen von Spieler:innen und
Schiedsrichter:innen anzeigen (Quelle: Saisonmanager-API, keine Speicherung
durch das Plugin außer kurzzeitigem Cache - diese beiden Endpunkte sind
bewusst vom Langzeit-Spiegel für den Ausfallfall ausgeschlossen). Die Anzeige
ist standardmäßig deaktiviert und lässt sich unter SM Floorball -> Einstellungen ("Personennamen
anzeigen") gemeinsam für beide Stellen ein-/ausschalten, wahlweise mit
abgekürztem Namensformat. Bestandsinstallationen behalten nach dem Update
automatisch das bisherige (aktivierte) Verhalten im Spieldetail. Details und
Hinweise zur eigenen Datenschutzerklärung siehe Abschnitt "Sicherheit &
Datenschutz" in `README.md`.

== Installation ==

Dieses Plugin wird nicht über das offizielle WordPress-Plugin-Verzeichnis
vertrieben. Installation direkt von GitHub:

1. Auf der [GitHub-Seite des Projekts](https://github.com/jarse32/Saisonmanager-Wordpress-Plugin)
   auf "Code" -> "Download ZIP" klicken.
2. In WordPress: *Plugins -> Installieren -> Plugin hochladen*, die
   heruntergeladene ZIP-Datei auswählen, hochladen, aktivieren.
3. Unter *SM Floorball* im Adminmenü den Saisonmanager-API-Key und die
   Vereine konfigurieren.

Nach der Installation zeigt WordPress automatisch "Update verfügbar", sobald
im `main`-Branch des Repositories eine neue Version veröffentlicht wird -
Updates laufen dann wie bei jedem anderen Plugin über *Plugins -> Aktualisieren*.

== Changelog ==

= 1.9.0 =
* Neu: gemerkte Einwilligung für die Zwei-Klick-Einbettung - Checkbox
  "<Anbieter>-Inhalte künftig immer laden" am Platzhalter, rein clientseitig
  in `localStorage` (kein Cookie, 12 Monate, getrennt pro Anbieter),
  widerrufbar über einen Link am geladenen Player. Steuerbar über die neue
  Einstellung "Einwilligung merken erlauben" (Standard: Aus)
* Neuer Shortcode `sm_livestream team_id="6754"`: eigenständiger, großer
  Livestream-/Aufzeichnungs-Player für ein Team (z.B. für eine eigene
  "Heute live"-Seite), inkl. Heim/Gast, Datum/Uhrzeit und LIVE-Abzeichen.
  Auswahl des Spiels: laufend > Aufzeichnung des letzten Spiels (wenn
  Anstoß höchstens 48 Std. zurückliegt) > nächstes Spiel > ältere
  Aufzeichnung (letztere abschaltbar über `nach_spielende="ausblenden"`)
* Ohne verfügbaren Stream gibt `sm_livestream` standardmäßig nichts aus
  (keine leere Box), wahlweise mit dezentem Hinweistext (`hinweis="true"`)

= 1.8.0 =
* Neu: Livestream-/Aufzeichnungs-Button in den Karten (`sm_naechstes_spiel`,
  `sm_letztes_spiel`, `sm_spiel_duo`) - öffnet das Spieldetail-Modal
* Einbettung (YouTube/Twitch) im Spieldetail-Modal über eine
  datenschutzfreundliche Zwei-Klick-Lösung: vor dem Klick keine Anfrage an
  den Anbieter, keine Cookies, keine dauerhafte Einwilligung
* Neue Einstellung "Livestream-Einbettung" unter SM Floorball ->
  Einstellungen (Zwei-Klick/Nur Link/Aus), Standard: Nur Link (öffnet den
  Stream extern statt eingebettet)
* Hinweis: Vereine, die die Einbettung nutzen, sollten den eingebundenen
  Anbieter in ihrer eigenen Datenschutzerklärung nennen (siehe `README.md`,
  Abschnitt "Livestream-/Aufzeichnungs-Einbettung")

= 1.7.1 =
* Bugfix `sm_spiel_duo`: Karten standen auf dem Handy nebeneinander statt
  gestapelt und liefen über den Bildschirmrand hinaus, wenn das Theme eine
  kleinere Root-Schriftgröße setzt (z.B. `html{font-size:62.5%}`) - die
  Umbruch-Schwelle des Karten-Layouts war in `rem` angegeben und skalierte
  sich dadurch mit

= 1.7.0 =
* Neuer Shortcode `sm_spiel_duo`: nächstes und letztes Spiel nebeneinander
  in einem gemeinsamen Karten-Layout, beide gleich hoch
* LIVE-Kennzeichnung für laufende Spiele in Karten und Spiellisten, per
  Attribut abschaltbar; eigenes `live_badge`-Attribut zur Anpassung des
  Textes
* Abgesagte Spiele werden jetzt ebenfalls gekennzeichnet (Karten und
  Spiellisten) und tauchen nicht mehr fälschlich als "letztes Spiel" auf
* Bugfix: ein laufendes Spiel konnte bis zu 2 Stunden lang weder als
  nächstes noch als letztes Spiel angezeigt werden
* Zeitzonen-Fix: Anstoßzeiten werden jetzt anhand der echten Zeitzone
  Europe/Berlin berechnet statt mit einem festen Offset, damit sie auch
  rund um die Zeitumstellung korrekt bleiben
* Voraussetzung jetzt WordPress 5.3 (bisher 5.0), wegen der für den
  Zeitzonen-Fix genutzten `wp_date()`-Funktion

= 1.6.0 =
* Neue Shortcode-Attribute `namen` (true/false) und `logo_groesse` (klein/
  mittel/gross/sehr_gross) bei `sm_tabelle`, `sm_spiele`, `sm_naechstes_spiel`,
  `sm_letztes_spiel` und `sm_vereinsuebersicht` - Standard entspricht jeweils
  exakt der bisherigen Darstellung. Lange Teamnamen brechen in `sm_spiele`
  und `sm_vereinsuebersicht` jetzt mehrzeilig um statt per Ellipsis
  abgeschnitten zu werden (z.B. "TV Eiche Horn Bremen…")
* Team-Highlighting: eigene Teams werden in `sm_tabelle` und `sm_spiele`
  automatisch hervorgehoben (Hintergrundton, fette Schrift, in der Tabelle
  zusätzlich ein Akzentbalken), sobald unter SM Floorball -> Vereine ein
  Verein konfiguriert ist - kein zusätzliches Attribut nötig. Neues Attribut
  `hervorheben` zum Abschalten oder zum gezielten Markieren eines einzelnen
  Teams (Team-ID oder Namensfragment), z.B. wenn mehrere eigene Teams in
  derselben Liga stehen

= 1.5.0 =
* Ausfallsicherheit gegen einen nicht erreichbaren Verbandsserver: kurzer,
  konfigurierbarer Timeout (Standard 6s statt bisher 15s) statt langem
  Warten, ein Circuit Breaker pro Host unterbricht nach 3 Fehlversuchen in
  Folge für 2 Minuten alle weiteren Verbindungsversuche, HTTP 429 wartet
  die vom Server vorgegebene Zeit ab
* Zusätzlicher Langzeit-Spiegel (bis zu 7 Tage) für Tabellen, Spielpläne,
  Liganamen und Team-Spielpläne: Ist der Server nicht erreichbar, wird die
  zuletzt bekannte Antwort mit einem Stand-Hinweis angezeigt statt einer
  leeren Seite. Bewusst ausgeschlossen: Spieldetails und die Scorerliste,
  da diese personenbezogene Daten (Spieler-/Schiedsrichternamen) enthalten
  können - hier bleibt es bei der bisherigen kurzzeitigen Zwischenspeicherung
* Fehlermeldungen jetzt rollengetrennt: Administrator:innen sehen technische
  Details (HTTP-Code, URL, Breaker-Status), Besucher:innen einen
  freundlichen, allgemeinen Hinweis
* Neue Einstellungen "Timeout" und "Cache-Dauer" (jetzt als Auswahlliste,
  neuer Standard 10 statt 5 Minuten - der API-Key hat serverseitig ohnehin
  diese Verzögerung, kürzeres Cachen liefert keine aktuelleren Daten)
* Neue Statusanzeige (Verbandsserver erreichbar/gesperrt, letzter Ausfall,
  Größe der Notreserve) sowie zwei getrennte Cache-Knöpfe: "Frische-Cache
  leeren" und "Cache vollständig zurücksetzen" (löscht auch die Notreserve,
  mit Sicherheitsabfrage)

= 1.4.0 =
* Neuer Menüpunkt "SM Floorball -> Design": Farben, Eckenradius,
  Schatten-Intensität, Schrift und Schriftgröße im Frontend ohne eigenes
  CSS anpassbar, mit Live-Vorschau und Presets (Eichehorn/Neutral/Dark)
* Bestehende Installationen sehen nach dem Update keine optische Änderung
  (die bisherigen Werte werden automatisch als Design übernommen)
* Templates lassen sich jetzt per Child-/Parent-Theme überschreiben
  (Unterordner "saisonmanager-floorball"), zusätzlich Filter
  smf_template_path
* UI-Texte ("Details", "Spielbericht", "Heim"/"Gast", "vs." usw.) über
  den neuen Filter smf_labels anpassbar, ohne Templates zu kopieren
* Programmatischer Filter smf_design_vars für Design-Werte, die die
  UI nicht abdeckt

= 1.3.0 =
* Verbände-Konfiguration entfernt (nur noch eine API-URL/ein Key nötig)
* Club-ID-basierte automatische Team-Erkennung inkl. Liga-IDs ("Teams laden")
* Team-Finder: Hilfe zum Auffinden der aktuellen Saison-ID / Vorsaison
* Update-Checker für Installation/Updates direkt über GitHub

= 1.2.0 =
* Eigener Saisonmanager-API-Key pro Installation statt gemeinsamem Proxy
* Team-Finder zum Auffinden von Team-IDs
* SSRF-Absicherung der Spieldetail-AJAX-Route

= 1.1.0 =
* Vereinsübersicht (alle Teams eines Vereins)

== Upgrade Notice ==

= 1.9.0 =
Keine Aktion nötig. Neu: gemerkte Zwei-Klick-Einwilligung (Standard weiter
aus) und der eigenständige Shortcode sm_livestream für einen großen
Player pro Team - beide unter SM Floorball -> Einstellungen anpassbar.

= 1.8.0 =
Keine Aktion nötig. Neu: Livestream-/Aufzeichnungs-Button in den Karten und
Einbettung im Spieldetail-Modal - Standard ist "Nur Link" (kein Embed, keine
Anbieter-Anfrage), unter SM Floorball -> Einstellungen anpassbar oder
abschaltbar.

= 1.7.1 =
Bugfix: Karten von `sm_spiel_duo` liefen auf dem Handy über den Bildschirmrand
hinaus statt zu stapeln (betraf Themes mit kleinerer Root-Schriftgröße).
Keine Aktion nötig.

= 1.7.0 =
Keine Aktion nötig. Neu: Shortcode `sm_spiel_duo`, LIVE-/Abgesagt-
Kennzeichnung in Karten und Spiellisten, Zeitzonen-Fix und ein Bugfix für
laufende Spiele. Voraussetzung jetzt WordPress 5.3 (bisher 5.0).

= 1.6.0 =
Keine Aktion nötig - das Erscheinungsbild bleibt unverändert, solange die
neuen Attribute `namen`/`logo_groesse`/`hervorheben` nicht gesetzt werden.
Ausnahme: eigene Teams werden jetzt automatisch in Tabelle und Spielplan
hervorgehoben, wenn unter SM Floorball -> Vereine ein Verein konfiguriert
ist - mit `hervorheben="false"` abschaltbar.

= 1.5.0 =
Keine Aktion nötig. Neu: Die Seite bleibt bei einem nicht erreichbaren
Verbandsserver nutzbar (kurzer Timeout, Circuit Breaker, Notreserve mit
Stand-Hinweis). Cache-Dauer wurde auf 10 Minuten Standard umgestellt.

= 1.4.0 =
Keine Aktion nötig - das Erscheinungsbild bleibt unverändert. Neu:
SM Floorball -> Design zum Anpassen von Farben/Schrift ohne eigenes CSS.

= 1.3.0 =
Verbände-Konfiguration entfällt. Bitte nach dem Update unter SM Floorball ->
Vereine die Club-ID pro Verein eintragen und "Teams laden" klicken.
