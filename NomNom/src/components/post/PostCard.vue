<script setup>
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

<style scoped>
.post-card {
  display: flex;
  flex-direction: column;
  height: 100%;
  overflow: hidden;
  background: #fffcf3;
  border: 1px solid rgb(72 57 52 / 12%);
  border-radius: 20px;
  transition: transform 180ms ease, box-shadow 180ms ease;
}

.post-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 14px 30px rgb(72 57 52 / 12%);
}

.post-cover-link {
  position: relative;
  display: block;
  aspect-ratio: 4 / 3;
  overflow: hidden;
}

.post-cover {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 320ms ease;
}

.post-card:hover .post-cover {
  transform: scale(1.05);
}

.price-badge {
  position: absolute;
  top: 12px;
  right: 12px;
  padding: 6px 12px;
  border-radius: 999px;
  background: #ffd77d;
  color: #483934;
  font-size: 0.8rem;
  font-weight: 800;
  letter-spacing: 0.5px;
}

.post-body {
  display: flex;
  flex: 1;
  flex-direction: column;
  gap: 10px;
  padding: 18px;
}

.post-meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  font-size: 0.8rem;
}

.cuisine-pill {
  padding: 4px 10px;
  border-radius: 999px;
  background: #dce9fc;
  color: #483934;
  font-weight: 700;
}

.location {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  color: #483934;
  opacity: 0.75;
}

.post-title {
  margin: 0;
  font-size: 1.05rem;
  font-weight: 800;
  line-height: 1.35;
}

.post-title a {
  color: #483934;
  text-decoration: none;
}

.post-title a:hover {
  color: #cd4639;
}

.post-restaurant {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin: 0;
  color: #483934;
  font-size: 0.9rem;
  opacity: 0.8;
}

.post-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: auto;
  padding-top: 12px;
  border-top: 1px solid rgb(72 57 52 / 10%);
}

.post-author {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: #483934;
  font-size: 0.85rem;
  font-weight: 600;
  text-decoration: none;
}

.post-author:hover {
  color: #cd4639;
}

.author-avatar {
  width: 26px;
  height: 26px;
  border-radius: 50%;
  object-fit: cover;
}

.post-likes {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: #cd4639;
  font-size: 0.85rem;
  font-weight: 700;
}

a:focus-visible {
  outline: 3px solid #cd4639;
  outline-offset: 3px;
  border-radius: 6px;
}
</style>