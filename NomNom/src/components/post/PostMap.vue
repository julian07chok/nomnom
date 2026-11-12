<!-- src/components/post/PostMap.vue -->
<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import '../../assets/css/post-map.css'

const props = defineProps({
    posts: {
        type: Array,
        required: true,
    },
})

const router = useRouter()
const mapContainer = ref(null)
const mapReady = ref(false)

let map = null
let markerLayer = null

// Default view: Kuala Lumpur
const DEFAULT_CENTER = [3.139, 101.6869]
const DEFAULT_ZOOM = 11

// Build markers from posts that have valid coordinates
const postsWithCoords = computed(() =>
    props.posts.filter(
        (p) =>
            p.latitude !== null &&
            p.longitude !== null &&
            !Number.isNaN(Number(p.latitude)) &&
            !Number.isNaN(Number(p.longitude)),
    ),
)

// Custom pin icon (SVG, no external asset needed)
function createPinIcon() {
    return L.divIcon({
        className: 'nomnom-marker',
        html: `
      <div class="nomnom-marker-pin">
        <i class="bi bi-shop" aria-hidden="true"></i>
      </div>
    `,
        iconSize: [36, 44],
        iconAnchor: [18, 44],
        popupAnchor: [0, -40],
    })
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;')
}

function buildPopupHtml(post) {
    const cover = post.coverImage || ''
    const title = escapeHtml(post.title)
    const restaurant = escapeHtml(post.restaurantName)
    const cuisine = escapeHtml(post.cuisine)
    const price = escapeHtml(post.priceRange)
    const location = escapeHtml(post.location)
    const id = Number(post.id)

    return `
    <div class="nomnom-popup">
      ${cover ? `<img src="${cover}" alt="" class="nomnom-popup-img" loading="lazy" />` : ''}
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
  `
}

function renderMarkers() {
    if (!map) return

    // Remove old markers
    if (markerLayer) {
        markerLayer.clearLayers()
    } else {
        markerLayer = L.layerGroup().addTo(map)
    }

    const icon = createPinIcon()
    const bounds = []

    for (const post of postsWithCoords.value) {
        const lat = Number(post.latitude)
        const lng = Number(post.longitude)

        const marker = L.marker([lat, lng], { icon })
        marker.bindPopup(buildPopupHtml(post), {
            maxWidth: 260,
            className: 'nomnom-popup-wrapper',
        })

        // Custom nav (so Vue Router handles the click instead of full page reload)
        marker.on('popupopen', (event) => {
            const popupEl = event.popup.getElement()
            const link = popupEl?.querySelector('.nomnom-popup-link')

            if (link) {
                link.addEventListener(
                    'click',
                    (e) => {
                        e.preventDefault()
                        const postId = e.currentTarget.dataset.postId
                        router.push({ name: 'post-detail', params: { id: postId } })
                        map.closePopup()
                    },
                    { once: true },
                )
            }
        })

        marker.addTo(markerLayer)
        bounds.push([lat, lng])
    }

    // Auto-fit to markers, or reset to KL if none
    if (bounds.length === 1) {
        map.setView(bounds[0], 14)
    } else if (bounds.length > 1) {
        map.fitBounds(bounds, { padding: [40, 40], maxZoom: 14 })
    } else {
        map.setView(DEFAULT_CENTER, DEFAULT_ZOOM)
    }
}

onMounted(() => {
    if (!mapContainer.value) return

    map = L.map(mapContainer.value, {
        center: DEFAULT_CENTER,
        zoom: DEFAULT_ZOOM,
        scrollWheelZoom: false, // don't hijack page scroll on mobile
        zoomControl: true,
    })

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution:
            '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors',
        maxZoom: 19,
    }).addTo(map)

    // Enable wheel zoom only when the map is clicked (good UX on scroll pages)
    map.on('click', () => map.scrollWheelZoom.enable())
    map.on('mouseout', () => map.scrollWheelZoom.disable())

    mapReady.value = true
    renderMarkers()
})

// Update markers when the posts prop changes
watch(postsWithCoords, renderMarkers, { deep: true })

onBeforeUnmount(() => {
    if (map) {
        map.remove()
        map = null
    }
})
</script>

<template>
    <section class="post-map-section" aria-labelledby="post-map-heading">
        <header class="post-map-header">
            <h2 id="post-map-heading" class="post-map-title">
                <i class="bi bi-map" aria-hidden="true"></i>
                Explore on the map
            </h2>
            <p class="post-map-subtitle">
                {{ postsWithCoords.length }}
                {{ postsWithCoords.length === 1 ? 'restaurant' : 'restaurants' }}
                on the map. Click a pin to see the post.
            </p>
        </header>

        <div ref="mapContainer" class="post-map" role="region" aria-label="Restaurant map"></div>

        <p v-if="!postsWithCoords.length" class="post-map-empty">
            No restaurants with location data yet.
        </p>
    </section>
</template>