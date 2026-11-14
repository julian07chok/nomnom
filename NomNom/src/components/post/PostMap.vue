<!-- src/components/post/PostMap.vue -->
<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { useRouter } from "vue-router";
import L from "leaflet";
import "leaflet/dist/leaflet.css";
import "../../assets/css/post-map.css";

const props = defineProps({
  posts: {
    type: Array,
    required: true,
  },
  userLocation: {
    // { lat: number, lng: number } or null
    type: Object,
    default: null,
  },
});

const router = useRouter();
const mapContainer = ref(null);
const mapReady = ref(false);

let map = null;
let markerLayer = null;
let userMarker = null;

const DEFAULT_CENTER = [3.139, 101.6869];
const DEFAULT_ZOOM = 11;

const postsWithCoords = computed(() =>
  props.posts.filter(
    (p) =>
      p.latitude !== null &&
      p.longitude !== null &&
      !Number.isNaN(Number(p.latitude)) &&
      !Number.isNaN(Number(p.longitude)),
  ),
);

// Restaurant pin (red, pin-shaped)
function createRestaurantIcon() {
  return L.divIcon({
    className: "nomnom-marker",
    html: `
      <div class="nomnom-marker-pin">
        <i class="bi bi-shop" aria-hidden="true"></i>
      </div>
    `,
    iconSize: [36, 44],
    iconAnchor: [18, 44],
    popupAnchor: [0, -40],
  });
}

// Current location marker (blue, circular pulse)
function createUserIcon() {
  return L.divIcon({
    className: "nomnom-user-marker",
    html: `
      <div class="nomnom-user-marker-dot">
        <div class="nomnom-user-marker-pulse"></div>
      </div>
    `,
    iconSize: [24, 24],
    iconAnchor: [12, 12],
  });
}

function escapeHtml(value) {
  return String(value ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

function buildPopupHtml(post) {
  const cover = post.coverImage || "";
  const title = escapeHtml(post.title);
  const restaurant = escapeHtml(post.restaurantName);
  const cuisine = escapeHtml(post.cuisine);
  const price = escapeHtml(post.priceRange);
  const location = escapeHtml(post.location);
  const id = Number(post.id);

  return `
    <div class="nomnom-popup">
      ${cover ? `<img src="${cover}" alt="" class="nomnom-popup-img" loading="lazy" />` : ""}
      <div class="nomnom-popup-body">
        <p class="nomnom-popup-cuisine">
          <span>${cuisine}</span>
          <span>${price}</span>
        </p>
        <h3 class="nomnom-popup-title">${title}</h3>
        <p class="nomnom-popup-restaurant">
          <i class="bi bi-shop"></i> ${restaurant}
        </p>
        <p class="nomnom-popup-location">
          <i class="bi bi-geo-alt"></i> ${location}
        </p>
        <a href="/posts/${id}" class="nomnom-popup-link" data-post-id="${id}">
          View post
        </a>
      </div>
    </div>
  `;
}

function renderMarkers() {
  if (!map) return;

  if (markerLayer) {
    markerLayer.clearLayers();
  } else {
    markerLayer = L.layerGroup().addTo(map);
  }

  const icon = createRestaurantIcon();
  const bounds = [];

  for (const post of postsWithCoords.value) {
    const lat = Number(post.latitude);
    const lng = Number(post.longitude);

    const marker = L.marker([lat, lng], { icon });
    marker.bindPopup(buildPopupHtml(post), {
      maxWidth: 260,
      className: "nomnom-popup-wrapper",
    });

    marker.on("popupopen", (event) => {
      const popupEl = event.popup.getElement();
      const link = popupEl?.querySelector(".nomnom-popup-link");

      if (link) {
        link.addEventListener(
          "click",
          (e) => {
            e.preventDefault();
            const postId = e.currentTarget.dataset.postId;
            router.push({ name: "post-detail", params: { id: postId } });
            map.closePopup();
          },
          { once: true },
        );
      }
    });

    marker.addTo(markerLayer);
    bounds.push([lat, lng]);
  }

  // ── Current location marker ─────────────────────────────
  renderUserMarker();

  // Include the user's location in the bounds if present
  if (props.userLocation) {
    const lat = Number(props.userLocation.lat);
    const lng = Number(props.userLocation.lng);
    if (!Number.isNaN(lat) && !Number.isNaN(lng)) {
      bounds.push([lat, lng]);
    }
  }

  if (bounds.length === 1) {
    map.setView(bounds[0], 14);
  } else if (bounds.length > 1) {
    map.fitBounds(bounds, { padding: [40, 40], maxZoom: 14 });
  } else {
    map.setView(DEFAULT_CENTER, DEFAULT_ZOOM);
  }
}

// Draw or redraw the current-location marker
function renderUserMarker() {
  if (!map) return;

  // Remove old user marker
  if (userMarker) {
    map.removeLayer(userMarker);
    userMarker = null;
  }

  if (!props.userLocation) return;

  const lat = Number(props.userLocation.lat);
  const lng = Number(props.userLocation.lng);
  if (Number.isNaN(lat) || Number.isNaN(lng)) return;

  userMarker = L.marker([lat, lng], {
    icon: createUserIcon(),
    interactive: false,
    zIndexOffset: 1000, // draw above restaurant markers
  }).addTo(map);
}

onMounted(() => {
  if (!mapContainer.value) return;

  map = L.map(mapContainer.value, {
    center: DEFAULT_CENTER,
    zoom: DEFAULT_ZOOM,
    scrollWheelZoom: false,
    zoomControl: true,
  });

  L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
    attribution:
      '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors',
    maxZoom: 19,
  }).addTo(map);

  map.on("click", () => map.scrollWheelZoom.enable());
  map.on("mouseout", () => map.scrollWheelZoom.disable());

  mapReady.value = true;
  renderMarkers();
});

