/**
 * Piwik - Open source web analytics
 * 
 * @link http://www.suenkel.de/
 * @version $Id$
 * @author Christian Suenkel
 * 
 * The GeoIPMap-plugin is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by the Free
 * Software Foundation, either version 3 of the License, or (at your option) any
 * later version.
 * 
 * The GeoIPMap-plugin is distributed in the hope that it will be useful, but
 * WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more
 * details.
 * 
 * You should have received a copy of the GNU General Public License along with
 * GeoIPMap. If not, see <http://www.gnu.org/licenses/>. The GeoIPMap-Plugin is
 * free software: you can redistribute it and/or modify
 * 
 * it under the terms of the GNU General Public License as published by the Free
 * Software Foundation, either version 3 of the License, or (at your option) any
 * later version.
 * 
 * GeoIPMap is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License along with
 * GeoIPMap. If not, see <http://www.gnu.org/licenses/>.
 * 
 * @category Piwik_Plugins
 * @package Piwik_GeoIPMap
 */

var CS = {

	ajaxurl : null,
	mapdiv : null,
	osm : null,
	liveupdate : {
		enabled : false,
		intervalId : null,
		updateinterval : 30,
		liveLastTS : 0
	},
	MAP : {
		ajaxurl : null,
		markerlayer : null,
		popup : null,
		osm : null,
		markers : null,
		markersupdate : null,
	},
};

/**
 * Initialize Map-Layer
 * 
 * @param div -
 *            id of the
 */
CS.initMap = function(div) {
	CS.MAPdiv = div;

	CS.MAP.osm = new OpenLayers.Map(CS.MAPdiv, {
		maxExtent : new OpenLayers.Bounds(-20037508.34, -20037508.34,
				20037508.34, 20037508.34),
		// numZoomLevels: 15,
		// maxResolution: 156543.0399,
		// units: 'm',
		projection : new OpenLayers.Projection("EPSG:900913"),
		displayProjection : new OpenLayers.Projection("EPSG:4326"),
		controls : [ new OpenLayers.Control.Navigation(),
				new OpenLayers.Control.PanZoomBar(),
				new OpenLayers.Control.Attribution(),
				new OpenLayers.Control.LayerSwitcher() ]
	});


	/* Goggle maps */
	var gphy = new OpenLayers.Layer.Google("Google Physical", {
		type : google.maps.MapTypeId.TERRAIN
	});
	var gmap = new OpenLayers.Layer.Google("Google Streets", // the default
	{
		numZoomLevels : 20
	});
	var ghyb = new OpenLayers.Layer.Google("Google Hybrid", {
		type : google.maps.MapTypeId.HYBRID,
		numZoomLevels : 20
	});
	var gsat = new OpenLayers.Layer.Google("Google Satellite", {
		type : google.maps.MapTypeId.SATELLITE,
		numZoomLevels : 22
	});
	CS.MAP.osm.addLayers([ gphy, gmap, ghyb, gsat ]);

	
	/* Openstreet Maps */
	var layerMapnik = new OpenLayers.Layer.OSM.Mapnik("Mapnik");
	CS.MAP.osm.addLayers([ layerMapnik ]);

	
	// Center World ;)
	CS.MAP.osm.setCenter(new OpenLayers.LonLat(10.2, 48.9).transform(
			CS.MAP.osm.displayProjection, CS.MAP.osm.getProjectionObject()), 5);

	/*
	 * Add Marker-Layer
	 */
	var SHADOW_Z_INDEX = 10;
	var MARKER_Z_INDEX = 11;

	CS.MAP.markerlayer = new OpenLayers.Layer.Vector("Visitors", {
		styleMap : new OpenLayers.StyleMap({
			// Set the external graphic and background graphic images.
			externalGraphic : "plugins/GeoIPMap/js/img/marker-gold.png",
			backgroundGraphic : "plugins/GeoIPMap/js/img/marker_shadow.png",

			// Makes sure the background graphic is placed correctly relative
			// to the external graphic.
			backgroundXOffset : 0,
			backgroundYOffset : -7,

			// Set the z-indexes of both graphics to make sure the background
			// graphics stay in the background (shadows on top of markers looks
			// odd; let's not do that).
			graphicZIndex : MARKER_Z_INDEX,
			backgroundGraphicZIndex : SHADOW_Z_INDEX,
			pointRadius : 10
		}),
		// isBaseLayer : true,
		rendererOptions : {
			yOrdering : true
		},
		renderers : OpenLayers.Layer.Vector.prototype.renderers
	});

	CS.MAP.osm.addLayers([ CS.MAP.markerlayer ]);
	var sf = new OpenLayers.Control.SelectFeature(CS.MAP.markerlayer);
	CS.MAP.osm.addControl(sf);
	sf.activate();

	// control that will show a popup when clicking POS
	var popupControl = new OpenLayers.Control.SelectFeature(CS.MAP.markerlayer,
			{
				onSelect : function(feature) {
					var pos = feature.geometry;
					if (CS.MAP.popup) {
						CS.MAP.osm.removePopup(CS.MAP.popup);
					}
					CS.MAP.popup = new OpenLayers.Popup("popup",
							new OpenLayers.LonLat(pos.x, pos.y),
							new OpenLayers.Size(200, 180), "<h3>"
									+ feature.attributes.title + "</h3>"
									+ feature.attributes.description, true);
					CS.MAP.osm.addPopup(CS.MAP.popup);
				}
			});
	CS.MAP.osm.addControl(popupControl);
	popupControl.activate();
};

