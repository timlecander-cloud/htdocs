<?php
// Config and globals remain the same
$GLOBALS['dbConnection'] = null;

// Add viewport-based caching
class ViewportCache {
  private static $cacheFile = 'viewport_cache.json';
  private static $cacheDuration = 300; // 5 minutes
    
  public static function getCacheKey($bounds) {
    return md5(json_encode($bounds));
  }
    
  public static function get($bounds) {
    if (!file_exists(self::$cacheFile)) {
      return null;
    }
        
    $cacheData = json_decode(file_get_contents(self::$cacheFile), true);
    $cacheKey = self::getCacheKey($bounds);
        
    if (!isset($cacheData[$cacheKey]) || (time() - $cacheData[$cacheKey]['timestamp'] > self::$cacheDuration)) {
      return null;
      }
        
    return $cacheData[$cacheKey]['data'];
   }
    
  public static function set($bounds, $data) {
    $cacheData = [];
    if (file_exists(self::$cacheFile)) {
      $cacheData = json_decode(file_get_contents(self::$cacheFile), true);
    }
        
    $cacheKey = self::getCacheKey($bounds);
    $cacheData[$cacheKey] = [
      'timestamp' => time(),
      'data' => $data
    ];
        
    file_put_contents(self::$cacheFile, json_encode($cacheData));
  }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Winneshiek County</title>
<style>
  html, body {
    height: 100%;
    margin: 0;
    padding: 0;
  }

  #hamburger-btn {
    position: fixed;
    top: 10px;
    left: 10px;
    z-index: 9999;
    background: #333;
    color: white;
    padding: 10px;
    border: none;
    cursor: pointer;
    border-radius: 5px;
    font-size: 36px;
  }

  #filter-panel {
    position: fixed;
    top: 50px;
    left: 10px;
    background: white;
    padding: 15px;
    border: 1px solid #ccc;
    box-shadow: 0 2px 4px rgba(0,0,0,0.3);
    display: none; /* Hidden by default */
    z-index: 10000;
    max-height: 80vh; /* Prevent it from covering the whole screen */
    overflow-y: auto; /* Scroll if content is too long */
    width: 800px; /* Keep it small so the map remains visible */
  }

  #loading-message {
    position: absolute;
    bottom: 5px;       /* anchor to bottom */
    right: 8px;        /* anchor to right */
    text-align: right; /* right-justify text */
    font-size: 48px;   /* 4x bigger than 12px */
    color: #ffcc00;    /* highlight color */
    display: none;     /* hidden by default */
    animation: flash 1s infinite; /* flashing animation */
  }

  @keyframes flash {
    0%, 50%, 100% { opacity: 1; }
    25%, 75% { opacity: 0; }
  }

  #viewSelector,
  #areaSelector,
  label[for="viewSelector"],
  label[for="areaSelector"] {
    font-family: 'Segoe UI', sans-serif;   /* Choose your typeface */
    font-size: 24px;                       /* Adjust size */
    font-weight: 500;                      /* Semi-bold for clarity */
    color: #333;                           /* A crisp, legible color */
  }

  .party-filters label {
    font-family: 'Roboto', sans-serif;  /* or 'Roboto', 'Segoe UI', etc. */
    font-size: 24px;                        /* Adjust as needed */
    font-weight: 500;                       /* Medium weight */
    color: #333;                            /* Dark text for clarity */
    line-height: 1.4;                       /* Improves readability */
    margin-bottom: 6px                    /* Space between labels */
    }

  .label.democrats {
    color: #1e90ff; /* Dodger Blue for Democrats */
  }

  .label.republican {
    color: #d32f2f; /* Republican Red */
  }

  .label.noparty {
    color: #D8BFD8;
  }

  .label.others {
    color: #008000; /* Green */
  }

  .label.not-registered {
    color: #FFFF00; /* Yellow */
  }

  .neighborhood-filter label {
    font-family: 'Roboto', sans-serif;  /* or 'Roboto', 'Segoe UI', etc. */
    font-size: 14px;                        /* Adjust as needed */
    font-weight: 500;                       /* Medium weight */
    color: #333;                            /* Dark text for clarity */
    line-height: 1.4;                       /* Improves readability */
    margin-bottom: 6px                    /* Space between labels */
  }

  .voterstatus-filter label {
    font-family: 'Roboto', sans-serif;  /* or 'Roboto', 'Segoe UI', etc. */
    font-size: 14px;                        /* Adjust as needed */
    font-weight: 500;                       /* Medium weight */
    color: #333;                            /* Dark text for clarity */
    line-height: 1.4;                       /* Improves readability */
    margin-bottom: 6px                    /* Space between labels */
  }

  .township-label {
    font-size: 48px;              /* 4x larger than the default 12px */
    font-weight: 600;
    color: rgba(34, 34, 34, 0.25); /* transparent text (25% opacity) */
    background: rgba(255, 255, 255, 0.25); /* optional: lighter background */
    border: none;                 /* optional: remove border if you want it cleaner */
    padding: 2px 6px;
    border-radius: 4px;
    pointer-events: none;         /* let clicks fall through to polygon */
  }

  #map {
      position: relative;
      height: 100vh; /* Full height */
      width: 100%; /* Full width */
  }

  .custom-marker {
      cursor: pointer;
      position: relative;
  }

  .custom-marker svg {
      position: absolute;
      transform: translate(-50%, -100%);
      transition: all 0.2s ease-in-out;
  }

  .custom-marker:hover svg {
      transform: translate(-50%, -100%) scale(1.2);
      z-index: 1001 !important;
  }

  /* 09-10-25 Voter Detail */
  .modal {
    position: fixed;
    top: 10%;
    left: 50%;
    transform: translateX(-50%);
    background: white;
    border: 1px solid #ccc;
    padding: 1rem;
    z-index: 9999;
    box-shadow: 0 2px 10px rgba(0,0,0,0.3);
    display: none;
  }

      .modal.hidden {
    display: none;
  }

  .modal.visible {
    display: block;
  }

  .hidden {
    display: none;
  }

  #drawTooltip {
    position: fixed;
    top: 20px;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(0, 0, 0, 0.75);
    color: #fff;
    padding: 6px 10px;
    border-radius: 4px;
    font-size: 14px;
    pointer-events: none;
    display: none;
    z-index: 99999;
  }

  #debugOverlay {
    position: fixed;
    bottom: 10px;
    left: 10px;
    background: rgba(0, 0, 0, 0.7);
    color: #fff;
    padding: 8px 12px;
    font-size: 12px;
    border-radius: 4px;
    z-index: 9999;
    pointer-events: none;
  }
