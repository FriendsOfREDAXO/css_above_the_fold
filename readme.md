#Redaxo 5 Addon css_above_the_fold

#Zweck

Das Addon verkürzt die Ladezeit einer Webseite. CSS-Regeln, die für 
das Rendern des sichtbaren Seitenbereich benötigt werden 
(Above the Fold), werden inline in den Head-Bereich eingebunden. 
Übrige Stylesheets werden nachträgliche geladen.

Damit lassen sich hohe Wertungen bei Google Insight erreichen. 
Es hilft bei der Beseitigung von Problem mit "Render-Blocking-Contents"

#Konfiguration

Das Addon besitzt keine Konfigurationsparameter, es fuktioniert vollautoamtisch.
CSS-Regeln werden im Cache gespeichert. Mit System > [Cache löschen] werden
die gespeicherten CSS-Regeln verworfen.

#Funktionsbeschreibung

CSS-Regeln werden pro Artikel, pro Sprache und pro Device (Mobile, Desktop)
gespeichert.

Wenn für eine Kombination (Artikel+Sprache+Gerät) noch nicht gespeichert sind,
dann wird eine Javascript-Funktion in die Seite eingebunden. Sobald die Seite
komplett geladen ist, werden die CSS-Regeln gesucht die für den sichtbaren
Bereich benötigt werden. Per Ajax werden sie zum Server gechrieben und 
dort gespeichert.

Sobald die Regeln für eien Kombination (Artikel+Sprache+Gerät) vorhanden sind,
werden diese direkt im Head-Bereich inline ausgegeben. Alle Stylesheets
werden automatisch ans Seitenende verschoben, hinter den schliessenden HTML Tag.

Die CSS-Analyse wird 2 Sekunden nach dem Event window.onload gestartet. 
Nicht alle Browser unterstützen die Funktion (Chrome funktioniert sicher).
