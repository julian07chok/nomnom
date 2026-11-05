<script setup>
import { computed, ref, watch } from "vue";
import { useRouter } from "vue-router";
import { useAuth } from "../services/auth";
import { usePosts } from "../services/posts";

import UserAvatar from "../components/user/UserAvatar.vue";
import Pagination from "../components/common/Pagination.vue";

import "../assets/css/my-profile.css";

const router = useRouter();
const { currentUser, logout } = useAuth();
const { posts } = usePosts();

// ==========================================
// 1. MY PUBLISHED POSTS
// ==========================================

const myPosts = computed(() => {
  if (!currentUser.value) return [];

  return posts.value
    .filter((post) => String(post.authorId) === String(currentUser.value.id))
    .sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt));
});

// ==========================================
// 2. MY SAVED POSTS
// ==========================================

// Currently uses savedPostIds from users.json.
// This will be replaced by a database API later.

const savedPosts = computed(() => {
  if (!currentUser.value) return [];

  const savedIds = currentUser.value.savedPostIds || [];

  return posts.value
    .filter((post) => savedIds.some((id) => String(id) === String(post.id)))
    .sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt));
});

// ==========================================
// 3. PROFILE INFORMATION
// ==========================================

const joinedDate = computed(() => {
  if (!currentUser.value?.createdAt) return "";

  return new Date(currentUser.value.createdAt).toLocaleDateString("en-MY", {
    month: "long",
    year: "numeric",
  });
});

// ==========================================
// 4. PROFILE TABS
// ==========================================

// Default tab: My Posts
const activeTab = ref("posts");

function switchTab(tab) {
  activeTab.value = tab;
}

// Select posts based on active tab
const displayedPosts = computed(() => {
  return activeTab.value === "posts" ? myPosts.value : savedPosts.value;
});

// ==========================================
// 5. PAGINATION
// ==========================================

const currentPage = ref(1);
const perPage = 12;

const totalPages = computed(() =>
  Math.max(1, Math.ceil(displayedPosts.value.length / perPage)),
);

const paginatedPosts = computed(() => {
  const start = (currentPage.value - 1) * perPage;

  return displayedPosts.value.slice(start, start + perPage);
});

// Reset pagination when switching tabs
watch(activeTab, () => {
  currentPage.value = 1;
});

// ==========================================
// 6. LOGOUT
// ==========================================

function handleLogout() {
  logout();
  router.replace({ name: "home" });
}
</script>

