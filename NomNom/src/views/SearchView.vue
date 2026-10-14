<!-- src/views/SearchView.vue -->
<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import posts from '../data/posts.json'
import PostCard from '../components/post/PostCard.vue'
import PostFilters from '../components/post/PostFilters.vue'
import Pagination from '../components/common/Pagination.vue'
import '../assets/css/search.css'

const route = useRoute()
const router = useRouter()

const POSTS_PER_PAGE = 6

// ─── Local state (initialised from URL) ────────────────────
const searchQuery = ref(String(route.query.q || ''))
const sortBy = ref(String(route.query.sort || 'newest'))
const currentPage = ref(Number(route.query.page) || 1)

const filters = ref({
  cuisine: String(route.query.cuisine || 'All'),
  location: String(route.query.location || 'All'),
  priceRange: String(route.query.priceRange || 'All'),
})

// ─── Computed: filtered + sorted posts ─────────────────────
const filteredPosts = computed(() => {
  let result = [...posts]

  // 1. Keyword
  const q = searchQuery.value.trim().toLowerCase()
  if (q) {
    result = result.filter(
      (p) =>
        p.title.toLowerCase().includes(q) ||
        p.restaurantName.toLowerCase().includes(q) ||
        p.location.toLowerCase().includes(q) ||
        p.cuisine.toLowerCase().includes(q) ||
        p.description.toLowerCase().includes(q),
    )
  }

  // 2. Cuisine
  if (filters.value.cuisine !== 'All') {
    result = result.filter((p) => p.cuisine === filters.value.cuisine)
  }

  // 3. Location
  if (filters.value.location !== 'All') {
    result = result.filter((p) => p.location === filters.value.location)
  }

  // 4. Price
  if (filters.value.priceRange !== 'All') {
    result = result.filter((p) => p.priceRange === filters.value.priceRange)
  }

  // 5. Sort
  if (sortBy.value === 'newest') {
    result.sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt))
  } else if (sortBy.value === 'oldest') {
    result.sort((a, b) => new Date(a.createdAt) - new Date(b.createdAt))
  } else if (sortBy.value === 'most-liked') {
    result.sort((a, b) => b.likes - a.likes)
  } else if (sortBy.value === 'least-liked') {
    result.sort((a, b) => a.likes - b.likes)
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

// ─── Sync state → URL ──────────────────────────────────────
// Flag prevents the route.query watcher from firing on our own updates
let isSyncingFromSelf = false

function syncUrl() {
  const query = {}

  if (searchQuery.value.trim()) query.q = searchQuery.value.trim()
  if (filters.value.cuisine !== 'All') query.cuisine = filters.value.cuisine
  if (filters.value.location !== 'All') query.location = filters.value.location
  if (filters.value.priceRange !== 'All') query.priceRange = filters.value.priceRange
  if (sortBy.value !== 'newest') query.sort = sortBy.value
  if (currentPage.value > 1) query.page = currentPage.value

  isSyncingFromSelf = true
  router.replace({ name: 'search', query }).finally(() => {
    // Give Vue Router one tick to process the change before unlocking
    setTimeout(() => {
      isSyncingFromSelf = false
    }, 0)
  })
}

// Filters changed → reset to page 1, then sync URL
watch(
  [searchQuery, filters, sortBy],
  () => {
    if (currentPage.value !== 1) currentPage.value = 1
    syncUrl()
  },
  { deep: true },
)

// Page changed → sync URL
watch(currentPage, syncUrl)

// ─── Sync URL → state (Back/Forward, pasted links) ─────────
watch(
  () => route.query,
  (q) => {
    if (isSyncingFromSelf) return

    const newPage = Number(q.page) || 1
    if (newPage !== currentPage.value) currentPage.value = newPage

    const newQ = String(q.q || '')
    if (newQ !== searchQuery.value) searchQuery.value = newQ

    const newSort = String(q.sort || 'newest')
    if (newSort !== sortBy.value) sortBy.value = newSort

    const newFilters = {
      cuisine: String(q.cuisine || 'All'),
      location: String(q.location || 'All'),
      priceRange: String(q.priceRange || 'All'),
    }
    if (JSON.stringify(newFilters) !== JSON.stringify(filters.value)) {
      filters.value = newFilters
    }
  },
)
</script>

<template>
  <main class="search-page">
    <div class="container search-inner">
      <!-- Search bar -->
      <section class="search-header">
        <h1 class="search-title">Explore</h1>
        <form class="search-bar" role="search" @submit.prevent>
          <i class="bi bi-search" aria-hidden="true"></i>
          <input
            v-model="searchQuery"
            type="search"
            class="search-input"
            placeholder="Search dishes, restaurants, locations…"
            aria-label="Search"
          />
          <button
            v-if="searchQuery"
            type="button"
            class="search-clear"
            aria-label="Clear search"
            @click="searchQuery = ''"
          >
            <i class="bi bi-x-lg" aria-hidden="true"></i>
          </button>
        </form>
      </section>

      <!-- Layout: filters sidebar + results -->
      <div class="row g-4 mt-1">
        <!-- Filters -->
        <div class="col-12 col-lg-3">
          <PostFilters v-model="filters" />
        </div>

        <!-- Results -->
        <div class="col-12 col-lg-9">
          <header class="results-header">
            <p class="results-count">
              <strong>{{ filteredPosts.length }}</strong>
              {{ filteredPosts.length === 1 ? 'result' : 'results' }}
              <span v-if="searchQuery">for "{{ searchQuery }}"</span>
            </p>

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

          <!-- Empty -->
          <div v-if="paginatedPosts.length === 0" class="results-empty">
            <p class="empty-emoji" aria-hidden="true">🔍</p>
            <h2>No results</h2>
            <p>Try different keywords or clear the filters.</p>
          </div>

          <!-- Grid -->
          <div v-else class="row g-4">
            <div
              v-for="post in paginatedPosts"
              :key="post.id"
              class="col-12 col-sm-6 col-xl-4"
            >
              <PostCard :post="post" />
            </div>
          </div>

          <Pagination
            v-if="totalPages > 1"
            v-model:currentPage="currentPage"
            :total-pages="totalPages"
          />
        </div>
      </div>
    </div>
  </main>
</template>