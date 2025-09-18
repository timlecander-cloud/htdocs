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
        
        if (!isset($cacheData[$cacheKey]) || 
            (time() - $cacheData[$cacheKey]['timestamp'] > self::$cacheDuration)) {
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

//function getDbConnection() {
//    if ($GLOBALS['dbConnection'] === null) {
//        $GLOBALS['dbConnection'] = pg_connect(
//            "host=localhost " .
//            "dbname=Winneshiek " .
//            "user=postgres " .
//            "password=(163Lydia)"
//        );
//    }
//    return $GLOBALS['dbConnection'];
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
  <label class="label not-registered"><input type="checkbox" value="Not registered" id="filter-nr"> Not registered</label>
</div>

<div class="strong-voter-filters">
  <label class="label strongvoters"><input type="checkbox" value="Strong" id="filter-strongvoters"> Strong Voters Only</label>
</div>

<!-- ✅ New Neighborhoods checkbox -->
<div class="neighborhood-filter">
  <label><input type="checkbox" id="filter-neighborhoods"> Neighborhoods Only</label>
</div>


<!--
<div class="voterstatus-filter">
  <label class="label inactivevoters"><input type="checkbox" value="Inactive" id="filter-voterstatus"> Inactive Voters Only (any party)</label>
</div>
  -->
    </div>

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
    </div>

    <script>
    // Declare global filter sets near the top of your script
    window.visibleParties = new Set();
    window.visibleTownships = new Set(); // if you're also using township filters
    window.shouldLoadMarkers = false;

    // Positions hamburger-btn
    document.getElementById('hamburger-btn').addEventListener('click', function() {
        const filterPanel = document.getElementById('filter-panel');
        filterPanel.style.display = (filterPanel.style.display === 'none' || filterPanel.style.display === '') ? 'block' : 'none';
    });

    </script>
<script>
let map;
let markers = [];
let clusteredMarkers = []; // Declare at the top of your script
let PinElement; // Add this
let globalOriginalLatitude = 0.0;  // Add this
let globalOriginalLongitude = 0.0; // Add this
let globalLastLatitude = 0.0;  // Add this
let globalLastLongitude = 0.0; // Add this
let globalmarkertitle = '';
let globalscalemultiplier = 4;
let globalmarkersize = 24; // base size in pixels
const MARKER_SIZE = 24; // Base size in pixels
// Add with your other global variables
// Added 07-27-25
const townshipOptions = ['none', 'all', 'Bloomfield', 'Bluffton', 'Burr Oak', 'Calmar', 'Canoe', 'Decorah', 'Frankville', 'Fremont', 'Glenwood', 'Hesper', 'Highland', 'Jackson', 'Lincoln', 'Madison', 'Military', 'Orleans', 'Pleasant', 'Springfield', 'Sumner', 'Washington'];
const precinctOptions = ['none', 'all', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'];
const wardOptions = ['none','DE1','DE2','DE3','DE4','DE5'];
const supervisorOptions = ['none','1','2','3','4','5'];

function clearMarkers() {
    markers.forEach(marker => {
        marker.map = null;  // This is how you remove AdvancedMarkerElements
    });
    markers = [];
}

// Added 08-24-25
function clearClusteredMarkers() {
  // Remove markers from the map
  //clusteredMarkers.forEach(marker => marker.setMap(null));
  clusteredMarkers.forEach(marker => {
    //if (marker.setLabel) {
      marker.setLabel(null); // For google.maps.Marker
    //}
    marker.setMap(null); // Detach from map
  });

  // Clear the array
  clusteredMarkers.length = 0;
  clusteredMarkers = [];

  // Clear markers from the clusterer
  //if (markerClusterer) {
  //  markerClusterer.clearMarkers();
  //}
  //if (clusteredMarkers) {
  //  clusteredMarkers.clear();
  //}
}

// Added 07-27-25
// Dropdown population function
function populateAreaOptions(view) {
  const areaSelector = document.getElementById('areaSelector');
  areaSelector.innerHTML = '';

  //const options = view === 'township' ? townshipOptions : precinctOptions;
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
      //options = precinctOptions;
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

//function handleView(currentView, selectedArea) {
function handleView() {
  const currentView = document.getElementById('viewSelector').value;
  const selectedArea = document.getElementById('areaSelector').value;

  switch (currentView) {
    case 'township':
      //console.log("Township view selected");
      const townships = selectedArea;
      //const townshipParams = `townships=${encodeURIComponent(townships)}`;
      //console.log('Township: ', townships);

      //if (Array.isArray(townships) && (townships.includes('none') || townships.length === 0)) {
      //  console.log('No township selected — exiting.');
      //  return;  // now this works!
      //}
      if (townships === 'none' || !townships) {
        //console.log('No township selected — exiting.');
        return;
      }

      loadMarkersInBounds(currentView, selectedArea);
      break;

    case 'precinct':
      //console.log("Precinct view selected");
      loadMarkersInBounds(currentView, selectedArea);
      break;

    case 'ward':
      loadMarkersInBounds(currentView, selectedArea);
      break;

    case 'supervisor':
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
  const { AdvancedMarkerElement } = await google.maps.importLibrary("marker");
  // 08-23-25 working on this now...I think the above line is the new 
  //new AdvancedMarkerElement({
  //  position: { lat: 40.7128, lng: -74.0060 },
  //  map: map,
  //  content: myCustomDiv,
  //  zIndex: 1000,
  //  title: "John Doe - 123 Main St (DEM) Active"
  //});

  const bounds = map.getBounds();
  const ne = bounds.getNorthEast();
  const sw = bounds.getSouthWest();
  const parties = Array.from(window.visibleParties);
  const partyParams = parties.map(p => `parties[]=${encodeURIComponent(p)}`).join('&');

  let townshipParams = '';
  let precinctParams = '';
  let wardParams = '';
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
  //const filterInactiveOnly = document.getElementById('filter-voterstatus').checked;
  const filterStrongVotersOnly = document.getElementById('filter-strongvoters').checked;

  fetch(`get_markers.php?north=${ne.lat()}&south=${sw.lat()}&east=${ne.lng()}&west=${sw.lng()}&${partyParams}&${townshipParams}&${neighborhoodParam}&${precinctParams}&${wardParams}&${supervisorParams}`)
  .then(response => response.json())
  .then(data => {
    clearMarkers();
    clearClusteredMarkers();

    const clusteredMarkers = [];

    if (data.markers) {
      const positionGroups = {};
      data.markers.forEach(markerData => {
        const positionKey = `${markerData.latitude},${markerData.longitude}`;
        if (!positionGroups[positionKey]) {
          positionGroups[positionKey] = [];
        }
        positionGroups[positionKey].push(markerData);
      });

      Object.values(positionGroups).forEach(group => {
        group.sort((a, b) => a.last_name.localeCompare(b.last_name));
        if (group.length > 10) {
          const markerData = group[0];
          const isValidParty = ['DEM', 'REP', 'NP', 'OTH'].includes(markerData.party);
          //const isInactive = markerData.voterstatus && markerData.voterstatus.toLowerCase().trim() === 'inactive';
          //const shouldDisplay = window.visibleParties.has(markerData.party) &&
          //  (!filterInactiveOnly || (isValidParty && isInactive));
          const shouldDisplay = window.visibleParties.has(markerData.party) && (isValidParty);

          if (shouldDisplay) {
            // 08-24-25 Put address at top of tooltip

            const partyCounts = group.reduce((acc, m) => {
            const party = m.party || 'UNK';
            acc[party] = (acc[party] || 0) + 1;
            return acc;
          }, {});

	        const partySummary = Object.entries(partyCounts)
	        .map(([party, count]) => `${party}: ${count}`)
	        .join(', ');

	        // Example output: "12 voters (DEM: 7, REP: 3, NP: 2)"
			    
	        // 08-25-25 Including partySummary gives ghost image when modifying label.
	        const labelText = `${group.length} voters\n`;
	        const address = group[0]?.address || 'Unknown address';
	        const namesList = group.map(m => {
            if (m.party === 'Not registered') {
              return `(Not registered) ${m.apartment}`;
            } else {
              //const strongVoterText = `Strong Voter: ${String(m.strong_voter).toLowerCase() === 'true' ? 'true' : 'false'}`;
              const strongVoterText = String(m.strong_voter).toLowerCase() === 'true' ? 'Strong Voter' : '';
              return `${m.first_name} ${m.last_name} ${m.apartment} (${m.party}) ${m.voterstatus} ${m.voterid} ${strongVoterText}`;
            }
    	    }).join('\r\n');

	        const tooltipContent = `${address}\n(${partySummary})\r\n${namesList}`;

          // 08-23-25 Next might be old API (supplied by co-pilot originally)
    	    // 08-24-25 voterCount added.
    	    const voterMarker = new google.maps.Marker({
    	      position: {
    	        lat: parseFloat(markerData.latitude),
    	        lng: parseFloat(markerData.longitude)
    	      },
    	      map: map,
    	      title: tooltipContent,
    	      label: {
    	        text: labelText,
    	        color: 'black',
    	        fontSize: '12px',
    	        fontWeight: 'bold'
    	      },
    	      icon: {
    	        path: google.maps.SymbolPath.CIRCLE,
    	        scale: 20,
    	        fillColor: '#FFFF00',
    	        fillOpacity: 0.9,
     			    strokeWeight: 1,
    	        strokeColor: '#fff'
    	      },
    	    });
			    //voterMarker.setLabel(''); // Blanks it out.

	        clusteredMarkers.push(voterMarker); 
        }
      } else {
			// Define array to collect voterids - 09-12-25
  			const voterIdArray = [];

        group.forEach((markerData, index) => {
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

		  	  // Collect voterid if group has more than one - 09-12-25
  			  if (group.length > 0 && markerData.voterid) {
  			    voterIdArray.push(markerData.voterid);
  			  }

          // 08-24-25 Place address at top of tooltip.
  			  const address = group[0]?.address || 'Unknown address';

	  		  const namesList = group.map(m => {
  			    if (m.party === 'Not registered') {
  			      return `(Not registered) ${m.apartment}`;
  			    } else {
  			      return `${m.first_name} ${m.last_name} ${m.apartment} (${m.party}) ${m.voterstatus} ${m.voterid}`;
  			    }
  			  }).join('\r\n');

	  		  const tooltipContent = `${address}\r\n${namesList}`;

          markerElement.title = tooltipContent;

  			  // 09-10-25 voter Detail
  			    markerElement.addEventListener('click', () => {
  			      showVoterDetailsModal(voterIdArray);
  			  });

          const isValidParty = ['DEM', 'REP', 'NP', 'OTH'].includes(markerData.party);
          //const isInactive = markerData.voterstatus && markerData.voterstatus.toLowerCase().trim() === 'inactive';
          const isStrongVoter = markerData.strong_voter === true || markerData.strong_voter === "true";

          //console.log('isStrongVoter:', isStrongVoter, markerData.last_name);

          //const shouldDisplay = window.visibleParties.has(markerData.party) &&
          //(!filterInactiveOnly || (isValidParty && isInactive));

          const shouldDisplay = window.visibleParties.has(markerData.party) &&
          (!filterStrongVotersOnly || (isValidParty && isStrongVoter));

          //const shouldDisplay = window.visibleParties.has(markerData.party) && isValidParty && isStrongVoter;

          //const shouldDisplay =
          //  window.visibleParties.has(markerData.party) &&
          //  (
          //    !filterInactiveOnly || (isValidParty && isInactive)
          //  ) &&
          //  isStrongVoter;

        //  const shouldDisplay =
        //    window.visibleParties.has(markerData.party) &&
        //    (
        //      isStrongVoter ||
        //      (!filterInactiveOnly || (isValidParty && isInactive))
        //    );


          // Markers with less than 10 use the new AdvancedMarkerElement
  			  const marker = new AdvancedMarkerElement({
            position: {
              lat: parseFloat(markerData.latitude),
              lng: parseFloat(markerData.longitude)
             },
             map: shouldDisplay ? map : null,
             content: markerElement,
             zIndex: 1000 - index
            });

            markers.push(marker);
            });
            }
          });

          const markerClusterer = new window.markerClusterer.MarkerClusterer({
		      map: map,
		      markers: clusteredMarkers
		    });
      }
   });
}

// 09-13-25 Voter Detail
function showVoterDetailsModal(voterIDArray = []) {
  const modal = document.getElementById('voter-modal');
  const contentEl = modal.querySelector('.modal-content');

  // Show the modal
  modal.classList.remove('hidden');
  modal.classList.add('visible');

  // ✅ Attach close handler right after modal is shown
  const closeBtn = modal.querySelector('.close-button');
  if (closeBtn) {
    closeBtn.addEventListener('click', () => {
      modal.classList.remove('visible');
      modal.classList.add('hidden');
    });
  }

  // Load content
  contentEl.innerHTML = '<p>Loading voter details...</p>';

  if (voterIDArray.length === 0) {
    contentEl.innerHTML += '<p>No voter selected.</p>';
    return;
  }

  const fetchPromises = voterIDArray.map(id =>
    fetch(`/get_voter_details.php?regn_num=${id}`).then(res => res.json())
  );

  Promise.all(fetchPromises)
    .then(voterDetailsArray => {
      const contentHTML = voterDetailsArray.map(voter => {
        const age = calculateAge(voter.birthdate);
        return `
          <div class="voter-block">
            <strong>${voter.first_name} ${voter.last_name}</strong><br>
            Party: ${voter.party}<br>
            Status: ${voter.voterstatus}<br>
            Age: ${age}<br>
            History: ${voter.general_election_history || 'None'}<br>
            Strong Voter: ${voter.strong_voter ? 'Yes' : 'No'}<br>
          </div>
          <hr>
        `;
      }).join('');
      contentEl.innerHTML = contentHTML;
    })
    .catch(err => {
      contentEl.innerHTML += '<p>Error loading voter details.</p>';
      console.error('Error fetching voter details:', err);
    });
}


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
  //if (voterstatus === 'Inactive') {
  //{
	//return '#FF69b4'; // Hot pink
  //}

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
    const { AdvancedMarkerElement, PinElement: Pin } = await google.maps.importLibrary("marker");
    
    // Now define your custom PinElement class
    PinElement = class extends Pin {
        constructor(background) {
            super({
                background: background,
                borderColor: '#000000',
                glyphColor: '#FFFFFF',
                scale: 1.0
            });
        }
    };

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
    window.shouldLoadMarkers = false;

    // Optionally enable map movement logic *after* filters are active
    map.addListener('idle', () => {
      if (window.shouldLoadMarkers) {
        //loadMarkersInBounds();
	handleView();
      }
    });

}

// Add this after your map initialization
function initFilters() {
    //console.log('initFilters fire'); // 06-20-25 (executed only once at very begining?)
    // Activate marker loading when any checkbox changes (added 06-20-25 9:15)
    window.shouldLoadMarkers = false;
    const filterIds = ['filter-dem', 'filter-rep', 'filter-np', 'filter-oth', 'filter-nr'];

    filterIds.forEach(id => {
        const checkbox = document.getElementById(id);
	//console.log('initFilters filterIds'); // (called only once at very beginning)
        if (checkbox) {
            checkbox.addEventListener('change', () => {
                window.shouldLoadMarkers = true;
                //loadMarkersInBounds(); // kick off marker fetch
		handleView();
            });
        }
    });    

    // Existing party filter code
    const filterDiv = document.getElementById('filter-panel');
    filterDiv.addEventListener('change', (e) => {
	//console.log('initFilters filter-panel'); // Called on each party click.
        if (e.target.type === 'checkbox') {
            if (e.target.checked) {
		window.visibleParties.add(e.target.value);
            } else {
		window.visibleParties.delete(e.target.value);
            }
	    // Move to bottom 06-22-25
	    // Added 06-21-25 (next 6 lines seem to fix synching issue of parties with actual. Ties marker loading to checkbox change).
  	    window.shouldLoadMarkers = true;

	    // Slight delay to ensure visibleParties is fully updated
	    setTimeout(() => {
	      //loadMarkersInBounds();
		    handleView();
	    }, 0);
        }
    });

    //// Add township filter handler
    //const townshipFilter = document.getElementById('townshipFilter');
    //townshipFilter.addEventListener('change', function(e) {
    //	const selected = townshipFilter.value;
    //	window.visibleTownships = new Set([selected]);
    //    
    //    updateMarkerVisibility();
    //
    //	window.shouldLoadMarkers = true;
    //
    //	setTimeout(() => {
    //	  loadMarkersInBounds();
    //	}, 0);
    //});

    // Added 07-27-25
    // Event listener to switch view
    document.getElementById('viewSelector').addEventListener('change', function () {
      const selectedView = this.value;
      populateAreaOptions(selectedView);
      //console.log('viewSelector EventListener');
      //loadMarkersInBounds();
        // 07-30-25 added
	    clearMarkers();
	    clearClusteredMarkers();
	    handleView();
    });

    // Added 07-27-25
    // Event listener to switch view
    document.getElementById('areaSelector').addEventListener('change', function () {
      const selectedArea = this.value;
      //populateAreaOptions(selectedView);
      //console.log('foo-bar2',selectedArea); //Reports e.g. Precinct 2, Bluffton
      //loadMarkersInBounds();
      handleView();
    });
}

function updateMarkerVisibility() {
    //console.log('updateMarkerVisibility');
    markers.forEach(marker => {
        const party = marker.content.dataset.party;
        const township = marker.content.dataset.township;
      	const isPartyVisible = window.visibleParties.has(party);
	      const isTownshipVisible = window.visibleTownships.has('all') || window.visibleTownships.has(township);
        marker.map = (isPartyVisible && isTownshipVisible) ? map : null;
    });
}

</script>

<script async
    src="https://maps.googleapis.com/maps/api/js?key=AIzaSyC_KbKXsaVsdkaOvEHWYfP0Gn1lBGB-eRU&loading=async&callback=initMap&libraries=marker"
    loading="async">
</script>
<script src="https://unpkg.com/@googlemaps/markerclusterer/dist/index.min.js"></script>
</body>
</html>
