{if isset($weatherdata)}
<div id="weather-banner" class="w-100 h-auto">
    <p>{$weatherdata.name} ({$weatherdata.sys.country}), {$weatherdata.weather.0.main}, {$weatherdata.main.temp}ºC Min: {$weatherdata.main.temp_min}ºC Max: {$weatherdata.main.temp_max}ºC,  Pressure: {$weatherdata.main.pressure}hPa, Humidity: {$weatherdata.main.humidity}%</p>
</div>
{/if}