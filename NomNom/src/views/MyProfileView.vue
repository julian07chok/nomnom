<script setup>
import { computed, ref } from "vue";
import { useRouter } from "vue-router";
import { useAuth } from "../services/auth";
import { usePosts } from "../services/posts";

import UserAvatar from "../components/user/UserAvatar.vue";
import PostCard from "../components/post/PostCard.vue";
import Pagination from "../components/common/Pagination.vue";

import "../assets/css/my-profile.css";

const router = useRouter();

// 1. Get the currently logged-in user
const { currentUser, logout } = useAuth();

// 2. Get all available posts
const { posts } = usePosts();

// 3. Filter posts belonging to the logged-in user
const myPosts = computed(() => {
  if (!currentUser.value) return [];

  return posts.value
    .filter((post) => String(post.authorId) === String(currentUser.value.id))
    .sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt));
});

// 4. Profile statistics
const totalPosts = computed(() => myPosts.value.length);

const joinedDate = computed(() => {
  if (!currentUser.value?.createdAt) return "";

  return new Date(currentUser.value.createdAt).toLocaleDateString("en-MY", {
    month: "long",
    year: "numeric",
  });
});

// 5. Pagination
const currentPage = ref(1);
const perPage = 6;

const totalPages = computed(() => Math.ceil(totalPosts.value / perPage));

const paginatedPosts = computed(() => {
  const start = (currentPage.value - 1) * perPage;
  return myPosts.value.slice(start, start + perPage);
});

// 6. Logout
function handleLogout() {
  logout();
  router.replace({ name: "home" });
}
</script>

<template>
  <main class="my-profile-page py-5">
    <div v-if="currentUser" class="container">
      <!-- PROFILE HEADER -->
      <section class="profile-card mb-5">
        <div class="profile-cover"></div>

        <div class="profile-content">
          <!-- Avatar and User Information -->
          <div class="profile-info">
            <UserAvatar :src="currentUser.avatar" :size="100" />

            <div class="profile-details">
              <h1 class="profile-name">
                {{ currentUser.username }}
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
              <strong>{{ totalPosts }}</strong>
              <span>Posts Published</span>
            </div>
          </div>

          <!-- Profile Actions -->
          <div class="profile-actions mt-4">
            <RouterLink to="/profile/edit" class="profile-button primary">
              <i class="bi bi-pencil-square" aria-hidden="true"></i>
              Edit Profile
            </RouterLink>

            <RouterLink to="/create" class="profile-button secondary">
              <i class="bi bi-plus-circle" aria-hidden="true"></i>
              Create Post
            </RouterLink>

            <RouterLink to="/saved" class="profile-button secondary">
              <i class="bi bi-bookmark" aria-hidden="true"></i>
              Saved Posts
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

      <!-- MY PUBLISHED POSTS -->
      <section id="my-posts" class="my-posts-section">
        <div class="posts-header">
          <div>
            <h2>My Food Discoveries</h2>
            <p>All the delicious places you've shared with NomNom.</p>
          </div>

          <span class="posts-count"> {{ totalPosts }} posts </span>
        </div>

        <!-- Posts Grid -->
        <div v-if="paginatedPosts.length" class="row g-4">
          <div
            v-for="post in paginatedPosts"
            :key="post.id"
            class="col-12 col-sm-6 col-lg-4"
          >
            <PostCard :post="post" />
          </div>
        </div>

        <!-- Empty State -->
        <div v-else class="profile-empty">
          <i class="bi bi-camera fs-1" aria-hidden="true"></i>
          <h3>No food discoveries yet!</h3>
          <p>
            Share your first restaurant experience with the NomNom community.
          </p>

          <RouterLink to="/create" class="profile-button primary">
            Create Your First Post
          </RouterLink>
        </div>

        <!-- Pagination -->
        <Pagination
          v-if="totalPages > 1"
          v-model:currentPage="currentPage"
          :total-pages="totalPages"
          :total-items="totalPosts"
          :per-page="perPage"
          scroll-target-id="my-posts"
        />
      </section>
    </div>
  </main>
</template>
