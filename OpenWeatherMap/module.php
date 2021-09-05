<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';  // globale Funktionen
require_once __DIR__ . '/../libs/local.php';   // lokale Funktionen

class OpenWeatherMap extends IPSModule
{
    use OpenWeatherMapCommonLib;
    use OpenWeatherMapLocalLib;

    public static $MAX_MINUTELY_FORECAST = 60;
    public static $MAX_HOURLY_FORECAST = 48;
    public static $MAX_DAILY_FORECAST = 7;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('module_disable', false);

        $this->RegisterPropertyString('appid', '');
        $this->RegisterPropertyString('location', '');
        $this->RegisterPropertyFloat('altitude', 0);

        $lang = '';
        if (isset($_ENV['LANG']) && preg_match('/([a-z]*)_.*/', $_ENV['LANG'], $r)) {
            $lang = $r[1];
        }
        $this->RegisterPropertyString('lang', $lang);

        $this->RegisterPropertyBoolean('with_absolute_pressure', false);
        $this->RegisterPropertyBoolean('with_absolute_humidity', false);
        $this->RegisterPropertyBoolean('with_dewpoint', false);
        $this->RegisterPropertyBoolean('with_windchill', false);
        $this->RegisterPropertyBoolean('with_heatindex', false);
        $this->RegisterPropertyBoolean('with_uv_index', false);
        $this->RegisterPropertyBoolean('with_windstrength', false);
        $this->RegisterPropertyBoolean('with_windstrength2text', false);
        $this->RegisterPropertyBoolean('with_windangle', true);
        $this->RegisterPropertyBoolean('with_winddirection', false);
        $this->RegisterPropertyBoolean('with_rain_probability', false);
        $this->RegisterPropertyBoolean('with_cloudiness', false);
        $this->RegisterPropertyBoolean('with_conditions', false);
        $this->RegisterPropertyBoolean('with_icon', false);
        $this->RegisterPropertyBoolean('with_condition_id', false);

        $this->RegisterPropertyInteger('minutely_forecast_count', 0);
        $this->RegisterPropertyInteger('hourly_forecast_count', 0);
        $this->RegisterPropertyInteger('daily_forecast_count', 0);

        $this->RegisterPropertyInteger('update_interval', 5);

        $this->SetMultiBuffer('Data', '');

        $this->CreateVarProfile('OpenWeatherMap.Temperatur', VARIABLETYPE_FLOAT, ' °C', -10, 30, 0, 1, 'Temperature');
        $this->CreateVarProfile('OpenWeatherMap.Humidity', VARIABLETYPE_FLOAT, ' %', 0, 0, 0, 0, 'Drops');
        $this->CreateVarProfile('OpenWeatherMap.absHumidity', VARIABLETYPE_FLOAT, ' g/m³', 10, 100, 0, 0, 'Drops');
        $this->CreateVarProfile('OpenWeatherMap.Dewpoint', VARIABLETYPE_FLOAT, ' °C', 0, 30, 0, 0, 'Drops');
        $this->CreateVarProfile('OpenWeatherMap.Heatindex', VARIABLETYPE_FLOAT, ' °C', 0, 100, 0, 0, 'Temperature');
        $this->CreateVarProfile('OpenWeatherMap.Pressure', VARIABLETYPE_FLOAT, ' mbar', 500, 1200, 0, 0, 'Gauge');
        $this->CreateVarProfile('OpenWeatherMap.UVIndex', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, 'Sun');
        $this->CreateVarProfile('OpenWeatherMap.WindSpeed', VARIABLETYPE_FLOAT, ' km/h', 0, 100, 0, 1, 'WindSpeed');
        $this->CreateVarProfile('OpenWeatherMap.WindStrength', VARIABLETYPE_INTEGER, ' bft', 0, 13, 0, 0, 'WindSpeed');
        $this->CreateVarProfile('OpenWeatherMap.WindAngle', VARIABLETYPE_INTEGER, ' °', 0, 360, 0, 0, 'WindDirection');
        $this->CreateVarProfile('OpenWeatherMap.WindDirection', VARIABLETYPE_STRING, '', 0, 0, 0, 0, 'WindDirection');
        $this->CreateVarProfile('OpenWeatherMap.Rainfall', VARIABLETYPE_FLOAT, ' mm', 0, 60, 0, 1, 'Rainfall');
        $this->CreateVarProfile('OpenWeatherMap.RainProbability', VARIABLETYPE_FLOAT, ' %', 0, 0, 0, 0, 'Rainfall');
        $this->CreateVarProfile('OpenWeatherMap.Snowfall', VARIABLETYPE_FLOAT, ' mm', 0, 60, 0, 1, 'Snow');
        $this->CreateVarProfile('OpenWeatherMap.Cloudiness', VARIABLETYPE_FLOAT, ' %', 0, 0, 0, 0, 'Cloud');

