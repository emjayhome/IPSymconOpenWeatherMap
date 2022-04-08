<?php

declare(strict_types=1);

trait OpenWeatherMapLocalLib
{
    public static $IS_INVALIDCONFIG = IS_EBASE + 1;
    public static $IS_SERVERERROR = IS_EBASE + 2;
    public static $IS_HTTPERROR = IS_EBASE + 3;
    public static $IS_INVALIDDATA = IS_EBASE + 4;

    public static $STATUS_INVALID = 0;
    public static $STATUS_VALID = 1;
    public static $STATUS_RETRYABLE = 2;

    private function GetFormStatus()
    {
        $formStatus = [];
        $formStatus[] = ['code' => IS_CREATING, 'icon' => 'inactive', 'caption' => 'Instance getting created'];
        $formStatus[] = ['code' => IS_ACTIVE, 'icon' => 'active', 'caption' => 'Instance is active'];
        $formStatus[] = ['code' => IS_DELETING, 'icon' => 'inactive', 'caption' => 'Instance is deleted'];
        $formStatus[] = ['code' => IS_INACTIVE, 'icon' => 'inactive', 'caption' => 'Instance is inactive'];
        $formStatus[] = ['code' => IS_NOTCREATED, 'icon' => 'inactive', 'caption' => 'Instance is not created'];

        $formStatus[] = ['code' => self::$IS_INVALIDCONFIG, 'icon' => 'error', 'caption' => 'Instance is inactive (invalid configuration)'];
        $formStatus[] = ['code' => self::$IS_SERVERERROR, 'icon' => 'error', 'caption' => 'Instance is inactive (server error)'];
        $formStatus[] = ['code' => self::$IS_HTTPERROR, 'icon' => 'error', 'caption' => 'Instance is inactive (http error)'];
        $formStatus[] = ['code' => self::$IS_INVALIDDATA, 'icon' => 'error', 'caption' => 'Instance is inactive (invalid data)'];

        return $formStatus;
    }

    private function CheckStatus()
    {
        switch ($this->GetStatus()) {
            case IS_ACTIVE:
                $class = self::$STATUS_VALID;
                break;
            case self::$IS_SERVERERROR:
            case self::$IS_HTTPERROR:
            case self::$IS_INVALIDDATA:
                $class = self::$STATUS_RETRYABLE;
                break;
            default:
                $class = self::$STATUS_INVALID;
                break;
        }

        return $class;
    }

    public function InstallVarProfiles(bool $reInstall = false)
    {
        if ($reInstall) {
            $this->SendDebug(__FUNCTION__, 'reInstall=' . $this->bool2str($reInstall), 0);
        }

        $this->CreateVarProfile('OpenWeatherMap.Temperatur', VARIABLETYPE_FLOAT, ' °C', -10, 30, 0, 1, 'Temperature', [], $reInstall);
        $this->CreateVarProfile('OpenWeatherMap.Humidity', VARIABLETYPE_FLOAT, ' %', 0, 0, 0, 0, 'Drops', [], $reInstall);
        $this->CreateVarProfile('OpenWeatherMap.absHumidity', VARIABLETYPE_FLOAT, ' g/m³', 10, 100, 0, 0, 'Drops', [], $reInstall);
        $this->CreateVarProfile('OpenWeatherMap.Dewpoint', VARIABLETYPE_FLOAT, ' °C', 0, 30, 0, 0, 'Drops', [], $reInstall);
        $this->CreateVarProfile('OpenWeatherMap.Heatindex', VARIABLETYPE_FLOAT, ' °C', 0, 100, 0, 0, 'Temperature', [], $reInstall);
        $this->CreateVarProfile('OpenWeatherMap.Pressure', VARIABLETYPE_FLOAT, ' mbar', 500, 1200, 0, 0, 'Gauge', [], $reInstall);

        $associations = [];
        $associations[] = ['Wert' =>  0, 'Name' => '%.1f', 'Farbe' => 0x80FF00];
        $associations[] = ['Wert' =>  3, 'Name' => '%.1f', 'Farbe' => 0xFFFF00];
        $associations[] = ['Wert' =>  6, 'Name' => '%.1f', 'Farbe' => 0xFF8040];
        $associations[] = ['Wert' =>  8, 'Name' => '%.1f', 'Farbe' => 0xFF0000];
        $associations[] = ['Wert' => 11, 'Name' => '%.1f', 'Farbe' => 0xFF00FF];
        $this->CreateVarProfile('OpenWeatherMap.UVIndex', VARIABLETYPE_FLOAT, '', 0, 12, 0, 0, 'Sun', $associations, $reInstall);

        $this->CreateVarProfile('OpenWeatherMap.WindSpeed', VARIABLETYPE_FLOAT, ' km/h', 0, 100, 0, 1, 'WindSpeed', [], $reInstall);
        $this->CreateVarProfile('OpenWeatherMap.WindStrength', VARIABLETYPE_INTEGER, ' bft', 0, 13, 0, 0, 'WindSpeed', [], $reInstall);
        $this->CreateVarProfile('OpenWeatherMap.WindAngle', VARIABLETYPE_INTEGER, ' °', 0, 360, 0, 0, 'WindDirection', [], $reInstall);
        $this->CreateVarProfile('OpenWeatherMap.WindDirection', VARIABLETYPE_STRING, '', 0, 0, 0, 0, 'WindDirection', [], $reInstall);
        $this->CreateVarProfile('OpenWeatherMap.Rainfall', VARIABLETYPE_FLOAT, ' mm', 0, 60, 0, 1, 'Rainfall', [], $reInstall);
        $this->CreateVarProfile('OpenWeatherMap.RainProbability', VARIABLETYPE_FLOAT, ' %', 0, 0, 0, 0, 'Rainfall', [], $reInstall);
        $this->CreateVarProfile('OpenWeatherMap.Snowfall', VARIABLETYPE_FLOAT, ' mm', 0, 60, 0, 1, 'Snow', [], $reInstall);
        $this->CreateVarProfile('OpenWeatherMap.Cloudiness', VARIABLETYPE_FLOAT, ' %', 0, 0, 0, 0, 'Cloud', [], $reInstall);
    }
}
