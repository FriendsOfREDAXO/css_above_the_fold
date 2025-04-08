# CSS Above The Fold für REDAXO

Dieses AddOn verkürzt die Ladezeit einer Website, indem es CSS-Regeln, die für das Rendern des sichtbaren Seitenbereichs benötigt werden (Above the Fold), inline in den `<head>`-Bereich einbindet. Übrige Stylesheets werden nachträglich asynchron geladen.

Damit lassen sich hohe Wertungen bei Google PageSpeed Insights und Lighthouse erreichen. Es hilft bei der Beseitigung von Problemen mit Render-Blocking-Contents.

## Features

- Viewport-basierte Generierung von Critical CSS (xs, sm, md, lg, xl, xxl)
- Automatische Erkennung und Extraktion des kritischen CSS
- Asynchrones Laden von CSS-Dateien nach dem Rendern der Seite
- Intelligente Cache-Funktion für bessere Performance
- Unterstützung für moderne CSS-Features (CSS-Variablen, Media Queries, @supports, etc.)
- Konfigurierbare "Immer einschließen" und "Nie einschließen" Selektoren

## Anforderungen

- REDAXO 5.18.1 oder höher
- PHP 8.1 oder höher

## Installation

1. Im REDAXO-Installer das AddOn "CSS Above The Fold" auswählen und installieren
2. AddOn aktivieren
3. Grundeinstellungen vornehmen (unter AddOns > CSS Above The Fold > Einstellungen)

## Verwendung

Das AddOn funktioniert vollautomatisch. Nach der Aktivierung wird für jede Kombination aus Artikel, Sprache und Viewport das Critical CSS beim ersten Aufruf generiert und im Cache gespeichert. Bei nachfolgenden Aufrufen wird das gespeicherte Critical CSS verwendet.

### Automatischer Modus

Die Standardverwendung ist vollautomatisch. CSS-Dateien, die im `<head>`-Bereich eingebunden sind, werden automatisch erkannt und optimiert:

1. Beim ersten Besuch einer Seite wird JavaScript eingebunden, das die sichtbaren Elemente ermittelt
2. Das CSS für diese Elemente wird extrahiert und per AJAX an den Server gesendet
3. Bei nachfolgenden Besuchen wird das gespeicherte CSS direkt in den `<head>` eingebunden
4. Die ursprünglichen CSS-Dateien werden asynchron geladen, um das Rendering nicht zu blockieren

### Manueller Modus

Falls du CSS-Dateien manuell asynchron laden möchtest, kannst du folgende Methode verwenden:

```php
<?php
// Manuelles asynchrones Laden einer CSS-Datei
use FriendsOfRedaxo\CssAboveTheFold\CssAboveTheFold;
echo CssAboveTheFold::loadCssAsync('/assets/css/style.css');
?>
```

## Konfiguration

### Allgemeine Einstellungen

- **AddOn aktivieren**: Aktiviert oder deaktiviert die Funktionalität des AddOns
- **CSS asynchron laden**: Lädt die ursprünglichen CSS-Dateien asynchron nach dem Rendern der Seite
- **Debug-Modus**: Aktiviert ausführliche Logging-Informationen für die Fehlersuche

### Viewport-Breakpoints

Das AddOn verwendet standardmäßig folgende Viewport-Breakpoints:

- **xs**: Extra Small (Mobile) - 375px
- **sm**: Small (Kleines Tablet) - 640px
- **md**: Medium (Tablet) - 768px
- **lg**: Large (Kleiner Desktop) - 1024px
- **xl**: Extra Large (Desktop) - 1280px
- **xxl**: Extra Extra Large (Großer Desktop) - 1536px

Diese Werte können in den Einstellungen angepasst werden.

### Selektor-Einstellungen

- **Immer einschließen**: CSS-Selektoren, die immer im Critical CSS enthalten sein sollen (einer pro Zeile)
- **Nie einschließen**: CSS-Selektoren, die nie im Critical CSS enthalten sein sollen (einer pro Zeile)

## Cache-Verwaltung

Das generierte Critical CSS wird im Cache-Verzeichnis des AddOns gespeichert. Du kannst den Cache über die Einstellungsseite verwalten:

- Einzelne Cache-Dateien löschen
- Gesamten Cache löschen (alle Dateien werden dann neu generiert)

## "Aufwärmen" des Caches

Um zu vermeiden, dass erste Besucher eine langsamere Seite erleben, kannst du den Cache vorher "aufwärmen". Hier ist ein Beispiel für ein Bash-Skript, das deine Sitemap abarbeitet:

