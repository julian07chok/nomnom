<script setup>
const props = defineProps({
  currentPage: { type: Number, required: true },
  totalPages: { type: Number, required: true },
})

const emit = defineEmits(['update:currentPage'])

function goTo(page) {
  if (page < 1 || page > props.totalPages || page === props.currentPage) return
  emit('update:currentPage', page)
  window.scrollTo({ top: 0, behavior: 'smooth' })
}
</script>

<template>
  <nav class="pagination-nav" aria-label="Pagination">
    <button
      type="button"
      class="page-btn"
      :disabled="currentPage === 1"
      aria-label="Previous page"
      @click="goTo(currentPage - 1)"
    >
      <i class="bi bi-chevron-left" aria-hidden="true"></i>
      <span class="page-btn-text">Prev</span>
    </button>

    <ul class="page-list">
      <li v-for="page in totalPages" :key="page">
        <button
          type="button"
          class="page-number"
          :class="{ 'is-active': page === currentPage }"
          :aria-current="page === currentPage ? 'page' : undefined"
          @click="goTo(page)"
        >
          {{ page }}
        </button>
      </li>
    </ul>

    <button
      type="button"
      class="page-btn"
      :disabled="currentPage === totalPages"
      aria-label="Next page"
      @click="goTo(currentPage + 1)"
    >
      <span class="page-btn-text">Next</span>
      <i class="bi bi-chevron-right" aria-hidden="true"></i>
    </button>
  </nav>
</template>

<style scoped>
.pagination-nav {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin-top: 40px;
  flex-wrap: wrap;
}

.page-list {
  display: flex;
  gap: 6px;
  margin: 0;
  padding: 0;
  list-style: none;
}

.page-btn,
.page-number {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 42px;
  height: 42px;
  padding: 0 14px;
  border: 1px solid rgb(72 57 52 / 20%);
  border-radius: 12px;
  background: #fffcf3;
  color: #483934;
  font-weight: 700;
  cursor: pointer;
  transition: background-color 150ms ease, color 150ms ease, border-color 150ms ease;
}

.page-btn {
  gap: 6px;
}

.page-btn:hover:not(:disabled),
.page-number:hover {
  background: #f4f2c2;
}

.page-number.is-active {
  background: #483934;
  color: #fffcf3;
  border-color: #483934;
}

.page-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

@media (max-width: 480px) {
  .page-btn-text {
    display: none;
  }
}

button:focus-visible {
  outline: 3px solid #cd4639;
  outline-offset: 3px;
}
</style>