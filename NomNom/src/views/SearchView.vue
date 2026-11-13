<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import { formatDistance } from "../utils/geo";
import { usePosts } from "../services/posts";
import { useAuth } from "../services/auth";
import { recordSearch } from "../services/recommendation";
import PostCard from "../components/post/PostCard.vue";
import PostFilters from "../components/post/PostFilters.vue";
import Pagination from "../components/common/Pagination.vue";
import SearchBar from "../components/common/SearchBar.vue";
import "../assets/css/search.css";

const route = useRoute();
const router = useRouter();
const { posts, loadPosts } = usePosts();
const { currentUser } = useAuth();

const laptopScreen = window.matchMedia("(min-width: 992px)");
const perPage = ref(laptopScreen.matches ? 6 : 4);
const radiusKm = 50;

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

function requestLocation() {
  locationError.value = "";

  if (!navigator.geolocation) {
    locationError.value = "Geolocation is not supported by your browser.";
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

      // Fetch the distance-filtered feed after location is confirmed
      reloadPostsForNearMe();
    },
    (error) => {
      isLocating.value = false;
      userLocation.value = null;

      // Don't reset isNearMeActive — leave the URL and state in sync
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
    isNearMeActive.value = false;
    userLocation.value = null;
    locationError.value = "";

    // Restore the unfiltered feed
    reloadPostsForNearMe();
  } else {
    isNearMeActive.value = true;
    requestLocation(); // reloadPostsForNearMe called on success
  }
}

// ─── Fetch posts based on current Near Me state ────────────
// Called explicitly from requestLocation (success),
// toggleNearMe (off), and the URL-to-state watcher.
// No watcher on userLocation — prevents the loop where
// every geolocation refinement re-triggers loadPosts.
function reloadPostsForNearMe() {
  if (isNearMeActive.value && userLocation.value) {
    loadPosts({
      latitude: userLocation.value.lat,
      longitude: userLocation.value.lng,
      radiusKm: radiusKm,
    });
  } else {
    loadPosts();
  }
}

// ─── Filtering + sorting (client-side, NOT distance) ──────
const filteredPosts = computed(() => {
  let result = [...posts.value];

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

  if (filters.value.cuisine !== "All") {
    result = result.filter((p) => p.cuisine === filters.value.cuisine);
  }

  if (filters.value.location !== "All") {
    result = result.filter((p) => p.location === filters.value.location);
  }

  if (filters.value.priceRange !== "All") {
    result = result.filter((p) => p.priceRange === filters.value.priceRange);
  }

  // Distance filtering & sorting are handled by PHP.
  // Only non-distance sorts are applied here.
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
  Math.max(1, Math.ceil(filteredPosts.value.length / perPage.value)),
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
  const start = (currentPage.value - 1) * perPage.value;
  return filteredPosts.value.slice(start, start + perPage.value);
});

// ─── Sync state → URL ──────────────────────────────────────
let isSyncingFromSelf = false;

function syncUrl() {
  if (isSyncingFromSelf) return;

  const query = {};

  if (searchQuery.value.trim()) query.q = searchQuery.value.trim();
  if (filters.value.cuisine !== "All") query.cuisine = filters.value.cuisine;
  if (filters.value.location !== "All") query.location = filters.value.location;
  if (filters.value.priceRange !== "All")
    query.priceRange = filters.value.priceRange;
  if (sortBy.value !== "newest") query.sort = sortBy.value;
  if (currentPage.value > 1) query.page = currentPage.value;
  if (isNearMeActive.value) query.near = "true";

  // Skip if the URL already matches our state
  const trackedKeys = [
    "q",
    "cuisine",
    "location",
    "priceRange",
    "sort",
    "page",
    "near",
  ];
  const queryKeys = Object.keys(query);
  const routeKeys = Object.keys(route.query).filter((k) =>
    trackedKeys.includes(k),
  );

  if (queryKeys.length === routeKeys.length) {
    let equal = true;
    for (const key of queryKeys) {
      if (String(query[key]) !== String(route.query[key])) {
        equal = false;
        break;
      }
    }
    if (equal) return;
  }

  isSyncingFromSelf = true;
  router.replace({ name: "search", query }).finally(() => {
    setTimeout(() => {
      isSyncingFromSelf = false;
    }, 0);
  });
}

// Filter changes → reset page → sync URL
watch(
  [searchQuery, filters, sortBy],
  () => {
    if (currentPage.value !== 1) currentPage.value = 1;
    syncUrl();
  },
  { deep: true },
);

// Near-me change → sync URL but don't reset page
watch(isNearMeActive, syncUrl);

// Page change → sync URL
watch(currentPage, syncUrl);

// URL → state (browser back/forward, pasted links)
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
      // Guard the isNearMeActive watcher from calling syncUrl
      isSyncingFromSelf = true;
      isNearMeActive.value = newNear;
      setTimeout(() => {
        isSyncingFromSelf = false;
      }, 0);

      if (newNear && !userLocation.value) {
        requestLocation(); // will reload on success
      } else {
        reloadPostsForNearMe();
      }
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

let searchHistoryTimer = null;

watch(
  [() => searchQuery.value.trim(), () => currentUser.value?.id],
  ([query, userId]) => {
    clearTimeout(searchHistoryTimer);

    const length = Array.from(query).length;

    if (!userId || length < 2 || length > 100) return;

    searchHistoryTimer = setTimeout(() => {
      if (currentUser.value?.id !== userId) return;
      void recordSearch(query).catch((error) => {
        console.warn("Unable to record search:", error.message);
      });
    }, 1000);
  },
  { immediate: true },
);

function updatePageSize(event) {
  currentPage.value = 1;
  perPage.value = event.matches ? 6 : 4;
}

onMounted(() => {
  laptopScreen.addEventListener("change", updatePageSize);

  // If arriving via a URL with ?near=true, trigger geolocation.
  // Running this in setup() before mount caused the loop.
  if (isNearMeActive.value && !userLocation.value) {
    requestLocation();
  }
});

onBeforeUnmount(() => {
  clearTimeout(searchHistoryTimer);
  laptopScreen.removeEventListener("change", updatePageSize);
});
</script>

<template>
  <main class="search-page">
    <div class="container search-inner">
      <section class="search-header">
        <h1 class="search-title">Explore</h1>
        <SearchBar
          v-model="searchQuery"
          variant="explore"
          :show-submit="false"
          clearable
        />
      </section>

      <div class="row g-4 mt-1">
        <div class="col-12 col-lg-3">
          <PostFilters v-model="filters" />
        </div>

        <div class="col-12 col-lg-9">
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

          <div
            v-if="locationError"
            class="alert alert-warning mb-3"
            role="alert"
          >
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            {{ locationError }}
          </div>

          <div v-if="paginatedPosts.length === 0" class="results-empty">
            <p class="empty-emoji" aria-hidden="true">🔍</p>
            <h2>No results</h2>
            <p v-if="isNearMeActive">
              There are no results within {{ radiusKm }}km of your location.
            </p>
            <p v-else>Try different keywords or clear the filters.</p>
          </div>

          <div v-else class="row g-3 g-md-4">
            <div
              v-for="post in paginatedPosts"
              :key="post.id"
              class="col-6 col-lg-4"
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
            :per-page="perPage"
            :total-items="filteredPosts.length"
          />
        </div>
      </div>
    </div>
  </main>
</template>