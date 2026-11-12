<!-- src/components/post/LocationPicker.vue -->
<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import '../../assets/css/location-picker.css'

const props = defineProps({
  latitude: { type: [Number, String], default: null },
  longitude: { type: [Number, String], default: null },
})

const emit = defineEmits(['update:latitude', 'update:longitude'])

const mapContainer = ref(null)
const mapRef = ref(null)
const markerRef = ref(null)

// Default: Kuala Lumpur
const DEFAULT_CENTER = [3.139, 101.6869]
const DEFAULT_ZOOM = 12

// Local string values so the user can type freely
const latInput = ref(props.latitude ?? '')
const lngInput = ref(props.longitude ?? '')

// Sync external props into local inputs
watch(
  () => props.latitude,
  (v) => {
    latInput.value = v ?? ''
  },
)
watch(
  () => props.longitude,
  (v) => {
    lngInput.value = v ?? ''
  },
)

function isValidLat(v) {
  return !Number.isNaN(v) && v >= -90 && v <= 90
}
function isValidLng(v) {
  return !Number.isNaN(v) && v >= -180 && v <= 180
}

function createPinIcon() {
  return L.divIcon({
    className: 'nomnom-marker',
    html: `<div class="nomnom-marker-pin"><i class="bi bi-shop" aria-hidden="true"></i></div>`,
    iconSize: [36, 44],
    iconAnchor: [18, 44],
  })
}

// Place or move the marker
function setMarker(lat, lng, { recenter = true } = {}) {
  if (!mapRef.value) return

  const numericLat = Number(lat)
  const numericLng = Number(lng)

  if (!isValidLat(numericLat) || !isValidLng(numericLng)) return

  if (markerRef.value) {
    markerRef.value.setLatLng([numericLat, numericLng])
  } else {
    markerRef.value = L.marker([numericLat, numericLng], {
      icon: createPinIcon(),
      draggable: true,
    }).addTo(mapRef.value)

    // Update lat/lng when the marker is dragged
    markerRef.value.on('dragend', (event) => {
      const pos = event.target.getLatLng()
      emit('update:latitude', Number(pos.lat.toFixed(6)))
      emit('update:longitude', Number(pos.lng.toFixed(6)))
      latInput.value = pos.lat.toFixed(6)
      lngInput.value = pos.lng.toFixed(6)
    })
  }

  if (recenter) {
    mapRef.value.setView(
      [numericLat, numericLng],
      mapRef.value.getZoom() < 13 ? 15 : mapRef.value.getZoom(),
    )
  }
}

function removeMarker() {
  if (markerRef.value && mapRef.value) {
    mapRef.value.removeLayer(markerRef.value)
    markerRef.value = null
  }
}

onMounted(async () => {
  if (!mapContainer.value) return

  const hasCoords =
    props.latitude != null &&
    props.longitude != null &&
    isValidLat(Number(props.latitude)) &&
    isValidLng(Number(props.longitude))

  const center = hasCoords
    ? [Number(props.latitude), Number(props.longitude)]
    : DEFAULT_CENTER

  mapRef.value = L.map(mapContainer.value, {
    center,
    zoom: hasCoords ? 15 : DEFAULT_ZOOM,
    scrollWheelZoom: false,
  })

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution:
      '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors',
    maxZoom: 19,
  }).addTo(mapRef.value)

  // Enable wheel zoom on map click
  mapRef.value.on('click', () => mapRef.value.scrollWheelZoom.enable())
  mapRef.value.on('mouseout', () => mapRef.value.scrollWheelZoom.disable())

  // Click on map → place marker here
  mapRef.value.on('click', (event) => {
    const { lat, lng } = event.latlng

    emit('update:latitude', Number(lat.toFixed(6)))
    emit('update:longitude', Number(lng.toFixed(6)))

    latInput.value = lat.toFixed(6)
    lngInput.value = lng.toFixed(6)

    setMarker(lat, lng, { recenter: false })
  })

  // Restore existing marker if prop passed in
  if (hasCoords) {
    setMarker(props.latitude, props.longitude, { recenter: false })
  }

  // Leaflet sometimes needs a resize hint after layout settles
  await nextTick()
  mapRef.value.invalidateSize()
})

