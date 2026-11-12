<script setup>
import { computed, ref, watch } from "vue";
import { useRoute } from "vue-router";
import { getUserByUsername } from "../services/auth";
import { usePosts } from "../services/posts";

import UserAvatar from "../components/user/UserAvatar.vue";
import Pagination from "../components/common/Pagination.vue";
import PostCuisineBadge from "../components/post/PostCuisineBadge.vue";
import "../assets/css/my-profile.css";
import "../assets/css/user-profile.css";

const route = useRoute();
const { posts } = usePosts();

// Find the user in the combined list
const profile = ref(null);
const profileLoading = ref(false);
const profileError = ref("");

// Find posts published by this user
const userPosts = computed(() => {
  if (!profile.value) return [];

  return posts.value
    .filter((post) => String(post.authorId) === String(profile.value.id))
    .sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt));
});

const totalLikesReceived = computed(() =>
  userPosts.value.reduce((total, post) => total + (Number(post.likes) || 0), 0),
);

// Pagination
const currentPage = ref(1);
const perPage = 6;

const totalPages = computed(() => Math.ceil(userPosts.value.length / perPage));

const paginatedPosts = computed(() => {
  const start = (currentPage.value - 1) * perPage;
  return userPosts.value.slice(start, start + perPage);
});

let profileRequestVersion = 0;

async function loadProfile() {
  const version = ++profileRequestVersion;
  const username = String(route.params.username || "");

  profile.value = null;
  profileError.value = "";
  profileLoading.value = true;

  try {
    const result = await getUserByUsername(username);

    if (version === profileRequestVersion) {
      profile.value = result;
    }
  } catch (error) {
    if (version === profileRequestVersion) {
      profileError.value = error.message || "Unable to load this profile.";
    }
  } finally {
    if (version === profileRequestVersion) {
      profileLoading.value = false;
    }
  }
}

watch(
  () => route.params.username,
  () => {
    currentPage.value = 1;
    loadProfile();
  },
  { immediate: true },
);

const joinedDate = computed(() => {
  if (!profile.value?.createdAt) return "";

  return new Date(profile.value.createdAt).toLocaleDateString("en-MY", {
    month: "long",
    year: "numeric",
  });
});
</script>

<template>
  <main class="profile-page py-5">
    <div class="container">
      <p v-if="profileLoading" class="py-4" role="status">Loading profile…</p>

      <div v-else-if="profileError" class="py-4">
        <p class="alert alert-danger" role="alert">
          {{ profileError }}
        </p>

        <button class="btn btn-outline-dark" type="button" @click="loadProfile">
          Try again
        </button>
      </div>

      <template v-else-if="profile">
        <!-- Profile Header -->
        <section class="profile-card mb-5">
          <div class="profile-cover"></div>

          <div class="profile-content">
            <div class="profile-info">
              <UserAvatar :src="profile.avatar" :size="100" />

              <div class="profile-details">
                <h1 class="profile-name">
                  {{ profile.displayName || profile.username }}
                </h1>

                <p class="profile-username">@{{ profile.username }}</p>

                <div class="profile-summary-row">
                  <div class="profile-summary">
                    <p class="profile-bio">
                      {{ profile.bio || "No bio added yet." }}
                    </p>

                    <p class="profile-joined">
                      <i class="bi bi-calendar3" aria-hidden="true"></i>
                      Joined {{ joinedDate }}
                    </p>
                  </div>

                  <div class="profile-stats">
                    <div class="profile-stat">
                      <span>Posts Published</span>
                      <strong>{{ userPosts.length }}</strong>
                    </div>

                    <div class="profile-stat">
                      <span>Likes Received</span>
                      <strong>{{ totalLikesReceived }}</strong>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!-- Published Posts -->
        <section id="profile-posts">
          <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h4 fw-bold mb-0">Food Discoveries</h2>

            <span class="text-secondary small"> Newest first </span>
          </div>

          <div v-if="paginatedPosts.length" class="profile-photo-grid">
            <article
              v-for="post in paginatedPosts"
              :key="post.id"
              class="profile-post-card"
            >
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

                <PostCuisineBadge :cuisine="post.cuisine" />
              </RouterLink>

              <div class="profile-post-content">
                <p class="profile-post-location">
                  <i class="bi bi-geo-alt-fill" aria-hidden="true"></i>
                  {{ post.location }}
                </p>

                <div class="profile-post-heading">
                  <h3 class="profile-post-title">
                    <RouterLink :to="`/posts/${post.id}`">
                      {{ post.title }}
                    </RouterLink>
                  </h3>
                </div>

                <div class="profile-post-footer">
                  <RouterLink
                    :to="`/users/${post.authorName}`"
                    class="profile-post-author"
                  >
                    <UserAvatar :src="post.authorAvatar" :size="24" />

                    <span class="profile-post-username">
                      {{ post.authorName }}
                    </span>
                  </RouterLink>

                  <div class="profile-post-likes">
                    <i class="bi bi-heart-fill" aria-hidden="true"></i>
                    <span>{{ post.likes }}</span>
                  </div>
                </div>
              </div>
            </article>
          </div>

          <div v-else class="text-center py-5">
            <i class="bi bi-journal-x fs-1"></i>
            <h3 class="h5 mt-3">No posts yet</h3>
            <p>This user hasn't shared any food discoveries.</p>
          </div>

          <Pagination
            v-if="totalPages > 1"
            v-model:currentPage="currentPage"
            :total-pages="totalPages"
            :total-items="userPosts.length"
            :per-page="perPage"
            scroll-target-id="profile-posts"
          />
        </section>
      </template>

      <!-- User Not Found -->
      <div v-else class="text-center py-5">
        <h1>User not found</h1>
        <p>This profile does not exist.</p>
        <RouterLink to="/" class="btn btn-dark"> Back to Discover </RouterLink>
      </div>
    </div>
  </main>
</template>
