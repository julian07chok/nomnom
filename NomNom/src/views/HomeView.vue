<script setup>
import { computed, onBeforeUnmount, ref } from "vue";
import { useRouter } from "vue-router";
import { useDiscoverFeed } from "../composables/useDiscoverFeed";
import { formatDistance } from "../utils/geo";

import AppButton from "../components/common/AppButton.vue";
import PostCard from "../components/post/PostCard.vue";
import SearchBar from "../components/common/SearchBar.vue";
import PostMap from "../components/post/PostMap.vue";

import "../assets/css/home.css";

const router = useRouter();
const radiusKm = 50;

const searchQuery = ref("");
const activeCuisine = ref("All");
const sortBy = ref("newest");

const userLocation = ref(null);
const isNearMeActive = ref(false);
const isLocating = ref(false);
const locationError = ref("");

let locationRequest = 0;

const cuisines = [
  "All",
  "Malaysian",
  "Chinese",
  "Indian",
  "Japanese",
  "Korean",
  "Thai",
  "Vietnamese",
  "Italian",
  "Western",
  "Cafe",
  "Dessert",
  "Fusion",
  "Middle Eastern",
];

const feedFilters = computed(() => ({
  cuisine: activeCuisine.value,
  sort: sortBy.value,
  lat: isNearMeActive.value ? (userLocation.value?.lat ?? null) : null,
  lng: isNearMeActive.value ? (userLocation.value?.lng ?? null) : null,
}));

const {
  posts,
  loading: feedLoading,
  error: feedError,
  hasMore,
  sentinel,
  loadMore,
} = useDiscoverFeed(feedFilters);

function selectCuisine(cuisine) {
  activeCuisine.value = cuisine;
}

function toggleNearMe() {
  if (isLocating.value) return;

  if (isNearMeActive.value) {
    isNearMeActive.value = false;
    userLocation.value = null;
    locationError.value = "";
    return;
  }

  if (!navigator.geolocation) {
    locationError.value = "Geolocation is not supported by your browser.";
    return;
  }

  const request = ++locationRequest;
  isLocating.value = true;
  locationError.value = "";

  navigator.geolocation.getCurrentPosition(
    (position) => {
      if (request !== locationRequest) return;

      userLocation.value = {
        lat: position.coords.latitude,
        lng: position.coords.longitude,
      };

      isNearMeActive.value = true;
      isLocating.value = false;
    },
    (error) => {
      if (request !== locationRequest) return;

      isLocating.value = false;
      isNearMeActive.value = false;
      userLocation.value = null;

      locationError.value =
        error.code === error.PERMISSION_DENIED
          ? "Location permission denied. Allow it to use Near Me."
          : "Unable to find your location. Please try again.";
    },
    { timeout: 10000, enableHighAccuracy: true },
  );
}

function clearFilters() {
  locationRequest++;
  isLocating.value = false;
  searchQuery.value = "";
  activeCuisine.value = "All";
  sortBy.value = "newest";
  locationError.value = "";
  isNearMeActive.value = false;
  userLocation.value = null;
}

function onSearch() {
  const q = searchQuery.value.trim();

  router.push({
    name: "search",
    query: q ? { q } : {},
  });
}

onBeforeUnmount(() => {
  locationRequest++;
});
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
                Real reviews, honest photos, and hidden gems — shared by food
                lovers across Malaysia.
              </p>

              <SearchBar
                v-model="searchQuery"
                variant="home"
                placeholder="Search dishes, restaurants, or locations…"
                @submit="onSearch"
              />
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
        <ul
          class="home-category-list"
          role="tablist"
          aria-label="Cuisine categories"
        >
          <li v-for="cuisine in cuisines" :key="cuisine">
            <button
              type="button"
              class="home-category-chip"
              :class="{ 'is-active': activeCuisine === cuisine }"
              role="tab"
              :aria-selected="activeCuisine === cuisine"
              @click="selectCuisine(cuisine)"
            >
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
              {{ posts.length }}
              {{
                posts.length === 1 ? "discovery loaded" : "discoveries loaded"
              }}

              <span v-if="isNearMeActive" class="text-success">
                · within {{ radiusKm }}km
              </span>
            </p>
          </div>

          <div class="home-feed-controls">
            <!-- TOGGLE NEAR ME BUTTON -->
            <button
              type="button"
              class="near-me-btn"
              :class="{ 'is-active': isNearMeActive }"
              :disabled="isLocating"
              @click="toggleNearMe"
            >
              <template v-if="isNearMeActive">
                <i class="bi bi-geo-alt-fill me-1" aria-hidden="true"></i>
                Near Me
                <i
                  class="bi bi-x-circle-fill ms-1"
                  aria-hidden="true"
                  title="Clear Near Me filter"
                ></i>
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
        <div
          v-if="locationError"
          class="alert alert-warning d-flex align-items-center mb-4"
          role="alert"
        >
          <i class="bi bi-exclamation-triangle-fill me-2"></i>
          {{ locationError }}
        </div>

        <!-- Empty state -->
        <div
          v-if="!feedLoading && !feedError && posts.length === 0"
          class="home-empty-state"
        >
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
        <div v-if="posts.length" class="row g-3 g-md-4">
          <div v-for="post in posts" :key="post.id" class="col-6 col-lg-4">
            <PostCard
              :post="post"
              :distance="
                post.distanceKm ? formatDistance(post.distanceKm) : null
              "
            />
          </div>
        </div>

        <!-- Always keep the observer target mounted. -->
        <div ref="sentinel" class="home-feed-sentinel" aria-hidden="true"></div>

        <div class="text-center mt-4" aria-live="polite">
          <p v-if="feedLoading" role="status">Loading discoveries…</p>

          <template v-else-if="feedError">
            <p class="text-danger" role="alert">{{ feedError }}</p>

            <AppButton variant="secondary" @click="loadMore">
              Try again
            </AppButton>
          </template>

          <AppButton v-else-if="hasMore" variant="secondary" @click="loadMore">
            Load more
          </AppButton>

          <p v-else-if="posts.length" class="text-secondary">
            You’ve reached the end of these discoveries.
          </p>
        </div>
      </div>
    </section>
  </main>
</template>
