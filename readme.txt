=== SM Floorball ===
Contributors: jarse32
Tags: floorball, sport, spielplan, ergebnisse, tabelle
Requires at least: 5.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.5.0
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
