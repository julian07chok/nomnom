<script setup>

import '../../assets/css/post-card.css'

defineProps({
  post: {
    type: Object,
    required: true,
  },
})
</script>

<template>
  <article class="post-card">
    <RouterLink :to="`/posts/${post.id}`" class="post-cover-link">
      <img
        :src="post.coverImage"
        :alt="post.title"
        class="post-cover"
        loading="lazy"
      />
      <span class="price-badge">{{ post.priceRange }}</span>
    </RouterLink>

    <div class="post-body">
      <div class="post-meta">
        <span class="cuisine-pill">{{ post.cuisine }}</span>
        <span class="location">
          <i class="bi bi-geo-alt-fill" aria-hidden="true"></i>
          {{ post.location }}
        </span>
      </div>

      <h3 class="post-title">
        <RouterLink :to="`/posts/${post.id}`">{{ post.title }}</RouterLink>
      </h3>

      <p class="post-restaurant">
        <i class="bi bi-shop" aria-hidden="true"></i>
        {{ post.restaurantName }}
      </p>

      <footer class="post-footer">
        <RouterLink
          :to="`/users/${post.authorName}`"
          class="post-author"
        >
          <img
            :src="post.authorAvatar"
            :alt="post.authorName"
            class="author-avatar"
          />
          <span>{{ post.authorName }}</span>
        </RouterLink>

        <span class="post-likes">
          <i class="bi bi-heart-fill" aria-hidden="true"></i>
          {{ post.likes }}
        </span>
      </footer>
    </div>
  </article>
</template>