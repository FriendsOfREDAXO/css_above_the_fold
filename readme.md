#CSS Above The Fold

Das Addon verkürzt die Ladezeit einer Website. CSS-Regeln, die für
das Rendern des sichtbaren Seitenbereichs benötigt werden 
(Above the Fold), werden inline in den Head-Bereich eingebunden. 
Übrige Stylesheets werden nachträglich geladen.

Damit lassen sich hohe Wertungen bei Google Insight erreichen. 
Es hilft bei der Beseitigung von Problem mit "Render-Blocking-Contents"

##Konfiguration

Das Addon besitzt keine Konfigurationsparameter, es funktioniert vollautomatisch.
CSS-Regeln werden im Cache gespeichert. Mit System > [Cache löschen] werden
die gespeicherten CSS-Regeln verworfen.

##Funktionsbeschreibung

CSS-Regeln werden pro Artikel, pro Sprache und pro Device (Mobile, Desktop)
gespeichert.

Wenn für eine Kombination (Artikel+Sprache+Gerät) noch nichts gespeichert ist,
wird eine Javascript-Funktion im Frontend eingebunden. Sobald die Seite
komplett geladen wurde, werden die CSS-Regeln gesucht, die für den sichtbaren
Bereich benötigt werden. Per Ajax werden diese an den Server gesendet und 
dort gespeichert.

Sobald die Regeln für die entsprechende Kombination vorhanden ist,
werden diese direkt im Head-Bereich inline ausgegeben. Alle Stylesheets
werden automatisch ans Seitenende verschoben, vor dem schliessenden HTML Tag.

Die CSS-Analyse wird automatisch bei document.ready ausgeführt.
