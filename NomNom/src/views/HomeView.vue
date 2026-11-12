<script setup>
import { computed, ref } from "vue";
import { useRouter } from "vue-router";
import { usePosts } from "../services/posts";
import { getDistanceKm, formatDistance } from "../utils/geo";
import PostCard from "../components/post/PostCard.vue";
import PaginationControls from "../components/common/Pagination.vue";
import SearchBar from "../components/common/SearchBar.vue";
import PostMap from "../components/post/PostMap.vue";
import "../assets/css/home.css";

const router = useRouter();
const { posts } = usePosts();

const POSTS_PER_PAGE = 6;
const radiusKm = 50; // Only show posts within 50km when Near Me is active

const searchQuery = ref("");
const activeCuisine = ref("All");
const sortBy = ref("newest");
const currentPage = ref(1);

// ── Near Me State ──────────────────────────────────────────
const userLocation = ref(null);
const isNearMeActive = ref(false);
const isLocating = ref(false);
const locationError = ref("");

const cuisines = [
  "All",
  "Malaysian",
  "Chinese",
  "Indian",
  "Japanese",
  "Korean",
  "Thai",
  "Vietnamese",
  "Western",
  "Cafe",
  "Dessert",
];

const toggleNearMe = () => {
  // If already active, turn it off
  if (isNearMeActive.value) {
    isNearMeActive.value = false;
    userLocation.value = null;
    locationError.value = "";
    return;
  }

  // Otherwise, locate user
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
      isNearMeActive.value = true;
      isLocating.value = false;
      currentPage.value = 1;
    },
    (error) => {
      isLocating.value = false;
      userLocation.value = null;
      isNearMeActive.value = false;

      switch (error.code) {
        case error.PERMISSION_DENIED:
          locationError.value =
            "Location permission denied. Please allow it to use Near Me.";
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
};

// ── Filtering + Sorting ────────────────────────────────────
const filteredPosts = computed(() => {
  let result = [...posts.value];

  // 1. Filter by cuisine
  if (activeCuisine.value !== "All") {
    result = result.filter((post) => post.cuisine === activeCuisine.value);
  }

  // 2. Filter by distance (Near Me)
  if (isNearMeActive.value && userLocation.value) {
    result = result
      .map((post) => {
        const dist = getDistanceKm(
          userLocation.value.lat,
          userLocation.value.lng,
          post.latitude,
          post.longitude,
        );
        return { ...post, distanceKm: dist };
      })
      .filter((post) => {
        // Only keep posts within the radius (50km)
        return post.distanceKm !== null && post.distanceKm <= radiusKm;
      });
  }

  // 3. Sort
  if (sortBy.value === "newest") {
    result.sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt));
  } else if (sortBy.value === "most-liked") {
    result.sort((a, b) => b.likes - a.likes);
  } else if (sortBy.value === "near-me") {
    // Just in case you want a distance sort option later
    result.sort((a, b) => (a.distanceKm || 0) - (b.distanceKm || 0));
  }

  return result;
});

const totalPages = computed(() =>
  Math.max(1, Math.ceil(filteredPosts.value.length / POSTS_PER_PAGE)),
);

const paginatedPosts = computed(() => {
  const start = (currentPage.value - 1) * POSTS_PER_PAGE;
  return filteredPosts.value.slice(start, start + POSTS_PER_PAGE);
});

function selectCuisine(cuisine) {
  activeCuisine.value = cuisine;
  currentPage.value = 1;
}

function clearFilters() {
  searchQuery.value = "";
  activeCuisine.value = "All";
  sortBy.value = "newest";
  currentPage.value = 1;
  locationError.value = "";
  isNearMeActive.value = false;
  userLocation.value = null;
}

function onSearch() {
  const q = searchQuery.value.trim();

  if (q) {
    router.push({ name: "search", query: { q } });
  } else {
    router.push({ name: "search" });
  }
}
</script>

