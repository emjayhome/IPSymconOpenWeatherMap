<?php

declare(strict_types=1);

// Einrichtung:
// String-Variable mit Profil "~HTML-Box" anlegen, VariablenID weiter unten eintragen
//
// ID der Instanz von "OpenWeatherMap - OneCall-Datenabruf" konfigurieren
//
// das Script auslösen bei Änderung der entsprechenden Variablen, z.B "Wetterbedingung" ('ConditionIcon')
//
//

// HTML-Box
$varID = xxxx;
// Instanz von "OpenWeatherMap - OneCall-Datenabruf"
$instID = yyyyy;

$scriptName = IPS_GetName($_IPS['SELF']) . '(' . $_IPS['SELF'] . ')';

$img_url = 'http://openweathermap.org/img/w/';

$icon = GetValueString(IPS_GetObjectIDByIdent('ConditionIcon', $instID));

$html = '<img src="' . $img_url . $icon . '.png">';

SetValueString($varID, $html);
