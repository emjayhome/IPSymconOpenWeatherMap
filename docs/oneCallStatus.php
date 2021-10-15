<?php

declare(strict_types=1);

// Einrichtung:
// String-Variable mit Profil "~HTML-Box" anlegen, VariablenID weiter unten eintragen
//
// ID der Instanz von "OpenWeatherMap - OneCall-Datenabruf" konfigurieren
//
// das Script auslösen bei Änderung einer Variablen, z.B "letzte Messung" ('LastMeasurement')
//
//
// die Einstellungen im Script nach Belieben anpassen

// HTML-Box
$varID = xxxx;
// Instanz von "OpenWeatherMap - OneCall-Datenabruf"
$instID = yyyyy;

$scriptName = IPS_GetName($_IPS['SELF']) . '(' . $_IPS['SELF'] . ')';

$img_url = 'http://openweathermap.org/img/w/';

$wday2name = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];

$daily_forecast_count = IPS_GetProperty($instID, 'daily_forecast_count');

$temperature = GetValueFormatted(IPS_GetObjectIDByIdent('Temperature', $instID));
$humidity = GetValueFormatted(IPS_GetObjectIDByIdent('Humidity', $instID));
$wind_speed = GetValueFormatted(IPS_GetObjectIDByIdent('WindSpeed', $instID));
$rain_1h = GetValueFormatted(IPS_GetObjectIDByIdent('Rain_1h', $instID));
$clouds = GetValueFormatted(IPS_GetObjectIDByIdent('Cloudiness', $instID));
$icon = GetValueString(IPS_GetObjectIDByIdent('ConditionIcon', $instID));

$html = '
<table>
<tr>

<td align="center" valign="top" style="width: 150px; padding: 0px; padding-left: 20px;">
' . 'aktuell' . '<br>';
if ($icon != '') {
    $html .= '
<img src="' . $img_url . $icon . '.png" style="float: left; padding-left: 17px;">';
}
$html .= '
<div style="float: right; font-size: 13px; padding-right: 17px;">
' . $temperature . '<br>
' . $humidity . '<br>
</div>
<div style="clear: both; font-size: 11px; padding: 0px">
<table>
	<tr>
	<td>' . 'Ø Wind' . '</td>
	<td>' . $wind_speed . '</td>
	</tr>
	<tr>
	<td>' . 'Regen 1h' . '</td>
	<td>' . $rain_1h . '</td>
	</tr>
	<tr>
	<td>' . 'Bewölkung' . '</td>
	<td>' . $clouds . '</td>
	</tr>
</table>
</div>
</td>
';

for ($i = 0; $i < $daily_forecast_count; $i++) {
    $pre = 'DailyForecast';
    $post = '_' . sprintf('%02d', $i);

    $timestamp = GetValueInteger(IPS_GetObjectIDByIdent($pre . 'Begin' . $post, $instID));
    $temperature_min = GetValueFormatted(IPS_GetObjectIDByIdent($pre . 'TemperatureMin' . $post, $instID));
    $temperature_max = GetValueFormatted(IPS_GetObjectIDByIdent($pre . 'TemperatureMax' . $post, $instID));
    $wind_speed = GetValueFormatted(IPS_GetObjectIDByIdent($pre . 'WindSpeed' . $post, $instID));
    $rain = GetValueFormatted(IPS_GetObjectIDByIdent($pre . 'Rain' . $post, $instID));
    $clouds = GetValueFormatted(IPS_GetObjectIDByIdent($pre . 'Cloudiness' . $post, $instID));
    $icon = GetValueString(IPS_GetObjectIDByIdent($pre . 'ConditionIcon' . $post, $instID));

    $is_today = date('d.m.Y', $timestamp) == date('d.m.Y', time());
    $weekDay = $is_today ? 'heute' : $wday2name[date('N', $timestamp) - 1];

    $html .= '
<td align="center" valign="top" style="width: 150px; padding: 0px; padding-left: 20px;">
' . $weekDay . '<br>';
    if ($icon != '') {
        $html .= '
<img src="' . $img_url . $icon . '.png" style="float: left; padding-left: 17px;">';
    }
    $html .= '
<div style="float: right; font-size: 13px; padding-right: 17px;">
' . $temperature_min . '<br>
' . $temperature_max . '<br>
</div>
<div style="clear: both; font-size: 11px; padding: 0px">
<table>
	<tr>
	<td>' . 'Ø Wind' . '</td>
	<td>' . $wind_speed . '</td>
	</tr>
	<tr>
	<td>' . 'Regen' . '</td>
	<td>' . $rain . '</td>
	</tr>
	<tr>
	<td>' . 'Bewölkung' . '</td>
	<td>' . $clouds . '</td>
	</tr>
</table>
</div>
</td>
';
}

$html .= '
</tr>
</table>';

SetValueString($varID, $html);