<template>
  <main class="home-page">
    <!-- Hero + Map -->
    <section class="home-hero">
      <div class="container">
        <div class="row align-items-center g-4 g-lg-5">

          <!-- Left column: title, subtitle, search bar -->
          <div class="col-12 col-lg-6">
            <div class="home-hero-content">
              <p class="home-hero-eyebrow">🍜 Community food journal</p>
              <h1 class="home-hero-title">
                Discover your next
                <span class="home-hero-title-accent">favourite bite</span>
              </h1>
              <p class="home-hero-subtitle">
                Real reviews, honest photos, and hidden gems — shared by food lovers
                across Malaysia.
              </p>

              <SearchBar v-model="searchQuery" variant="home" placeholder="Search dishes, restaurants, or locations…"
                @submit="onSearch" />
            </div>
          </div>

          <!-- Right column: interactive map (desktop only) -->
          <div class="col-12 col-lg-6">
            <PostMap :posts="posts" />
          </div>

        </div>
      </div>
    </section>

    <!-- Cuisine categories -->
    <section class="home-categories-section">
      <div class="container">
        <ul class="home-category-list" role="tablist" aria-label="Cuisine categories">
          <li v-for="cuisine in cuisines" :key="cuisine">
            <button type="button" class="home-category-chip" :class="{ 'is-active': activeCuisine === cuisine }"
              role="tab" :aria-selected="activeCuisine === cuisine" @click="selectCuisine(cuisine)">
              {{ cuisine }}
            </button>
          </li>
        </ul>
      </div>
    </section>

    <!-- Feed -->
    <section id="home-feed-top" class="home-feed-section">
      <div class="container">
        <header class="home-feed-header">
          <div>
            <h2 id="home-feed-title" class="home-feed-title">
              {{
                activeCuisine === "All" ? "Latest discoveries" : activeCuisine
              }}
            </h2>
            <p class="home-feed-count">
              {{ filteredPosts.length }}
              {{ filteredPosts.length === 1 ? "post" : "posts" }}
              <span v-if="isNearMeActive" class="text-success">
                · within {{ radiusKm }}km</span>
            </p>
          </div>

          <div class="home-feed-controls">
            <!-- TOGGLE NEAR ME BUTTON -->
            <button type="button" class="near-me-btn" :class="{ 'is-active': isNearMeActive }" :disabled="isLocating"
              @click="toggleNearMe">
              <template v-if="isNearMeActive">
                <i class="bi bi-geo-alt-fill me-1" aria-hidden="true"></i>
                Near Me
                <i class="bi bi-x-circle-fill ms-1" aria-hidden="true" title="Clear Near Me filter"></i>
              </template>
              <template v-else>
                <i class="bi bi-geo-alt me-1" aria-hidden="true"></i>
                {{ isLocating ? "Locating…" : "Near Me" }}
              </template>
            </button>

            <!-- SORTING -->
            <label for="sort" class="home-sort-label">Sort by</label>
            <select id="sort" v-model="sortBy" class="home-sort-select">
              <option value="newest">Newest</option>
              <option value="most-liked">Most liked</option>
            </select>
          </div>
        </header>

        <!-- Location error -->
        <div v-if="locationError" class="alert alert-warning d-flex align-items-center mb-4" role="alert">
          <i class="bi bi-exclamation-triangle-fill me-2"></i>
          {{ locationError }}
        </div>

        <!-- Empty state -->
        <div v-if="paginatedPosts.length === 0" class="home-empty-state">
          <p class="home-empty-emoji" aria-hidden="true">🍽️</p>
          <h3>No posts found</h3>
          <p v-if="isNearMeActive">
            There are no food discoveries within {{ radiusKm }}km of your
            location.
          </p>
          <p v-else>Try a different search or explore another cuisine.</p>
          <button type="button" class="home-empty-btn" @click="clearFilters">
            Clear filters
          </button>
        </div>

        <!-- Post grid -->
        <div v-else class="row g-3 g-md-4">
          <div v-for="post in paginatedPosts" :key="post.id" class="col-6 col-lg-4">
            <PostCard :post="post" :distance="post.distanceKm ? formatDistance(post.distanceKm) : null
              " />
          </div>
        </div>

        <!-- Pagination -->
        <PaginationControls v-if="totalPages > 1" v-model:currentPage="currentPage" :total-pages="totalPages"
          :total-items="filteredPosts.length" :per-page="POSTS_PER_PAGE" scroll-target-id="home-feed-top" />
      </div>
    </section>
  </main>
</template>
