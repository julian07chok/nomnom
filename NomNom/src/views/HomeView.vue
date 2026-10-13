<script setup>
import { computed, ref } from 'vue'
import posts from '../data/posts.json'
import PostCard from '../components/post/PostCard.vue'
import PaginationControls from '../components/common/Pagination.vue'
import '../assets/css/home.css'

const POSTS_PER_PAGE = 6

const searchQuery = ref('')
const activeCuisine = ref('All')
const sortBy = ref('newest')
const currentPage = ref(1)

const cuisines = [
  'All',
  'Malaysian',
  'Chinese',
  'Indian',
  'Japanese',
  'Korean',
  'Thai',
  'Vietnamese',
  'Western',
  'Cafe',
  'Dessert',
]

const filteredPosts = computed(() => {
  let result = [...posts]

  // Filter by cuisine
  if (activeCuisine.value !== 'All') {
    result = result.filter((post) => post.cuisine === activeCuisine.value)
  }

  // Filter by search query
  const query = searchQuery.value.trim().toLowerCase()
  if (query) {
    result = result.filter(
      (post) =>
        post.title.toLowerCase().includes(query) ||
        post.restaurantName.toLowerCase().includes(query) ||
        post.location.toLowerCase().includes(query) ||
        post.cuisine.toLowerCase().includes(query),
    )
  }

  // Sort
  if (sortBy.value === 'newest') {
    result.sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt))
  } else if (sortBy.value === 'most-liked') {
    result.sort((a, b) => b.likes - a.likes)
  }

  return result
})

const totalPages = computed(() =>
  Math.max(1, Math.ceil(filteredPosts.value.length / POSTS_PER_PAGE)),
)

const paginatedPosts = computed(() => {
  const start = (currentPage.value - 1) * POSTS_PER_PAGE
  return filteredPosts.value.slice(start, start + POSTS_PER_PAGE)
})

function selectCuisine(cuisine) {
  activeCuisine.value = cuisine
  currentPage.value = 1
}

function clearFilters() {
  searchQuery.value = ''
  activeCuisine.value = 'All'
  sortBy.value = 'newest'
  currentPage.value = 1
}

// Reset to page 1 when the search changes
function onSearch() {
  currentPage.value = 1
}
</script>

<template>
  <main class="home-page">
    <!-- Hero -->
    <section class="home-hero">
      <div class="container">
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

          <form class="home-hero-search" role="search" @submit.prevent="onSearch">
            <i class="bi bi-search search-icon" aria-hidden="true"></i>
            <input v-model="searchQuery" type="search" class="home-search-input"
              placeholder="Search dishes, restaurants, or locations…" aria-label="Search posts" />
            <button type="submit" class="home-search-btn">Search</button>
          </form>
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
    <section id="home-feed-top" section class="home-feed-section">
      <div class="container">
        <header class="home-feed-header">
          <div>
            <h2 id="home-feed-title" class="home-feed-title">
              {{ activeCuisine === 'All' ? 'Latest discoveries' : activeCuisine }}
            </h2>
            <p class="home-feed-count">
              {{ filteredPosts.length }}
              {{ filteredPosts.length === 1 ? 'post' : 'posts' }}
              <span v-if="searchQuery">for "{{ searchQuery }}"</span>
            </p>
          </div>

          <div class="home-feed-controls">
            <label for="sort" class="home-sort-label">Sort by</label>
            <select id="sort" v-model="sortBy" class="home-sort-select">
              <option value="newest">Newest</option>
              <option value="most-liked">Most liked</option>
            </select>
          </div>
        </header>

        <!-- Empty state -->
        <div v-if="paginatedPosts.length === 0" class="home-empty-state">
          <p class="home-empty-emoji" aria-hidden="true">🍽️</p>
          <h3>No posts found</h3>
          <p>Try a different search or explore another cuisine.</p>
          <button type="button" class="home-empty-btn" @click="clearFilters">
            Clear filters
          </button>
        </div>

        <!-- Post grid -->
        <div v-else class="home-post-grid">
          <PostCard v-for="post in paginatedPosts" :key="post.id" :post="post" />
        </div>

        <!-- Pagination -->
        <PaginationControls v-if="totalPages > 1" v-model:currentPage="currentPage" :total-pages="totalPages"
          :total-items="filteredPosts.length" :per-page="POSTS_PER_PAGE" scroll-target-id="home-feed-top" />
      </div>
    </section>
  </main>
</template>