</style>
<!-- Load Google Maps JavaScript API (not) relocated at bottom of body 10-24-25 -->
 <!--
<script async src="https://maps.googleapis.com/maps/api/js?key=AIzaSyC_KbKXsaVsdkaOvEHWYfP0Gn1lBGB-eRU&loading=async&callback=initMap&libraries=marker,drawing" loading="async">
-->
<!-- </script> -->
<!--
<script async src="https://maps.googleapis.com/maps/api/js?key=AIzaSyC_KbKXsaVsdkaOvEHWYfP0Gn1lBGB-eRU&loading=async&callback=initMap"></script>
-->

</head>
<body>
  <!-- Hamburger menu button -->
  <button id="hamburger-btn">☰ Filters</button>

  <!-- Hidden filters container -->
  <div id="filter-panel">
    <!-- View Selector Dropdown -->
    <label for="viewSelector">Current View:</label>
    <select id="viewSelector">
      <option value="precinct">Voting Precincts</option>
      <option value="township">Townships</option>
      <option value="ward">Wards</option>
      <option value="supervisor">Supervisors</option>
    </select>

    <!-- Area Options Dropdown -->
    <label for="areaSelector">Select Area:</label>
    <select id="areaSelector"></select>

    <div class="party-filters">
      <label class="label democrats"><input type="checkbox" value="DEM" id="filter-dem"> Democrats</label>
      <label class="label republican"><input type="checkbox" value="REP" id="filter-rep"> Republicans</label>
      <label class="label noparty"><input type="checkbox" value="NP" id="filter-np"> No Party</label>
      <label class="label others"><input type="checkbox" value="OTH" id="filter-oth"> Others</label>
      <label class="label not-registered"><input type="checkbox" value="Not registered" id="filter-nr"> Not registered</label>
    </div>

    <!-- ✅ New Neighborhoods checkbox -->
    <div class="neighborhood-filter">
      <label><input type="checkbox" id="filter-neighborhoods"> Neighborhoods Only</label>
    </div>

    <div class="strong-voter-filters">
      <label class="label strongvoters"><input type="checkbox" value="Strong" id="filter-strongvoters"> Strong Voters Only</label>
    </div>

    <div class="young-strong-voter-filters">
      <label class="label youngstrongvoters"><input type="checkbox" value="YoungStrong" id="filter-youngstrongvoters"> Young Strong Voters Only (under 28)</label>  
    </div>

    <div class="voterstatus-filter">
      <label class="label inactivevoters"><input type="checkbox" value="Inactive" id="filter-voterstatus"> Inactive Voters Only (any party)</label>
    </div>

    <div class="needs-ride-filter">
      <label><input type="checkbox" value="NeedsRide" id="filter-needs-ride"> Needs Ride to Poll</label>
    </div>
    
    <div class="Township Trustee or Clerk">
      <label><input type="checkbox" value="TrusteeClerk" id="filter-trustee-clerk"> Township Trustee or Clerk</label>
    </div>

    <!--<button id="drawRectangleBtn">Draw Rectangle</button>-->
    <button id="drawRectangleBtn" style="display: none;">Draw Rectangle</button>

    <!-- Loading message element -->
    <div id="loading-message">⏳ Loading markers...</div>
  </div> <!-- end of filter-panel -->

  <div id="drawTooltip" style="
  position: fixed;
  top: 20px;
  left: 50%;
  transform: translateX(-50%);
  background: rgba(0,0,0,0.75);
  color: #fff;
  padding: 6px 10px;
  border-radius: 4px;
  font-size: 14px;
  pointer-events: none;
  display: none;
  z-index: 9999;
