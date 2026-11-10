<!-- src/views/PostDetailView.vue -->
<script setup>
import { computed, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { usePosts } from "../services/posts";
import { useAuth } from "../services/auth";

import PostGallery from "../components/post/PostGallery.vue";
import UserAvatar from "../components/user/UserAvatar.vue";

import "../assets/css/post-detail.css";

const route = useRoute();
const router = useRouter();
const isDeleting = ref(false);
const deleteError = ref("");

const {
  posts,
  isLoading,
  postError,
  deletePost: deletePostFromService,
} = usePosts();

const { currentUser, profileLinkFor } = useAuth();

const post = computed(
  () =>
    posts.value.find((item) => String(item.id) === String(route.params.id)) ??
    null,
);

const notFound = computed(
  () => !isLoading.value && !postError.value && !post.value,
);

const isOwner = computed(
  () =>
    Boolean(currentUser.value && post.value) &&
    Number(post.value.authorId) === Number(currentUser.value.id),
);

async function handleDelete() {
  if (!post.value || isDeleting.value || !isOwner.value) return;

  if (!window.confirm("Delete this post? This cannot be undone.")) {
    return;
  }

  isDeleting.value = true;
  deleteError.value = "";

  try {
    await deletePostFromService(post.value.id);
  } catch (error) {
    deleteError.value = error.message || "Unable to delete this post.";
    isDeleting.value = false;
    return;
  }

  await router.push({ name: "home" });
  isDeleting.value = false;
}
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

        <div v-if="isOwner" class="d-flex flex-wrap gap-2 mb-4">
          <RouterLink
            v-if="!isDeleting"
            class="btn btn-outline-dark"
            :to="{ name: 'edit-post', params: { id: post.id } }"
          >
            <i class="bi bi-pencil-square me-1" aria-hidden="true"></i>
            Edit post
          </RouterLink>

          <button
            class="btn btn-outline-danger"
            type="button"
            :disabled="isDeleting"
            @click="handleDelete"
          >
            {{ isDeleting ? "Deleting…" : "Delete post" }}
          </button>
        </div>

        <p v-if="deleteError" class="alert alert-danger" role="alert">
          {{ deleteError }}
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
