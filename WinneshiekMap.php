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
      <label class="label not-registered"><input type="checkbox" value="NOT REGISTERED" id="filter-nr"> Not registered</label>
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

    <div class="Neighborhood Member Level">
      <label><input type="checkbox" value="NeighborhoodMember" id="filter-neighborhood-member"> Neighborhood Member</label>   
    </div>

    <button id="drawRectangleBtn">Draw Rectangle</button>
    <!-- <button id="drawRectangleBtn" style="display: none;">Draw Rectangle</button> -->

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
  // Global cache object
  const markerApartmentCache = {}; // Object


  let AdvancedMarkerElement; // 10-05-25 Steamlining creation of markers.
  const townshipOptions = ['none', 'all', 'Bloomfield', 'Bluffton', 'Burr Oak', 'Calmar', 'Canoe', 'Decorah', 'Frankville', 'Fremont', 'Glenwood', 'Hesper', 'Highland', 'Jackson', 'Lincoln', 'Madison', 'Military', 'Orleans', 'Pleasant', 'Springfield', 'Sumner', 'Washington'];
  const precinctOptions = ['none', 'all', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'];
  const wardOptions = ['none','all','DE1','DE2','DE3','DE4','DE5'];
  const supervisorOptions = ['none','1','2','3','4','5'];

  let map;
  let visibleParties = new Set();
  let selectedParties = new Set();
  let isDrawing = false;
  let startLatLng = null;
  let rectangleOverlay = null;

  let drawnPolygons = [];
  let drawnPolygonMarkers = [];
  let currentViewType = null; // track the last drawn type

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
    if (viewType === currentViewType) return;

    drawnPolygons.forEach((polygon) => polygon.setMap(null));
    drawnPolygons = [];

    drawnPolygonMarkers.forEach((marker) => (marker.map = null));
    drawnPolygonMarkers = [];

    const viewStyles = {
      township: { strokeColor: "#0000FF", fillColor: "#0000FF" },
      precinct: { strokeColor: "#0000FF", fillColor: "#0000FF" },
      supervisor: { strokeColor: "#0000FF", fillColor: "#0000FF" },
    };

    if (!["township", "precinct", "supervisor"].includes(viewType)) return;

    fetch(`get_boundaries.php?type=${viewType}`)
      .then((response) => response.json())
      .then((geojson) => {
        geojson.features.forEach((feature) => {
          const geom = feature.geometry;
          if (!geom || !geom.coordinates) return;

          let rings = [];
          if (geom.type === "Polygon") {
            rings = geom.coordinates;
          } else if (geom.type === "MultiPolygon") {
            rings = geom.coordinates.flat();
          }

          rings.forEach((ring) => {
            const path = ring.map((coord) => ({ lat: coord[1], lng: coord[0] }));
            //console.log('Drawing polygon with path:', path);
            const polygon = new google.maps.Polygon({
              paths: path,
              strokeColor: viewStyles[viewType].strokeColor,
              strokeOpacity: 0.8,
              strokeWeight: 2,
              fillColor: viewStyles[viewType].fillColor,
              fillOpacity: 0.35,
              clickable: false,   // ✅ prevents swallowing mouse events
            });

            const hasOverride =
              feature.properties?.override_lat != null &&
              feature.properties?.override_lng != null;

            const position = hasOverride
              ? { lat: feature.properties.override_lat, lng: feature.properties.override_lng }
              : getPolygonCentroid(polygon.getPath());

            const labelDiv = document.createElement("div");
            labelDiv.className = "township-label";
            labelDiv.textContent = feature.properties?.name ?? "Unnamed entity";

            const marker = new google.maps.marker.AdvancedMarkerElement({
              position,
              map, // ✅ now uses the global let map
              content: labelDiv,
              collisionBehavior: google.maps.CollisionBehavior.REQUIRED,
            });
            drawnPolygonMarkers.push(marker);
            //console.log('Marker created with position:', position);

            polygon.setMap(map); // ✅ now uses the global let map
            drawnPolygons.push(polygon);
          });
        });

        currentViewType = viewType;
        //console.log(`Successfully drew ${viewType} polygons`);
      })
      .catch((error) => console.error(`Error loading ${viewType} boundaries:`, error));
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

    const bounds = map.getBounds();
    const ne = bounds.getNorthEast();
    const sw = bounds.getSouthWest();
    const allFilters = Array.from(window.activeFilters);
    const parties = allFilters.filter(f =>
      ['DEM', 'REP', 'NP', 'OTH', 'NOT REGISTERED'].includes(f)
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

    const AllParties = 'parties[]=DEM&parties[]=REP&parties[]=NP&parties[]=OTH&parties[]=NOT%20REGISTERED';

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
          const positionKey = `${markerData.latitude},${markerData.longitude}`;
          if (!positionGroups[positionKey]) {
            positionGroups[positionKey] = [];
          }
          positionGroups[positionKey].push(markerData);
        }

        for (const group of Object.values(positionGroups)) {
          group.sort((a, b) => {
            const nameA = a.last_name || '';
            const nameB = b.last_name || '';
            return nameA.localeCompare(nameB);
          });

          let voterIdArray = []; // Local array for clustered markers
          let voterAptArray = []; // Local array for apartment info
          let voterPartyArray = []; // Local array for party info
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
          const groupHasNeighborhoodMember = group.some(m => Number(m.neighborhood_member_level) > 0);
          const groupHasNeighborhoodMemberCount = group.filter(m => Number(m.neighborhood_member_level) > 0).length;          

          let shouldInclude;

          //console.log('m.party for group:', group.map(m => m.party)); // Returns valid party values
          //console.log('Coordinates for group:', group.map(m => `${m.latitude},${m.longitude}`));

        // ✅ Apply full filter stack for registered voters
        for (const m of group) {
            voterIdArray.push(m.voterid);
            voterAptArray.push(m.apartment);
            voterPartyArray.push(m.party);
          } // End for m of group

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
            group.forEach(markerData => {
              //console.log('Processing markerData for apartment cache:', markerData);
              const voterId = markerData.voterid;

              //if (!markerApartmentCache.has(voterId)) {
                const aptMarkerData = {
                  position: {
                    lat: parseFloat(markerData.latitude),
                    lng: parseFloat(markerData.longitude)
                  },
                  name: `${markerData.first_name} ${markerData.last_name}`,
                  //address: markerData.address,
                  address: normalizeAddress(markerData.address),
                  voterId: voterId,
                  party: markerData.party,
                  precinct: markerData.precinct,
                  township: markerData.township,
                  ward: markerData.ward,
                  supervisor: markerData.supervisor,
                  strong_voter: markerData.strong_voter,
                  young_strong_voter: markerData.young_strong_voter,
                  voterstatus: markerData.voterstatus,
                  needs_ride_to_poll: markerData.needs_ride_to_poll,
                  township_trustee_or_clerk: markerData.township_trustee_or_clerk,
                  neighborhood_member_level: markerData.neighborhood_member_level
                };
              //   //markerApartmentCache.set(voterId, aptMarkerData);
              //   //console.log('aptMarkerData:', aptMarkerData);
              //   // Ensure we have an array for this address
              //   if (!markerApartmentCache[markerData.address]) {
              //     markerApartmentCache[markerData.address] = [];
              //   }

              //   // Prevent duplicates by voterId
              //   const records = markerApartmentCache[markerData.address];
              //   if (!records.some(r => r.voterId === voterId)) {
              //     records.push(aptMarkerData);
              //     //console.log('Added aptMarkerData:', aptMarkerData);
              //     // console.log('markerApartmentCache just updated:', markerApartmentCache);
              //     //console.log("Cache updated for address:", markerData.address);
              //     //console.log("Current records:", markerApartmentCache[markerData.address]);                  
              //   }                
              // //}
                //const addressKey = markerData.address.trim();
                const addressKey = normalizeAddress(markerData.address);
                if (!markerApartmentCache[addressKey]) {
                  markerApartmentCache[addressKey] = [];
                }

                const records = markerApartmentCache[addressKey];
                if (!records.some(r => r.voterId === voterId)) {
                  records.push(aptMarkerData);
                  //console.log("Cache updated for address:", addressKey, records);
                }              
              }); // End for group.forEach
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
              label.className = 'marker-label'; // give it a class for easy lookup              
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
              // Now filter down to only the parties selected in activeFilters
              // const partySummary = parties
              //   .filter(p => partyCounts[p]) // only include if there’s a count
              //   .map(p => `${p}: ${partyCounts[p]}`)
              //   .join(', ');              
              //console.log('PartySummary for address where partyfilters:', partySummary,markerData.address,parties);

              // Assuming group is your array of marker objects
              const nmlCount = group.reduce((acc, m) => {
                const level = m.neighborhood_member_level;
                if (level >= 1) {   // ✅ include all levels 1 and above
                  acc++;
                }
                return acc;
              }, 0);

              //const nmlSummary = `NML: ${nmlCount}`;
              // const nmlSummary = nmlCount >= 1 ? `NML: ${nmlCount}` : '';
              const nmlSummary = '';

              //console.log(nmlSummary);
              // Example output: "NML: 42"

              const tooltipContent = `${address}`;

              // Party summary node
              const partyNode = document.createElement('div');
              partyNode.className = 'party-summary';
              //partyNode.textContent = '${partySummary}';
              partyNode.textContent = partySummary;
              container.appendChild(partyNode);              

              // Apartment flag node
              const apartmentNode = document.createElement('div');
              apartmentNode.className = 'marker-apartment';
              apartmentNode.dataset.isApartment = "true"; // metadata flag
              container.appendChild(apartmentNode);

              container.title = tooltipContent;
              
              const marker = new AdvancedMarkerElement({
                position: {
                  lat: parseFloat(markerData.latitude),
                  lng: parseFloat(markerData.longitude)
                },
                map: map,                
                content: container
              }); // End of marker

              // Attach metadata so you can query later
              //marker.address = markerData.address;
              marker.address = normalizeAddress(markerData.address);
              marker.party = markerData.party;
              marker.voterId = markerData.voterId;

              // // Attach metadata manually
              // const party = markerData.party;
              // const precinct = markerData.precinct;
              // const township = markerData.township;
              // const ward = markerData.ward;
              // const supervisor = markerData.supervisor;

              marker.metadata = {
                //address: markerData.address,
                //party,
                precinct: markerData.precinct,
                township: markerData.township,
                ward: markerData.ward,
                supervisor: markerData.supervisor,
                strong_voter: markerData.strong_voter,
                young_strong_voter: markerData.young_strong_voter,
                voterstatus: markerData.voterstatus,
                needs_ride_to_poll: markerData.needs_ride_to_poll,
                township_trustee_or_clerk: markerData.township_trustee_or_clerk,
                neighborhood_member_level: markerData.neighborhood_member_level
              };                      

              attachClusteredMarkerClick(marker, voterIdArray, voterPartyArray, address, voterAptArray, map, infoWindow, activeFilters);

              marker.data = markerData; // Stuff things party, etc. 
              markerCache.set(voterIdArray[1], marker); // cache for reuse
              window.allMarkers.push(marker); // For "clustered" Markers
            } else { // End of if (!markerCache.has(voterIdArray[1]))
              // console.log('Reusing cached marker for voter ID:', voterIdArray[1], 'at', new Date().toISOString());
              // const cachedMarker = markerCache.get(voterIdArray[1]);
              // if (cachedMarker) {
              //   //cachedMarker.setTitle("Updated Title");
              //   cachedMarker.title = "Updated Title";
              //   //cachedMarker.setPosition({ lat: newLat, lng: newLng }); // if needed
              // }             
            }
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

                    const tooltipContent = `${address}\r\n${namesList}`;

                    markerElement.title = tooltipContent;

                    const marker = new AdvancedMarkerElement({
                      position: {
                        lat: parseFloat(markerData.latitude),
                        lng: parseFloat(markerData.longitude)
                      },
                      map: map,
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
                      township_trustee_or_clerk: markerData.township_trustee_or_clerk,
                      neighborhood_member_level: markerData.neighborhood_member_level
                    };                      

                    attachClusteredMarkerClick(marker, voterIdArray, voterPartyArray, address, voterAptArray, map, infoWindow, activeFilters);

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

  function attachClusteredMarkerClick(marker,voterIdArray,voterPartyArray,address,voterAptArray,map,infoWindow,activeFilters) { // <-- pass in your ActiveFilters set
    marker.addListener('click', () => {
      if (!ENABLE_MARKER_CLICK) return;

      const fetchPromises = voterIdArray.map((id, index) => {
        const apt_info = voterAptArray[index];

        if (voterPartyArray[index].toLowerCase() === 'not registered') {
          return Promise.resolve({
            first_name: 'Not Registered',
            last_name: '',
            apt_info: apt_info,
            party: 'Not Registered'
          });
        } else {
          return fetch(`/get_voter_details.php?regn_num=${id}`)
            .then(res => res.json())
            .then(voter => ({ ...voter, apt_info })); // attach apt_info to fetched voter
        }
      });

      Promise.all(fetchPromises).then(voterDetailsArray => {
        // Filter by ActiveFilters before rendering
        const filteredVoters = voterDetailsArray.filter(voter => {
          if (voter.first_name === 'Not Registered') {
            // Always include "Not Registered" entries
            return true;
          }
          return activeFilters.has(voter.party);
        });

        const voterBlocks = filteredVoters.map(voter => {
          if (voter.first_name === 'Not Registered') {
            return `
              <div style="margin-bottom: 8px;">
                <strong>Not Registered ${voter.apt_info}</strong>
              </div>
            `;
          }

          const age = voter.birthdate ? calculateAge(voter.birthdate) : 'Unknown';
          const history = voter.general_election_history || 'None';
          const strong = voter.strong_voter === 't' ? 'Yes' : 'No';
          const youngStrong = voter.young_strong_voter === 't' ? 'Yes' : 'No';
          const needsRide = voter.needs_ride_to_poll === 't' ? 'Yes' : 'No';
          const trusteeClerk = voter.township_trustee_or_clerk === 't' ? 'Yes' : 'No';
          const neighborhoodMember = Number(voter.neighborhood_member_level) > 0 ? 'Yes' : 'No';

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
              Township Trustee or Clerk: ${trusteeClerk}<br>
              Neighborhood Member: ${neighborhoodMember}
            </div>
          `;
        }).join('<hr>');

        const htmlContent = `
          <div style="max-width: 300px; font-size: 12px;">
            <strong>${address}</strong><br><br>
            ${voterBlocks || '<em>No voters match selected filters.</em>'}
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
      case 'NOT REGISTERED': return '#FFFF00';
      default: return '#000000';
    }
  }

  async function initMap() {
    // Default center if no saved state
    const defaultCenter = { lat: 43.2844, lng: -91.8237 };
    const defaultZoom = 11.3;

    // Restore saved state
    const savedZoom = localStorage.getItem("mapZoom");
    const savedBounds = localStorage.getItem("mapBounds");

    // Import libraries
    const { Map } = await google.maps.importLibrary("maps");
    const { AdvancedMarkerElement: AME } = await google.maps.importLibrary("marker");
    const { DrawingManager } = await google.maps.importLibrary("drawing");

    AdvancedMarkerElement = AME; // expose globally if needed

    // Create the map instance
    map = new Map(document.getElementById("map"), {
      zoom: defaultZoom,
      center: defaultCenter,
      mapId: "d2dc915212929407d8b8bd36",
    });

    // Restore bounds if saved
    if (savedBounds) {
      const boundsObj = JSON.parse(savedBounds);
      const bounds = new google.maps.LatLngBounds(boundsObj.southwest, boundsObj.northeast);
      map.fitBounds(bounds);
    }

    // Persist zoom changes
    map.addListener("zoom_changed", () => {
      localStorage.setItem("mapZoom", map.getZoom());
    });

    // Filters and idle logic
    initFilters();
    map.addListener("idle", () => {
      handleViewAndUpdate();
      removeOutOfBoundsAdvancedMarkers(map, window.allMarkers);
      addInBoundsAdvancedMarkers(map, markerCache, window.allMarkers);

      const bounds = map.getBounds();
      if (bounds) {
        const boundsObj = {
          northeast: bounds.getNorthEast().toJSON(),
          southwest: bounds.getSouthWest().toJSON(),
        };
        localStorage.setItem("mapBounds", JSON.stringify(boundsObj));
        localStorage.setItem("mapZoom", map.getZoom());
      }
    });

    // Drawing button logic
    const drawBtn = document.getElementById("drawRectangleBtn");
    const tooltip = document.getElementById("drawTooltip");

    drawBtn.addEventListener("click", () => {
      isDrawing = !isDrawing;
      if (isDrawing) {
        startLatLng = null;
        map.setOptions({ draggable: false });
        tooltip.style.display = "block";
        drawBtn.textContent = "Cancel Drawing";
      } else {
        map.setOptions({ draggable: true });
        tooltip.style.display = "none";
        drawBtn.textContent = "Start Drawing";
        startLatLng = null;
        if (rectangleOverlay) {
          rectangleOverlay.setMap(null);
          rectangleOverlay = null;
        }
      }
    });

    // Mouse events
    map.addListener("mousedown", (e) => {
      //console.log("Map mousedown at", e.latLng.toUrlValue());      
      if (!isDrawing) return;
      startLatLng = e.latLng;
    });

    map.addListener("mousemove", (e) => {
      //console.log("Map mousemove at", e.latLng.toUrlValue(), isDrawing);
      if (!isDrawing || !startLatLng) return;
      const bounds = new google.maps.LatLngBounds();
      bounds.extend(startLatLng);
      bounds.extend(e.latLng);

      if (rectangleOverlay) {
        rectangleOverlay.setBounds(bounds);
      } else {
        rectangleOverlay = new google.maps.Rectangle({
          bounds,
          map,
          clickable: false,
          strokeColor: "#FF0000",
          strokeWeight: 2,
          fillOpacity: 0.1,
        });
      }
    });

    map.addListener("mouseup", () => {
      //console.log("Map mouseup");
      if (!isDrawing) return;
      isDrawing = false;
      map.setOptions({ draggable: true });
      tooltip.style.display = "none";

      if (rectangleOverlay && rectangleOverlay.getBounds) {
        const bounds = rectangleOverlay.getBounds();
        const counts = countPartiesInsideRectangle(bounds);

        alert(
          `Democrats: ${counts.DEM}
        Republicans: ${counts.REP}
        No Party: ${counts.NP}
        Other: ${counts.OTH}
        Not Registered: ${counts["NOT REGISTERED"]}`
        );

        rectangleOverlay.setMap(null);
        rectangleOverlay = null;
        drawBtn.textContent = "Start Drawing";
        startLatLng = null;

      }
    });

    // Initialize polygons
    window.currentViewType = "precinct";
    populateAreaOptions("precinct");
    drawPolygons("precinct");
  } //end of initMap

  function updateVisiblePartiesFromRectangle(bounds) {
    visibleParties.clear();

    if (!Array.isArray(window.allMarkers)) {
      console.warn("allMarkers is not an array");
      return;
    }

    for (const entry of window.allMarkers) {
      if (!entry) continue;

      const { position, metadata } = entry;
      const party = metadata?.party;

      // Skip markers missing required fields
      if (!position || !party) continue;

      // ✅ Skip markers whose party is NOT selected in the UI
      if (!selectedParties.has(party)) continue;

      // ✅ Now check if the marker is inside the rectangle
      if (bounds.contains(position)) {
        visibleParties.add(party);
      }
    }

    console.log("visibleParties inside rectangle:", [...visibleParties]);
  }

  function countPartiesInsideRectangle(bounds) {
    const counts = {
      DEM: 0,
      REP: 0,
      NP: 0,
      OTH: 0,
      "NOT REGISTERED": 0
    };

    for (const entry of window.allMarkers) {
      if (!entry) continue;

      // Skip markers not visible on the map OR hidden via CSS
      if (entry.map === null) continue;
      if (entry.element?.style?.display === "none") continue;      

      const { position, metadata } = entry;
      const party = metadata?.party;

      if (!position || !party) continue;

      // Skip if user has filtered this party out
      if (!selectedParties.has(party)) continue;

      // Check if marker is inside the rectangle
      if (bounds.contains(position)) {
        if (counts.hasOwnProperty(party)) {
          counts[party]++;
        } else {
          // Safety: unexpected party codes
          counts[party] = 1;
        }
      }
    }

    return counts;
  }

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

      handleViewAndUpdate(); // New combined function
    });

    // Event listener to switch view
    document.getElementById('areaSelector').addEventListener('change', function (e) {
      const selectedArea = this.value;
      window.selectedAreaValue = e.target.value; // e.g., 'Franklin 1'
      handleViewAndUpdate(); // New combined function
    }); // areaSelector change event

    document.querySelectorAll('.party-filters input[type="checkbox"]').forEach(cb => {
      cb.addEventListener('change', () => {
        if (cb.checked) {
          selectedParties.add(cb.value);   // e.g., "DEM"
        } else {
          selectedParties.delete(cb.value);
        }

        //console.log("Selected parties:", [...selectedParties]);
      });
    });

  } // End of initFilters

  function updateDebugOverlay() {
    // const overlay = document.getElementById('debugOverlay');
    // overlay.innerHTML = `
    //   <strong>Debug Info</strong><br>
    //   isDrawing: ${isDrawing}<br>
    //   startLatLng: ${startLatLng ? startLatLng.toUrlValue() : 'null'}<br>
    //   rectangle: ${rectangleOverlay ? 'active' : 'none'}<br>
    // `;
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
    const keys = Object.keys(markerApartmentCache);
    const markers = window.allMarkers;
    const total = markers?.length || 0;
    //console.log('Total markers to evaluate for visibility:', total);
    if (total === 0) return;

    const viewType = window.currentViewType?.trim();
    const selectedArea = window.selectedAreaValue?.trim();
    const activeFilters = window.activeFilters || new Set();

    for (const marker of markers) {
      const metadata = marker.metadata || {};
      const rawParty = String(metadata.party).trim();
      const party = rawParty.toUpperCase();

      const isStrongVoter = String(metadata.strong_voter).toLowerCase() === "t";
      const isYoungStrongVoter = String(metadata.young_strong_voter).toLowerCase() === "t";
      const isInactive = String(metadata.voterstatus).toLowerCase().trim() === "inactive";
      const isNeedsRide = String(metadata.needs_ride_to_poll).toLowerCase() === "t";
      const isTrusteeClerk = String(metadata.township_trustee_or_clerk).toLowerCase() === "t";
      const isNeighborhoodMember = Number(metadata.neighborhood_member_level) > 0;
      const isNotRegistered = rawParty.toLowerCase() === 'not registered';

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
      const title = marker.content?.title ?? '';

      // Check if marker is flagged as apartment
      const isApartmentMarker = marker.content.querySelector('.marker-apartment')?.dataset.isApartment === "true";

      const nmlRequired = activeFilters.has('NeighborhoodMember');
      const trusteeClerkRequired = activeFilters.has('TrusteeClerk');
      const strongVoterRequired = activeFilters.has('StrongVoter');
      const youngStrongVoterRequired = activeFilters.has('YoungStrongVoter');
      const inactiveRequired = activeFilters.has('Inactive');
      const needsRideRequired = activeFilters.has('NeedsRide');

      let partyMatch;
      if (isApartmentMarker) {
        // ✅ Apartment markers: look up all residents at this address
        const residents = markerApartmentCache[marker.address] || []; 

        partyMatch = residents.some(resident => activeFilters.has(resident.party));
      } else {
        partyMatch = activeFilters.has(party);
      }

      let neighborhoodMemberMatch = false;
      if (isApartmentMarker && nmlRequired) {
        //const lookupKey = marker.address;
        const lookupKey = normalizeAddress(marker.address);
        const apartmentRecords = markerApartmentCache[lookupKey] || [];

        neighborhoodMemberMatch = apartmentRecords.some(r =>
          activeFilters.has(r.party) &&
          Number(r.neighborhood_member_level) >= 1
        );
      } else if (!isApartmentMarker && nmlRequired) {
        neighborhoodMemberMatch = isNeighborhoodMember;
      }

      let trusteeClerkMatch = false;

      if (isApartmentMarker && trusteeClerkRequired) {
        //const lookupKey = marker.address;
        const lookupKey = normalizeAddress(marker.address);
        const apartmentRecords = markerApartmentCache[lookupKey] || [];

        // Check if any resident at this address matches the active party filter
        // AND has township_trustee_or_clerk set to true
        trusteeClerkMatch = apartmentRecords.some(r =>
          activeFilters.has(r.party) &&
          Boolean(r.township_trustee_or_clerk) // assumes true/false or truthy/falsy
        );
      } else if (!isApartmentMarker && trusteeClerkRequired) {
        trusteeClerkMatch = isTrusteeClerk; // single marker already has this flag
      }

      let strongVoterMatch = false;

      if (isApartmentMarker && strongVoterRequired) {
        //const lookupKey = marker.address;
        const lookupKey = normalizeAddress(marker.address);
        const apartmentRecords = markerApartmentCache[lookupKey] || [];

        // Check if any resident at this address matches the active party filter
        // AND has township_trustee_or_clerk set to true
        strongVoterMatch = apartmentRecords.some(r =>
          activeFilters.has(r.party) &&
          Boolean(r.strong_voter) // assumes true/false or truthy/falsy
        );
      } else if (!isApartmentMarker && strongVoterRequired) {
        strongVoterMatch = isStrongVoter; // single marker already has this flag
      }

      let youngStrongVoterMatch = false;

      if (isApartmentMarker && youngStrongVoterRequired) {
        //const lookupKey = marker.address;
        const lookupKey = normalizeAddress(marker.address);
        const apartmentRecords = markerApartmentCache[lookupKey] || [];

        // Check if any resident at this address matches the active party filter
        // AND has township_trustee_or_clerk set to true
        youngStrongVoterMatch = apartmentRecords.some(r =>
          activeFilters.has(r.party) &&
          Boolean(r.young_strong_voter) // assumes true/false or truthy/falsy
        );
      } else if (!isApartmentMarker && youngStrongVoterRequired) {
        youngStrongVoterMatch = marker.isYoungStrongVoter; // single marker already has this flag
      }

      let inactiveMatch = false;

      if (isApartmentMarker && inactiveRequired) {
        //const lookupKey = marker.address;
        const lookupKey = normalizeAddress(marker.address);
        const apartmentRecords = markerApartmentCache[lookupKey] || [];

        // Check if any resident at this address matches the active party filter
        // AND has voterstatus set to "inactive"
        inactiveMatch = apartmentRecords.some(r =>
          activeFilters.has(r.party) &&
          String(r.voterstatus).trim().toLowerCase() === "inactive"
        );
      } else if (!isApartmentMarker && inactiveRequired) {
        // For a single marker, assume you’ve already normalized voterstatus
        inactiveMatch = String(marker.voterstatus).trim().toLowerCase() === "inactive";
      }

      let needsRideMatch = false;

      if (isApartmentMarker && needsRideRequired) {
        //const lookupKey = marker.address;
        const lookupKey = normalizeAddress(marker.address);
        const apartmentRecords = markerApartmentCache[lookupKey] || [];

        // Check if any resident at this address matches the active party filter
        // AND has needs_ride_to_poll set to true
        needsRideMatch = apartmentRecords.some(r =>
          activeFilters.has(r.party) &&
          Boolean(r.needs_ride_to_poll) // assumes true/false or truthy/falsy
        );
      } else if (!isApartmentMarker && needsRideRequired) {
        // For a single marker, check the flag directly
        needsRideMatch = Boolean(marker.needs_ride_to_poll);
      }


      const matchesFilter =
        (!activeFilters.has('Strong') || isStrongVoter) &&
        (!activeFilters.has('YoungStrong') || isYoungStrongVoter) &&
        (!activeFilters.has('Inactive') || isInactive) &&
        (!activeFilters.has('NeedsRide') || isNeedsRide) &&
        (!activeFilters.has('TrusteeClerk') || isTrusteeClerk) &&
        (!nmlRequired || neighborhoodMemberMatch) &&
        (
          isApartmentMarker
            ? (hasPartyFilter && partyMatch)
            : partyMatch
        );

      //console.log('Marker:', title, 'TrusteeClerkMatch:', trusteeClerkMatch,'matchesFilter:', matchesFilter); 

      let shouldBeVisible = false;
      if (isApartmentMarker) {
        //const lookupKey = marker.address;
        const lookupKey = normalizeAddress(marker.address);
        const apartmentRecords = markerApartmentCache[lookupKey] || [];
        //console.log('lookupKey for apartment marker:', lookupKey, '.apartmentRecords count:', apartmentRecords.length);
        //console.log(`Evaluating Apartment Marker: ${title} | Total Residents: ${apartmentRecords.length}`);
        let matchingCount = 0;
        apartmentRecords.forEach(r => {
          const neighborhoodMemberMatchforCount =
            (!activeFilters.has('NeighborhoodMember')) ||
            Number(r.neighborhood_member_level) >= 1;

          const partyMatch = partyMatchForCounting(r, activeFilters);
          //console.log('partyMatchForCounting for voterid:', r.voterid, 'with party:', r.party, 'result:', partyMatch);
          //if (neighborhoodMemberMatchforCount && partyMatch) {
          if (partyMatch) {
            matchingCount++;
          }
        });

        shouldBeVisible =
          matchesArea &&
          matchesFilter &&
          (matchingCount > 0);

        //console.log(`Apartment Marker: ${title} | Matching Count: ${matchingCount} | Visible: ${shouldBeVisible}`);

        // Update the marker label with the count
        const labelEl = marker.content.querySelector('.marker-label');
        if (labelEl) {
          labelEl.textContent = `${matchingCount} voters`;
        }
      } else {
        shouldBeVisible = matchesArea && matchesFilter;
      }


      if (marker.element) {
        marker.element.style.display = shouldBeVisible ? 'block' : 'none';
      }
      //console.log(`Marker: ${title} | Visible: ${shouldBeVisible}`);
    }
  } // End of updateMarkerVisibility

  // Normalize party string once per record
  function normalizeParty(party) {
    return String(party || '').trim().toUpperCase();
  }

  // Party filter check
  function hasPartyFilter(activeFilters) {
    return ['DEM', 'REP', 'NP', 'OTH', 'Not registered']
      .some(p => activeFilters.has(p));
  }

  function normalizeAddress(address) {
    return address
      .toLowerCase()              // case-insensitive
      .replace(/\s+/g, ' ')       // collapse multiple spaces
      .replace(/-/g, ' ')         // treat hyphens as spaces
      .trim();                    // remove leading/trailing spaces
  }  

  // Party match for counting (only DEM/REP/OTH/NP)
  function partyMatchForCounting(r, activeFilters) {
    //console.log('In partyMatchForCounting for voterid:', r.voterid, 'with party:', r.party, 'activeFilters:', Array.from(activeFilters));
    const party = normalizeParty(r.party);

    return (
      !hasPartyFilter(activeFilters) ||
      (['DEM','REP','OTH','NP'].includes(party) && activeFilters.has(party))
    );
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