">Click to start drawing</div>

 <div id="debugOverlay">Debug overlay is active</div>
  
  <!--<div id="map" style="height: 600px;"></div>-->

  <div id="map"></div>

  <!-- 09-10-25 Voter Detail -->
  <!-- Modal container -->
  <div id="voter-modal" class="modal hidden">
    <button class="close-button">x</button>
    <div class="modal-content"></div>
    <div class="modal-name"></div>
    <div class="modal-party"></div>
    <div class="modal-status"></div>
    <div class="modal-birthdate"></div>
    <div class="modal-age"></div>
    <div class="modal-general_election_history"></div>
    <div class="modal-strong_voter"></div>
    <div class="modal-young_strong_voter"></div>
  </div>

  <div id="progressWindow" style="
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: white;
    border: 1px solid #ccc;
    padding: 20px;
    z-index: 9999;
    display: none;
    box-shadow: 0 0 10px rgba(0,0,0,0.2);
    text-align: center;
  ">
    <div style="margin-bottom: 10px;">Updating markers…</div>
    <progress id="progressBar" value="0" max="100" style="width: 250px;"></progress>
  </div>

  <script>
    // Declare global filter sets near the top of your script
    window.activeFilters = new Set(); // includes DEM, REP, Strong, YoungStrong, etc.
    window.currentViewType = null; // 'precinct', 'township', etc.
    window.selectedAreaValue = null; // e.g., 'Franklin 1'

    // Positions hamburger-btn
    document.getElementById('hamburger-btn').addEventListener('click', function() {
      const filterPanel = document.getElementById('filter-panel');
      filterPanel.style.display = (filterPanel.style.display === 'none' || filterPanel.style.display === '') ? 'block' : 'none';
      //console.log('Filter panel toggled to', filterPanel.style.display, 'at', new Date().toISOString());
    });
  
  
  window.allMarkers = [];

  const markerCache = new Map(); // voterId → marker

  let AdvancedMarkerElement; // 10-05-25 Steamlining creation of markers.
  const townshipOptions = ['none', 'all', 'Bloomfield', 'Bluffton', 'Burr Oak', 'Calmar', 'Canoe', 'Decorah', 'Frankville', 'Fremont', 'Glenwood', 'Hesper', 'Highland', 'Jackson', 'Lincoln', 'Madison', 'Military', 'Orleans', 'Pleasant', 'Springfield', 'Sumner', 'Washington'];
  const precinctOptions = ['none', 'all', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'];
  const wardOptions = ['none','all','DE1','DE2','DE3','DE4','DE5'];
  const supervisorOptions = ['none','1','2','3','4','5'];

  let isDrawing = false;
  let startLatLng = null;
  let rectangleOverlay = null;

  let drawnPolygons = [];
  let drawnPolygonMarkers = [];
  let currentViewType = null; // track the last drawn type

  // For smart toggling of visibility for markers.
  const selectedParties = {
    DEM: false,
    REP: false,
    NP: false,
    OTH: false,
    'Not Registered': false,
    'Strong': false,
    'YoungStrong': false,
    'Inactive': false,
    'NeedsRide': false
    // add others as needed
  };

  const loadedParties = {
    DEM: false,
    REP: false,
    NP: false,
    OTH: false,
    'Not Registered': false,
    'Strong': false,
    'YoungStrong': false,
    'Inactive': false,
    'NeedsRide': false
    // add others as needed
  };

  // Dropdown population function
  function populateAreaOptions(view) {
    const areaSelector = document.getElementById('areaSelector');
    areaSelector.innerHTML = '';

    let options = [];
    switch (view) {
      case 'township':
        options = townshipOptions;
        break;
      case 'precinct':
        options = precinctOptions;
        break;
      case 'ward':
        options = wardOptions;
        break;
      case 'supervisor':
        options = supervisorOptions;
        break;
      default:
        console.warn(`Unknown view: ${view}`);
        return;
    }
    
    options.forEach(area => {
      const opt = document.createElement('option');
      opt.value = area;
      opt.textContent = area;
      areaSelector.appendChild(opt);
    });
  }

  async function handleView() {
  const currentView = document.getElementById('viewSelector').value;
  const selectedArea = document.getElementById('areaSelector').value;

  //console.log('handleView called at', new Date().toISOString(), 'with view:', currentView, 'and area:', selectedArea);

    switch (currentView) {
      case 'township':
        const townships = selectedArea;
        if (townships === 'none' || !townships) {
          return;
        }

        await loadMarkersInBounds(currentView, selectedArea);
        break;

      case 'precinct':
        if (selectedArea === 'none') {
          //console.log('No precinct selected, hiding all markers');
          ['DEM', 'REP', 'NP', 'OTH', 'Not registered'].forEach(party => {
            //console.log(`Hiding markers for party: ${party}`);
            //setFilterVisibility(party, false); // 10-18-25 07:15 Need to address this separately. This has been deprecated.
          });
          return;
        }
        // handle precinct logic here
        await loadMarkersInBounds(currentView, selectedArea);
        break;

      case 'ward':
        await loadMarkersInBounds(currentView, selectedArea);
        break;

      case 'supervisor':
        await loadMarkersInBounds(currentView, selectedArea);
        break;    

      default:
        console.warn("Unknown view:", currentView);
        return;
    }

    drawPolygons(currentView);
  }

  function drawPolygons(viewType) {
    // If the viewType hasn't changed, skip redraw
    if (viewType === currentViewType) {
      //console.log(`Skipping redraw: ${viewType} already displayed`);
      return;
    }

    // Clear any previously drawn polygons
    drawnPolygons.forEach(polygon => polygon.setMap(null));
    drawnPolygons = [];

    // Clear any previously drawn markers
    drawnPolygonMarkers.forEach(marker => marker.map = null);
    drawnPolygonMarkers = [];

    // Define stroke/fill styles per view
    const viewStyles = {
      township: { strokeColor: '#0000FF', fillColor: '#0000FF' },
      precinct: { strokeColor: '#0000FF', fillColor: '#0000FF' },
      //precinct: { strokeColor: '#c3c3e9ff', fillColor: '#c3c3e9ff' }
    };

    // Only draw if viewType is supported
    if (!['township', 'precinct'].includes(viewType)) {
      //console.log(`${viewType.charAt(0).toUpperCase() + viewType.slice(1)} view selected (no polygons to draw)`);
      return;
    }

    //console.log(`${viewType.charAt(0).toUpperCase() + viewType.slice(1)} view selected`);

    fetch(`get_boundaries.php?type=${viewType}`)
      .then(response => response.json())
      .then(geojson => {
        geojson.features.forEach(feature => {
          const geom = feature.geometry;
          if (!geom || !geom.coordinates) {
            console.warn('Missing geometry:', feature);
            return;
          }

          let rings = [];

          if (geom.type === 'Polygon') {
            rings = geom.coordinates;
          } else if (geom.type === 'MultiPolygon') {
            rings = geom.coordinates.flat();
          } else {
            console.warn('Unsupported geometry type:', geom.type);
            return;
          }

          rings.forEach(ring => {
            const path = ring.map(coord => ({
              lat: coord[1], // GeoJSON: [lng, lat] → Google Maps: {lat, lng}
              lng: coord[0]
            }));

            const polygon = new google.maps.Polygon({
              paths: path,
              strokeColor: viewStyles[viewType].strokeColor,
              strokeOpacity: 0.8,
              strokeWeight: 2,
              fillColor: viewStyles[viewType].fillColor,
              fillOpacity: 0.35
            });

            //const centroid = getPolygonCentroid(polygon.getPath());

            // If your precinct_boundaries table includes override_lat and override_lng,
            // make sure they are passed through into feature.properties.

            const hasOverride =
              feature.properties?.override_lat != null &&
              feature.properties?.override_lng != null;

            const position = hasOverride
              ? { lat: feature.properties.override_lat, lng: feature.properties.override_lng }
              : getPolygonCentroid(polygon.getPath());            

            // Create a DOM element for the label
            const labelDiv = document.createElement("div");
            labelDiv.className = "township-label";
            //labelDiv.textContent = "Union Township";
            labelDiv.textContent = feature.properties?.name ?? "Unnamed entity";

            // Drop the label with AdvancedMarkerElement
            const marker = new google.maps.marker.AdvancedMarkerElement({
              position: position,
              map: map,
              content: labelDiv,
              collisionBehavior: google.maps.CollisionBehavior.REQUIRED
            });
            drawnPolygonMarkers.push(marker);

            polygon.setMap(map);
            drawnPolygons.push(polygon);
          });
        });

        // Update the current viewType after successful draw
        currentViewType = viewType;        
      })
      .catch(error => console.error(`Error loading ${viewType} boundaries:`, error));
  }

  function getPolygonCentroid(path) {
    let lat = 0, lng = 0;
    path.forEach(function(coord) {
      lat += coord.lat();
      lng += coord.lng();
    });
    return { lat: lat / path.length, lng: lng / path.length };
  }

  // In your main HTML file
  async function loadMarkersInBounds(view, area) {
    showLoadingMessage();

    const bounds = window.map.getBounds();
    const ne = bounds.getNorthEast();
    const sw = bounds.getSouthWest();
    const allFilters = Array.from(window.activeFilters);
    const parties = allFilters.filter(f =>
      ['DEM', 'REP', 'NP', 'OTH', 'Not registered'].includes(f)
    );

    const partyParams = parties.map(p => `parties[]=${encodeURIComponent(p)}`).join('&');

    //console.log('Fetching markers with parties:', parties, 'and params:', partyParams);

    let townshipParams = '';
    let precinctParams = '';
    let wardParams = '';
    let supervisorParams = '';
    
    switch (view) {
      case 'township':
        townshipParams = `townships=${encodeURIComponent(area)}`;
        break;
      case 'precinct':
        precinctParams = `precincts=${encodeURIComponent(area)}`;
        break;
      case 'ward':
        wardParams = `wards=${encodeURIComponent(area)}`;
        break;
      case 'supervisor':
        supervisorParams = `supervisors=${encodeURIComponent(area)}`;
        break;
    }

    const neighborhoodChecked = document.getElementById('filter-neighborhoods').checked;
    const neighborhoodParam = neighborhoodChecked ? '&neighborhoods=true' : '';
    const filterInactiveOnly = document.getElementById('filter-voterstatus').checked;
    const filterStrongVotersOnly = document.getElementById('filter-strongvoters').checked;
    const filterYoungStrongVotersOnly = document.getElementById('filter-youngstrongvoters').checked;
    const filterNeedsRide = document.getElementById('filter-needs-ride').checked;
    const filterNotRegistered = document.getElementById('filter-nr').checked;

    const contentString = `
    <div style="max-height: 200px; overflow-y: auto;">
      <p>This is a long block of text that will scroll if it exceeds 200px in height.</p>
      <p>More content...</p>
      <p>Even more content...</p>
    </div>
    `;

    const infoWindow = new google.maps.InfoWindow({
      content: contentString
    });

    const voterIdArray = []; //Moved outside fetch to accumulate IDs across all markers
    const voterAptArray = []; // Moved outside fetch to accumulate apt info

    const AllParties = 'parties[]=DEM&parties[]=REP&parties[]=NP&parties[]=OTH&parties[]=Not%20registered';

    const url = `get_markers.php?north=${ne.lat()}&south=${sw.lat()}&east=${ne.lng()}&west=${sw.lng()}&${AllParties}&${townshipParams}&${neighborhoodParam}&${precinctParams}&${wardParams}&${supervisorParams}`;
    //const url = 'markers.json';

    //showLoadingMessage();
    try {
      const response = await fetch(url);
      //console.log('Response received at', new Date().toISOString());

      const data = await response.json();
      //console.log('Data structure:', data);

      if (data.markers) {
        //console.log('Markers loaded:', data.markers.length);
        const positionGroups = {};
        for (const markerData of data.markers) {
          //showLoadingMessage();
          const positionKey = `${markerData.latitude},${markerData.longitude}`;
          if (!positionGroups[positionKey]) {
            positionGroups[positionKey] = [];
          }
          positionGroups[positionKey].push(markerData);
        }

        for (const group of Object.values(positionGroups)) {
          //group.sort((a, b) => a.last_name.localeCompare(b.last_name));
          // for (const marker of group) {
          //   if (!marker.last_name) {
          //     console.warn('Missing last_name:', marker);
          //   }
          // }

          group.sort((a, b) => {
            const nameA = a.last_name || '';
            const nameB = b.last_name || '';
            return nameA.localeCompare(nameB);
          });

          let voterIdArray = []; // Local array for clustered markers
          let voterAptArray = []; // Local array for apartment info
          const markerData = group[0];

          const groupHasStrongVoter = group.some(m => String(m.strong_voter).toLowerCase() === "t");
          const groupHasStrongVoterCount = group.filter(m => String(m.strong_voter).toLowerCase() === "t").length;
          const groupHasYoungStrongVoter = group.some(m => String(m.young_strong_voter).toLowerCase() === "t");
          const groupHasYoungStrongVoterCount = group.filter(m => String(m.young_strong_voter).toLowerCase() === "t").length;
          const groupHasInactive = group.some(m => m.voterstatus && m.voterstatus.toLowerCase().trim() === 'inactive');
          const groupHasInactiveCount = group.filter(m => m.voterstatus && m.voterstatus.toLowerCase().trim() === 'inactive').length;
          const groupHasNeedsRide = group.some(m => String(m.needs_ride_to_poll).toLowerCase() === "t");
          const groupHasTrusteeClerk = group.some(m => m.township_trustee_or_clerk && m.township_trustee_or_clerk.toLowerCase() === 't');
          const groupHasNeedsRideCount = group.filter(m => String(m.needs_ride_to_poll).toLowerCase() === "t").length;
          const groupHasTrusteeClerkCount = group.filter(m => m.township_trustee_or_clerk && m.township_trustee_or_clerk.toLowerCase() === 't').length;

          let shouldInclude;

          //console.log('m.party for group:', group.map(m => m.party)); // Returns valid party values
          //console.log('Coordinates for group:', group.map(m => `${m.latitude},${m.longitude}`));

        // ✅ Apply full filter stack for registered voters
        for (const m of group) {
            voterIdArray.push(m.voterid);
            voterAptArray.push(m.apartment);
          } // End for m of group

          //showLoadingMessage();

          const labelText = `${voterIdArray.length} voters\n`;
          const address = group[0]?.address || 'Unknown address';

          const namesList = group
            .map(m => {
              if (m.party === 'Not registered') {
                return `(Not registered) ${m.apartment}`;
              } else {
                return `${m.first_name} ${m.last_name} ${m.apartment} (${m.party})`;
              }
            })
            .join('\r\n');

            //console.log('Creating marker for address:', address, 'with voters:\n', namesList);

          // If voterIdArray is > 10, use circle with count label. Otherwise, use offset markers.
          if (voterIdArray.length > 10) {
            if (!markerCache.has(voterIdArray[1])) {
              const container = document.createElement('div');
              container.style.position = 'relative';
              container.style.display = 'flex';
              container.style.flexDirection = 'column';
              container.style.alignItems = 'center';

              const circle = document.createElement('div');
              circle.style.width = '40px';       // scale * 2
              circle.style.height = '40px';
              circle.style.borderRadius = '50%';
              circle.style.backgroundColor = '#FFFF00';  // fillColor
              circle.style.opacity = '0.9';              // fillOpacity
              circle.style.border = '1px solid #fff';    // strokeColor + strokeWeight
              circle.style.boxSizing = 'border-box';
            
              // Label
              const label = document.createElement('div');
              label.textContent = labelText; // or dynamic content
              label.style.position = 'absolute';
              label.style.top = '50%';
              label.style.left = '50%';
              label.style.transform = 'translate(-50%, -50%)';
              label.style.fontSize = '16px';
              label.style.fontWeight = 'bold';
              label.style.color = '#000';

              // Assemble
              container.appendChild(circle);
              container.appendChild(label);

              const partyCounts = group.reduce((acc, m) => {
                const party = m.party || 'UNK';
                acc[party] = (acc[party] || 0) + 1;
                return acc;
              },
              {} // Needed apparently. Otherwise, includes latitude, etc.
              ); // End of reduce

              const partySummary = Object.entries(partyCounts).map(([party, count]) => `${party}: ${count}`).join(', ');

              const tooltipContent = `${address}\r\n${partySummary}`;

              container.title = tooltipContent;
              
              const marker = new AdvancedMarkerElement({
                position: {
                  lat: parseFloat(markerData.latitude),
                  lng: parseFloat(markerData.longitude)
                },
                map: window.map,                
                content: container
              }); // End of marker

              // Attach metadata manually
              const party = markerData.party;
              const precinct = markerData.precinct;
              const township = markerData.township;
              const ward = markerData.ward;
              const supervisor = markerData.supervisor;

              marker.metadata = {
                party,
                precinct,
                township,
                ward,
                supervisor,
                strong_voter: markerData.strong_voter,
                young_strong_voter: markerData.young_strong_voter,
                voterstatus: markerData.voterstatus,
                needs_ride_to_poll: markerData.needs_ride_to_poll,
                township_trustee_or_clerk: markerData.township_trustee_or_clerk
              };                      

              attachClusteredMarkerClick(marker, voterIdArray, address, voterAptArray, map, infoWindow);

              marker.data = markerData; // Stuff things party, etc. 
              markerCache.set(voterIdArray[1], marker); // cache for reuse
              window.allMarkers.push(marker); // For "clustered" Markers
            } // End of if (!markerCache.has(voterIdArray[1]))
          } else { // End if voterIdArray.length > 10
            //showLoadingMessage();
            group.forEach(markerData => {
              voterIdArray.forEach((id, index) => {
                if (id === markerData.voterid) {
                  if (!markerCache.has(markerData.voterid)) {   
                    const baseSize = 24;
                    const sizeReduction = -4;
                    const minSize = 12;
                    const markerSize = Math.max(baseSize - (index * sizeReduction), minSize);
                    const offset = index * 3;
                    const party = markerData.party;
                    const precinct = markerData.precinct;
                    const township = markerData.township;
                    const ward = markerData.ward;
                    const supervisor = markerData.supervisor;

                    const markerElement = document.createElement('div');
                    markerElement.className = 'custom-marker';
                    markerElement.dataset.party = markerData.party;
                    markerElement.dataset.township = markerData.township;
                    markerElement.innerHTML = `
                      <svg viewBox="0 0 24 24" 
                      width="${markerSize}" 
                      height="${markerSize}" 
                      style="margin-left: ${offset}px; margin-top: ${-offset}px;">
                      <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z" 
                      fill="${getPartyColor(markerData.party, markerData.voterstatus)}" 
                      stroke="black" stroke-width="1"/>
                      </svg>
                    `;

                    //console.log('namesList for marker:', namesList);
                    const tooltipContent = `${address}\r\n${namesList}`;

                    markerElement.title = tooltipContent;

                    const marker = new AdvancedMarkerElement({
                      position: {
                        lat: parseFloat(markerData.latitude),
                        lng: parseFloat(markerData.longitude)
                      },
                      map: window.map,
                      content: markerElement,
                      zIndex: 1000 - index
                    }); // End of marker

                    // Attach metadata manually
                    marker.metadata = {
                      party,
                      precinct,
                      township,
                      ward,
                      supervisor,
                      strong_voter: markerData.strong_voter,
                      young_strong_voter: markerData.young_strong_voter,
                      voterstatus: markerData.voterstatus,
                      needs_ride_to_poll: markerData.needs_ride_to_poll,
                      township_trustee_or_clerk: markerData.township_trustee_or_clerk
                    };                      

                    attachClusteredMarkerClick(marker, voterIdArray, address, voterAptArray, map, infoWindow);

                    marker.data = markerData; // Stuff things party, etc. 
                    markerCache.set(markerData.voterid, marker); // cache for reuse
                    window.allMarkers.push(marker); // For non-clustered Markers

                    //console.log('Created marker for voterid:', markerData.voterid, 'at', new Date().toISOString(), 'with metadata:', marker.metadata);
                  } // end if if (!markerCache.has(markerData.voterid))
                } // End of id check
              }); // End of voterIdArray.forEach
            }); // End of group.forEach (groups < 10)
          } // End of else (voterIdArray.length <= 10
        }
      }
    } catch (error) { // End of try-catch
      console.error('Error in loadMarkersInBounds fetch:', error);
    }
    hideLoadingMessage();
  } // End of loadMarkersInBounds

  const ENABLE_MARKER_CLICK = true;

  function attachClusteredMarkerClick(marker, voterIdArray, address, voterAptArray, map, infoWindow) {
    marker.addListener('click', () => {
      if (!ENABLE_MARKER_CLICK) return;

      const fetchPromises = voterIdArray.map((id, index) => {
        const apt_info = voterAptArray[index];

        if (id === 'not registered') {
          return Promise.resolve({
            first_name: 'Not Registered',
            last_name: '',
            apt_info: apt_info
          });
        } else {
          return fetch(`/get_voter_details.php?regn_num=${id}`)
            .then(res => res.json())
            .then(voter => ({ ...voter, apt_info })); // attach apt_info to fetched voter
        }
      });

      Promise.all(fetchPromises).then(voterDetailsArray => {
        const voterBlocks = voterDetailsArray.map(voter => {
          if (voter.first_name === 'Not Registered') {
            return `
              <div style="margin-bottom: 8px;">
                <strong>Not Registered ${voter.apt_info}</strong>
              </div>
            `;
          }

          const age = voter.birthdate ? calculateAge(voter.birthdate) : 'Unknown';
          const history = voter.general_election_history || 'None';
          //const strong = voter.strong_voter ? 'Yes' : 'No';
          const strong = voter.strong_voter === 't' ? 'Yes' : 'No';
          //const youngStrong = voter.young_strong_voter ? 'Yes' : 'No';
          const youngStrong = voter.young_strong_voter === 't' ? 'Yes' : 'No';
          const needsRide = voter.needs_ride_to_poll === 't' ? 'Yes' : 'No';
          const trusteeClerk = voter.township_trustee_or_clerk === 't' ? 'Yes' : 'No';

          return `
            <div style="margin-bottom: 8px;">
              <strong>${voter.first_name} ${voter.last_name} ${voter.apt_info}</strong><br>
              Party: ${voter.party}<br>
              Status: ${voter.voterstatus}<br>
              Age: ${age}<br>
              History: ${history}<br>
              Strong Voter: ${strong}<br>
              Young Strong Voter: ${youngStrong}<br>
              Needs ride to poll: ${needsRide}<br>
              Township Trustee or Clerk: ${trusteeClerk}
            </div>
          `;
        }).join('<hr>');

        const htmlContent = `
          <div style="max-width: 300px; font-size: 12px;">
            <strong>${address}</strong><br><br>
            ${voterBlocks}
          </div>
        `;

        infoWindow.setContent(htmlContent);
        infoWindow.open(map, marker);
      }).catch(err => {
        console.error('Error fetching voter details:', err);
        infoWindow.setContent('<div>Error loading voter details.</div>');
        infoWindow.open(map, marker);
      });
    });
  } // end of attachClusteredMarkerClick

  function calculateAge(birthdateStr) {
    const birthdate = new Date(birthdateStr);
    const today = new Date();

    let age = today.getFullYear() - birthdate.getFullYear();
    const monthDiff = today.getMonth() - birthdate.getMonth();
    const dayDiff = today.getDate() - birthdate.getDate();

    if (monthDiff < 0 || (monthDiff === 0 && dayDiff < 0)) {
      age--; // birthday hasn't occurred yet this year
    }

    return age;
  }

  function getPartyColor(party,voterstatus) {
    switch(party) {
      case 'OTH': return '#008000';
      case 'NP': return '#D8BFD8';
      case 'REP': return '#d32f2f';
      case 'DEM': return '#1e90ff';
      case 'Not registered': return '#FFFF00';
      default: return '#000000';
    }
  }

  window.initMap = function() {
    (async () => {
    //console.log('initMap called at', new Date().toISOString());
    // First import the libraries

    // Default center if no saved state
    const defaultCenter = { lat: 43.2844, lng: -91.8237 };
    const defaultZoom = 11.3;

    // Check localStorage for saved state
    const savedZoom = localStorage.getItem("mapZoom");
    const savedBounds = localStorage.getItem("mapBounds");

    const { Map } = await google.maps.importLibrary("maps");
    
    const { AdvancedMarkerElement: AME } = await google.maps.importLibrary("marker");
    const { DrawingManager } = await google.maps.importLibrary("drawing");
    
    AdvancedMarkerElement = AME;    

    startLatLng = null;
    rectangleOverlay = null;
    isDrawing = false;
      
    //map = new google.maps.Map(document.getElementById('map'), {
    window.map = new google.maps.Map(document.getElementById('map'), {
      //zoom: 18, // Includes about square mile
      //zoom: 20, // To debug local neighborhood
      //zoom: 8, //Entire MidWest
      //zoom: 16, // about two sq. miles.
      //zoom: 11.3, // Entire Winneshiek County
      zoom: defaultZoom,
      //center: {lat: 43.38024, lng: -91.85018} // Compound
      //center: {lat: 43.36217, lng: -91.85208} // Huthinsons
      //center: {lat: 43.2844, lng: -91.8237} //Winneshiek County
      center: defaultCenter,
      //center: {lat: 43.30473, lng: -91.80182} //502 Mound St
      // styles: [
      //   {
      //     featureType: "poi.business",
      //     stylers: [{ visibility: "off" }]
      //   },
      //   {
      //     featureType: "poi",
      //     elementType: "labels",
      //     stylers: [{ visibility: "off" }]
      //   }      
      // ]
      // ,
      
      mapId: "d2dc915212929407d8b8bd36", // Map ID is required for advanced markers
    });

    // If bounds were saved, restore them
    if (savedBounds) {
      const boundsObj = JSON.parse(savedBounds);
      const bounds = new google.maps.LatLngBounds(boundsObj.southwest, boundsObj.northeast);
      map.fitBounds(bounds);
    }

    // Save zoom level whenever it changes
    map.addListener("zoom_changed", () => {
      localStorage.setItem("mapZoom", map.getZoom());
    });

    //console.log('window.map instanceof google.maps.Map:', window.map instanceof google.maps.Map);

    initFilters();

    // Optionally enable map movement logic *after* filters are active
    window.map.addListener('idle', () => {
      //console.log('handleViewAndUpdate called at', new Date().toISOString(), 'in addListener idle');        

      // 1. Handle view updates      
      handleViewAndUpdate(); // New combined function
      //console.log('calling removeOutOfBoundsAdvancedMarkers called at', new Date().toISOString(), 'in addListener idle');
      // 2. Marker management
      removeOutOfBoundsAdvancedMarkers(window.map, window.allMarkers);
      //console.log('done with removeOutOfBoundsAdvancedMarkers called at', new Date().toISOString(), 'in addListener idle');
      addInBoundsAdvancedMarkers(window.map, markerCache, window.allMarkers);

      // 3. Persist viewport state
      const bounds = window.map.getBounds();
      if (bounds) {
        const boundsObj = {
          northeast: bounds.getNorthEast().toJSON(),
          southwest: bounds.getSouthWest().toJSON(),
        };
        localStorage.setItem("mapBounds", JSON.stringify(boundsObj));
        localStorage.setItem("mapZoom", window.map.getZoom());
      }
    });
    
    const drawBtn = document.getElementById('drawRectangleBtn');
    const tooltip = document.getElementById('drawTooltip');

    drawBtn.addEventListener('click', () => {
      isDrawing = !isDrawing;

      if (isDrawing) {
        startLatLng = null; // ✅ clear previous starting point        
        map.setOptions({ draggable: false });
        tooltip.style.display = 'block';
        drawBtn.textContent = 'Cancel Drawing'; // optional: update button label
      } else {
        map.setOptions({ draggable: true });
        tooltip.style.display = 'none';
        drawBtn.textContent = 'Start Drawing'; // optional: revert label
        startLatLng = null;

        // If a rectangle was partially drawn, remove it
        if (rectangleOverlay) {
          rectangleOverlay.setMap(null);
          rectangleOverlay = null;
        }
      }
    });

    //map.addListener('mousedown', (e) => {
    window.map.addListener('mousedown', (e) => {
      updateDebugOverlay();
      if (!isDrawing) return;
      startLatLng = e.latLng;
    });

    //map.addListener('mousemove', (e) => {
    window.map.addListener('mousemove', (e) => {
      updateDebugOverlay();
      if (!isDrawing || !startLatLng) return;

      const bounds = new google.maps.LatLngBounds();
      bounds.extend(startLatLng);
      bounds.extend(e.latLng);

      if (rectangleOverlay) {
        rectangleOverlay.setBounds(bounds);
      } else {
        rectangleOverlay = new google.maps.Rectangle({
          bounds: bounds,
          map: map,
          strokeColor: '#FF0000',
          strokeWeight: 2,
          fillOpacity: 0.1,
        });
      }
    });

    //map.addListener('mouseup', () => {
    window.map.addListener('mouseup', () => {
      updateDebugOverlay();
      if (!isDrawing) return;

      isDrawing = false;
      map.setOptions({ draggable: true });
      tooltip.style.display = 'none';

      if (rectangleOverlay && rectangleOverlay.getBounds) {
        const bounds = rectangleOverlay.getBounds();

        const ne = bounds.getNorthEast();
        const sw = bounds.getSouthWest();

        // trigger fetch to filter_markers.php here
        const partiesToSend = Array.from(window.visibleParties);

        fetch('filter_markers.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({
            ne_lat: ne.lat(),
            ne_lng: ne.lng(),
            sw_lat: sw.lat(),
            sw_lng: sw.lng(),
            visibleParties: partiesToSend // e.g., ['Democrat', 'Republican']
          })
        })
        .then(response => response.json())
        .then(data => {
          // Display results
          alert(`Democrats: ${data.dem ?? 0}, Republicans: ${data.rep ?? 0}, No Party: ${data.np ?? 0}, Other: ${data.oth ?? 0}`);

          // ✅ Remove the rectangle from the map
          rectangleOverlay.setMap(null);
          rectangleOverlay = null; 
          
          drawBtn.textContent = 'Start Drawing';

          startLatLng = null; // ✅ clear starting point to prevent phantom rectangles          
        });
      } else {
        console.warn('No rectangle was drawn.');
      }
    });
    //console.log('Google Maps API version:', google.maps.version); // 10-05-25 Google Maps API version: 3.62.8d
    })();

    // ✅ Safe to initialize view here
    window.currentViewType = 'precinct';
    populateAreaOptions('precinct');
    drawPolygons('precinct'); // This now runs after the API is ready    
  } // End of initMap

  // Add this after your map initialization
  function initFilters() {
    const filterDiv = document.getElementById('filter-panel');
    filterDiv.addEventListener('change', (e) => {
      if (e.target.type === 'checkbox') {
        const filterKey = e.target.value;
        const isChecked = e.target.checked;
        //console.log('Checkbox changed for filter:', filterKey, 'checked:', isChecked);
        //showLoadingMessage();

        if (isChecked) {
          window.activeFilters.add(filterKey);
        } else {
          window.activeFilters.delete(filterKey);
        }

        //onFilterCheckboxChange(filterKey, isChecked); // Deprecated 10-18-25
        //handleView(); // Experimental
        //updateMarkerVisibility(); // Experimental
        //console.log('handleViewAndUpdate called at', new Date().toISOString(), 'in filter panel change event');
        handleViewAndUpdate(); // New combined function
      }
    });

    // Event listener to switch view
    document.getElementById('viewSelector').addEventListener('change', function (e) {
      const selectedView = this.value;
      populateAreaOptions(selectedView);
      drawPolygons(selectedView);

      // Update global view type
      window.currentViewType = selectedView;
      //console.log('Current view type set to:', window.currentViewType);

      // Update selected area value after repopulating options
      const areaSelector = document.getElementById('areaSelector');
      window.selectedAreaValue = areaSelector?.value || null;

      //console.log('handleViewAndUpdate called at', new Date().toISOString(), 'in viewSelector addEventListener');

      //handleView();
      //updateMarkerVisibility();
      handleViewAndUpdate(); // New combined function
    });

    // Event listener to switch view
    document.getElementById('areaSelector').addEventListener('change', function (e) {
      const selectedArea = this.value;
      //console.log('handleView called at', new Date().toISOString(), 'in areaSelector addEventListener');      
      //handleView();
      window.selectedAreaValue = e.target.value; // e.g., 'Franklin 1'
      //console.log('Selected area value:', window.selectedAreaValue);
      //updateMarkerVisibility();
      //console.log('handleViewAndUpdate called at', new Date().toISOString(), 'in areaSelector change event');
      handleViewAndUpdate(); // New combined function
    }); // areaSelector change event

  } // End of initFilters

  function updateDebugOverlay() {
    const overlay = document.getElementById('debugOverlay');
    overlay.innerHTML = `
      <strong>Debug Info</strong><br>
      isDrawing: ${isDrawing}<br>
      startLatLng: ${startLatLng ? startLatLng.toUrlValue() : 'null'}<br>
      rectangle: ${rectangleOverlay ? 'active' : 'none'}<br>
    `;
  }
  
  function logAllMarkersInCache() {
  console.log('🔍 Dumping markerCache contents:');

  for (const [key, marker] of markerCache.entries()) {
    const data = marker.data || {};
    console.log(`🧭 Key: ${key}`);
    //console.log(`   Position: (${marker.position.lat()}, ${marker.position.lng()})`);
    console.log(`   Party: ${data.party}`);
    console.log(`   Township: ${data.township}`);
    console.log(`   Voter ID: ${data.voterid}`);
    console.log(`   Full Data:`, data);
  }

  console.log(`✅ Total markers in cache: ${markerCache.size}`);
}

