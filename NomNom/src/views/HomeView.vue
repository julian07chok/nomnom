<script setup>
import { computed, ref } from 'vue'
import posts from '../data/posts.json'
import PostCard from '../components/post/PostCard.vue'
import PaginationControls from '../components/common/Pagination.vue'

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
    <section class="hero">
      <div class="container">
        <div class="hero-content">
          <p class="hero-eyebrow">🍜 Community food journal</p>
          <h1 class="hero-title">
            Discover your next
            <span class="hero-title-accent">favourite bite</span>
          </h1>
          <p class="hero-subtitle">
            Real reviews, honest photos, and hidden gems — shared by food lovers
            across Malaysia.
          </p>

          <form class="hero-search" role="search" @submit.prevent="onSearch">
            <i class="bi bi-search search-icon" aria-hidden="true"></i>
            <input
              v-model="searchQuery"
              type="search"
              class="search-input"
              placeholder="Search dishes, restaurants, or locations…"
              aria-label="Search posts"
            />
            <button type="submit" class="search-btn">Search</button>
          </form>
        </div>
      </div>
    </section>

    <!-- Cuisine categories -->
    <section class="categories-section">
      <div class="container">
        <ul class="category-list" role="tablist" aria-label="Cuisine categories">
          <li v-for="cuisine in cuisines" :key="cuisine">
            <button
              type="button"
              class="category-chip"
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
    <section class="feed-section">
      <div class="container">
        <header class="feed-header">
          <div>
            <h2 class="feed-title">
              {{ activeCuisine === 'All' ? 'Latest discoveries' : activeCuisine }}
            </h2>
            <p class="feed-count">
              {{ filteredPosts.length }}
              {{ filteredPosts.length === 1 ? 'post' : 'posts' }}
              <span v-if="searchQuery">for "{{ searchQuery }}"</span>
            </p>
          </div>

          <div class="feed-controls">
            <label for="sort" class="sort-label">Sort by</label>
            <select id="sort" v-model="sortBy" class="sort-select">
              <option value="newest">Newest</option>
              <option value="most-liked">Most liked</option>
            </select>
          </div>
        </header>

        <!-- Empty state -->
        <div v-if="paginatedPosts.length === 0" class="empty-state">
          <p class="empty-emoji" aria-hidden="true">🍽️</p>
          <h3>No posts found</h3>
          <p>Try a different search or explore another cuisine.</p>
          <button type="button" class="empty-btn" @click="clearFilters">
            Clear filters
          </button>
        </div>

        <!-- Post grid -->
        <div v-else class="post-grid">
          <PostCard
            v-for="post in paginatedPosts"
            :key="post.id"
            :post="post"
          />
        </div>

        <!-- Pagination -->
        <PaginationControls
          v-if="totalPages > 1"
          v-model:currentPage="currentPage"
          :total-pages="totalPages"
        />
      </div>
    </section>
  </main>
</template>

<style scoped>
.home-page {
  background: #fffcf3;
}