onBeforeUnmount(() => {
  if (mapRef.value) {
    mapRef.value.remove()
    mapRef.value = null
  }
})

// ─── React to external coordinate changes (geocoding) ──────
// When the parent updates latitude/longitude (e.g., from an
// address geocode), move the marker to the new position.
watch(
  () => [props.latitude, props.longitude],
  ([lat, lng]) => {
    const numericLat = Number(lat)
    const numericLng = Number(lng)

    if (
      lat != null &&
      lng != null &&
      isValidLat(numericLat) &&
      isValidLng(numericLng)
    ) {
      setMarker(numericLat, numericLng, { recenter: true })
    }
  },
)

// Manual lat/lng input handlers
function onLatBlur() {
  const v = parseFloat(latInput.value)
  if (!isValidLat(v)) {
    latInput.value = props.latitude ?? ''
    return
  }
  emit('update:latitude', Number(v.toFixed(6)))
  latInput.value = v.toFixed(6)

  if (isValidLng(parseFloat(lngInput.value))) {
    setMarker(v, parseFloat(lngInput.value))
  }
}

function onLngBlur() {
  const v = parseFloat(lngInput.value)
  if (!isValidLng(v)) {
    lngInput.value = props.longitude ?? ''
    return
  }
  emit('update:longitude', Number(v.toFixed(6)))
  lngInput.value = v.toFixed(6)

  if (isValidLat(parseFloat(latInput.value))) {
    setMarker(parseFloat(latInput.value), v)
  }
}

function clearLocation() {
  latInput.value = ''
  lngInput.value = ''
  removeMarker()
  emit('update:latitude', null)
  emit('update:longitude', null)

  if (mapRef.value) {
    mapRef.value.setView(DEFAULT_CENTER, DEFAULT_ZOOM)
  }
}

const hasLocation = () =>
  isValidLat(Number(latInput.value)) && isValidLng(Number(lngInput.value))
</script>

<template>
  <section class="location-picker" aria-labelledby="location-picker-heading">
    <header class="location-picker-header">
      <h3 id="location-picker-heading" class="location-picker-title">
        <i class="bi bi-geo-alt-fill" aria-hidden="true"></i>
        Pin the restaurant on the map
      </h3>
      <p class="location-picker-hint">
        Click the map or drag the pin to mark the restaurant's location.
        Coordinates are filled automatically.
      </p>
    </header>

    <div
      ref="mapContainer"
      class="location-picker-map"
      role="application"
      aria-label="Interactive map. Click to set the restaurant location."
    ></div>

    <div class="location-picker-fields">
      <div class="location-field">
        <label for="post-lat" class="location-label">Latitude</label>
        <input
          id="post-lat"
          v-model="latInput"
          class="location-input"
          type="text"
          inputmode="decimal"
          placeholder="e.g. 3.139"
          @blur="onLatBlur"
        />
      </div>

      <div class="location-field">
        <label for="post-lng" class="location-label">Longitude</label>
        <input
          id="post-lng"
          v-model="lngInput"
          class="location-input"
          type="text"
          inputmode="decimal"
          placeholder="e.g. 101.6869"
          @blur="onLngBlur"
        />
      </div>

      <button
        v-if="hasLocation()"
        type="button"
        class="location-clear"
        @click="clearLocation"
      >
        <i class="bi bi-x-circle" aria-hidden="true"></i>
        Clear pin
      </button>
    </div>

    <p class="location-picker-help">
      <i class="bi bi-info-circle" aria-hidden="true"></i>
      Tip: Use "Find on map" from the address field, or click the map to set the location manually.
    </p>
  </section>
</template>