let lastFilterState = {};

function getCurrentFilterState() {
  const checkboxes = document.querySelectorAll('#filter-panel input[type="checkbox"]');
  const state = {};
  checkboxes.forEach(cb => {
    state[cb.name || cb.id || cb.dataset.key || cb.value] = cb.checked;
  });
  return state;
}

function hasFilterChanged(currentState) {
  const keys = Object.keys(currentState);
  if (keys.length !== Object.keys(lastFilterState).length) return true;
  return keys.some(key => currentState[key] !== lastFilterState[key]);
}

function removeOutOfBoundsAdvancedMarkers(map, markersArray) {
  const bounds = map.getBounds();
  if (!bounds) return;

  let removedCount = 0;

  for (let i = markersArray.length - 1; i >= 0; i--) {
    const marker = markersArray[i];
    if (!bounds.contains(marker.position)) {
      marker.map = null; // Removes from map
      markersArray.splice(i, 1); // Removes from array
      removedCount++;
    }
  }

  //console.log(`Removed ${removedCount} out-of-bounds marker${removedCount !== 1 ? 's' : ''}.`);
}

function addInBoundsAdvancedMarkers(map, markerCache, activeMarkersArray) {
  const bounds = map.getBounds();
  if (!bounds) return;

  let addedCount = 0;

  for (const [voterid, marker] of markerCache.entries()) {
    const metadata = marker.metadata || {};
    const areaValue = metadata[window.currentViewType]; // e.g., metadata.township

    // Check if marker matches current view filter
    let matchesView = true;
    if (window.currentViewType && window.selectedAreaValue) {
      const selected = window.selectedAreaValue.toLowerCase().trim();
      matchesView = selected === 'all' || areaValue === window.selectedAreaValue;
    }

    // Only add marker if it's in bounds, not already active, and matches view
    if (
      bounds.contains(marker.position) &&
      !activeMarkersArray.includes(marker) &&
      matchesView
    ) {
      marker.map = map;
      activeMarkersArray.push(marker);
      addedCount++;
    }
  }

  //console.log(`Added ${addedCount} marker${addedCount !== 1 ? 's' : ''} back in bounds.`);
}