watch(postsWithCoords, renderMarkers, { deep: true });

// Watch user location changes to update the marker + recenter
watch(
  () => props.userLocation,
  async () => {
    if (!map) return;

    renderUserMarker();

    // If we have a user location, include it in the fitBounds call
    // so the map shows both the user and nearby restaurants.
    if (props.userLocation) {
      const bounds = [];

      for (const post of postsWithCoords.value) {
        bounds.push([Number(post.latitude), Number(post.longitude)]);
      }

      bounds.push([
        Number(props.userLocation.lat),
        Number(props.userLocation.lng),
      ]);

      if (bounds.length > 1) {
        map.fitBounds(bounds, { padding: [40, 40], maxZoom: 14 });
      } else {
        map.setView(
          [Number(props.userLocation.lat), Number(props.userLocation.lng)],
          14,
        );
      }
    }
  },
  { deep: true },
);

onBeforeUnmount(() => {
  if (map) {
    map.remove();
    map = null;
  }
});
</script>

<template>
  <section class="post-map-section" aria-labelledby="post-map-heading">
    <div class="container">
      <header class="post-map-header">
        <h2 id="post-map-heading" class="post-map-title">
          <i class="bi bi-map" aria-hidden="true"></i>
          Explore on the map
        </h2>
        <p class="post-map-subtitle">
          {{ postsWithCoords.length }}
          {{ postsWithCoords.length === 1 ? "restaurant" : "restaurants" }}
          on the map. Click a pin to see the post.
        </p>
      </header>

      <div
        ref="mapContainer"
        class="post-map"
        role="region"
        aria-label="Restaurant map"
      ></div>

      <div v-if="userLocation" class="post-map-legend">
        <span class="legend-item">
          <span class="legend-dot legend-dot-restaurant"></span>
          Restaurant
        </span>
        <span class="legend-item">
          <span class="legend-dot legend-dot-user"></span>
          Your location
        </span>
      </div>

      <p v-if="!postsWithCoords.length" class="post-map-empty">
        No restaurants with location data yet.
      </p>
    </div>
  </section>
</template>