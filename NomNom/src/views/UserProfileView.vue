<script setup>
import { computed, ref, watch } from "vue";
import { useRoute } from "vue-router";
import users from "../data/users.json";
import { usePosts } from "../services/posts";

import UserAvatar from "../components/user/UserAvatar.vue";
import PostCard from "../components/post/PostCard.vue";
import Pagination from "../components/common/Pagination.vue";
import "../assets/css/user-profile.css";

const route = useRoute();
const { posts } = usePosts();

// Get the username from /users/:username
const profile = computed(
  () =>
    users.find(
      (user) =>
        user.username.toLowerCase() ===
        String(route.params.username).toLowerCase(),
    ) ?? null,
);

// Find posts published by this user
const userPosts = computed(() => {
  if (!profile.value) return [];

  return posts.value
    .filter((post) => post.authorId === profile.value.id)
    .sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt));
});

// Pagination
const currentPage = ref(1);
const perPage = 6;

const totalPages = computed(() => Math.ceil(userPosts.value.length / perPage));

const paginatedPosts = computed(() => {
  const start = (currentPage.value - 1) * perPage;
  return userPosts.value.slice(start, start + perPage);
});

// Reset when opening another user's profile
watch(
  () => route.params.username,
  () => {
    currentPage.value = 1;
  },
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
      <template v-if="profile">
        <!-- Profile Header -->
        <section class="profile-card mb-5">
          <div class="profile-cover"></div>

          <div class="p-4">
            <div class="d-flex flex-column flex-sm-row gap-3">
              <UserAvatar :src="profile.avatar" :size="96" />

              <div>
                <h1 class="h2 fw-bold mb-1">
                  {{ profile.username }}
                </h1>

                <p class="text-secondary mb-2">
                  {{ "@" + profile.username }}
                </p>

                <p class="mb-2">
                  {{ profile.bio || "No bio added yet." }}
                </p>

                <p class="text-secondary small mb-0">
                  <i class="bi bi-calendar3 me-1"></i>
                  Joined {{ joinedDate }}
                </p>
              </div>
            </div>

            <div class="profile-stats mt-4">
              <strong>{{ userPosts.length }}</strong>
              <span>Published Posts</span>
            </div>
          </div>
        </section>

        <!-- Published Posts -->
        <section id="profile-posts">
          <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h4 fw-bold mb-0">Food Discoveries</h2>

            <span class="text-secondary small"> Newest first </span>
          </div>

          <div
            v-if="paginatedPosts.length"
            class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4"
          >
            <div v-for="post in paginatedPosts" :key="post.id" class="col">
              <PostCard :post="post" />
            </div>
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
