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

Um zu vermeiden, dass erste Besucher eine langsamere Seite erleben, kannst du den Cache vorher "aufwärmen". Da das Critical CSS durch JavaScript generiert wird, benötigst du einen Headless-Browser, der die Seiten öffnet und das JavaScript ausführt.

### Mit Puppeteer (Node.js)

Hier ist ein Beispiel mit Puppeteer, das unterschiedliche Viewports simuliert:

```javascript
// cache-warmer.js
const puppeteer = require('puppeteer');
const fs = require('fs');

// URLs zum Aufwärmen - entweder aus Datei oder Array
const urls = [
  'https://deine-website.de/',
  'https://deine-website.de/kontakt',
  'https://deine-website.de/ueber-uns',
  // Weitere URLs...
];

// Zu simulierende Viewports
const viewports = [
  { name: 'xs', width: 375, height: 667 },
  { name: 'md', width: 768, height: 1024 },
  { name: 'xl', width: 1280, height: 800 },
];

async function warmCache() {
  const browser = await puppeteer.launch();
  
  console.log('Cache-Warming gestartet...');
  
  for (const url of urls) {
    console.log(`\nVerarbeite URL: ${url}`);
    
    for (const viewport of viewports) {
      console.log(`  - Viewport: ${viewport.name} (${viewport.width}x${viewport.height})`);
      
      const page = await browser.newPage();
      
      // Viewport setzen
      await page.setViewport({ 
        width: viewport.width, 
        height: viewport.height 
      });
      
      // User-Agent setzen für bessere Kompatibilität
      await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.4896.127 Safari/537.36');
      
      // Seite laden und warten, bis das Netzwerk inaktiv ist
      await page.goto(url, { 
        waitUntil: 'networkidle2',
        timeout: 60000 // 60 Sekunden Timeout
      });
      
      // Warten, damit das JavaScript Zeit hat, den CSS-Cache zu generieren
      console.log('    Warte auf CSS-Extraktion...');
      await page.waitForTimeout(5000);
      
      await page.close();
    }
  }
  
  await browser.close();
  console.log('\nCache-Warming abgeschlossen!');
}

warmCache().catch(err => {
  console.error('Fehler beim Cache-Warming:', err);
  process.exit(1);
});
```

Installation und Ausführung:

```bash
# Puppeteer installieren
npm install puppeteer

# Script ausführen
node cache-warmer.js
```

### Mit Sitemap (Node.js mit Puppeteer)

Wenn du eine Sitemap hast, kannst du die URLs daraus extrahieren:

```javascript
const puppeteer = require('puppeteer');
const axios = require('axios');
const xml2js = require('xml2js');

// Sitemap URL
const SITEMAP_URL = 'https://deine-website.de/sitemap.xml';

// Viewports
const viewports = [
  { name: 'xs', width: 375, height: 667 },
  { name: 'xl', width: 1280, height: 800 },
];

async function getSitemapUrls() {
  const response = await axios.get(SITEMAP_URL);
  const parser = new xml2js.Parser();
  const result = await parser.parseStringPromise(response.data);
  
  // URLs aus der Sitemap extrahieren
  return result.urlset.url.map(urlObj => urlObj.loc[0]);
}

async function warmCache() {
  const urls = await getSitemapUrls();
  const browser = await puppeteer.launch();
  
  console.log(`Gefundene URLs: ${urls.length}`);
  
  for (const url of urls) {
    for (const viewport of viewports) {
      console.log(`Verarbeite ${url} mit Viewport ${viewport.name}`);
      
      const page = await browser.newPage();
      await page.setViewport({ width: viewport.width, height: viewport.height });
      await page.goto(url, { waitUntil: 'networkidle2' });
      await page.waitForTimeout(5000);
      await page.close();
    }
  }
  
  await browser.close();
  console.log('Cache-Warming abgeschlossen!');
}

warmCache();
```

### Im Backend ausführen

Du könntest auch eine Funktion in das REDAXO-Backend integrieren, die einen solchen Prozess startet. Dies würde eine zusätzliche Implementierung erfordern, bei der ein PHP-Script Puppeteer oder einen ähnlichen Headless-Browser steuert.

**Hinweis:** Einfache curl-Anfragen reichen nicht aus, da sie JavaScript nicht ausführen können und somit das Critical CSS nicht generiert wird.

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
