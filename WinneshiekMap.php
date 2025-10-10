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
<!-- Load Google Maps JavaScript API -->
 <!--
<script async src="https://maps.googleapis.com/maps/api/js?key=AIzaSyC_KbKXsaVsdkaOvEHWYfP0Gn1lBGB-eRU&loading=async&callback=initMap&libraries=marker,drawing" loading="async">
-->
<!-- </script> -->
<script async src="https://maps.googleapis.com/maps/api/js?key=AIzaSyC_KbKXsaVsdkaOvEHWYfP0Gn1lBGB-eRU&loading=async&callback=initMap"></script>

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
      <label><input type="checkbox" id="filter-needs-ride"> Needs Ride to Poll</label>
    </div>  
  <!--<button id="drawRectangleBtn">Draw Rectangle</button>-->
  <button id="drawRectangleBtn" style="display: none;">Draw Rectangle</button>

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

  <script>
    // Declare global filter sets near the top of your script
    window.visibleParties = new Set();
    window.visibleTownships = new Set(); // if you're also using township filters
    //window.shouldLoadMarkers = false;

    // Positions hamburger-btn
    document.getElementById('hamburger-btn').addEventListener('click', function() {
      const filterPanel = document.getElementById('filter-panel');
      filterPanel.style.display = (filterPanel.style.display === 'none' || filterPanel.style.display === '') ? 'block' : 'none';
    });
  
  
  let map;
  let markers = [];
  
  const markerCache = new Map(); // voterId → marker

  let AdvancedMarkerElement; // 10-05-25 Steamlining creation of markers.
  const townshipOptions = ['none', 'all', 'Bloomfield', 'Bluffton', 'Burr Oak', 'Calmar', 'Canoe', 'Decorah', 'Frankville', 'Fremont', 'Glenwood', 'Hesper', 'Highland', 'Jackson', 'Lincoln', 'Madison', 'Military', 'Orleans', 'Pleasant', 'Springfield', 'Sumner', 'Washington'];
  const precinctOptions = ['none', 'all', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'];
  const wardOptions = ['none','DE1','DE2','DE3','DE4','DE5'];
  const supervisorOptions = ['none','1','2','3','4','5'];

  let isDrawing = false;
  let startLatLng = null;
  let rectangleOverlay = null;

  // For smart toggling of visibility for markers.
  const selectedParties = {
    DEM: false,
    REP: false,
    NP: false,
    OTH: false,
    'Not Registered': false
    // add others as needed
  };

  const loadedParties = {
    DEM: false,
    REP: false,
    NP: false,
    OTH: false,
    'Not Registered': false
    // add others as needed
  };

  const ENABLE_CLEARMARKER = false;

  function clearMarkers() {
    if (!ENABLE_CLEARMARKER) return;
    console.log('clearMarkers called at', new Date().toISOString(), 'with', markers.length, 'markers');

    markers.forEach(marker => {
        marker.map = null; // Detach from map
    });
    markers.length = 0; // Clear the array without reassigning
    console.log('clearMarkers called at', new Date().toISOString(), ' end');      
  }

  // Added 07-27-25
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

  // Added 07-27-25
  // Initial population
  populateAreaOptions('precinct');

  function handleView() {
  const currentView = document.getElementById('viewSelector').value;
  const selectedArea = document.getElementById('areaSelector').value;

    switch (currentView) {
      case 'township':
        const townships = selectedArea;
        if (townships === 'none' || !townships) {
          return;
        }

        console.log('loadMarkersInBouns called at', new Date().toISOString(), 'in township');
        loadMarkersInBounds(currentView, selectedArea);
        break;

      case 'precinct':
        console.log('loadMarkersInBouns called at', new Date().toISOString(), 'in precinct');        
        loadMarkersInBounds(currentView, selectedArea);
        break;

      case 'ward':
        console.log('loadMarkersInBouns called at', new Date().toISOString(), 'in ward');
        loadMarkersInBounds(currentView, selectedArea);
        break;

      case 'supervisor':
        console.log('loadMarkersInBouns called at', new Date().toISOString(), 'in supervisor');
        loadMarkersInBounds(currentView, selectedArea);
        break;    

      default:
        console.warn("Unknown view:", currentView);
        return;
    }
  }

  // In your main HTML file
  // 08-23-25 clusteredMarkers added
  async function loadMarkersInBounds(view, area) {
    console.log('start loadMarkersInBounds at', new Date().toISOString(), 'with', markers.length, 'markers');

    // 10-05-25 redundant. Let initMap do it.
    //const { AdvancedMarkerElement } = await google.maps.importLibrary("marker");

    const bounds = map.getBounds();
    const ne = bounds.getNorthEast();
    const sw = bounds.getSouthWest();
    const parties = Array.from(window.visibleParties);
    const partyParams = parties.map(p => `parties[]=${encodeURIComponent(p)}`).join('&');

    let townshipParams = '';
    let precinctParams = '';
    let wardParams = '';
    let supervisorParams = '';
    let markerCluster = null;

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

    //const infoWindow = new google.maps.InfoWindow(); // Single InfoWindow instance for clustered markers.
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

    fetch(`get_markers.php?north=${ne.lat()}&south=${sw.lat()}&east=${ne.lng()}&west=${sw.lng()}&${partyParams}&${townshipParams}&${neighborhoodParam}&${precinctParams}&${wardParams}&${supervisorParams}`)
    .then(response => response.json())
    .then(data => {
      clearMarkers();

      if (data.markers) {
        const positionGroups = {};
        data.markers.forEach(markerData => {
          const positionKey = `${markerData.latitude},${markerData.longitude}`;
          if (!positionGroups[positionKey]) {
            positionGroups[positionKey] = [];
          }
          positionGroups[positionKey].push(markerData);
        }); // End of forEach markerData

        Object.values(positionGroups).forEach(group => {
          group.sort((a, b) => a.last_name.localeCompare(b.last_name));

          let voterIdArray = []; // Local array for clustered markers
          let voterAptArray = []; // Local array for apartment info
          const markerData = group[0];

          //const apt_info = markerData.apartment;
          
          const isValidParty = ['DEM', 'REP', 'NP', 'OTH'].includes(markerData.party);
          const isInactive = markerData.voterstatus && markerData.voterstatus.toLowerCase().trim() === 'inactive';
          const isStrongVoter = markerData.strong_voter === true || markerData.strong_voter === "true";
          const isYoungStrongVoter = markerData.young_strong_voter === true || markerData.young_strong_voter === "true";
          const isNeedsRide = String(markerData.needs_ride_to_poll).toLowerCase() === "t"; // PostgreSQL 't' for true

          const groupHasStrongVoter = group.some(m => m.strong_voter === true || m.strong_voter === "true");
          const groupHasStrongVoterCount = group.filter(m => m.strong_voter === true || m.strong_voter === "true").length;
          const groupHasYoungStrongVoter = group.some(m => m.young_strong_voter === true || m.young_strong_voter === "true");
          const groupHasYoungStrongVoterCount = group.filter(m => m.young_strong_voter === true || m.young_strong_voter === "true").length;
          const groupHasInactive = group.some(m => m.voterstatus && m.voterstatus.toLowerCase().trim() === 'inactive');
          const groupHasInactiveCount = group.filter(m => m.voterstatus && m.voterstatus.toLowerCase().trim() === 'inactive').length;
          const groupHasNeedsRide = group.some(m => String(m.needs_ride_to_poll).toLowerCase() === "t");
          const groupHasNeedsRideCount = group.filter(m => String(m.needs_ride_to_poll).toLowerCase() === "t").length;

          group.forEach(m => {
            let shouldInclude;

            if (String(m.party).toLowerCase() === 'not registered') {
              // ✅ Handle Not Registered voters independently
              shouldInclude = filterNotRegistered;
            } else {
              // ✅ Apply full filter stack for registered voters
              const isValidParty = ['DEM', 'REP', 'NP', 'OTH'].includes(String(m.party).toUpperCase());
              const isInactive = m.voterstatus?.toLowerCase().trim() === 'inactive';
              const isStrongVoter = m.strong_voter === true || m.strong_voter === "true";
              const isYoungStrongVoter = m.young_strong_voter === true || m.young_strong_voter === "true";
              const isNeedsRide = String(m.needs_ride_to_poll).toLowerCase() === "t";

              shouldInclude =
                window.visibleParties.has(m.party) &&
                isValidParty &&
                (
                  (!filterStrongVotersOnly || isStrongVoter) &&
                  (!filterYoungStrongVotersOnly || isYoungStrongVoter) &&
                  (!filterInactiveOnly || isInactive) &&
                  (!filterNeedsRide || isNeedsRide)
                );
            }              

            if (shouldInclude && m.voterid) {
              voterIdArray.push(m.voterid);
              voterAptArray.push(m.apartment);
            }
          }); // End for each m in group

          const labelText = `${voterIdArray.length} voters\n`;
          const address = group[0]?.address || 'Unknown address';
          const namesList = group
            .filter(m => voterIdArray.includes(m.voterid)) // ✅ Only include matching voter IDs
            .map(m => {
              if (m.party === 'Not registered') {
                return `(Not registered) ${m.apartment}`;
              } else {
                return `${m.first_name} ${m.last_name} ${m.apartment} (${m.party})`;
              }
            })
            .join('\r\n');

            // If voterIdArray is > 10, use circle with count label. Otherwise, use offset markers.
            if (voterIdArray.length > 10) {
              if (!markerCache.has(voterIdArray[1])) {
                const container = document.createElement('div');
                container.style.position = 'relative';
                container.style.display = 'flex';
                container.style.flexDirection = 'column';
                container.style.alignItems = 'center';

                // 09-22-25 For "clustered" groups, use a yellow circle rather than glimph
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
                  map: map,                
                  content: container
                  //zIndex: 1000 - index
                }); // End of marker

                attachClusteredMarkerClick(marker, voterIdArray, address, voterAptArray, map, infoWindow);

                marker.data = markerData; // Stuff things party, etc. 
                markerCache.set(voterIdArray[1], marker); // cache for reuse
                markers.push(marker); // For "clustered" Markers
              } // End if !markerCache.has(voterIdArray[1])
            } else {
              group.forEach(markerData => {
                voterIdArray.forEach((id, index) => {
                  if (id === markerData.voterid) {
                    //console.log(markerCache.has(markerData.voterid))
                    //logAllMarkersInCache();
                    if (!markerCache.has(markerData.voterid)) {   
                      //console.log('Start creating new marker for id:', id); reports persons4.regn_num
                      //console.log('Start creating new marker for voterId:', markerData.voterid, markerCache.voterid); // reports person4.reg_num
                      const baseSize = 24;
                      const sizeReduction = -4;
                      const minSize = 12;
                      const markerSize = Math.max(baseSize - (index * sizeReduction), minSize);
                      const offset = index * 3;

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

                      attachClusteredMarkerClick(marker, voterIdArray, address, voterAptArray, map, infoWindow);

                      //console.log(markerData.voterid,markerData.party);
                      marker.data = markerData; // Stuff things party, etc. 
                      markerCache.set(markerData.voterid, marker); // cache for reuse
                      markers.push(marker); // For non-clustered Markers
                      //console.log('End creating new marker for voterId:', voterid);
                    } // end if if (!markerCache.has(markerData.voterid))
                  }
                });
              });
            };
        }); // End of Object.values(positionGroups).forEach
      }
    }); // End then data
    console.log('stop loadMarkersInBounds at', new Date().toISOString(), 'with', markers.length, 'markers');    
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
          const strong = voter.strong_voter ? 'Yes' : 'No';
          const youngStrong = voter.young_strong_voter ? 'Yes' : 'No';
          const needsRide = voter.needs_ride_to_poll === 't' ? 'Yes' : 'No';

          return `
            <div style="margin-bottom: 8px;">
              <strong>${voter.first_name} ${voter.last_name} ${voter.apt_info}</strong><br>
              Party: ${voter.party}<br>
              Status: ${voter.voterstatus}<br>
              Age: ${age}<br>
              History: ${history}<br>
              Strong Voter: ${strong}<br>
              Young Strong Voter: ${youngStrong}<br>
              Needs ride to poll: ${needsRide}
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
  } // End of attachClusteredMarkerClick

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

  async function initMap() {
    // First import the libraries
    const { Map } = await google.maps.importLibrary("maps");
    //const { AdvancedMarkerElement, PinElement: Pin } = await google.maps.importLibrary("marker");
    
    // 10-05-25 Declared global AdvancedMarkerElement in liue of below.
    //const { AdvancedMarkerElement } = await google.maps.importLibrary("marker");
    const { AdvancedMarkerElement: AME } = await google.maps.importLibrary("marker");
    const { DrawingManager } = await google.maps.importLibrary("drawing");
    
    AdvancedMarkerElement = AME;    

    startLatLng = null;
    //tempRectangle = null;
    rectangleOverlay = null;
    isDrawing = false;
      
      // 10-05-25 deprecated. old.
      // Now define your custom PinElement class
      //PinElement = class extends Pin {
      //  constructor(background) {
      //    super({
      //      background: background,
      //      borderColor: '#000000',
      //      glyphColor: '#FFFFFF',
      //      scale: 1.0
      //    });
      //  }
      //};

      map = new google.maps.Map(document.getElementById('map'), {
        //zoom: 18, // Includes about square mile
        //zoom: 20, // To debug local neighborhood
        //zoom: 8, //Entire MidWest
        //zoom: 16, // about two sq. miles.
        zoom: 11.8, // Entire Winneshiek County
        //center: {lat: 43.38024, lng: -91.85018} // Compound
        //center: {lat: 43.36217, lng: -91.85208} // Huthinsons
        center: {lat: 43.2844, lng: -91.8237} //Winneshiek County
        //center: {lat: 43.30473, lng: -91.80182} //502 Mound St
        ,
        mapId: "d2dc915212929407d8b8bd36", // Map ID is required for advanced markers
      });
        
      initFilters();

    // Added 06-20-25 09:15
    // Let filters control when to load markers
    //window.shouldLoadMarkers = false;

    // Optionally enable map movement logic *after* filters are active
    map.addListener('idle', () => {
      //if (window.shouldLoadMarkers) {
        console.log('handleView called at', new Date().toISOString(), 'in addListener idle');        
        handleView();
      //}
    });
    
    //map.addListener('idle', () => {
    //  const currentFilterState = getCurrentFilterState();
    //  if (hasFilterChanged(currentFilterState)) {
    //    console.log('handleView called at', new Date().toISOString(), 'in addListener idle');
    //    handleView();
    //    lastFilterState = currentFilterState;
    //  } else {
    //    console.log('No filter change detected. Skipping handleView.');
    //  }
    //});

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

    map.addListener('mousedown', (e) => {
      updateDebugOverlay();
      if (!isDrawing) return;
      startLatLng = e.latLng;
    });

    map.addListener('mousemove', (e) => {
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

    map.addListener('mouseup', () => {
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
  } // End of initMap

  // Add this after your map initialization
  function initFilters() {
    // 10-04-25 deprecate? Doubling on also on getElementById('filter-panel')
    //// Activate marker loading when any checkbox changes (added 06-20-25 9:15)
    //window.shouldLoadMarkers = false;
    //const filterIds = ['filter-dem', 'filter-rep', 'filter-np', 'filter-oth', 'filter-nr'];

    //filterIds.forEach(id => {
    //  const checkbox = document.getElementById(id);
    //  if (checkbox) {
    //    checkbox.addEventListener('change', () => {
    //      window.shouldLoadMarkers = true;
    //      console.log('handleView called at', new Date().toISOString(), 'in getElementByID for id');
    //      handleView();
    //    });
    //  }
    //}); // forEach

    const filterDiv = document.getElementById('filter-panel');
    filterDiv.addEventListener('change', (e) => {
      if (e.target.type === 'checkbox') {
        const partyName = e.target.value;
        const isChecked = e.target.checked;

        // Update global visibleParties set
        if (isChecked) {
          window.visibleParties.add(partyName);
        } else {
          window.visibleParties.delete(partyName);
        }

        // Call our smart toggle logic
        onPartyCheckboxChange(partyName, isChecked);

        // Optional: still set this if other logic depends on it
        //window.shouldLoadMarkers = true;
      }
});

    // Event listener to switch view
    document.getElementById('viewSelector').addEventListener('change', function () {
      const selectedView = this.value;
      populateAreaOptions(selectedView);
      console.log('handleView called at', new Date().toISOString(), 'in viewSelector addEventListener');
      handleView();
    }); // viewSelector change event

    // Added 07-27-25
    // Event listener to switch view
    document.getElementById('areaSelector').addEventListener('change', function () {
      const selectedArea = this.value;
      console.log('handleView called at', new Date().toISOString(), 'in areaSelector addEventListener');      
      handleView();
    }); // areaSelector change event
  } // End of initFilters

  function updateMarkerVisibility() {
    markers.forEach(marker => {
      const party = marker.content.dataset.party;
      const township = marker.content.dataset.township;
      const isPartyVisible = window.visibleParties.has(party);
      const isTownshipVisible = window.visibleTownships.has('all') || window.visibleTownships.has(township);
      marker.map = (isPartyVisible && isTownshipVisible) ? map : null;
    });
  }

  function updateDebugOverlay() {
    const overlay = document.getElementById('debugOverlay');
    overlay.innerHTML = `
      <strong>Debug Info</strong><br>
      isDrawing: ${isDrawing}<br>
      startLatLng: ${startLatLng ? startLatLng.toUrlValue() : 'null'}<br>
      rectangle: ${rectangleOverlay ? 'active' : 'none'}<br>
    `;
  }

  //function onPartyCheckboxChange(partyName, isChecked) {
  //  if (isChecked && !selectedParties[partyName]) {
  //    selectedParties[partyName] = true;
  //    console.log('calling handleView in onPartyCheckboxChange');
  //    handleView(partyName); // load markers for this party
  //  } else if (!isChecked && selectedParties[partyName]) {
  //    console.log('onPartyCheckboxChange selectedParties[partyName]:', selectedParties[partyName], ' partyName: ',partyName);
  //    selectedParties[partyName] = false;
  //    setPartyVisibility(partyName, false); // hide markers
  //  }
  //}

  function onPartyCheckboxChange(partyName, isChecked) {
    const wasSelected = selectedParties[partyName] === true;
    const wasLoaded = loadedParties[partyName] === true;

    if (isChecked && !wasLoaded) {
      // First time ever → load markers
      loadedParties[partyName] = true;
      selectedParties[partyName] = true;
      console.log('calling handleView for first-time load of', partyName);
      handleView(partyName);
    } else if (isChecked && wasLoaded && !wasSelected) {
      // Already loaded, just re-show
      selectedParties[partyName] = true;
      //console.log('re-showing markers for', partyName);
      setPartyVisibility(partyName, true);
    } else if (!isChecked && wasSelected) {
      // Hide markers
      selectedParties[partyName] = false;
      //console.log('hiding markers for', partyName);
      setPartyVisibility(partyName, false);
    }
  }

  function setPartyVisibility(partyName, isVisible) {
    for (const [id, marker] of markerCache.entries()) {
      const data = marker.data;
      //console.log(data); undefined
      //console.log(marker.data); undefined
      //console.log('setPartyVisibility for:', id); reports e.g. persons4.regn_num
      if (data.party === partyName) {
        marker.element.style.display = isVisible ? 'block' : 'none';
      }
    }
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

</script>

</body>
</html>
