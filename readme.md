# CSS Above The Fold für REDAXO

Dieses AddOn beschleunigt deine Website, indem es das kritische CSS für den sichtbaren Bereich ("above the fold") identifiziert, direkt in den `<head>` einfügt und den Rest asynchron nachlädt. Das Ergebnis sind bessere PageSpeed-Werte, schnellere Ladezeiten und eine verbesserte Nutzererfahrung.

## Features 

- **Flexibler Viewport-Ansatz**: Sechs anpassbare Breakpoints (xs, sm, md, lg, xl, xxl) für optimales CSS auf allen Geräten
- **Unterstützung für modernes CSS**: Vollständige Unterstützung von CSS-Variablen, Media Queries, verschachtelten Regeln und komplexen Selektoren
- **Optimierte Performance**: Effiziente Algorithmen zur CSS-Extraktion und intelligentes Caching
- **Anpassbare Selektoren**: Volle Kontrolle darüber, welche CSS-Selektoren immer oder nie im Critical CSS enthalten sein sollen
- **CSS-Variablen-Integration**: Automatische Extraktion und Einbindung aller CSS-Variablen aus `:root`-Selektoren
- **Beibehaltung wichtiger Regeln**: Option zum automatischen Einschließen aller CSS-Regeln mit `!important`
- **Intelligente Erkennung**: Präzise Identifikation tatsächlich sichtbarer Elemente im Viewport

## Technische Details

Unter der Haube passiert Folgendes:

1. **CSS-Erkennung beim ersten Besuch**:
   - JavaScript identifiziert alle im Viewport sichtbaren Elemente
   - Die relevanten CSS-Regeln werden aus allen Stylesheets extrahiert
   - Es werden nur die Media Queries berücksichtigt, die zum aktuellen Viewport passen
   - Das Ergebnis wird per AJAX an den Server geschickt und gecacht

2. **Bei folgenden Besuchen**:
   - Critical CSS wird direkt inline in den `<head>` eingebunden
   - Die regulären Stylesheets werden mit `<link rel="preload" ... onload="this.rel='stylesheet'">` asynchron geladen
   - Es gibt keinen FOUC (Flash of Unstyled Content) mehr!

3. **Viewport-Detection**:
   - Server-seitig via User-Agent (grundlegende Erkennung)
   - Client-seitig präzise via JavaScript

## Installation

1. Im REDAXO-Installer nach "CSS Above The Fold" suchen und installieren
2. Zum Backend-Menü "CSS Above The Fold" navigieren
3. Einstellungen nach Bedarf anpassen (die Standardeinstellungen sind bereits optimiert)
4. Die Website im Frontend aufrufen, um das erste Critical CSS zu generieren

## Einstellungen

Im Einstellungsbereich des AddOns können folgende Optionen angepasst werden:

- **AddOn aktivieren**: Schaltet die Funktionalität ein oder aus
- **CSS asynchron laden**: Wenn aktiviert, werden normale CSS-Dateien asynchron geladen
- **Debug-Modus**: Aktiviert ausführliche Logging-Informationen für die Fehlersuche
- **Wichtige Regeln bewahren**: Behält CSS-Regeln mit !important immer bei
- **CSS-Variablen einschließen**: Fügt alle `:root`-Variablen automatisch zum Critical CSS hinzu
- **Viewport-Breakpoints**: Definiere die Breiten der verschiedenen Viewport-Größen in Pixel
- **Immer einschließen**: CSS-Selektoren, die immer im Critical CSS enthalten sein sollen
- **Nie einschließen**: CSS-Selektoren, die nie im Critical CSS enthalten sein sollen

## Cache-Verwaltung

Im Cache-Verwaltungsbereich kannst du:

- Alle gespeicherten Critical CSS-Dateien einsehen
- Einzelne Cache-Dateien löschen
- Den gesamten Cache auf einmal leeren

## Optimierungstipps

Um die besten Ergebnisse mit diesem AddOn zu erzielen:

1. **Wichtige CSS-Variablen immer einschließen**: Aktiviere die Option "CSS-Variablen einschließen" oder füge `:root` zur "Immer einschließen"-Liste hinzu.

2. **Framework-Komponenten optimal nutzen**: Füge grundlegende Grid- und Typografie-Klassen deines CSS-Frameworks zur "Immer einschließen"-Liste hinzu:
   - Bootstrap: `.container`, `.row`, `.col-*`
   - Tailwind: Die wichtigsten Utility-Klassen
   - Foundation: `.grid-container`, `.grid-x`, `.cell`

3. **Animations-CSS ausschließen**: Füge Animations- und Keyframe-Selektoren zur "Nie einschließen"-Liste hinzu, um das Critical CSS schlank zu halten.

4. **Selektoren für versteckte Elemente ausschließen**: Füge Klassen wie `.hidden`, `.d-none`, `.invisible` zur "Nie einschließen"-Liste hinzu.

5. **Cache regelmäßig leeren**: Aktualisiere den Cache nach größeren Design-Änderungen, um das Critical CSS zu aktualisieren.

## API für Entwickler

Für spezielle Anwendungsfälle stellt das AddOn hilfreiche Funktionen bereit:

```php
// Manuelles asynchrones Laden einer CSS-Datei
echo rex_add_css_async('/assets/css/special.css');

// Pfad zur Cache-Datei für einen bestimmten Viewport, Artikel und Sprache abrufen
$cachePath = rex_get_critical_css_file('md', $articleId, $clangId);

// Cache für einen bestimmten Artikel leeren
rex_delete_critical_css($articleId, $clangId);

// Komplette API-Dokumentation in der Klassendokumentation
```

## Fehlerbehebung

Bei Problemen mit dem AddOn:

- **Seltsame Darstellungsfehler?** Aktiviere den Debug-Modus und überprüfe die REDAXO-Logs.
- **JS-Fehler?** Prüfe die Browser-Konsole auf JavaScript-Fehler.
- **Bestimmte Stile fehlen?** Füge die betreffenden Selektoren zur "Immer einschließen"-Liste hinzu.
- **Cache wird nicht erstellt?** Prüfe auf CORS-Probleme oder JavaScript-Fehler in der Konsole.

## Limitierungen

- Das AddOn funktioniert am besten mit Seiten, deren Design einem einheitlichen Muster folgt.
- Sehr dynamische Inhalte oder Ajax-basierte Seiten können zusätzliche Konfiguration erfordern.
- Websites mit vielen Third-Party-Scripts sollten deren CSS zur "Nie einschließen"-Liste hinzufügen.

## Mitwirken

Pull Requests sind herzlich willkommen! Besonders für:

- Verbesserungen der CSS-Extraktion
- Optimierungen für spezifische CSS-Frameworks
- Backend-UI-Erweiterungen
- Unterstützung für weitere CSS-Features

## Danke an alle Beteiligten!

Dieses AddOn ist ein Community-Projekt. Vielen Dank an alle, die dazu beigetragen haben!

## Autor

**Friends Of REDAXO**

* http://www.redaxo.org
* https://github.com/FriendsOfREDAXO

**Projektleitung**

[Thomas Skerbis](https://github.com/skerbis)

## Lizenz

MIT Lizenz, siehe [LICENSE.md](LICENSE.md)