/**
 * Select a Tab
 * 
 * @param tabType
 */
CS.selectTab = function(tabType) {
	CS.MAP.reset(tabType);
};

/**
 * Reset the map an load new Data from Server
 * 
 * @param tabType
 */
CS.MAP.reset = function(tabType) {

	// set new Uniq-ID for ascyn processes
	CS.uniqLoad = Math.random();

	// stop liveupdate
	if (CS.liveupdate.intervalId != null) {
		clearInterval(CS.liveupdate.intervalId);
	}

	// install liveupdater and ajaxurl
	if (tabType != 'live') {
		CS.liveupdate.enabled = false;
		CS.MAP.ajaxurl = CS.ajaxurl + "&continent=" + tabType;
	} else {
		CS.liveupdate.enabled = true;
		CS.MAP.ajaxurl = CS.ajaxurl + "&dmode=live&timestamp="
				+ CS.liveupdate.liveLastTS;
		CS.liveupdate.intervalId = setInterval('CS.MAP.refresh()',
				CS.liveupdate.updateinterval * 1000);
	}

	/*
	 * Move ViewPort to the Continent
	 */
	switch (tabType) {
	// TODO: center viewport...
	default:
	case 'world':
	case '':
		CS.MAP.osm.setCenter(
				new OpenLayers.LonLat(10.2, 48.9).transform(
						CS.MAP.osm.displayProjection, CS.MAP.osm
								.getProjectionObject()), 5);
		break;
	}

	CS.MAP.refresh();
};

/**
 * Reload Data form the Server and refresh the markers
 */
CS.MAP.refresh = function() {

	var uniqLoaderId = CS.uniqLoad;

	// remove all Markers
	CS.MAP.markerlayer.removeFeatures(CS.MAP.markerlayer.features);

	// Load new Data:
	$
			.ajax({
				url : CS.MAP.ajaxurl,
				type : "GET",
				async : true,
				dataType : "json",
				fail : function(req) {
					alert('Request Failed');
				},
				success : function(req) {
					var features = [];
					var bounds = new OpenLayers.Bounds();

					if (req['data'].length <= 0)
						return;

					// transform ajax to openlayer-markers
					for ( var t = 0; t < req['data'].length; t++) {

						var location = req['data'][t];

						var myLatLng = new OpenLayers.LonLat(
								location.longitude, location.latitude)
								.transform(CS.MAP.osm.displayProjection,
										CS.MAP.osm.getProjectionObject());
						bounds.extend(myLatLng);

						var pixel = CS.MAP.osm
								.getViewPortPxFromLonLat(myLatLng);
						var lonLat = CS.MAP.osm.getLonLatFromViewPortPx(pixel);
						var geom = new OpenLayers.Geometry.Point(lonLat.lon,
								lonLat.lat);
						// console.log('mylat', geom);
						features.push(new OpenLayers.Feature.Vector(geom, {
							title : location.title,
							description : location.html
						}));

					}
					if (uniqLoaderId == CS.uniqLoad) {
						CS.MAP.markerlayer.addFeatures(features);
						CS.MAP.osm.zoomToExtent(bounds.scale(1.5));
					}
				}
			});

};
