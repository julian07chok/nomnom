<script setup>
import { computed, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { getDistanceKm, formatDistance } from "../utils/geo";
import { usePosts } from "../services/posts";
import PostCard from "../components/post/PostCard.vue";
import PostFilters from "../components/post/PostFilters.vue";
import Pagination from "../components/common/Pagination.vue";
import SearchBar from "../components/common/SearchBar.vue";
import "../assets/css/search.css";

const route = useRoute();
const router = useRouter();
const { posts } = usePosts();

const POSTS_PER_PAGE = 4;
const radiusKm = 50; // Radius in kilometers for Near Me

// ─── Local state (initialised from URL) ────────────────────
const searchQuery = ref(String(route.query.q || ""));
const sortBy = ref(String(route.query.sort || "newest"));
const currentPage = ref(Number(route.query.page) || 1);

const filters = ref({
  cuisine: String(route.query.cuisine || "All"),
  location: String(route.query.location || "All"),
  priceRange: String(route.query.priceRange || "All"),
});

// ─── Near Me State ─────────────────────────────────────────
const userLocation = ref(null);
const isNearMeActive = ref(route.query.near === "true");
const isLocating = ref(false);
const locationError = ref("");

// If arriving via URL with near=true, try to get location immediately
if (isNearMeActive.value) {
  requestLocation();
}

function requestLocation() {
  locationError.value = "";

  if (!navigator.geolocation) {
    locationError.value = "Geolocation is not supported by your browser.";
    isNearMeActive.value = false;
    return;
  }

  isLocating.value = true;

  navigator.geolocation.getCurrentPosition(
    (position) => {
      userLocation.value = {
        lat: position.coords.latitude,
        lng: position.coords.longitude,
      };
      isLocating.value = false;
    },
    (error) => {
      isLocating.value = false;
      isNearMeActive.value = false;
      userLocation.value = null;

      switch (error.code) {
        case error.PERMISSION_DENIED:
          locationError.value = "Location permission denied.";
          break;
        case error.POSITION_UNAVAILABLE:
          locationError.value = "Location information is unavailable.";
          break;
        case error.TIMEOUT:
          locationError.value = "Location request timed out.";
          break;
        default:
          locationError.value = "An unknown error occurred.";
      }
    },
    { timeout: 10000, enableHighAccuracy: true },
  );
}

function toggleNearMe() {
  if (isNearMeActive.value) {
    // Turn off
    isNearMeActive.value = false;
    userLocation.value = null;
    locationError.value = "";
  } else {
    // Turn on
    isNearMeActive.value = true;
    requestLocation();
  }
}

// ─── Computed: filtered + sorted posts ─────────────────────
const filteredPosts = computed(() => {
  let result = [...posts.value];

  // 1. Keyword
  const q = searchQuery.value.trim().toLowerCase();
  if (q) {
    result = result.filter(
      (p) =>
        p.title.toLowerCase().includes(q) ||
        p.restaurantName.toLowerCase().includes(q) ||
        p.location.toLowerCase().includes(q) ||
        p.cuisine.toLowerCase().includes(q) ||
        p.description.toLowerCase().includes(q),
    );
  }

  // 2. Cuisine
  if (filters.value.cuisine !== "All") {
    result = result.filter((p) => p.cuisine === filters.value.cuisine);
  }

  // 3. Location (Dropdown filter)
  if (filters.value.location !== "All") {
    result = result.filter((p) => p.location === filters.value.location);
  }

  // 4. Price
  if (filters.value.priceRange !== "All") {
    result = result.filter((p) => p.priceRange === filters.value.priceRange);
  }

  // 5. Near Me (Distance Filter)
  if (isNearMeActive.value && userLocation.value) {
    result = result
      .map((p) => {
        const dist = getDistanceKm(
          userLocation.value.lat,
          userLocation.value.lng,
          p.latitude,
          p.longitude,
        );
        return { ...p, distanceKm: dist };
      })
      .filter((p) => p.distanceKm !== null && p.distanceKm <= radiusKm);
  }

  // 6. Sort
  if (sortBy.value === "newest") {
    result.sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt));
  } else if (sortBy.value === "oldest") {
    result.sort((a, b) => new Date(a.createdAt) - new Date(b.createdAt));
  } else if (sortBy.value === "most-liked") {
    result.sort((a, b) => b.likes - a.likes);
  } else if (sortBy.value === "least-liked") {
    result.sort((a, b) => a.likes - b.likes);
  }

  return result;
});

const totalPages = computed(() =>
  Math.max(1, Math.ceil(filteredPosts.value.length / POSTS_PER_PAGE)),
);

watch(
  totalPages,
  (maximum) => {
    if (!Number.isInteger(currentPage.value) || currentPage.value < 1) {
      currentPage.value = 1;
    } else if (currentPage.value > maximum) {
      currentPage.value = maximum;
    }
  },
  { immediate: true },
);

const paginatedPosts = computed(() => {
  const start = (currentPage.value - 1) * POSTS_PER_PAGE;
  return filteredPosts.value.slice(start, start + POSTS_PER_PAGE);
});

// ─── Sync state → URL ──────────────────────────────────────
let isSyncingFromSelf = false;

