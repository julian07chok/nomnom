<script setup>
defineProps({
  cuisine: {
    type: String,
    required: true,
  },
});
</script>

<template>
  <span class="post-cuisine-badge" :title="cuisine">
    <span>{{ cuisine }}</span>
  </span>
</template>

<style scoped>
.post-cuisine-badge {
  position: absolute;
  top: 12px;
  right: 12px;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  max-width: calc(100% - 24px);
  padding: 6px 10px;
  border-radius: 999px;
  background: var(--light-blue);
  color: var(--text);
  font-size: 0.75rem;
  font-weight: 700;
  line-height: 1.3;
}

.post-cuisine-badge span {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

@media (max-width: 575.98px) {
  .post-cuisine-badge {
    top: 8px;
    right: 8px;
    max-width: calc(100% - 16px);
    padding: 4px 7px;
    font-size: 0.7rem;
  }
}
</style>
