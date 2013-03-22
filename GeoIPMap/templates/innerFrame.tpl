<head>
    <title>GeoIPMap</title>
{* 
   workaround for the crucial smarty-outputfilter "cachebuster"
   DONT!!! delete the two whitespaces between "script" an "type"
   UNSMART!! :-(
*}
    <script  type="text/javascript" src="http://maps.google.com/maps/api/js?v=3.5&amp;sensor=false"></script>
    <script  type="text/javascript" src="http://www.openlayers.org/api/OpenLayers.js"></script>
    <script  type="text/javascript" src="http://www.openstreetmap.org/openlayers/OpenStreetMap.js"></script>
    <script  type="text/javascript" src="libs/jquery/jquery.js"></script>
    <script  type="text/javascript" src="libs/jquery/jquery-ui.js"></script>
    <script  type="text/javascript" src="plugins/GeoIPMap/js/osm.js"></script>
    <link rel="stylesheet" href="libs/jquery/themes/base/jquery-ui.css" type="text/css" />
</head>
<body>
    <script type="text/javascript">
    </script>
    <div id="selector" style="font-size: smaller;" >
        <input type="radio" id="radio1" name="chooser" value="" checked="true"/><label for="radio1">World</label>
        <input type="radio" id="radio9" name="chooser" value="live"/><label for="radio9">Live-Data</label>
        <input type="radio" id="radio2" name="chooser" value="eur"/><label for="radio2">Europe</label>
        <input type="radio" id="radio3" name="chooser" value="amn"/><label for="radio3">North America</label>
        <input type="radio" id="radio4" name="chooser" value="ams"/><label for="radio4">South America</label>
        <input type="radio" id="radio5" name="chooser" value="amc"/><label for="radio4">Central America</label>
        <input type="radio" id="radio6" name="chooser" value="asi"/><label for="radio6">Asia</label>
        <input type="radio" id="radio7" name="chooser" value="afr"/><label for="radio7">Africa</label>
        <input type="radio" id="radio8" name="chooser" value="oce"/><label for="radio8">Oceania</label>
    </div>
    <div id="map" style="width: 100%; height: 90%; border: 1px solid rgb(119, 119, 119); overflow: hidden; position: relative; background-color: rgb(229, 227, 223);"><div id="updatetxt">On Updates: please refer to <a target="_blank" href="http://suenkel.de/asset/source/Piwik">Piwik-Plugins</a>-Page</div></div>
{literal}
    <script type="text/javascript">
        <!--
        
        $(window).ready(function () {
        
            CS.ajaxurl='{/literal}{$ajaxurl}{literal}';        
            CS.initMap('map');
            CS.selectTab();   
            $('#updatetxt').fadeOut(); 
        
       $('#selector').buttonset();
              $(this).find("input:radio[name='chooser']").click(function() {
                  CS.selectTab($("input:radio:checked[name='chooser']").val());
       
      });

            });

        // -->
    </script>
    {/literal}
</body>