function syncUrl() {
  const query = {};

  if (searchQuery.value.trim()) query.q = searchQuery.value.trim();
  if (filters.value.cuisine !== "All") query.cuisine = filters.value.cuisine;
  if (filters.value.location !== "All") query.location = filters.value.location;
  if (filters.value.priceRange !== "All")
    query.priceRange = filters.value.priceRange;
  if (sortBy.value !== "newest") query.sort = sortBy.value;
  if (currentPage.value > 1) query.page = currentPage.value;
  if (isNearMeActive.value) query.near = "true";

  isSyncingFromSelf = true;
  router.replace({ name: "search", query }).finally(() => {
    setTimeout(() => {
      isSyncingFromSelf = false;
    }, 0);
  });
}

// Filters changed → reset to page 1, then sync URL
watch(
  [searchQuery, filters, sortBy, isNearMeActive],
  () => {
    if (currentPage.value !== 1) currentPage.value = 1;
    syncUrl();
  },
  { deep: true },
);

// Page changed → sync URL
watch(currentPage, syncUrl);

// ─── Sync URL → state (Back/Forward, pasted linksx) ─────────
watch(
  () => route.query,
  (q) => {
    if (isSyncingFromSelf) return;

    const newPage = Number(q.page) || 1;
    if (newPage !== currentPage.value) currentPage.value = newPage;

    const newQ = String(q.q || "");
    if (newQ !== searchQuery.value) searchQuery.value = newQ;

    const newSort = String(q.sort || "newest");
    if (newSort !== sortBy.value) sortBy.value = newSort;

    const newNear = q.near === "true";
    if (newNear !== isNearMeActive.value) {
      isNearMeActive.value = newNear;
      if (newNear && !userLocation.value) requestLocation();
    }

    const newFilters = {
      cuisine: String(q.cuisine || "All"),
      location: String(q.location || "All"),
      priceRange: String(q.priceRange || "All"),
    };
    if (JSON.stringify(newFilters) !== JSON.stringify(filters.value)) {
      filters.value = newFilters;
    }
  },
);
</script>

<template>
  <main class="search-page">
    <div class="container search-inner">
      <!-- Search bar -->
      <section class="search-header">
        <h1 class="search-title">Explore</h1>
        <SearchBar
          v-model="searchQuery"
          variant="explore"
          :show-submit="false"
          clearable
        />
      </section>

      <!-- Layout: filters sidebar + results -->
      <div class="row g-4 mt-1">
        <!-- Filters -->
        <div class="col-12 col-lg-3">
          <PostFilters v-model="filters" />
        </div>

        <!-- Results -->
        <div class="col-12 col-lg-9">
          <!-- Results header -->
          <header class="results-header">
            <div class="results-left">
              <p class="results-count">
                <strong>{{ filteredPosts.length }}</strong>
                {{ filteredPosts.length === 1 ? "result" : "results" }}
                <span v-if="searchQuery">for "{{ searchQuery }}"</span>
                <span v-if="isNearMeActive" class="near-badge">
                  · within {{ radiusKm }}km
                </span>
              </p>

              <button
                type="button"
                class="near-me-btn"
                :class="{ 'is-active': isNearMeActive }"
                :disabled="isLocating"
                @click="toggleNearMe"
              >
                <template v-if="isNearMeActive">
                  <i class="bi bi-geo-alt-fill" aria-hidden="true"></i>
                  Near Me
                  <i
                    class="bi bi-x-circle-fill"
                    aria-hidden="true"
                    title="Clear Near Me"
                  ></i>
                </template>
                <template v-else>
                  <i class="bi bi-geo-alt" aria-hidden="true"></i>
                  {{ isLocating ? "Locating…" : "Near Me" }}
                </template>
              </button>
            </div>

            <div class="results-sort">
              <label for="sort" class="sort-label">Sort</label>
              <select id="sort" v-model="sortBy" class="sort-select">
                <option value="newest">Newest</option>
                <option value="oldest">Oldest</option>
                <option value="most-liked">Most liked</option>
                <option value="least-liked">Least liked</option>
              </select>
            </div>
          </header>

          <!-- Location error -->
          <div
            v-if="locationError"
            class="alert alert-warning mb-3"
            role="alert"
          >
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            {{ locationError }}
          </div>

          <!-- Empty -->
          <div v-if="paginatedPosts.length === 0" class="results-empty">
            <p class="empty-emoji" aria-hidden="true">🔍</p>
            <h2>No results</h2>
            <p v-if="isNearMeActive">
              There are no results within {{ radiusKm }}km of your location.
            </p>
            <p v-else>Try different keywords or clear the filters.</p>
          </div>

          <!-- Grid -->
          <div v-else class="row g-3 g-md-4">
            <div
              v-for="post in paginatedPosts"
              :key="post.id"
              class="col-6 col-xl-4"
            >
              <PostCard
                :post="post"
                :distance="
                  post.distanceKm ? formatDistance(post.distanceKm) : null
                "
              />
            </div>
          </div>

          <Pagination
            v-model:currentPage="currentPage"
            :total-pages="totalPages"
            :per-page="POSTS_PER_PAGE"
            :total-items="filteredPosts.length"
          />
        </div>
      </div>
    </div>
  </main>
</template>