/* Hero */
.hero {
  padding: 48px 0 40px;
  background: linear-gradient(180deg, #dce9fc 0%, #fffcf3 100%);
}

.hero-content {
  max-width: 720px;
}

.hero-eyebrow {
  display: inline-block;
  margin: 0 0 16px;
  padding: 8px 16px;
  border-radius: 999px;
  background: #ffd77d;
  color: #483934;
  font-size: 0.85rem;
  font-weight: 700;
}

.hero-title {
  margin: 0 0 16px;
  color: #483934;
  font-size: clamp(2rem, 5vw, 3.25rem);
  font-weight: 800;
  line-height: 1.1;
  letter-spacing: -1px;
}

.hero-title-accent {
  color: #cd4639;
}

.hero-subtitle {
  max-width: 560px;
  margin: 0 0 32px;
  color: #483934;
  font-size: 1.05rem;
  line-height: 1.7;
  opacity: 0.85;
}

.hero-search {
  display: flex;
  align-items: center;
  gap: 8px;
  max-width: 620px;
  padding: 8px;
  background: #fffcf3;
  border: 2px solid #483934;
  border-radius: 999px;
  box-shadow: 0 8px 24px rgb(72 57 52 / 10%);
}

.search-icon {
  margin-left: 12px;
  color: #483934;
  font-size: 1.1rem;
}

.search-input {
  flex: 1;
  min-width: 0;
  padding: 12px 8px;
  border: none;
  background: transparent;
  color: #483934;
  font-size: 1rem;
  outline: none;
}

.search-input::placeholder {
  color: rgb(72 57 52 / 55%);
}

.search-btn {
  padding: 12px 26px;
  border: none;
  border-radius: 999px;
  background: #483934;
  color: #fffcf3;
  font-weight: 700;
  cursor: pointer;
  transition: background-color 150ms ease;
}

.search-btn:hover {
  background: #cd4639;
}

/* Categories */
.categories-section {
  padding: 32px 0 0;
}

.category-list {
  display: flex;
  gap: 10px;
  margin: 0;
  padding: 0 0 8px;
  list-style: none;
  overflow-x: auto;
  scrollbar-width: thin;
}

.category-chip {
  padding: 10px 20px;
  border: 1px solid rgb(72 57 52 / 18%);
  border-radius: 999px;
  background: #fffcf3;
  color: #483934;
  font-weight: 600;
  white-space: nowrap;
  cursor: pointer;
  transition: background-color 150ms ease, border-color 150ms ease;
}

.category-chip:hover {
  background: #f4f2c2;
}

.category-chip.is-active {
  background: #483934;
  color: #fffcf3;
  border-color: #483934;
}

/* Feed */
.feed-section {
  padding: 32px 0 64px;
}

.feed-header {
  display: flex;
  flex-direction: column;
  gap: 16px;
  margin-bottom: 28px;
}

.feed-title {
  margin: 0 0 4px;
  color: #483934;
  font-size: 1.6rem;
  font-weight: 800;
  letter-spacing: -0.5px;
}

.feed-count {
  margin: 0;
  color: #483934;
  font-size: 0.9rem;
  opacity: 0.7;
}

.feed-controls {
  display: flex;
  align-items: center;
  gap: 10px;
}

.sort-label {
  color: #483934;
  font-size: 0.9rem;
  font-weight: 600;
}

.sort-select {
  padding: 10px 16px;
  border: 1px solid rgb(72 57 52 / 20%);
  border-radius: 12px;
  background: #fffcf3;
  color: #483934;
  font-weight: 600;
  cursor: pointer;
}

.sort-select:focus-visible {
  outline: 3px solid #cd4639;
  outline-offset: 3px;
}

/* Grid */
.post-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 24px;
}

/* Empty state */
.empty-state {
  padding: 64px 24px;
  text-align: center;
  background: #f4f2c2;
  border-radius: 20px;
}

.empty-emoji {
  margin: 0 0 12px;
  font-size: 3rem;
}

.empty-state h3 {
  margin: 0 0 8px;
  color: #483934;
  font-size: 1.25rem;
}

.empty-state p {
  margin: 0 0 20px;
  color: #483934;
  opacity: 0.75;
}

.empty-btn {
  padding: 12px 24px;
  border: none;
  border-radius: 999px;
  background: #483934;
  color: #fffcf3;
  font-weight: 700;
  cursor: pointer;
}

.empty-btn:hover {
  background: #cd4639;
}

/* Focus styles */
button:focus-visible,
input:focus-visible,
select:focus-visible {
  outline: 3px solid #cd4639;
  outline-offset: 3px;
}

/* Responsive — mobile-first breakpoints */
@media (min-width: 576px) {
  .post-grid {
    grid-template-columns: repeat(2, 1fr);
  }
}

@media (min-width: 768px) {
  .hero {
    padding: 72px 0 56px;
  }

  .feed-header {
    flex-direction: row;
    align-items: flex-end;
    justify-content: space-between;
  }
}

@media (min-width: 992px) {
  .post-grid {
    grid-template-columns: repeat(3, 1fr);
  }
}
</style>