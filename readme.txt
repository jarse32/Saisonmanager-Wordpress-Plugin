=== SM Floorball ===
Contributors: jarse32
Tags: floorball, sport, spielplan, ergebnisse, tabelle
Requires at least: 5.0
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 1.3.0
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

Ein eigener Saisonmanager-API-Key ist erforderlich (kostenlos, nicht-kommerzielle
Nutzung) - Details siehe `README.md` im Repository.

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

= 1.3.0 =
Verbände-Konfiguration entfällt. Bitte nach dem Update unter SM Floorball ->
Vereine die Club-ID pro Verein eintragen und "Teams laden" klicken.