function updateMarkerVisibility() {
  const markers = window.allMarkers;
  const total = markers?.length || 0;
  //console.log('Total markers to evaluate for visibility:', total);
  if (total === 0) return;

  const viewType = window.currentViewType?.trim();
  const selectedArea = window.selectedAreaValue?.trim();
  const activeFilters = window.activeFilters || new Set();

  //console.log('Updating marker visibility with active filters:', Array.from(activeFilters), 'and selectedArea:', selectedArea, 'for viewType:', viewType); // Updating marker visibility with active filters: ['Not registered']0: "Not registered"length: 1[[Prototype]]: Array(0) and selectedArea: 7 for viewType: precinct
  //console.log(selectedArea && selectedArea.toLowerCase() !== 'all'
  //  ? `Filtering for selectedArea: ${selectedArea} in updateMarkerVisibility`
  //  : 'No area filter applied in updateMarkerVisibility');

  let visibleCount = 0;

  for (const marker of markers) {
    const metadata = marker.metadata || {};
    const rawParty = String(metadata.party).trim();
    const party = rawParty.toUpperCase();

    const isStrongVoter = String(metadata.strong_voter).toLowerCase() === "t";
    const isYoungStrongVoter = String(metadata.young_strong_voter).toLowerCase() === "t";
    const isInactive = String(metadata.voterstatus).toLowerCase().trim() === "inactive";
    const isNeedsRide = String(metadata.needs_ride_to_poll).toLowerCase() === "t";
    const isTrusteeClerk = String(metadata.township_trustee_or_clerk).toLowerCase() === "t";
    const isNotRegistered = rawParty.toLowerCase() === 'not registered';

    // Match if no area selected, or 'all', or if any metadata value matches selectedArea
    // const matchesArea =
    //   !selectedArea ||
    //   selectedArea.toLowerCase() === 'all' ||
    //   Object.values(metadata).some(val => String(val).trim() === selectedArea);
    //console.log('Raw currentViewType:', window.currentViewType);
    //const viewType = window.currentViewType?.trim();

    let matchesArea = false;

    if (!selectedArea || selectedArea.toLowerCase() === 'all') {
      matchesArea = true;
    } else {
      switch (viewType) {
        case 'precinct':
          matchesArea = String(metadata.precinct).trim().toLowerCase() === selectedArea.toLowerCase();
          break;

        case 'township':
          matchesArea = String(metadata.township).trim().toLowerCase() === selectedArea.toLowerCase();
          break;

        case 'ward':
          matchesArea = String(metadata.ward).trim().toLowerCase() === selectedArea.toLowerCase();
          break;

        case 'supervisor':
          matchesArea = String(metadata.supervisor).trim().toLowerCase() === selectedArea.toLowerCase();
          break;

        default:
          matchesArea = false;
      }
    }

    const matchesFilter =
      (
        (!activeFilters.has('Strong') || isStrongVoter) &&
        (!activeFilters.has('YoungStrong') || isYoungStrongVoter) &&
        (!activeFilters.has('Inactive') || isInactive) &&
        (!activeFilters.has('NeedsRide') || isNeedsRide) &&
        (!activeFilters.has('TrusteeClerk') || isTrusteeClerk) &&
        (
          activeFilters.has(party) ||
          (isNotRegistered && activeFilters.has('Not registered'))
        )
      );

    const shouldBeVisible = matchesArea && matchesFilter;
    //console.log(`Marker ID: ${marker.data?.voterid || 'unknown'}`, 'Matches Area:', matchesArea, 'Matches Filter:', matchesFilter, 'Should be visible:', shouldBeVisible, 'Metadata:', metadata);

    if (marker.element) {
      marker.element.style.display = shouldBeVisible ? 'block' : 'none';
    }

    //if (shouldBeVisible) visibleCount++;
    if (shouldBeVisible) {
      visibleCount++;
      //console.log(`Visible marker ID: ${marker.data?.voterid || 'unknown'}`, 'Metadata:', metadata);
    }
  }

  //console.log(`Visible markers: ${visibleCount} of ${total}`);
}

function showLoadingMessage() {
  document.getElementById('loading-message').style.display = 'block';
}

function hideLoadingMessage() {
  document.getElementById('loading-message').style.display = 'none';
}

async function handleViewAndUpdate() {
  await handleView(); // waits for loadMarkersInBounds to finish
  //console.log('handleView completed, now updating marker visibility at', new Date().toISOString(), 'in handleViewAndUpdate');
  updateMarkerVisibility(); // runs only after handleView completes
}

</script>
<script async src="https://maps.googleapis.com/maps/api/js?key=AIzaSyC_KbKXsaVsdkaOvEHWYfP0Gn1lBGB-eRU&loading=async&callback=initMap"></script>
</body>
</html>