```bash
#!/bin/bash
# CSS Above The Fold Cache Warmer

# Sitemap URL
SITEMAP_URL="https://deine-website.de/sitemap.xml"

# URLs aus der Sitemap extrahieren
URLS=$(curl -s $SITEMAP_URL | grep -oP '(?<=<loc>).*(?=</loc>)')

# Jeden URL aufrufen
for URL in $URLS; do
    echo "Warming cache for: $URL"
    curl -s -A "Mozilla/5.0 (X11; Linux x86_64)" $URL > /dev/null
    # Kurze Pause, um den Server nicht zu überlasten
    sleep 2
done

echo "Cache warming complete."
```

## Wie es technisch funktioniert

1. **Erkennung**: Bei einem neuen Besuch identifiziert das AddOn den Viewport basierend auf dem User-Agent
2. **Critical CSS Extraktion**:
   - Ein JavaScript-Skript ermittelt alle im Viewport sichtbaren Elemente
   - Für jedes Element werden die zugehörigen CSS-Regeln aus allen Stylesheets extrahiert
   - Medienabfragen, die dem aktuellen Viewport entsprechen, werden ebenfalls berücksichtigt
3. **Datenübertragung**: Das extrahierte CSS wird per AJAX an den Server gesendet und in einer Datei gespeichert
4. **Auslieferung**: Bei nachfolgenden Besuchen wird das gespeicherte CSS inline in den `<head>` eingefügt
5. **Asynchrones Laden**: Die ursprünglichen CSS-Dateien werden asynchron geladen, um das Rendering nicht zu blockieren

## Tipps für die Optimierung

- Definiere wichtige globale Stile in "Immer einschließen", z.B. `.container`, Typografie-Klassen, etc.
- Platziere selten sichtbare oder unwichtige Stile in "Nie einschließen"
- Prüfe den Cache regelmäßig, um ungewöhnlich große CSS-Dateien zu identifizieren
- Leere den Cache nach größeren Design-Änderungen komplett

## Bekannte Einschränkungen

- Das AddOn kann keine externen CSS-Dateien von anderen Domains verarbeiten (CORS-Beschränkungen)
- Die serverseitige Viewport-Erkennung ist eine Schätzung und kann nicht so präzise sein wie die clientseitige Erkennung
- Animationen und Keyframes werden derzeit nicht im Critical CSS berücksichtigt

## Lizenz

MIT License

## Credits

Dieses AddOn ist eine vollständige Neuentwicklung des ursprünglichen CSS Above The Fold AddOns von Friends Of REDAXO. Die neue Version nutzt moderne PHP- und JavaScript-Techniken sowie einen verbesserten viewport-basierten Ansatz.

## Hinweise zur Performance

Das CSS Above The Fold AddOn bringt deutliche Performance-Verbesserungen für deine Website:

1. **Erste bedeutende Inhalte (FCP)**: Durch das Inline-Laden des kritischen CSS wird der First Contentful Paint deutlich beschleunigt.
2. **Zeit bis zur Interaktivität (TTI)**: Da CSS das Rendering nicht mehr blockiert, ist die Seite schneller interaktiv.
3. **PageSpeed Insights**: Die Punktzahl in Google PageSpeed Insights und Core Web Vitals verbessert sich erheblich.

## Fehlerbehebung

### Das Critical CSS wird nicht erstellt
- Prüfe, ob JavaScript im Browser aktiviert ist
- Prüfe, ob die Seite keine JavaScript-Fehler enthält, die die Ausführung blockieren
- Aktiviere den Debug-Modus und prüfe die REDAXO-Logs auf Fehler

### Bestimmte Stile fehlen im Critical CSS
- Füge die fehlenden Selektoren zur "Immer einschließen"-Liste hinzu
- Prüfe, ob die Elemente wirklich im sichtbaren Bereich sind
- Bei komplexen Selektoren können manchmal Teile übersehen werden

### Nach Aktualisierung des CSS erscheint noch immer das alte Design
- Lösche den Cache für die betroffenen Seiten
- Führe ein vollständiges Leeren des Caches durch
- Prüfe, ob Browsercache oder CDN alte Versionen zwischenspeichern

## Support

Bei Fragen oder Problemen stehen folgende Ressourcen zur Verfügung:

- [GitHub-Issues](https://github.com/FriendsOfREDAXO/css_above_the_fold/issues)
- [REDAXO-Forum](https://friendsofredaxo.github.io/community/)

## Mitwirken

Beiträge zum AddOn sind herzlich willkommen! Wenn du Verbesserungen oder Fehlerbehebungen beitragen möchtest:

1. Fork das Repository
2. Erstelle einen Feature-Branch (`git checkout -b feature/AmazingFeature`)
3. Commit deine Änderungen (`git commit -m 'Add some AmazingFeature'`)
4. Push zum Branch (`git push origin feature/AmazingFeature`)
5. Öffne einen Pull Request

## Danksagung

Vielen Dank an alle Mitwirkenden und Tester, die zur Entwicklung dieses AddOns beigetragen haben.
