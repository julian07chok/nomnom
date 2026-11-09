<!-- src/views/PostDetailView.vue -->
<script setup>
import { computed } from "vue";
import { useRoute } from "vue-router";
import { usePosts } from "../services/posts";
import { useAuth } from "../services/auth";

import PostGallery from "../components/post/PostGallery.vue";
import UserAvatar from "../components/user/UserAvatar.vue";

import "../assets/css/post-detail.css";

const route = useRoute();

const { posts, isLoading, postError } = usePosts();
const { profileLinkFor } = useAuth();

const post = computed(
  () =>
    posts.value.find((item) => String(item.id) === String(route.params.id)) ??
    null,
);

const notFound = computed(
  () => !isLoading.value && !postError.value && !post.value,
);
</script>

<template>
  <main class="post-detail">
    <div class="container post-detail-inner">
      <!-- 404 -->
      <div v-if="notFound" class="not-found">
        <h1>Post not found</h1>
        <p>The post you're looking for doesn't exist or was removed.</p>
        <RouterLink class="not-found-link" to="/">Back to Discover</RouterLink>
      </div>

      <!-- Post -->
      <article v-else-if="post" class="post-article">
        <header class="post-header">
          <RouterLink :to="profileLinkFor(post.authorName)" class="post-author">
            <UserAvatar :src="post.authorAvatar" :size="40" />
            <div>
              <p class="post-author-name">{{ post.authorName }}</p>
              <p class="post-date">
                {{ new Date(post.createdAt).toLocaleDateString() }}
              </p>
            </div>
          </RouterLink>

          <span class="post-cuisine">{{ post.cuisine }}</span>
        </header>

        <h1 class="post-title">{{ post.title }}</h1>

        <PostGallery :images="post.gallery" :alt="post.title" />

        <p class="text-secondary mt-3">
          <i class="bi bi-heart" aria-hidden="true"></i>
          {{ post.likes }} {{ post.likes === 1 ? "like" : "likes" }}
        </p>

        <section class="post-info">
          <h2 class="post-restaurant">
            <i class="bi bi-shop" aria-hidden="true"></i>
            {{ post.restaurantName }}
          </h2>
          <ul class="post-meta">
            <li>
              <i class="bi bi-geo-alt" aria-hidden="true"></i>
              {{ post.address }}
            </li>
            <li>
              <i class="bi bi-currency-dollar" aria-hidden="true"></i>
              {{ post.priceRange }}
            </li>
            <li>
              <i class="bi bi-tag" aria-hidden="true"></i>
              {{ post.location }}
            </li>
          </ul>
        </section>

        <section class="post-description">
          <h2>About this place</h2>
          <p>{{ post.description }}</p>
        </section>
      </article>
    </div>
  </main>
</template>