        $this->RegisterTimer('UpdateData', 0, 'OpenWeatherMap_UpdateData(' . $this->InstanceID . ');');
        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);

        if ($Message == IPS_KERNELMESSAGE && $Data[0] == KR_READY) {
            $this->SetUpdateInterval();
        }
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $with_absolute_pressure = $this->ReadPropertyBoolean('with_absolute_pressure');
        $with_absolute_humidity = $this->ReadPropertyBoolean('with_absolute_humidity');
        $with_dewpoint = $this->ReadPropertyBoolean('with_dewpoint');
        $with_windchill = $this->ReadPropertyBoolean('with_windchill');
        $with_heatindex = $this->ReadPropertyBoolean('with_heatindex');
        $with_uv_index = $this->ReadPropertyBoolean('with_uv_index');
        $with_windstrength = $this->ReadPropertyBoolean('with_windstrength');
        $with_windstrength2text = $this->ReadPropertyBoolean('with_windstrength2text');
        $with_windangle = $this->ReadPropertyBoolean('with_windangle');
        $with_winddirection = $this->ReadPropertyBoolean('with_winddirection');
        $with_rain_probability = $this->ReadPropertyBoolean('with_rain_probability');
        $with_cloudiness = $this->ReadPropertyBoolean('with_cloudiness');
        $with_conditions = $this->ReadPropertyBoolean('with_conditions');
        $with_icon = $this->ReadPropertyBoolean('with_icon');
        $with_condition_id = $this->ReadPropertyBoolean('with_condition_id');
        $minutely_forecast_count = $this->ReadPropertyInteger('minutely_forecast_count');
        $hourly_forecast_count = $this->ReadPropertyInteger('hourly_forecast_count');
        $daily_forecast_count = $this->ReadPropertyInteger('daily_forecast_count');

        $vpos = 0;
        $this->MaintainVariable('Temperature', $this->Translate('Temperature'), VARIABLETYPE_FLOAT, 'OpenWeatherMap.Temperatur', $vpos++, true);
        $this->MaintainVariable('Humidity', $this->Translate('Humidity'), VARIABLETYPE_FLOAT, 'OpenWeatherMap.Humidity', $vpos++, true);
        $this->MaintainVariable('AbsoluteHumidity', $this->Translate('absolute humidity'), VARIABLETYPE_FLOAT, 'OpenWeatherMap.absHumidity', $vpos++, $with_absolute_humidity);
        $this->MaintainVariable('Dewpoint', $this->Translate('Dewpoint'), VARIABLETYPE_FLOAT, 'OpenWeatherMap.Dewpoint', $vpos++, $with_dewpoint);
        $this->MaintainVariable('Heatindex', $this->Translate('Heatindex'), VARIABLETYPE_FLOAT, 'OpenWeatherMap.Heatindex', $vpos++, $with_heatindex);
        $this->MaintainVariable('Windchill', $this->Translate('Windchill'), VARIABLETYPE_FLOAT, 'OpenWeatherMap.Temperatur', $vpos++, $with_windchill);
        $this->MaintainVariable('UVIndex', $this->Translate('UV-Index'), VARIABLETYPE_INTEGER, 'OpenWeatherMap.UVIndex', $vpos++, $with_uv_index);
        $this->MaintainVariable('Pressure', $this->Translate('Air pressure'), VARIABLETYPE_FLOAT, 'OpenWeatherMap.Pressure', $vpos++, true);
        $this->MaintainVariable('AbsolutePressure', $this->Translate('absolute pressure'), VARIABLETYPE_FLOAT, 'OpenWeatherMap.Pressure', $vpos++, $with_absolute_pressure);
        $this->MaintainVariable('WindSpeed', $this->Translate('Windspeed'), VARIABLETYPE_FLOAT, 'OpenWeatherMap.WindSpeed', $vpos++, true);
        $this->MaintainVariable('WindStrength', $this->Translate('Windstrength'), VARIABLETYPE_INTEGER, 'OpenWeatherMap.WindStrength', $vpos++, $with_windstrength);
        $this->MaintainVariable('WindStrengthText', $this->Translate('Windstrength'), VARIABLETYPE_STRING, '', $vpos++, $with_windstrength2text);
        $this->MaintainVariable('WindAngle', $this->Translate('Winddirection'), VARIABLETYPE_INTEGER, 'OpenWeatherMap.WindAngle', $vpos++, $with_windangle);
        $this->MaintainVariable('WindDirection', $this->Translate('Winddirection'), VARIABLETYPE_STRING, 'OpenWeatherMap.WindDirection', $vpos++, $with_winddirection);
        $this->MaintainVariable('WindGust', $this->Translate('Windgust'), VARIABLETYPE_FLOAT, 'OpenWeatherMap.WindSpeed', $vpos++, true);
        $this->MaintainVariable('Rain_1h', $this->Translate('Rainfall of last hour'), VARIABLETYPE_FLOAT, 'OpenWeatherMap.Rainfall', $vpos++, true);
        $this->MaintainVariable('Snow_1h', $this->Translate('Snowfall of last hour'), VARIABLETYPE_FLOAT, 'OpenWeatherMap.Snowfall', $vpos++, true);
        $this->MaintainVariable('Cloudiness', $this->Translate('Cloudiness'), VARIABLETYPE_FLOAT, 'OpenWeatherMap.Cloudiness', $vpos++, $with_cloudiness);
        $this->MaintainVariable('Conditions', $this->Translate('Conditions'), VARIABLETYPE_STRING, '', $vpos++, $with_conditions);
        $this->MaintainVariable('ConditionIcon', $this->Translate('Condition-icon'), VARIABLETYPE_STRING, '', $vpos++, $with_icon);
        $this->MaintainVariable('ConditionId', $this->Translate('Condition-id'), VARIABLETYPE_STRING, '', $vpos++, $with_condition_id);

        for ($i = 0; $i < self::$MAX_MINUTELY_FORECAST; $i++) {
            $vpos = 1000 + (100 * $i);
            $use = $i < $minutely_forecast_count;
            $s = ' #M' . ($i + 1);
            $pre = 'MinutelyForecast';
            $post = '_' . sprintf('%02d', $i);

            $this->MaintainVariable($pre . 'Begin' . $post, $this->Translate('Begin of minutely forecast-period') . $s, VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, $use);
            $this->MaintainVariable($pre . 'RainProbability' . $post, $this->Translate('Rain propability') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.RainProbability', $vpos++, $use && $with_rain_probability);
        }

        for ($i = 0; $i < self::$MAX_HOURLY_FORECAST; $i++) {
            $vpos = 2000 + (100 * $i);
            $use = $i < $hourly_forecast_count;
            $s = ' #H' . ($i + 1);
            $pre = 'HourlyForecast';
            $post = '_' . sprintf('%02d', $i);

            $this->MaintainVariable($pre . 'Begin' . $post, $this->Translate('Begin of hourly forecast-period') . $s, VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, $use);
            $this->MaintainVariable($pre . 'Temperature' . $post, $this->Translate('Temperature') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.Temperatur', $vpos++, $use);
            $this->MaintainVariable($pre . 'UVIndex' . $post, $this->Translate('UV-Index') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.Humidity', $vpos++, $use);
            $this->MaintainVariable($pre . 'Humidity' . $post, $this->Translate('Humidity') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.Humidity', $vpos++, $use);
            $this->MaintainVariable($pre . 'Pressure' . $post, $this->Translate('Air pressure') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.Pressure', $vpos++, $use);
            $this->MaintainVariable($pre . 'AbsolutePressure' . $post, $this->Translate('absolute pressure') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.Pressure', $vpos++, $use && $with_absolute_pressure);
            $this->MaintainVariable($pre . 'WindSpeed' . $post, $this->Translate('Windspeed') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.WindSpeed', $vpos++, $use);
            $this->MaintainVariable($pre . 'WindStrength' . $post, $this->Translate('Windstrength') . $s, VARIABLETYPE_INTEGER, 'OpenWeatherMap.WindStrength', $vpos++, $use && $with_windstrength);
            $this->MaintainVariable($pre . 'WindStrengthText' . $post, $this->Translate('Windstrength') . $s, VARIABLETYPE_STRING, '', $vpos++, $use && $with_windstrength2text);
            $this->MaintainVariable($pre . 'WindAngle' . $post, $this->Translate('Winddirection') . $s, VARIABLETYPE_INTEGER, 'OpenWeatherMap.WindAngle', $vpos++, $use && $with_windangle);
            $this->MaintainVariable($pre . 'WindDirection' . $post, $this->Translate('Winddirection') . $s, VARIABLETYPE_STRING, 'OpenWeatherMap.WindDirection', $vpos++, $use && $with_winddirection);
            $this->MaintainVariable($pre . 'WindGust' . $post, $this->Translate('Windgust') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.WindSpeed', $vpos++, $use);
            $this->MaintainVariable($pre . 'RainProbability' . $post, $this->Translate('Rain propability') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.RainProbability', $vpos++, $use && $with_rain_probability);
            $this->MaintainVariable($pre . 'Cloudiness' . $post, $this->Translate('Cloudiness') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.Cloudiness', $vpos++, $use && $with_cloudiness);
            $this->MaintainVariable($pre . 'Conditions' . $post, $this->Translate('Conditions') . $s, VARIABLETYPE_STRING, '', $vpos++, $use && $with_conditions);
            $this->MaintainVariable($pre . 'ConditionIcon' . $post, $this->Translate('Condition-icon') . $s, VARIABLETYPE_STRING, '', $vpos++, $use && $with_icon);
            $this->MaintainVariable($pre . 'ConditionId' . $post, $this->Translate('Condition-id') . $s, VARIABLETYPE_STRING, '', $vpos++, $use && $with_condition_id);
        }

        for ($i = 0; $i < self::$MAX_DAILY_FORECAST; $i++) {
            $vpos = 3000 + (100 * $i);
            $use = $i < $daily_forecast_count;
            $s = ' #D' . ($i + 1);
            $pre = 'DailyForecast';
            $post = '_' . sprintf('%02d', $i);

            $this->MaintainVariable($pre . 'Begin' . $post, $this->Translate('Begin of daily forecast-period') . $s, VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, $use);
            $this->MaintainVariable($pre . 'TemperatureMorning' . $post, $this->Translate('Morning temperature') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.Temperatur', $vpos++, $use);
            $this->MaintainVariable($pre . 'TemperatureDay' . $post, $this->Translate('Day temperature') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.Temperatur', $vpos++, $use);
            $this->MaintainVariable($pre . 'TemperatureEvening' . $post, $this->Translate('Evening temperature') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.Temperatur', $vpos++, $use);
            $this->MaintainVariable($pre . 'TemperatureNight' . $post, $this->Translate('Night temperature') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.Temperatur', $vpos++, $use);
            $this->MaintainVariable($pre . 'TemperatureMin' . $post, $this->Translate('minimum temperature') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.Temperatur', $vpos++, $use);
            $this->MaintainVariable($pre . 'TemperatureMax' . $post, $this->Translate('maximum temperature') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.Temperatur', $vpos++, $use);
            $this->MaintainVariable($pre . 'UVIndex' . $post, $this->Translate('UV-Index') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.Humidity', $vpos++, $use);
            $this->MaintainVariable($pre . 'Humidity' . $post, $this->Translate('Humidity') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.Humidity', $vpos++, $use);
            $this->MaintainVariable($pre . 'Pressure' . $post, $this->Translate('Air pressure') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.Pressure', $vpos++, $use);
            $this->MaintainVariable($pre . 'AbsolutePressure' . $post, $this->Translate('absolute pressure') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.Pressure', $vpos++, $use && $with_absolute_pressure);
            $this->MaintainVariable($pre . 'WindSpeed' . $post, $this->Translate('Windspeed') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.WindSpeed', $vpos++, $use);
            $this->MaintainVariable($pre . 'WindStrength' . $post, $this->Translate('Windstrength') . $s, VARIABLETYPE_INTEGER, 'OpenWeatherMap.WindStrength', $vpos++, $use && $with_windstrength);
            $this->MaintainVariable($pre . 'WindStrengthText' . $post, $this->Translate('Windstrength') . $s, VARIABLETYPE_STRING, '', $vpos++, $use && $with_windstrength2text);
            $this->MaintainVariable($pre . 'WindAngle' . $post, $this->Translate('Winddirection') . $s, VARIABLETYPE_INTEGER, 'OpenWeatherMap.WindAngle', $vpos++, $use && $with_windangle);
            $this->MaintainVariable($pre . 'WindDirection' . $post, $this->Translate('Winddirection') . $s, VARIABLETYPE_STRING, 'OpenWeatherMap.WindDirection', $vpos++, $use && $with_winddirection);
            $this->MaintainVariable($pre . 'WindGust' . $post, $this->Translate('Windgust') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.WindSpeed', $vpos++, $use);
            $this->MaintainVariable($pre . 'RainProbability' . $post, $this->Translate('Rain propability') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.RainProbability', $vpos++, $use && $with_rain_probability);
            $this->MaintainVariable($pre . 'Cloudiness' . $post, $this->Translate('Cloudiness') . $s, VARIABLETYPE_FLOAT, 'OpenWeatherMap.Cloudiness', $vpos++, $use && $with_cloudiness);
            $this->MaintainVariable($pre . 'Conditions' . $post, $this->Translate('Conditions') . $s, VARIABLETYPE_STRING, '', $vpos++, $use && $with_conditions);
            $this->MaintainVariable($pre . 'ConditionIcon' . $post, $this->Translate('Condition-icon') . $s, VARIABLETYPE_STRING, '', $vpos++, $use && $with_icon);
            $this->MaintainVariable($pre . 'ConditionId' . $post, $this->Translate('Condition-id') . $s, VARIABLETYPE_STRING, '', $vpos++, $use && $with_condition_id);
        }

        $vpos = 9000;
        $this->MaintainVariable('LastMeasurement', $this->Translate('last measurement'), VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, true);

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        if ($module_disable) {
            $this->SetTimerInterval('UpdateData', 0);
            $this->SetStatus(IS_INACTIVE);
            return;
        }

        $appid = $this->ReadPropertyString('appid');
        if ($appid == '') {
            $this->SetStatus(self::$IS_INVALIDCONFIG);
            return;
        }

        $refs = $this->GetReferenceList();
        foreach ($refs as $ref) {
            $this->UnregisterReference($ref);
        }
        $propertyNames = [];
        foreach ($propertyNames as $name) {
            $oid = $this->ReadPropertyInteger($name);
            if ($oid > 0) {
                $this->RegisterReference($oid);
            }
        }

        if (IPS_GetKernelRunlevel() == KR_READY) {
            $this->SetUpdateInterval();
        }

        $this->SetStatus(IS_ACTIVE);
    }

    public function GetConfigurationForm()
    {
        $formElements = $this->GetFormElements();
        $formActions = $this->GetFormActions();
        $formStatus = $this->GetFormStatus();

        $form = json_encode(['elements' => $formElements, 'actions' => $formActions, 'status' => $formStatus]);
        if ($form == '') {
            $this->SendDebug(__FUNCTION__, 'json_error=' . json_last_error_msg(), 0);
            $this->SendDebug(__FUNCTION__, '=> formElements=' . print_r($formElements, true), 0);
            $this->SendDebug(__FUNCTION__, '=> formActions=' . print_r($formActions, true), 0);
            $this->SendDebug(__FUNCTION__, '=> formStatus=' . print_r($formStatus, true), 0);
        }
        return $form;
    }

    private function GetFormElements()
    {
        $formElements = [];

        $formElements[] = [
            'type'    => 'CheckBox',
            'name'    => 'module_disable',
            'caption' => 'Disable instance'
        ];
        $formElements[] = [
            'type'    => 'Label',
            'caption' => 'OpenWeatherMap - fetch current observations and forecast'
        ];

        $items = [];
        $items[] = [
            'type'    => 'ValidationTextBox',
            'name'    => 'appid',
            'caption' => 'API-Key'
        ];

        $items[] = [
            'type'    => 'Label',
            'caption' => 'station data - if position is not set, Modue \'Location\' is used'
        ];
        $items[] = [
            'type'    => 'SelectLocation',
            'name'    => 'location',
            'caption' => 'Location',
        ];
        $items[] = [
            'type'    => 'NumberSpinner',
            'name'    => 'altitude',
            'caption' => 'Altitude',
            'suffix'  => 'm'
        ];

        $items[] = [
            'type'    => 'Label',
            'caption' => 'Language setting for textual weather-information (de, en, ...)'
        ];
        $items[] = [
            'type'    => 'ValidationTextBox',
            'name'    => 'lang',
            'caption' => 'Language code'
        ];

        $formElements[] = [
            'type'    => 'ExpansionPanel',
            'items'   => $items,
            'caption' => 'Basic settings',
        ];

        $items = [];
        $items[] = [
            'type'    => 'CheckBox',
            'name'    => 'with_absolute_pressure',
            'caption' => ' ... absolute Pressure'
        ];
        $items[] = [
            'type'    => 'CheckBox',
            'name'    => 'with_absolute_humidity',
            'caption' => ' ... absolute Humidity'
        ];
        $items[] = [
            'type'    => 'CheckBox',
            'name'    => 'with_dewpoint',
            'caption' => ' ... Dewpoint'
        ];
        $items[] = [
            'type'    => 'CheckBox',
            'name'    => 'with_heatindex',
            'caption' => ' ... Heatindex'
        ];
        $items[] = [
            'type'    => 'CheckBox',
            'name'    => 'with_uv_index',
            'caption' => ' ... UV-Index'
        ];
        $items[] = [
            'type'    => 'CheckBox',
            'name'    => 'with_windchill',
            'caption' => ' ... Windchill'
        ];
        $items[] = [
            'type'    => 'CheckBox',
            'name'    => 'with_windstrength',
            'caption' => ' ... Windstrength'
        ];
        $items[] = [
            'type'    => 'CheckBox',
            'name'    => 'with_windstrength2text',
            'caption' => ' ... Windstrength as text'
        ];
        $items[] = [
            'type'    => 'CheckBox',
            'name'    => 'with_windangle',
            'caption' => ' ... Winddirection in degrees'
        ];
        $items[] = [
            'type'    => 'CheckBox',
            'name'    => 'with_winddirection',
            'caption' => ' ... Winddirection with label'
        ];
        $items[] = [
            'type'    => 'CheckBox',
            'name'    => 'with_rain_probability',
            'caption' => ' ... Rain propability'
        ];
        $items[] = [
            'type'    => 'CheckBox',
            'name'    => 'with_cloudiness',
            'caption' => ' ... Cloudiness'
        ];
        $items[] = [
            'type'    => 'CheckBox',
            'name'    => 'with_conditions',
            'caption' => ' ... Conditions'
        ];
        $items[] = [
            'type'    => 'CheckBox',
            'name'    => 'with_icon',
            'caption' => ' ... Condition-icon'
        ];
        $items[] = [
            'type'    => 'CheckBox',
            'name'    => 'with_condition_id',
            'caption' => ' ... Condition-id'
        ];

        $items[] = [
            'type'    => 'Label',
            'caption' => 'precipitation forecast by the minute (max 60 minutes)'
        ];
        $items[] = [
            'type'  => 'RowLayout',
            'items' => [
                [
                    'type'    => 'NumberSpinner',
                    'name'    => 'minutely_forecast_count',
                    'caption' => 'Count',
                    'maximum' => self::$MAX_MINUTELY_FORECAST
                ],
                [
                    'type'    => 'Label',
                    'caption' => ' ... attention: decreasing the number deletes the unused variables!'
                ]
            ]
        ];

        $items[] = [
            'type'    => 'Label',
            'caption' => 'hourly forecast (max 48 hour)'
        ];
        $items[] = [
            'type'  => 'RowLayout',
            'items' => [
                [
                    'type'    => 'NumberSpinner',
                    'name'    => 'hourly_forecast_count',
                    'caption' => 'Count',
                    'maximum' => self::$MAX_HOURLY_FORECAST
                ],
                [
                    'type'    => 'Label',
                    'caption' => ' ... attention: decreasing the number deletes the unused variables!'
                ]
            ]
        ];

        $items[] = [
            'type'    => 'Label',
            'caption' => 'daily forecast (max 7 days)'
        ];
        $items[] = [
            'type'  => 'RowLayout',
            'items' => [
                [
                    'type'    => 'NumberSpinner',
                    'name'    => 'daily_forecast_count',
                    'caption' => 'Count',
                    'maximum' => self::$MAX_DAILY_FORECAST
                ],
                [
                    'type'    => 'Label',
                    'caption' => ' ... attention: decreasing the number deletes the unused variables!'
                ]
            ]
        ];

        $formElements[] = [
            'type'    => 'ExpansionPanel',
            'items'   => $items,
            'caption' => 'optional weather data',
        ];

        $formElements[] = [
            'type'    => 'Label',
            'caption' => 'Update weatherdata every X minutes'
        ];
        $formElements[] = [
            'type'    => 'NumberSpinner',
            'name'    => 'update_interval',
            'caption' => 'Minutes'
        ];

        return $formElements;
    }

    private function GetFormActions()
    {
        $formActions = [];

        $formActions[] = [
            'type'    => 'Button',
            'caption' => 'Update weatherdata',
            'onClick' => 'OpenWeatherMap_UpdateData($id);'
        ];

        return $formActions;
    }

    protected function SetUpdateInterval()
    {
        $min = $this->ReadPropertyInteger('update_interval');
        $msec = $min > 0 ? $min * 1000 * 60 : 0;
        $this->SetTimerInterval('UpdateData', $msec);
    }

    public function UpdateData()
    {
        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            return;
        }

        $lat = 0;
        $lng = 0;
        $location = $this->ReadPropertyString('location');
        $this->SendDebug(__FUNCTION__, 'location=' . $location, 0);
        if ($location != false) {
            $loc = json_decode($location, true);
            if ($loc != false) {
                $lat = $loc['latitude'];
                $lng = $loc['longitude'];
            }
        }
        if ($lat == 0 && $lng == 0) {
            $id = IPS_GetInstanceListByModuleID('{45E97A63-F870-408A-B259-2933F7EABF74}')[0];
            $loc = json_decode(IPS_GetProperty($id, 'Location'), true);
            $lat = $loc['latitude'];
            $lng = $loc['longitude'];
        }

        $args = [
            'lat'   => number_format($lat, 6, '.', ''),
            'lon'   => number_format($lng, 6, '.', ''),
            'units' => 'metric'
        ];

        $lang = $this->ReadPropertyString('lang');
        if ($lang != '') {
            $args['lang'] = $lang;
        }
        $args['units'] = 'metric';

        $jdata = $this->do_HttpRequest('data/2.5/onecall', $args);
        $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);
        if ($jdata == '') {
            $this->SetMultiBuffer('Data', '');
            return;
        }

        $with_absolute_pressure = $this->ReadPropertyBoolean('with_absolute_pressure');
        $with_absolute_humidity = $this->ReadPropertyBoolean('with_absolute_humidity');
        $with_dewpoint = $this->ReadPropertyBoolean('with_dewpoint');
        $with_windchill = $this->ReadPropertyBoolean('with_windchill');
        $with_heatindex = $this->ReadPropertyBoolean('with_heatindex');
        $with_uv_index = $this->ReadPropertyBoolean('with_uv_index');
        $with_windstrength = $this->ReadPropertyBoolean('with_windstrength');
        $with_windstrength2text = $this->ReadPropertyBoolean('with_windstrength2text');
        $with_windangle = $this->ReadPropertyBoolean('with_windangle');
        $with_winddirection = $this->ReadPropertyBoolean('with_winddirection');
        $with_rain_probability = $this->ReadPropertyBoolean('with_rain_probability');
        $with_cloudiness = $this->ReadPropertyBoolean('with_cloudiness');
        $with_conditions = $this->ReadPropertyBoolean('with_conditions');
        $with_icon = $this->ReadPropertyBoolean('with_icon');
        $with_condition_id = $this->ReadPropertyBoolean('with_condition_id');

        $timestamp = $this->GetArrayElem($jdata, 'current.dt', 0);
        $temperature = $this->GetArrayElem($jdata, 'current.temp', 0);
        $pressure = $this->GetArrayElem($jdata, 'current.pressure', 0);
        $humidity = $this->GetArrayElem($jdata, 'current.humidity', 0);

        $visibility = $this->GetArrayElem($jdata, 'current.visibility', 0);

        $wind_speed = $this->GetArrayElem($jdata, 'current.wind_speed', 0);
        $wind_deg = $this->GetArrayElem($jdata, 'current.wind_deg', 0);
        $wind_gust = $this->GetArrayElem($jdata, 'current.wind_gust', 0);

        $clouds = $this->GetArrayElem($jdata, 'current.clouds', 0);

        $conditions = '';
        $icon = '';
        $id = '';
        $weather = $this->GetArrayElem($jdata, 'current.weather', '');
        if ($weather != '') {
            foreach ($weather as $w) {
                $description = $this->GetArrayElem($w, 'description', '');
                if ($description != '') {
                    $conditions .= ($conditions != '' ? ', ' : '') . $this->Translate($description);
                }
            }
            $icon = $this->GetArrayElem($weather, '0.icon', '');
            $id = $this->GetArrayElem($weather, '0.id', '');
        }

        $this->SetValue('Temperature', $temperature);

        if ($with_dewpoint) {
            $dewpoint = $this->GetArrayElem($jdata, 'current.dew_point', 0);
            $this->SetValue('Dewpoint', $dewpoint);
        }

        if ($with_windchill) {
            $windchill = $this->GetArrayElem($jdata, 'current.feels_like', 0);
            $this->SetValue('Windchill', $windchill);
        }

        if ($with_heatindex) {
            $heatindex = $this->CalcHeatindex($temperature, $humidity);
            $this->SetValue('Heatindex', $heatindex);
        }

        $this->SetValue('Pressure', $pressure);
        if ($with_absolute_pressure) {
            $altitude = $this->ReadPropertyFloat('altitude');
            $abs_pressure = $this->CalcAbsolutePressure($pressure, $temperature, $altitude);
            $this->SetValue('AbsolutePressure', $abs_pressure);
        }

        $this->SetValue('Humidity', $humidity);
        if ($with_absolute_humidity) {
            $abs_humidity = $this->CalcAbsoluteHumidity($temperature, $humidity);
            $this->SetValue('AbsoluteHumidity', $abs_humidity);
        }

        if ($with_uv_index) {
            $uvi = $this->GetArrayElem($jdata, 'current.uvi', 0);
            $this->SetValue('UVIndex', $uvi);
        }

        $this->SetValue('WindSpeed', $wind_speed);
        if ($with_windangle) {
            $this->SetValue('WindAngle', $wind_deg);
        }
        if ($with_windstrength) {
            $windstrength = $this->ConvertWindSpeed2Strength((int) $wind_speed);
            $this->SetValue('WindStrength', $windstrength);
        }
        if ($with_windstrength2text) {
            $bft = $this->ConvertWindSpeed2Strength($wind_speed);
            $windstrength = $this->ConvertWindStrength2Text($bft);
            $this->SetValue('WindStrengthText', $windstrength);
        }
        if ($with_winddirection) {
            $dir = $this->ConvertWindDirection2Text((int) $wind_deg) . ' (' . $wind_deg . '°)';
            $this->SetValue('WindDirection', $dir);
        }
        $this->SetValue('WindGust', $wind_gust);

        $rain_1h = $this->GetArrayElem($jdata, 'rain.1h', 0);
        $this->SetValue('Rain_1h', $rain_1h);

        $snow_1h = $this->GetArrayElem($jdata, 'snow.1h', 0);
        $this->SetValue('Snow_1h', $snow_1h);

        if ($with_cloudiness) {
            $this->SetValue('Cloudiness', $clouds);
        }

        if ($with_conditions) {
            $this->SetValue('Conditions', $conditions);
        }

        if ($with_icon) {
            $this->SetValue('ConditionIcon', $icon);
        }

        if ($with_condition_id) {
            $this->SetValue('ConditionId', $id);
        }

        $minutely_forecast_count = $this->ReadPropertyInteger('minutely_forecast_count');
        for ($i = 0; $i < $minutely_forecast_count; $i++) {
            $pre = 'MinutelyForecast';
            $post = '_' . sprintf('%02d', $i);

            $ent = $this->GetArrayElem($jdata, 'minutely.' . $i, '');
            $this->SendDebug(__FUNCTION__, 'minutely[' . $i . ']=' . print_r($ent, true), 0);

            $timestamp = $this->GetArrayElem($ent, 'dt', 0);
            $precipitation = $this->GetArrayElem($ent, 'precipitation', 0);
        }

        $hourly_forecast_count = $this->ReadPropertyInteger('hourly_forecast_count');
        for ($i = 0; $i < $hourly_forecast_count; $i++) {
            $pre = 'HourlyForecast';
            $post = '_' . sprintf('%02d', $i);

            $ent = $this->GetArrayElem($jdata, 'hourly.' . $i, '');
            $this->SendDebug(__FUNCTION__, 'hourly[' . $i . ']=' . print_r($ent, true), 0);

            $timestamp = $this->GetArrayElem($ent, 'dt', 0);
            $temperature = $this->GetArrayElem($ent, 'temp', 0);
            $uvi = $this->GetArrayElem($ent, 'uvi', 0);
            $pressure = $this->GetArrayElem($ent, 'pressure', 0);
            $humidity = $this->GetArrayElem($ent, 'humidity', 0);

            $visibility = $this->GetArrayElem($ent, 'visibility', 0);

            $wind_speed = $this->GetArrayElem($ent, 'wind_speed', 0);
            $wind_deg = $this->GetArrayElem($ent, 'wind_deg', 0);
            $wind_gust = $this->GetArrayElem($ent, 'wind_gust', 0);

            $pop = $this->GetArrayElem($ent, 'pop', 0);

            $clouds = $this->GetArrayElem($ent, 'clouds.all', 0);
            $conditions = $this->GetArrayElem($ent, 'weather.0.description', '');

            $conditions = '';
            $weather = $this->GetArrayElem($ent, 'weather', '');
            if ($weather != '') {
                foreach ($weather as $w) {
                    $description = $this->GetArrayElem($w, 'description', '');
                    if ($description != '') {
                        $conditions .= ($conditions != '' ? ', ' : '') . $this->Translate($description);
                    }
                }
                $icon = $this->GetArrayElem($weather, '0.icon', '');
                $id = $this->GetArrayElem($weather, '0.id', '');
            }

            $this->SetValue($pre . 'Begin' . $post, $timestamp);

            $this->SetValue($pre . 'Temperature' . $post, $temperature);

            $this->SetValue($pre . 'Pressure' . $post, $pressure);
            if ($with_absolute_pressure) {
                $abs_humidity = $this->CalcAbsoluteHumidity($temperature, $humidity);
                $this->SetValue($pre . 'AbsolutePressure' . $post, $abs_pressure);
            }

            $this->SetValue($pre . 'UVIndex' . $post, $uvi);

            $this->SetValue($pre . 'Humidity' . $post, $humidity);

            $this->SetValue($pre . 'WindSpeed' . $post, $wind_speed);
            if ($with_windangle) {
                $this->SetValue($pre . 'WindAngle' . $post, $wind_deg);
            }
            if ($with_windstrength) {
                $windstrength = $this->ConvertWindSpeed2Strength((int) $wind_speed);
                $this->SetValue($pre . 'WindStrength' . $post, $windstrength);
            }
            if ($with_windstrength2text) {
                $bft = $this->ConvertWindSpeed2Strength($wind_speed);
                $windstrength = $this->ConvertWindStrength2Text($bft);
                $this->SetValue($pre . 'WindStrengthText' . $post, $windstrength);
            }
            if ($with_winddirection) {
                $dir = $this->ConvertWindDirection2Text((int) $wind_deg) . ' (' . $wind_deg . '°)';
                $this->SetValue($pre . 'WindDirection' . $post, $dir);
            }
            $this->SetValue($pre . 'WindGust' . $post, $wind_gust);

            if ($with_rain_probability) {
                $this->SetValue($pre . 'RainProbability' . $post, (float) $pop * 100);
            }

            if ($with_cloudiness) {
                $this->SetValue($pre . 'Cloudiness' . $post, $clouds);
            }

            if ($with_conditions) {
                $this->SetValue($pre . 'Conditions' . $post, $conditions);
            }

            if ($with_icon) {
                $this->SetValue($pre . 'ConditionIcon' . $post, $icon);
            }

            if ($with_condition_id) {
                $this->SetValue($pre . 'ConditionId' . $post, $id);
            }
        }

        $daily_forecast_count = $this->ReadPropertyInteger('daily_forecast_count');
        for ($i = 0; $i < $daily_forecast_count; $i++) {
            $pre = 'DailyForecast';
            $post = '_' . sprintf('%02d', $i);

            $ent = $this->GetArrayElem($jdata, 'daily.' . $i, '');
            $this->SendDebug(__FUNCTION__, 'daily[' . $i . ']=' . print_r($ent, true), 0);

            $timestamp = $this->GetArrayElem($ent, 'dt', 0);
            $temperature_morning = $this->GetArrayElem($ent, 'temp.morn', 0);
            $temperature_day = $this->GetArrayElem($ent, 'temp.day', 0);
            $temperature_evening = $this->GetArrayElem($ent, 'temp.evening', 0);
            $temperature_night = $this->GetArrayElem($ent, 'temp.night', 0);
            $temperature_min = $this->GetArrayElem($ent, 'temp.min', 0);
            $temperature_max = $this->GetArrayElem($ent, 'temp.max', 0);

            $uvi = $this->GetArrayElem($ent, 'uvi', 0);
            $pressure = $this->GetArrayElem($ent, 'pressure', 0);
            $humidity = $this->GetArrayElem($ent, 'humidity', 0);

            $visibility = $this->GetArrayElem($ent, 'visibility', 0);

            $wind_speed = $this->GetArrayElem($ent, 'wind_speed', 0);
            $wind_deg = $this->GetArrayElem($ent, 'wind_deg', 0);
            $wind_gust = $this->GetArrayElem($ent, 'wind_gust', 0);

            $pop = $this->GetArrayElem($ent, 'pop', 0);

            $clouds = $this->GetArrayElem($ent, 'clouds.all', 0);
            $conditions = $this->GetArrayElem($ent, 'weather.0.description', '');

            $conditions = '';
            $weather = $this->GetArrayElem($ent, 'weather', '');
            if ($weather != '') {
                foreach ($weather as $w) {
                    $description = $this->GetArrayElem($w, 'description', '');
                    if ($description != '') {
                        $conditions .= ($conditions != '' ? ', ' : '') . $this->Translate($description);
                    }
                }
                $icon = $this->GetArrayElem($weather, '0.icon', '');
                $id = $this->GetArrayElem($weather, '0.id', '');
            }

            $this->SetValue($pre . 'Begin' . $post, $timestamp);

            $this->SetValue($pre . 'TemperatureMorning' . $post, $temperature_morning);
            $this->SetValue($pre . 'TemperatureDay' . $post, $temperature_day);
            $this->SetValue($pre . 'TemperatureEvening' . $post, $temperature_evening);
            $this->SetValue($pre . 'TemperatureNight' . $post, $temperature_night);
            $this->SetValue($pre . 'TemperatureMin' . $post, $temperature_min);
            $this->SetValue($pre . 'TemperatureMax' . $post, $temperature_max);

            $this->SetValue($pre . 'Pressure' . $post, $pressure);
            if ($with_absolute_pressure) {
                $abs_humidity = $this->CalcAbsoluteHumidity($temperature, $humidity);
                $this->SetValue($pre . 'AbsolutePressure' . $post, $abs_pressure);
            }

            $this->SetValue($pre . 'UVIndex' . $post, $uvi);

            $this->SetValue($pre . 'Humidity' . $post, $humidity);

            $this->SetValue($pre . 'WindSpeed' . $post, $wind_speed);
            if ($with_windangle) {
                $this->SetValue($pre . 'WindAngle' . $post, $wind_deg);
            }
            if ($with_windstrength) {
                $windstrength = $this->ConvertWindSpeed2Strength((int) $wind_speed);
                $this->SetValue($pre . 'WindStrength' . $post, $windstrength);
            }
            if ($with_windstrength2text) {
                $bft = $this->ConvertWindSpeed2Strength($wind_speed);
                $windstrength = $this->ConvertWindStrength2Text($bft);
                $this->SetValue($pre . 'WindStrengthText' . $post, $windstrength);
            }
            if ($with_winddirection) {
                $dir = $this->ConvertWindDirection2Text((int) $wind_deg) . ' (' . $wind_deg . '°)';
                $this->SetValue($pre . 'WindDirection' . $post, $dir);
            }
            $this->SetValue($pre . 'WindGust' . $post, $wind_gust);

            if ($with_rain_probability) {
                $this->SetValue($pre . 'RainProbability' . $post, (float) $pop * 100);
            }

            if ($with_cloudiness) {
                $this->SetValue($pre . 'Cloudiness' . $post, $clouds);
            }

            if ($with_conditions) {
                $this->SetValue($pre . 'Conditions' . $post, $conditions);
            }

            if ($with_icon) {
                $this->SetValue($pre . 'ConditionIcon' . $post, $icon);
            }

            if ($with_condition_id) {
                $this->SetValue($pre . 'ConditionId' . $post, $id);
            }
        }

        $this->SetValue('LastMeasurement', $timestamp);

        $this->SetMultiBuffer('Data', json_encode($jdata));

        $this->SetStatus(IS_ACTIVE);
    }

    private function do_HttpRequest($cmd, $args)
    {
        $appid = $this->ReadPropertyString('appid');

        $url = 'https://api.openweathermap.org/' . $cmd . '?appid=' . $appid;

        if ($args != '') {
            foreach ($args as $arg => $value) {
                $url .= '&' . $arg;
                if ($value != '') {
                    $url .= '=' . rawurlencode((string) $value);
                }
            }
        }

        $this->SendDebug(__FUNCTION__, 'http-get: url=' . $url, 0);

        $time_start = microtime(true);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $cdata = curl_exec($ch);
        $cerrno = curl_errno($ch);
        $cerror = $cerrno ? curl_error($ch) : '';
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $duration = round(microtime(true) - $time_start, 2);
        $this->SendDebug(__FUNCTION__, ' => errno=' . $cerrno . ', httpcode=' . $httpcode . ', duration=' . $duration . 's', 0);
        $this->SendDebug(__FUNCTION__, '    cdata=' . $cdata, 0);

        $statuscode = 0;
        $err = '';
        $jdata = '';
        if ($cerrno) {
            $statuscode = self::$IS_SERVERERROR;
            $err = 'got curl-errno ' . $cerrno . ' (' . $cerror . ')';
        } elseif ($httpcode != 200) {
            if ($httpcode >= 500 && $httpcode <= 599) {
                $statuscode = self::$IS_SERVERERROR;
                $err = "got http-code $httpcode (server error)";
            } else {
                $err = "got http-code $httpcode";
                $statuscode = self::$IS_HTTPERROR;
            }
        } elseif ($cdata == '') {
            $statuscode = self::$IS_INVALIDDATA;
            $err = 'no data';
        } else {
            $jdata = json_decode($cdata, true);
            if ($jdata == '') {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'malformed response';
            }
        }

        if ($statuscode) {
            $this->LogMessage('url=' . $url . ' => statuscode=' . $statuscode . ', err=' . $err, KL_WARNING);
            $this->SendDebug(__FUNCTION__, ' => statuscode=' . $statuscode . ', err=' . $err, 0);
            $this->SetStatus($statuscode);
        }

        return $jdata;
    }

    // relative Luffeuchtigkeit in absolute Feuchte umrechnen
    //   Quelle: https://www.wetterochs.de/wetter/feuchte.html
    public function CalcAbsoluteHumidity(float $temp, float $humidity)
    {
        if ($temp >= 0) {
            $a = 7.5;
            $b = 237.3;
        } else {
            $a = 7.6;
            $b = 240.7;
        }

        $R = 8314.3; // universelle Gaskonstante in J/(kmol*K)
        $mw = 18.016; // Molekulargewicht des Wasserdampfes in kg/kmol

        // Sättigungsdamphdruck in hPa
        $SDD = 6.1078 * pow(10, (($a * $temp) / ($b + $temp)));

        // Dampfdruck in hPa
        $DD = $humidity / 100 * $SDD;

        $v = log10($DD / 6.1078);

        // Taupunkttemperatur in °C
        $TD = $b * $v / ($a - $v);

        // Temperatur in Kelvin
        $TK = $temp + 273.15;

        // absolute Feuchte in g Wasserdampf pro m³ Luft
        $AF = pow(10, 5) * $mw / $R * $DD / $TK;
        $AF = round($AF * 10) / 10; // auf eine NK runden

        return $AF;
    }

    // gemessenen Luftdruck in absoluen Luftdruck (Meereshöhe) umrechnen
    //   Quelle: https://rechneronline.de/barometer/hoehe.php
    public function CalcAbsolutePressure(float $pressure, float $temp, float $altitude)
    {
        // Temperaturgradient (geschätzt)
        $TG = 0.0065;

        // Höhe = Differenz Meereshöhe zu Standort
        $ad = $altitude * -1;

        // Temperatur auf Meereshöhe herunter rechnen
        //     Schätzung: Temperatur auf Meereshöhe = Temperatur + Temperaturgradient * Höhe
        $T = $temp + $TG * $ad;
        // Temperatur in Kelvin
        $TK = $T + 273.15;

        // Luftdruck auf Meereshöhe = Barometeranzeige / (1-Temperaturgradient*Höhe/Temperatur auf Meereshöhe in Kelvin)^(0,03416/Temperaturgradient)
        $AP = $pressure / pow((1 - $TG * $ad / $TK), (0.03416 / $TG));

        return $AP;
    }

    // Windrichtung in Grad als Bezeichnung ausgeben
    //   Quelle: https://www.windfinder.com/wind/windspeed.htm
    public function ConvertWindDirection2Text(int $dir)
    {
        $dir2txt = [
            'N',
            'NNE',
            'NE',
            'ENE',
            'E',
            'ESE',
            'SE',
            'SSE',
            'S',
            'SSW',
            'SW',
            'WSW',
            'W',
            'WNW',
            'NW',
            'NNW',
        ];

        $idx = floor((($dir + 11.25) % 360) / 22.5);
        if ($idx >= 0 && $idx < count($dir2txt)) {
            $txt = $this->Translate($dir2txt[$idx]);
        } else {
            $txt = '';
        }
        return $txt;
    }

    // Windgeschwindigkeit in Beaufort umrechnen
    //   Quelle: https://de.wikipedia.org/wiki/Beaufortskala
    public function ConvertWindSpeed2Strength(int $speed)
    {
        $kn2bft = [1, 4, 7, 11, 16, 22, 28, 34, 41, 48, 56, 64];

        $kn = $speed / 1.852;
        for ($i = 0; $i < count($kn2bft); $i++) {
            if ($kn < $kn2bft[$i]) {
                break;
            }
        }
        return $i;
    }

    // Windstärke als Text ausgeben
    //  Quelle: https://de.wikipedia.org/wiki/Beaufortskala
    public function ConvertWindStrength2Text(int $bft)
    {
        $bft2txt = [
            'Calm',
            'Light air',
            'Light breeze',
            'Gentle breeze',
            'Moderate breeze',
            'Fresh breeze',
            'Strong breeze',
            'High wind',
            'Gale',
            'Strong gale',
            'Storm',
            'Hurricane force',
            'Violent storm'
        ];

        if ($bft >= 0 && $bft < count($bft2txt)) {
            $txt = $this->Translate($bft2txt[$bft]);
        } else {
            $txt = '';
        }
        return $txt;
    }

    // Temperatur als Heatindex umrechnen
    //   Quelle: https://de.wikipedia.org/wiki/Hitzeindex
    public function CalcHeatindex(float $temp, float $hum)
    {
        if ($temp < 27 || $hum < 40) {
            return $temp;
        }
        $c1 = -8.784695;
        $c2 = 1.61139411;
        $c3 = 2.338549;
        $c4 = -0.14611605;
        $c5 = -1.2308094 * pow(10, -2);
        $c6 = -1.6424828 * pow(10, -2);
        $c7 = 2.211732 * pow(10, -3);
        $c8 = 7.2546 * pow(10, -4);
        $c9 = -3.582 * pow(10, -6);

        $hi = $c1
            + $c2 * $temp
            + $c3 * $hum
            + $c4 * $temp * $hum
            + $c5 * pow($temp, 2)
            + $c6 * pow($hum, 2)
            + $c7 * pow($temp, 2) * $hum
            + $c8 * $temp * pow($hum, 2)
            + $c9 * pow($temp, 2) * pow($hum, 2);
        $hi = round($hi); // ohne NK
        return $hi;
    }

    private function ms2kmh($speed)
    {
        return is_numeric($speed) ? $speed * 3.6 : '';
    }

    public function GetRawData()
    {
        $data = $this->GetMultiBuffer('Data');
        $this->SendDebug(__FUNCTION__, 'name=' . $name . ', size=' . strlen($data) . ', data=' . $data, 0);
        return $data;
    }
}
