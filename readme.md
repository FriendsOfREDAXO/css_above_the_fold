# CSS Above The Fold für REDAXO

Moin! Mit diesem AddOn werden Websites blitzschnell geladen - es identifiziert CSS für den sichtbaren Bereich einer Seite (also "above the fold"), packt es direkt in den `<head>` und lädt den Rest asynchron nach. Resultat? Bessere PageSpeed-Werte, schnellere Ladezeiten und glücklichere Nutzer!

## Features 

- **Smarter Viewport-Ansatz**: Statt der simplen mobile/desktop-Unterteilung gibt's jetzt anpassbare Breakpoints (xs, sm, md, lg, xl, xxl)
- **Unterstützung moderner CSS-Features**: CSS-Variablen, verschachtelte Media Queries, komplexe Selektoren - alles kein Problem mehr!
- **Richtig gute Performance**: Effizientere Algorithmen zur CSS-Extraktion und cleveres Caching
- **Feintuning möglich**: Selektoren können jetzt explizit ein- oder ausgeschlossen werden
- **DevOps-freundlich**: Mit dem mitgelieferten GitHub-Actions-Workflow kann der Cache automatisch warm gehalten werden

## Technische Details für Entwickler

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

## Installation in 30 Sekunden

1. Im Installer "CSS Above The Fold" installieren
2. Aktivieren
3. Profit! 🚀

## API für Entwickler

Für spezielle Anwendungsfälle gibt's diese nützlichen Methoden:

```php
use FriendsOfRedaxo\CssAboveTheFold\CssAboveTheFold;

// Manuelles asynchrones Laden einer CSS-Datei
echo CssAboveTheFold::loadCssAsync('/assets/css/special.css');

// CSS-Cache-Datei für einen bestimmten Viewport abrufen
$cachePath = CssAboveTheFold::getCacheFile('md', $articleId, $clangId);

// Cache für einen Artikel leeren
CssAboveTheFold::deleteCacheFile('xl_1_1.css');

// Gesamten Cache leeren
$deletedFiles = CssAboveTheFold::deleteAllCacheFiles();
```

## Optimierungstricks

- **CSS-Variablen im Critical CSS**: Packt wichtige CSS-Variablen in die "Immer einschließen"-Liste
- **Framework-Komponenten**: Bootstrap/Tailwind/etc. Grid-System und Typografie sollten immer eingeschlossen werden
- **Animations-CSS ausschließen**: Keyframes und Animationen aufräumen? Ab in die "Nie einschließen"-Liste
- **Viewport-Analyse**: Im Cache nachschauen, welche Viewports am häufigsten sind und darauf optimieren

## Für Performance-Nerds: Cache-Warming (Beta, aktuell nur Desktop)

Damit das Critical CSS schon vor dem ersten Besucher bereitsteht, nutzt diesen GitHub-Actions-Workflow:

```yaml
# Workflow-Datei aus .github/workflows-template kopieren
# Secrets einrichten:
# - SITEMAP_URL: https://example.com/sitemap.xml
# - WAIT_TIME: 5000
# - MAX_URLS: 0 (alle URLs)
```

Der Workflow krabbelt durch die Sitemap, öffnet jede Seite mit verschiedenen Viewport-Größen und lässt das JavaScript den CSS-Cache generieren. Mega praktisch nach Deployments!

## Warum die alte Version in die Tonne treten?

Die alte Version hatte ein paar Schwachstellen:
- Probleme mit komplexeren CSS-Strukturen
- Keine Unterstützung für moderne CSS-Features
- Primitive mobile/desktop-Unterscheidung
- Keine Möglichkeit, den Cache automatisiert zu füllen
- Manchmal verpasste sie wichtige CSS-Regeln

Version 2.0 löst all diese Probleme und bringt die Technik auf den neuesten Stand!

## Fehlerbehebung für fortgeschrittene Nutzer

- **Seltsame Darstellungsfehler?** Debug-Modus aktivieren und die REDAXO-Logs checken
- **JS-Fehler?** Die Browser-Konsole verrät mehr
- **Bestimmte Stile fehlen?** Liste der CSS-Selektoren überprüfen und ggf. zur "Immer einschließen"-Liste hinzufügen
- **Cache wird nicht erstellt?** Möglicherweise CORS-Probleme oder JS-Fehler - Debug-Modus hilft!
- **304 Responses beim Cache-Warming?** Kein Problem, der Workflow hat Cache-Busting eingebaut

## Mitmachen

PRs sind herzlich willkommen! Besonders für:
- Unterstützung weiterer CSS-Features
- Verbesserungen der Extraktion-Algorithmen
- Backend-UI-Verbesserungen
- Weitere Cache-Warming-Methoden

## Danke an alle Beteiligten!

Dieses AddOn ist ein Community-Projekt. Vielen Dank an alle, die dazu beigetragen haben!

## Autor

**Friends Of REDAXO**

* http://www.redaxo.org
* https://github.com/FriendsOfREDAXO

**Projektleitung**

[Thomas Skerbis](https://github.com/skerbis)
