/**
 * KDNA Events Google Maps init.
 *
 * Lazy-loads the Google Maps JS API (deduplicated across the page),
 * initialises any element carrying a data-kdna-events-map attribute,
 * and handles Elementor editor re-initialisation so the map keeps
 * re-rendering as the author changes controls.
 */
(function () {
	'use strict';

	var mapsLoader = null;

	var STYLE_PRESETS = {
		'default': [],
		'silver': [
			{ elementType: 'geometry', stylers: [{ color: '#f5f5f5' }] },
			{ elementType: 'labels.icon', stylers: [{ visibility: 'off' }] },
			{ elementType: 'labels.text.fill', stylers: [{ color: '#616161' }] },
			{ elementType: 'labels.text.stroke', stylers: [{ color: '#f5f5f5' }] },
			{ featureType: 'administrative.land_parcel', elementType: 'labels.text.fill', stylers: [{ color: '#bdbdbd' }] },
			{ featureType: 'poi', elementType: 'geometry', stylers: [{ color: '#eeeeee' }] },
			{ featureType: 'poi', elementType: 'labels.text.fill', stylers: [{ color: '#757575' }] },
			{ featureType: 'poi.park', elementType: 'geometry', stylers: [{ color: '#e5e5e5' }] },
			{ featureType: 'poi.park', elementType: 'labels.text.fill', stylers: [{ color: '#9e9e9e' }] },
			{ featureType: 'road', elementType: 'geometry', stylers: [{ color: '#ffffff' }] },
			{ featureType: 'road.arterial', elementType: 'labels.text.fill', stylers: [{ color: '#757575' }] },
			{ featureType: 'road.highway', elementType: 'geometry', stylers: [{ color: '#dadada' }] },
			{ featureType: 'road.highway', elementType: 'labels.text.fill', stylers: [{ color: '#616161' }] },
			{ featureType: 'road.local', elementType: 'labels.text.fill', stylers: [{ color: '#9e9e9e' }] },
			{ featureType: 'transit.line', elementType: 'geometry', stylers: [{ color: '#e5e5e5' }] },
			{ featureType: 'transit.station', elementType: 'geometry', stylers: [{ color: '#eeeeee' }] },
			{ featureType: 'water', elementType: 'geometry', stylers: [{ color: '#c9c9c9' }] },
			{ featureType: 'water', elementType: 'labels.text.fill', stylers: [{ color: '#9e9e9e' }] }
		],
		'retro': [
			{ elementType: 'geometry', stylers: [{ color: '#ebe3cd' }] },
			{ elementType: 'labels.text.fill', stylers: [{ color: '#523735' }] },
			{ elementType: 'labels.text.stroke', stylers: [{ color: '#f5f1e6' }] },
			{ featureType: 'administrative', elementType: 'geometry.stroke', stylers: [{ color: '#c9b2a6' }] },
			{ featureType: 'administrative.land_parcel', elementType: 'geometry.stroke', stylers: [{ color: '#dcd2be' }] },
			{ featureType: 'administrative.land_parcel', elementType: 'labels.text.fill', stylers: [{ color: '#ae9e90' }] },
			{ featureType: 'landscape.natural', elementType: 'geometry', stylers: [{ color: '#dfd2ae' }] },
			{ featureType: 'poi', elementType: 'geometry', stylers: [{ color: '#dfd2ae' }] },
			{ featureType: 'poi', elementType: 'labels.text.fill', stylers: [{ color: '#93817c' }] },
			{ featureType: 'poi.park', elementType: 'geometry.fill', stylers: [{ color: '#a5b076' }] },
			{ featureType: 'poi.park', elementType: 'labels.text.fill', stylers: [{ color: '#447530' }] },
			{ featureType: 'road', elementType: 'geometry', stylers: [{ color: '#f5f1e6' }] },
			{ featureType: 'road.arterial', elementType: 'geometry', stylers: [{ color: '#fdfcf8' }] },
			{ featureType: 'road.highway', elementType: 'geometry', stylers: [{ color: '#f8c967' }] },
			{ featureType: 'road.highway', elementType: 'geometry.stroke', stylers: [{ color: '#e9bc62' }] },
			{ featureType: 'road.highway.controlled_access', elementType: 'geometry', stylers: [{ color: '#e98d58' }] },
			{ featureType: 'road.highway.controlled_access', elementType: 'geometry.stroke', stylers: [{ color: '#db8555' }] },
			{ featureType: 'road.local', elementType: 'labels.text.fill', stylers: [{ color: '#806b63' }] },
			{ featureType: 'transit.line', elementType: 'geometry', stylers: [{ color: '#dfd2ae' }] },
			{ featureType: 'transit.line', elementType: 'labels.text.fill', stylers: [{ color: '#8f7d77' }] },
			{ featureType: 'transit.line', elementType: 'labels.text.stroke', stylers: [{ color: '#ebe3cd' }] },
			{ featureType: 'transit.station', elementType: 'geometry', stylers: [{ color: '#dfd2ae' }] },
			{ featureType: 'water', elementType: 'geometry.fill', stylers: [{ color: '#b9d3c2' }] },
			{ featureType: 'water', elementType: 'labels.text.fill', stylers: [{ color: '#92998d' }] }
		],
		'dark': [
			{ elementType: 'geometry', stylers: [{ color: '#212121' }] },
			{ elementType: 'labels.icon', stylers: [{ visibility: 'off' }] },
			{ elementType: 'labels.text.fill', stylers: [{ color: '#757575' }] },
			{ elementType: 'labels.text.stroke', stylers: [{ color: '#212121' }] },
			{ featureType: 'administrative', elementType: 'geometry', stylers: [{ color: '#757575' }] },
			{ featureType: 'administrative.country', elementType: 'labels.text.fill', stylers: [{ color: '#9e9e9e' }] },
			{ featureType: 'administrative.locality', elementType: 'labels.text.fill', stylers: [{ color: '#bdbdbd' }] },
			{ featureType: 'poi', elementType: 'labels.text.fill', stylers: [{ color: '#757575' }] },
			{ featureType: 'poi.park', elementType: 'geometry', stylers: [{ color: '#181818' }] },
			{ featureType: 'poi.park', elementType: 'labels.text.fill', stylers: [{ color: '#616161' }] },
			{ featureType: 'poi.park', elementType: 'labels.text.stroke', stylers: [{ color: '#1b1b1b' }] },
			{ featureType: 'road', elementType: 'geometry.fill', stylers: [{ color: '#2c2c2c' }] },
			{ featureType: 'road', elementType: 'labels.text.fill', stylers: [{ color: '#8a8a8a' }] },
			{ featureType: 'road.arterial', elementType: 'geometry', stylers: [{ color: '#373737' }] },
			{ featureType: 'road.highway', elementType: 'geometry', stylers: [{ color: '#3c3c3c' }] },
			{ featureType: 'road.highway.controlled_access', elementType: 'geometry', stylers: [{ color: '#4e4e4e' }] },
			{ featureType: 'road.local', elementType: 'labels.text.fill', stylers: [{ color: '#616161' }] },
			{ featureType: 'transit', elementType: 'labels.text.fill', stylers: [{ color: '#757575' }] },
			{ featureType: 'water', elementType: 'geometry', stylers: [{ color: '#000000' }] },
			{ featureType: 'water', elementType: 'labels.text.fill', stylers: [{ color: '#3d3d3d' }] }
		],
		'night': [
			{ elementType: 'geometry', stylers: [{ color: '#242f3e' }] },
			{ elementType: 'labels.text.fill', stylers: [{ color: '#746855' }] },
			{ elementType: 'labels.text.stroke', stylers: [{ color: '#242f3e' }] },
			{ featureType: 'administrative.locality', elementType: 'labels.text.fill', stylers: [{ color: '#d59563' }] },
			{ featureType: 'poi', elementType: 'labels.text.fill', stylers: [{ color: '#d59563' }] },
			{ featureType: 'poi.park', elementType: 'geometry', stylers: [{ color: '#263c3f' }] },
			{ featureType: 'poi.park', elementType: 'labels.text.fill', stylers: [{ color: '#6b9a76' }] },
			{ featureType: 'road', elementType: 'geometry', stylers: [{ color: '#38414e' }] },
			{ featureType: 'road', elementType: 'geometry.stroke', stylers: [{ color: '#212a37' }] },
			{ featureType: 'road', elementType: 'labels.text.fill', stylers: [{ color: '#9ca5b3' }] },
			{ featureType: 'road.highway', elementType: 'geometry', stylers: [{ color: '#746855' }] },
			{ featureType: 'road.highway', elementType: 'geometry.stroke', stylers: [{ color: '#1f2835' }] },
			{ featureType: 'road.highway', elementType: 'labels.text.fill', stylers: [{ color: '#f3d19c' }] },
			{ featureType: 'transit', elementType: 'geometry', stylers: [{ color: '#2f3948' }] },
			{ featureType: 'transit.station', elementType: 'labels.text.fill', stylers: [{ color: '#d59563' }] },
			{ featureType: 'water', elementType: 'geometry', stylers: [{ color: '#17263c' }] },
			{ featureType: 'water', elementType: 'labels.text.fill', stylers: [{ color: '#515c6d' }] },
			{ featureType: 'water', elementType: 'labels.text.stroke', stylers: [{ color: '#17263c' }] }
		],
		'aubergine': [
			{ elementType: 'geometry', stylers: [{ color: '#1d2c4d' }] },
			{ elementType: 'labels.text.fill', stylers: [{ color: '#8ec3b9' }] },
			{ elementType: 'labels.text.stroke', stylers: [{ color: '#1a3646' }] },
			{ featureType: 'administrative.country', elementType: 'geometry.stroke', stylers: [{ color: '#4b6878' }] },
			{ featureType: 'administrative.land_parcel', elementType: 'labels.text.fill', stylers: [{ color: '#64779e' }] },
			{ featureType: 'administrative.province', elementType: 'geometry.stroke', stylers: [{ color: '#4b6878' }] },
			{ featureType: 'landscape.man_made', elementType: 'geometry.stroke', stylers: [{ color: '#334e87' }] },
			{ featureType: 'landscape.natural', elementType: 'geometry', stylers: [{ color: '#023e58' }] },
			{ featureType: 'poi', elementType: 'geometry', stylers: [{ color: '#283d6a' }] },
			{ featureType: 'poi', elementType: 'labels.text.fill', stylers: [{ color: '#6f9ba5' }] },
			{ featureType: 'poi', elementType: 'labels.text.stroke', stylers: [{ color: '#1d2c4d' }] },
			{ featureType: 'poi.park', elementType: 'geometry.fill', stylers: [{ color: '#023e58' }] },
			{ featureType: 'poi.park', elementType: 'labels.text.fill', stylers: [{ color: '#3C7680' }] },
			{ featureType: 'road', elementType: 'geometry', stylers: [{ color: '#304a7d' }] },
			{ featureType: 'road', elementType: 'labels.text.fill', stylers: [{ color: '#98a5be' }] },
			{ featureType: 'road', elementType: 'labels.text.stroke', stylers: [{ color: '#1d2c4d' }] },
			{ featureType: 'road.highway', elementType: 'geometry', stylers: [{ color: '#2c6675' }] },
			{ featureType: 'road.highway', elementType: 'geometry.stroke', stylers: [{ color: '#255763' }] },
			{ featureType: 'road.highway', elementType: 'labels.text.fill', stylers: [{ color: '#b0d5ce' }] },
			{ featureType: 'road.highway', elementType: 'labels.text.stroke', stylers: [{ color: '#023e58' }] },
			{ featureType: 'transit', elementType: 'labels.text.fill', stylers: [{ color: '#98a5be' }] },
			{ featureType: 'transit', elementType: 'labels.text.stroke', stylers: [{ color: '#1d2c4d' }] },
			{ featureType: 'transit.line', elementType: 'geometry.fill', stylers: [{ color: '#283d6a' }] },
			{ featureType: 'transit.station', elementType: 'geometry', stylers: [{ color: '#3a4762' }] },
			{ featureType: 'water', elementType: 'geometry', stylers: [{ color: '#0e1626' }] },
			{ featureType: 'water', elementType: 'labels.text.fill', stylers: [{ color: '#4e6d70' }] }
		]
	};

	/**
	 * Lazy-load the Google Maps JS API exactly once.
	 *
	 * Reuses an in-flight promise so multiple widgets on the same page
	 * do not duplicate the script tag. If another integration already
	 * loaded google.maps on the page, resolves immediately.
	 *
	 * @param {string} apiKey Google Maps JS API key.
	 * @return {Promise}
	 */
	function loadGoogleMaps(apiKey) {
		if (window.google && window.google.maps) {
			return Promise.resolve(window.google.maps);
		}
		if (mapsLoader) {
			return mapsLoader;
		}
		if (!apiKey) {
			return Promise.reject(new Error('Missing Google Maps API key'));
		}

		mapsLoader = new Promise(function (resolve, reject) {
			var callbackName = '__kdnaEventsMapsReady_' + Date.now();
			window[callbackName] = function () {
				try {
					delete window[callbackName];
				} catch (e) {
					window[callbackName] = undefined;
				}
				resolve(window.google.maps);
			};

			var src = 'https://maps.googleapis.com/maps/api/js?key=' + encodeURIComponent(apiKey) +
				'&callback=' + callbackName + '&loading=async&v=weekly';

			var script = document.createElement('script');
			script.src = src;
			script.async = true;
			script.defer = true;
			script.onerror = function () {
				mapsLoader = null;
				reject(new Error('Failed to load Google Maps'));
			};
			document.head.appendChild(script);
		});

		return mapsLoader;
	}

	/**
	 * Initialise a single map container.
	 *
	 * Idempotent, relies on a dataset flag so repeated calls from the
	 * Elementor frontend hooks do not stack maps or markers.
	 *
	 * @param {HTMLElement} element Container carrying data-kdna-events-map.
	 */
	function initMap(element) {
		if (!element || element.getAttribute('data-kdna-events-map-initialised') === '1') {
			return;
		}

		var lat = parseFloat(element.getAttribute('data-lat'));
		var lng = parseFloat(element.getAttribute('data-lng'));

		if (isNaN(lat) || isNaN(lng) || (lat === 0 && lng === 0)) {
			return;
		}

		var apiKey = (window.kdnaEvents && window.kdnaEvents.maps && window.kdnaEvents.maps.apiKey) || '';
		if (!apiKey) {
			return;
		}

		element.setAttribute('data-kdna-events-map-initialised', '1');

		var zoom = parseInt(element.getAttribute('data-zoom'), 10);
		if (isNaN(zoom)) {
			zoom = 15;
		}
		var presetKey = element.getAttribute('data-style-preset') || 'default';
		var markerIcon = element.getAttribute('data-marker-icon') || '';

		loadGoogleMaps(apiKey).then(function (maps) {
			var styles = STYLE_PRESETS[presetKey] || [];
			var map = new maps.Map(element, {
				center: { lat: lat, lng: lng },
				zoom: zoom,
				styles: styles,
				mapTypeControl: false,
				streetViewControl: false,
				fullscreenControl: true
			});

			var markerOptions = {
				position: { lat: lat, lng: lng },
				map: map
			};
			if (markerIcon) {
				markerOptions.icon = {
					url: markerIcon,
					scaledSize: new maps.Size(42, 42)
				};
			}
			new maps.Marker(markerOptions);
		}).catch(function () {
			element.setAttribute('data-kdna-events-map-initialised', '0');
		});
	}

	/**
	 * Initialise every uninitialised map container on the page.
	 */
	function initAllMaps() {
		var elements = document.querySelectorAll('[data-kdna-events-map]');
		for (var i = 0; i < elements.length; i++) {
			initMap(elements[i]);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initAllMaps);
	} else {
		initAllMaps();
	}

	// Elementor editor re-initialisation.
	if (typeof window.jQuery !== 'undefined') {
		window.jQuery(window).on('elementor/frontend/init', function () {
			if (!window.elementorFrontend || !window.elementorFrontend.hooks) {
				return;
			}
			window.elementorFrontend.hooks.addAction(
				'frontend/element_ready/kdna-events-event-location.default',
				function ($scope) {
					if (!$scope || !$scope[0]) {
						return;
					}
					var container = $scope[0].querySelector('[data-kdna-events-map]');
					if (container) {
						container.setAttribute('data-kdna-events-map-initialised', '0');
						initMap(container);
					}
				}
			);
		});
	}

	window.KDNAEventsMaps = {
		init: initAllMaps,
		initElement: initMap,
		presets: STYLE_PRESETS
	};
})();