<template>
  <main class="my-profile-page py-5">
    <div v-if="currentUser" class="container">
      <!-- ======================================
           PROFILE HEADER
           ====================================== -->

      <section class="profile-card mb-5">
        <div class="profile-cover"></div>

        <div class="profile-content">
          <!-- Avatar and User Information -->
          <div class="profile-info">
            <UserAvatar :src="currentUser.avatar" :size="100" />

            <div class="profile-details">
              <h1 class="profile-name">
                {{ currentUser.displayName || currentUser.username }}
              </h1>

              <p class="profile-username">@{{ currentUser.username }}</p>

              <p class="profile-bio">
                {{ currentUser.bio || "No bio added yet." }}
              </p>

              <p class="profile-joined">
                <i class="bi bi-calendar3" aria-hidden="true"></i>
                Joined {{ joinedDate }}
              </p>
            </div>
          </div>

          <!-- Profile Statistics -->
          <div class="profile-stats mt-4">
            <div class="stat-item">
              <strong>{{ myPosts.length }}</strong>
              <span>Posts Published</span>
            </div>

            <div class="stat-item">
              <strong>{{ savedPosts.length }}</strong>
              <span>Posts Saved</span>
            </div>
          </div>

          <!-- Profile Actions -->
          <div class="profile-actions mt-4">
            <RouterLink to="/profile/edit" class="profile-button primary">
              <i class="bi bi-pencil-square" aria-hidden="true"></i>
              Edit Profile
            </RouterLink>

            <button
              type="button"
              class="profile-button logout"
              @click="handleLogout"
            >
              <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
              Logout
            </button>
          </div>
        </div>
      </section>

      <!-- ======================================
           MY POSTS / SAVED POSTS TABS
           ====================================== -->

      <section id="profile-posts" class="my-posts-section">
        <!-- Tab Navigation -->
        <div class="profile-tabs" aria-label="Profile post collections">
          <button
            type="button"
            class="profile-tab"
            :class="{ 'is-active': activeTab === 'posts' }"
            :aria-pressed="activeTab === 'posts'"
            @click="switchTab('posts')"
          >
            <i class="bi bi-grid-3x3" aria-hidden="true"></i>
            My Posts
          </button>

          <button
            type="button"
            class="profile-tab"
            :class="{ 'is-active': activeTab === 'saved' }"
            :aria-pressed="activeTab === 'saved'"
            @click="switchTab('saved')"
          >
            <i class="bi bi-bookmark" aria-hidden="true"></i>
            Saved Posts
          </button>
        </div>

        <!-- Section Heading -->
        <div class="posts-header">
          <div>
            <h2>
              {{
                activeTab === "posts"
                  ? "My Food Discoveries"
                  : "Saved Food Discoveries"
              }}
            </h2>

            <p>
              {{
                activeTab === "posts"
                  ? "All the delicious places you've shared with NomNom."
                  : "All the food discoveries you've bookmarked."
              }}
            </p>
          </div>
        </div>

        <!-- Compact Photo Grid -->
        <div v-if="paginatedPosts.length" class="profile-photo-grid">
          <article
            v-for="post in paginatedPosts"
            :key="post.id"
            class="profile-post-card"
          >
            <!-- Cover Image -->
            <RouterLink
              :to="`/posts/${post.id}`"
              class="profile-photo-link"
              :aria-label="`View ${post.title}`"
            >
              <img
                :src="post.coverImage"
                :alt="post.title"
                class="profile-photo-img"
                loading="lazy"
              />

              <!-- Saved Indicator -->
              <span
                v-if="activeTab === 'saved'"
                class="profile-photo-bookmark"
                aria-hidden="true"
              >
                <i class="bi bi-bookmark-fill"></i>
              </span>
            </RouterLink>

            <!-- Post Information -->
            <div class="profile-post-content">
              <!-- Title and Category -->
              <div class="profile-post-heading">
                <h3 class="profile-post-title">
                  <RouterLink :to="`/posts/${post.id}`">
                    {{ post.title }}
                  </RouterLink>
                </h3>

                <span class="profile-post-category">
                  {{ post.cuisine }}
                </span>
              </div>

              <!-- Author and Likes -->
              <div class="profile-post-footer">
                <!-- Author -->
                <RouterLink
                  :to="`/users/${post.authorName}`"
                  class="profile-post-author"
                >
                  <img
                    :src="post.authorAvatar"
                    :alt="post.authorName"
                    class="profile-post-avatar"
                    loading="lazy"
                  />

                  <span class="profile-post-username">
                    {{ post.authorName }}
                  </span>
                </RouterLink>

                <!-- Likes -->
                <div class="profile-post-likes">
                  <i class="bi bi-heart-fill" aria-hidden="true"></i>
                  <span>{{ post.likes }}</span>
                </div>
              </div>
            </div>
          </article>
        </div>

        <!-- Empty State -->
        <div v-else class="profile-empty">
          <i
            :class="
              activeTab === 'posts'
                ? 'bi bi-camera fs-1'
                : 'bi bi-bookmark fs-1'
            "
            aria-hidden="true"
          ></i>

          <h3>
            {{
              activeTab === "posts"
                ? "No food discoveries yet!"
                : "No saved posts yet!"
            }}
          </h3>

          <p>
            {{
              activeTab === "posts"
                ? "Share your first restaurant experience with the NomNom community."
                : "Explore food discoveries and bookmark your favourite posts."
            }}
          </p>

          <RouterLink
            :to="activeTab === 'posts' ? '/create' : '/'"
            class="profile-button primary"
          >
            {{
              activeTab === "posts" ? "Create Your First Post" : "Explore Posts"
            }}
          </RouterLink>
        </div>

        <!-- Pagination -->
        <Pagination
          v-if="totalPages > 1"
          v-model:currentPage="currentPage"
          :total-pages="totalPages"
          :total-items="displayedPosts.length"
          :per-page="perPage"
          scroll-target-id="profile-posts"
        />
      </section>
    </div>
  </main>
</template>
